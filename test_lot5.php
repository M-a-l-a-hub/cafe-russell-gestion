<?php

declare(strict_types=1);

/**
 * Test du lot 5 :
 *  1. Fuseau horaire Africa/Kinshasa et connexion partagée
 *  2. Catégorie du sac figée dans l'activité (historique)
 *  3. Un seul sac actif par vendeur, tous sites confondus
 *  4. Annulation d'une activité créée par erreur
 *  5. Le premier ravitaillement crée l'activité ; consommables sur le reçu
 *  6. Une journée avec une activité en cours ne se clôture pas
 *
 * Usage : php test_lot5.php [AAAA-MM-JJ]
 *
 * Prérequis : migrations 001 à 026 et seeds 001 à 003 chargés, et
 * src/ à jour (config, models, services). Le test écrit dans la base :
 * utilise une base de test.
 */

require_once __DIR__ . '/src/config/database.php';
require_once __DIR__ . '/src/models/User.php';
require_once __DIR__ . '/src/models/Journee.php';
require_once __DIR__ . '/src/services/JourneeService.php';
require_once __DIR__ . '/src/services/DemarrageActiviteService.php';
require_once __DIR__ . '/src/services/RavitaillementService.php';
require_once __DIR__ . '/src/services/RetourService.php';
require_once __DIR__ . '/src/services/ClotureActiviteService.php';
require_once __DIR__ . '/src/services/AnnulationActiviteService.php';
require_once __DIR__ . '/src/services/SacService.php';

$echecs = 0;
$total = 0;

function verifier(string $libelle, mixed $obtenu, mixed $attendu): void
{
    global $echecs, $total;

    $total++;

    $ok = is_numeric($obtenu) && is_numeric($attendu)
        ? abs((float) $obtenu - (float) $attendu) < 0.005
        : $obtenu === $attendu;

    if (!$ok) {
        $echecs++;
    }

    echo ($ok ? '[OK]     ' : '[ÉCHEC]  ')
        . "{$libelle} : obtenu=" . var_export($obtenu, true)
        . ' attendu=' . var_export($attendu, true) . PHP_EOL;
}

function verifierRefus(
    string $libelle,
    callable $action,
    string $classeAttendue = RuntimeException::class
): void {
    global $echecs, $total;

    $total++;

    try {
        $action();

        $echecs++;
        echo "[ÉCHEC]  {$libelle} : acceptée alors qu'elle devait être refusée" . PHP_EOL;
    } catch (Throwable $e) {
        $ok = $e instanceof $classeAttendue;

        if (!$ok) {
            $echecs++;
        }

        echo ($ok ? '[OK]     ' : '[ÉCHEC]  ')
            . "{$libelle} : refusée (" . get_class($e) . ') ' . $e->getMessage() . PHP_EOL;
    }
}

function valeur(PDO $db, string $sql, array $params = []): mixed
{
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchColumn();
}

function executer(PDO $db, string $sql, array $params = []): void
{
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
}

try {
    $db = getDatabaseConnection();
    $userModel = new User();

    $admin = $userModel->findByTelephone('+243000000001');

    if ($admin === null) {
        throw new RuntimeException('Utilisateur de démo introuvable : charge le seed 002.');
    }

    $adminId = (int) $admin['id'];
    $siteId = (int) valeur($db, "SELECT id FROM sites WHERE code = 'SITE_DEMO'");
    $vendeur1 = (int) valeur($db, "SELECT id FROM vendeurs WHERE code = 'VENDEUR_DEMO_01'");
    $vendeur2 = (int) valeur($db, "SELECT id FROM vendeurs WHERE code = 'VENDEUR_DEMO_02'");
    $vendeur3 = (int) valeur($db, "SELECT id FROM vendeurs WHERE code = 'VENDEUR_DEMO_03'");
    $petiteId = (int) valeur($db, "SELECT id FROM produits WHERE code = 'PETITE_TASSE'");
    $grandeId = (int) valeur($db, "SELECT id FROM produits WHERE code = 'GRANDE_TASSE'");

    if (in_array(0, [$siteId, $vendeur1, $vendeur2, $vendeur3, $petiteId, $grandeId], true)) {
        throw new RuntimeException('Données de démo manquantes : charge les seeds 001 à 003.');
    }

    if (valeur($db, "SELECT COUNT(*) FROM information_schema.columns WHERE table_name = 'activites' AND column_name = 'sac_categorie'") === 0) {
        throw new RuntimeException('Migration 026 absente : charge src/database/migrations/026_activites_historique_sac_et_vendeur.sql.');
    }

    $suffixe = date('His') . random_int(10, 99);

    $journeeService = new JourneeService();
    $demarrage = new DemarrageActiviteService();
    $ravitaillement = new RavitaillementService();
    $retour = new RetourService();
    $cloture = new ClotureActiviteService();
    $annulation = new AnnulationActiviteService();
    $sacService = new SacService();

    // ------------------------------------------------------------
    echo PHP_EOL . '=== 1. Fuseau horaire et connexion ===' . PHP_EOL;
    // ------------------------------------------------------------

    verifier('PHP : fuseau horaire', date_default_timezone_get(), 'Africa/Kinshasa');
    verifier('PostgreSQL : fuseau de la session', valeur($db, "SELECT current_setting('TimeZone')"), 'Africa/Kinshasa');
    verifier('Connexion partagée', getDatabaseConnection() === getDatabaseConnection(), true);
    verifier('Date PHP = date PostgreSQL', date('Y-m-d'), valeur($db, 'SELECT CURRENT_DATE'));

    // ------------------------------------------------------------
    // Préparation : deux sites, deux journées, des sacs de test.
    // ------------------------------------------------------------

    $ouverte = (new Journee())->getOpenBySiteId($siteId);

    if ($ouverte !== null) {
        $journeeService->cloturer((int) $ouverte['id'], $adminId, 'Clôture avant test');
        echo PHP_EOL . "Journée {$ouverte['date_jour']} clôturée avant le test." . PHP_EOL;
    }

    $date = $argv[1] ?? (string) valeur(
        $db,
        'SELECT COALESCE(MAX(date_jour), CURRENT_DATE - 1) + 1 FROM journees WHERE site_id = :site',
        ['site' => $siteId]
    );

    $stmt = $db->prepare("INSERT INTO sites (code, nom) VALUES (:code, 'Site de test lot 5') RETURNING id");
    $stmt->execute(['code' => "SITE_LOT5_{$suffixe}"]);
    $siteB = (int) $stmt->fetchColumn();

    $journeeId = $journeeService->ouvrir($siteId, $date, $adminId, 'Test lot 5');
    $journeeB = $journeeService->ouvrir($siteB, $date, $adminId, 'Test lot 5 (site B)');

    $sacH = $sacService->creer($siteId, "LH-{$suffixe}", 'standard', $adminId);
    $sacX = $sacService->creer($siteId, "LX-{$suffixe}", 'standard', $adminId);
    $sacZ = $sacService->creer($siteId, "LZ-{$suffixe}", 'standard', $adminId);
    $sacB = $sacService->creer($siteB, "LB-{$suffixe}", 'standard', $adminId);

    echo PHP_EOL . "Journées {$date} ouvertes (site de démo id={$journeeId}, site de test id={$journeeB})." . PHP_EOL;

    // ------------------------------------------------------------
    echo PHP_EOL . '=== 2. Catégorie du sac figée dans l’activité ===' . PHP_EOL;
    // ------------------------------------------------------------

    $r = $ravitaillement->enregistrerPourVendeur(
        $journeeId,
        $vendeur1,
        $adminId,
        [['produit_id' => $petiteId, 'quantite' => 10]],
        $sacH,
        'matin'
    );
    $actH = $r['activite_id'];

    verifier('Catégorie figée à la création', valeur($db, 'SELECT sac_categorie FROM activites WHERE id = :id', ['id' => $actH]), 'standard');

    $retour->enregistrer($actH, $adminId, []);

    // Russell change la catégorie du sac après coup.
    executer($db, "UPDATE sacs SET categorie = 'russell' WHERE id = :id", ['id' => $sacH]);

    $resume = $cloture->cloturer($actH, $adminId, 5000);

    verifier('Bénéficiaire = catégorie au moment de l’activité', $resume['beneficiaire_benefice'], 'entreprise');
    verifier('Activité toujours « standard »', valeur($db, 'SELECT sac_categorie FROM activites WHERE id = :id', ['id' => $actH]), 'standard');
    verifierRefus('Catégorie d’une activité non modifiable en base', fn () => executer($db, "UPDATE activites SET sac_categorie = 'russell' WHERE id = :id", ['id' => $actH]));

    $actH2 = $demarrage->demarrer($journeeId, $vendeur1, $sacH, 'soir', $adminId);
    verifier('Nouvelle activité : catégorie actuelle du sac', valeur($db, 'SELECT sac_categorie FROM activites WHERE id = :id', ['id' => $actH2]), 'russell');

    // ------------------------------------------------------------
    echo PHP_EOL . '=== 3. Annulation d’une activité créée par erreur ===' . PHP_EOL;
    // ------------------------------------------------------------

    verifierRefus('Annulation sans description', fn () => $annulation->annuler($actH2, $adminId, '   '), InvalidArgumentException::class);
    verifier('Activité toujours en cours après le refus', valeur($db, 'SELECT statut FROM activites WHERE id = :id', ['id' => $actH2]), 'en_cours');

    $annulation->annuler($actH2, $adminId, 'Vendeur absent, activité créée par erreur');

    verifier('Activité annulée', valeur($db, 'SELECT statut FROM activites WHERE id = :id', ['id' => $actH2]), 'annulee');
    verifier('Activité annulée : date de fin renseignée', valeur($db, 'SELECT COUNT(*) FROM activites WHERE id = :id AND date_fin IS NOT NULL', ['id' => $actH2]), 1);
    verifier('Sac libéré', valeur($db, 'SELECT statut FROM sacs WHERE id = :id', ['id' => $sacH]), 'libre');
    verifier(
        'Annulation : audit avec description',
        valeur($db, "SELECT COUNT(*) FROM audit_logs WHERE action = 'activite_annulee' AND enregistrement_id = :id AND raison = 'Vendeur absent, activité créée par erreur'", ['id' => $actH2]),
        1
    );
    verifierRefus('Activité déjà annulée', fn () => $annulation->annuler($actH2, $adminId, 'Encore'));

    executer($db, "UPDATE sacs SET categorie = 'standard' WHERE id = :id", ['id' => $sacH]);

    // ------------------------------------------------------------
    echo PHP_EOL . '=== 4. Un seul sac actif par vendeur ===' . PHP_EOL;
    // ------------------------------------------------------------

    $actX = $demarrage->demarrer($journeeId, $vendeur2, $sacX, 'matin', $adminId);

    verifierRefus('Même vendeur sur un autre site', fn () => $demarrage->demarrer($journeeB, $vendeur2, $sacB, 'matin', $adminId));
    verifierRefus('Même vendeur, même journée, autre sac', fn () => $demarrage->demarrer($journeeId, $vendeur2, $sacZ, 'soir', $adminId));
    verifierRefus(
        'Insertion directe refusée par la base',
        fn () => executer($db, "INSERT INTO activites (journee_id, vendeur_id, sac_id, periode) VALUES (:j, :v, :s, 'matin')", ['j' => $journeeB, 'v' => $vendeur2, 's' => $sacB])
    );

    $annulation->annuler($actX, $adminId, 'Test : libère le vendeur');

    $actB = $demarrage->demarrer($journeeB, $vendeur2, $sacB, 'matin', $adminId);
    verifier('Le vendeur peut travailler sur un autre site une fois libre', valeur($db, 'SELECT statut FROM activites WHERE id = :id', ['id' => $actB]), 'en_cours');
    $annulation->annuler($actB, $adminId, 'Test : fin du test sur le site B');

    // ------------------------------------------------------------
    echo PHP_EOL . '=== 5. Premier ravitaillement et consommables ===' . PHP_EOL;
    // ------------------------------------------------------------

    $lignesPetites = [['produit_id' => $petiteId, 'quantite' => 30]];
    $lignesGrandes = [['produit_id' => $grandeId, 'quantite' => 20]];

    verifierRefus('Premier ravitaillement sans sac', fn () => $ravitaillement->enregistrerPourVendeur($journeeId, $vendeur3, $adminId, $lignesPetites, null, 'matin'), InvalidArgumentException::class);

    verifierRefus(
        'Produit inexistant',
        fn () => $ravitaillement->enregistrerPourVendeur($journeeId, $vendeur3, $adminId, [['produit_id' => 999999999, 'quantite' => 5]], $sacZ, 'matin')
    );
    verifier('Refus : aucune activité créée (annulation complète)', valeur($db, 'SELECT COUNT(*) FROM activites WHERE journee_id = :j AND vendeur_id = :v', ['j' => $journeeId, 'v' => $vendeur3]), 0);
    verifier('Refus : sac resté libre', valeur($db, 'SELECT statut FROM sacs WHERE id = :id', ['id' => $sacZ]), 'libre');

    $r1 = $ravitaillement->enregistrerPourVendeur($journeeId, $vendeur3, $adminId, $lignesPetites, $sacZ, 'matin');
    verifier('Premier ravitaillement : activité créée', $r1['activite_creee'], true);
    verifier('Sac Z en utilisation', valeur($db, 'SELECT statut FROM sacs WHERE id = :id', ['id' => $sacZ]), 'en_utilisation');

    $r2 = $ravitaillement->enregistrerPourVendeur($journeeId, $vendeur3, $adminId, $lignesGrandes);
    verifier('Deuxième ravitaillement : même activité', $r2['activite_id'], $r1['activite_id']);
    verifier('Deuxième ravitaillement : pas de nouvelle activité', $r2['activite_creee'], false);
    verifier('Une seule activité pour le vendeur', valeur($db, 'SELECT COUNT(*) FROM activites WHERE journee_id = :j AND vendeur_id = :v', ['j' => $journeeId, 'v' => $vendeur3]), 1);

    verifierRefus('Autre sac pour un vendeur déjà en activité', fn () => $ravitaillement->enregistrerPourVendeur($journeeId, $vendeur3, $adminId, $lignesPetites, $sacX));
    verifierRefus('Annulation après ravitaillement', fn () => $annulation->annuler($r1['activite_id'], $adminId, 'Erreur'));

    $retour->enregistrer($r1['activite_id'], $adminId, [['produit_id' => $petiteId, 'quantite' => 5]]);

    // Vendu : 25 petites (12 500) + 20 grandes (20 000) = 32 500.
    $resume = $cloture->cloturer($r1['activite_id'], $adminId, 32500);

    verifier('CA', $resume['ca_theorique'], 32500);
    verifier('Transport : 2 ravitaillements x 3 000', $resume['transport'], 6000);
    verifier('Commission 25 %', $resume['commission'], 8125);
    verifier('Consommables sur le reçu : 30 x 150 + 20 x 250', $resume['consommables'], 9500);
    verifier('Bénéfice', $resume['benefice'], 8875);
    verifier('Bénéficiaire', $resume['beneficiaire_benefice'], 'entreprise');

    // ------------------------------------------------------------
    echo PHP_EOL . '=== 6. Clôture de journée et activité en cours ===' . PHP_EOL;
    // ------------------------------------------------------------

    $actEnCours = $demarrage->demarrer($journeeId, $vendeur1, $sacH, 'matin', $adminId);

    verifierRefus('Clôture d’une journée avec une activité en cours', fn () => $journeeService->cloturer($journeeId, $adminId));
    verifier('Journée toujours ouverte', valeur($db, 'SELECT statut FROM journees WHERE id = :id', ['id' => $journeeId]), 'ouverte');

    $annulation->annuler($actEnCours, $adminId, 'Test : fin du test');

    $journeeService->cloturer($journeeId, $adminId, 'Fin du test lot 5');
    $journeeService->cloturer($journeeB, $adminId, 'Fin du test lot 5');

    verifier('Journée clôturée une fois les activités terminées', valeur($db, 'SELECT statut FROM journees WHERE id = :id', ['id' => $journeeId]), 'cloturee');

    executer($db, 'UPDATE sites SET actif = FALSE WHERE id = :id', ['id' => $siteB]);

    echo PHP_EOL . ($echecs === 0
        ? "Tous les tests sont passés ({$total} vérifications)."
        : "{$echecs} vérification(s) en échec sur {$total}.") . PHP_EOL;

    exit($echecs === 0 ? 0 : 1);
} catch (Throwable $e) {
    echo PHP_EOL . 'Erreur : ' . get_class($e) . ' - ' . $e->getMessage() . PHP_EOL;
    echo $e->getFile() . ':' . $e->getLine() . PHP_EOL;
    exit(1);
}
