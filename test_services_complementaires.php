<?php

declare(strict_types=1);

/**
 * Second test : ce que test_cycle_activite.php ne couvre pas.
 *
 *  1. TarifService        (prix, coût, transport historisés)
 *  2. MouvementSacService (transfert de sac entre sites)
 *  3. Cas particuliers de la clôture d'activité
 *     (écart avec motif, retour obligatoire, activité sans ravitaillement)
 *  4. Clôture / réouverture de journée avec audit
 *
 * Usage : php test_services_complementaires.php [AAAA-MM-JJ]
 *
 * Prérequis : migrations + seeds 001 à 003, et src/services à jour
 * (dont JourneeService.php avec l'audit de réouverture).
 *
 * Ce test écrit dans la base : utilise une base de test. Il crée un
 * produit, une catégorie de transport et un site de test (désactivés à
 * la fin), plus une journée. Par défaut la journée prend la date qui
 * suit la dernière journée du site de démo ; passe une date en argument
 * pour en choisir une autre.
 */

require_once __DIR__ . '/src/config/database.php';
require_once __DIR__ . '/src/models/User.php';
require_once __DIR__ . '/src/models/Journee.php';
require_once __DIR__ . '/src/services/JourneeService.php';
require_once __DIR__ . '/src/services/DemarrageActiviteService.php';
require_once __DIR__ . '/src/services/RavitaillementService.php';
require_once __DIR__ . '/src/services/RetourService.php';
require_once __DIR__ . '/src/services/ClotureActiviteService.php';
require_once __DIR__ . '/src/services/MouvementSacService.php';
require_once __DIR__ . '/src/services/TarifService.php';

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

/**
 * Vérifie qu'une opération est refusée par une exception du type attendu.
 */
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
    $responsable = $userModel->findByTelephone('+243000000002');

    if ($admin === null || $responsable === null) {
        throw new RuntimeException('Utilisateurs de démo introuvables : charge le seed 002.');
    }

    $adminId = (int) $admin['id'];
    $responsableId = (int) $responsable['id'];

    $siteId = (int) valeur($db, "SELECT id FROM sites WHERE code = 'SITE_DEMO'");
    $vendeur1 = (int) valeur($db, "SELECT id FROM vendeurs WHERE code = 'VENDEUR_DEMO_01'");
    $vendeur2 = (int) valeur($db, "SELECT id FROM vendeurs WHERE code = 'VENDEUR_DEMO_02'");
    $vendeur3 = (int) valeur($db, "SELECT id FROM vendeurs WHERE code = 'VENDEUR_DEMO_03'");
    $petiteId = (int) valeur($db, "SELECT id FROM produits WHERE code = 'PETITE_TASSE'");
    $grandeId = (int) valeur($db, "SELECT id FROM produits WHERE code = 'GRANDE_TASSE'");

    $sacs = [];

    foreach (['DEMO-01', 'DEMO-02', '19'] as $numero) {
        $sacs[$numero] = (int) valeur(
            $db,
            'SELECT id FROM sacs WHERE site_id = :site AND numero = :numero',
            ['site' => $siteId, 'numero' => $numero]
        );
    }

    if (in_array(0, [$siteId, $vendeur1, $vendeur2, $vendeur3, $petiteId, $grandeId], true)
        || in_array(0, $sacs, true)) {
        throw new RuntimeException('Données de démo manquantes : charge les seeds 001 à 003.');
    }

    $suffixe = date('His') . random_int(10, 99);

    // ------------------------------------------------------------
    echo PHP_EOL . '=== 1. TarifService ===' . PHP_EOL;
    // ------------------------------------------------------------

    $tarifs = new TarifService();

    $stmt = $db->prepare("INSERT INTO produits (code, nom) VALUES (:code, 'Produit de test') RETURNING id");
    $stmt->execute(['code' => "TEST_PROD_{$suffixe}"]);
    $produitTest = (int) $stmt->fetchColumn();

    executer($db, "INSERT INTO produit_prix (produit_id, prix, date_debut) VALUES (:p, 500, '2026-01-01')", ['p' => $produitTest]);
    executer($db, "INSERT INTO produit_couts (produit_id, cout_unitaire, date_debut) VALUES (:p, 150, '2026-01-01')", ['p' => $produitTest]);

    $r = $tarifs->changer(TarifService::PRIX, $produitTest, 600, '2026-10-01', $adminId, 'Test hausse');

    verifier('Prix : ancienne valeur', $r['ancienne_valeur'], 500);
    verifier('Prix : nouvelle valeur', $r['nouvelle_valeur'], 600);
    verifier(
        'Prix : ancienne période fermée la veille',
        valeur($db, 'SELECT date_fin FROM produit_prix WHERE produit_id = :p AND prix = 500', ['p' => $produitTest]),
        '2026-09-30'
    );
    verifier(
        'Prix : une seule période ouverte',
        valeur($db, 'SELECT COUNT(*) FROM produit_prix WHERE produit_id = :p AND date_fin IS NULL', ['p' => $produitTest]),
        1
    );
    verifier(
        'Prix : audit avec raison',
        valeur(
            $db,
            "SELECT COUNT(*) FROM audit_logs WHERE action = 'prix_modifie' AND enregistrement_id = :id AND raison = 'Test hausse'",
            ['id' => $r['id']]
        ),
        1
    );

    $sqlPrixAuJour = "
        SELECT prix FROM produit_prix
        WHERE produit_id = :p
          AND date_debut <= :d1
          AND (date_fin IS NULL OR date_fin >= :d2)
    ";

    verifier('Prix applicable le 2026-09-30', valeur($db, $sqlPrixAuJour, ['p' => $produitTest, 'd1' => '2026-09-30', 'd2' => '2026-09-30']), 500);
    verifier('Prix applicable le 2026-10-01', valeur($db, $sqlPrixAuJour, ['p' => $produitTest, 'd1' => '2026-10-01', 'd2' => '2026-10-01']), 600);

    verifierRefus('Prix : même montant', fn () => $tarifs->changer(TarifService::PRIX, $produitTest, 600, '2026-11-01', $adminId));
    verifierRefus('Prix : même date que la période en cours', fn () => $tarifs->changer(TarifService::PRIX, $produitTest, 700, '2026-10-01', $adminId));
    verifierRefus('Prix : date antérieure', fn () => $tarifs->changer(TarifService::PRIX, $produitTest, 700, '2026-09-15', $adminId));
    verifierRefus('Prix : montant invalide', fn () => $tarifs->changer(TarifService::PRIX, $produitTest, '12abc', '2026-11-01', $adminId), InvalidArgumentException::class);
    verifierRefus('Prix : montant nul', fn () => $tarifs->changer(TarifService::PRIX, $produitTest, 0, '2026-11-01', $adminId), InvalidArgumentException::class);
    verifierRefus('Prix : montant négatif', fn () => $tarifs->changer(TarifService::PRIX, $produitTest, -5, '2026-11-01', $adminId), InvalidArgumentException::class);
    verifierRefus('Prix : date invalide', fn () => $tarifs->changer(TarifService::PRIX, $produitTest, 700, '2026-13-40', $adminId), InvalidArgumentException::class);
    verifierRefus('Tarif : type inconnu', fn () => $tarifs->changer('xyz', $produitTest, 700, '2026-11-01', $adminId), InvalidArgumentException::class);

    $r = $tarifs->changer(TarifService::COUT, $produitTest, '175,50', '2026-10-01', $adminId, 'Hausse du lait');
    verifier('Coût : montant avec virgule', $r['nouvelle_valeur'], '175.50');

    $stmt = $db->prepare("INSERT INTO categories_transport (code, nom) VALUES (:code, 'Catégorie de test') RETURNING id");
    $stmt->execute(['code' => "TEST_CAT_{$suffixe}"]);
    $categorieTest = (int) $stmt->fetchColumn();

    executer($db, "INSERT INTO transport_prix (categorie_transport_id, montant_par_ravitaillement, date_debut) VALUES (:c, 1000, '2026-01-01')", ['c' => $categorieTest]);

    $r = $tarifs->changer(TarifService::TRANSPORT, $categorieTest, 1200, '2026-10-01', $adminId);
    verifier('Transport : nouvelle valeur', $r['nouvelle_valeur'], 1200);

    $r = $tarifs->changer(TarifService::TRANSPORT, $categorieTest, 0, '2026-11-01', $adminId);
    verifier('Transport : montant zéro accepté', $r['nouvelle_valeur'], '0.00');

    executer($db, 'UPDATE produits SET actif = FALSE WHERE id = :id', ['id' => $produitTest]);
    executer($db, 'UPDATE categories_transport SET actif = FALSE WHERE id = :id', ['id' => $categorieTest]);

    // ------------------------------------------------------------
    echo PHP_EOL . '=== 2. MouvementSacService ===' . PHP_EOL;
    // ------------------------------------------------------------

    $mouvements = new MouvementSacService();
    $sacTransfert = $sacs['DEMO-02'];

    $stmt = $db->prepare("INSERT INTO sites (code, nom) VALUES (:code, 'Site de test') RETURNING id");
    $stmt->execute(['code' => "SITE_TEST_{$suffixe}"]);
    $siteB = (int) $stmt->fetchColumn();

    $mouvementId = $mouvements->deplacer($sacTransfert, $siteB, $adminId, 'Transfert de test');

    verifier('Sac : site après transfert', valeur($db, 'SELECT site_id FROM sacs WHERE id = :id', ['id' => $sacTransfert]), $siteB);
    verifier('Mouvement : site de départ', valeur($db, 'SELECT site_depart_id FROM mouvements_sacs WHERE id = :id', ['id' => $mouvementId]), $siteId);
    verifier('Mouvement : site d’arrivée', valeur($db, 'SELECT site_arrivee_id FROM mouvements_sacs WHERE id = :id', ['id' => $mouvementId]), $siteB);
    verifier(
        'Mouvement : audit',
        valeur($db, "SELECT COUNT(*) FROM audit_logs WHERE action = 'sac_deplace' AND enregistrement_id = :id", ['id' => $mouvementId]),
        1
    );

    verifierRefus('Sac : déjà rattaché à ce site', fn () => $mouvements->deplacer($sacTransfert, $siteB, $adminId, 'Test'));
    verifierRefus('Sac : motif vide', fn () => $mouvements->deplacer($sacTransfert, $siteId, $adminId, '   '), InvalidArgumentException::class);
    verifierRefus('Sac : site inexistant', fn () => $mouvements->deplacer($sacTransfert, 999999999, $adminId, 'Test'));

    // Numéro déjà présent sur le site d'arrivée.
    $stmt = $db->prepare("INSERT INTO sacs (site_id, numero) VALUES (:site, 'DEMO-02') RETURNING id");
    $stmt->execute(['site' => $siteId]);
    $doublon = (int) $stmt->fetchColumn();

    verifierRefus('Sac : numéro déjà présent sur le site d’arrivée', fn () => $mouvements->deplacer($sacTransfert, $siteId, $adminId, 'Test'));

    executer($db, 'DELETE FROM sacs WHERE id = :id', ['id' => $doublon]);

    // Sac en cours d'utilisation.
    executer($db, "UPDATE sacs SET statut = 'en_utilisation' WHERE id = :id", ['id' => $sacs['DEMO-01']]);
    verifierRefus('Sac : en cours d’utilisation', fn () => $mouvements->deplacer($sacs['DEMO-01'], $siteB, $adminId, 'Test'));
    executer($db, "UPDATE sacs SET statut = 'libre' WHERE id = :id", ['id' => $sacs['DEMO-01']]);

    // Retour du sac sur le site de démo.
    $mouvements->deplacer($sacTransfert, $siteId, $adminId, 'Retour du transfert de test');
    verifier('Sac : de retour sur le site de démo', valeur($db, 'SELECT site_id FROM sacs WHERE id = :id', ['id' => $sacTransfert]), $siteId);

    // Site d'arrivée désactivé.
    executer($db, 'UPDATE sites SET actif = FALSE WHERE id = :id', ['id' => $siteB]);
    verifierRefus('Sac : site d’arrivée désactivé', fn () => $mouvements->deplacer($sacs['DEMO-01'], $siteB, $adminId, 'Test'));

    // ------------------------------------------------------------
    echo PHP_EOL . '=== 3. Clôture d’activité : cas particuliers ===' . PHP_EOL;
    // ------------------------------------------------------------

    $journeeService = new JourneeService();
    $demarrage = new DemarrageActiviteService();
    $ravitaillement = new RavitaillementService();
    $retour = new RetourService();
    $cloture = new ClotureActiviteService();

    $ouverte = (new Journee())->getOpenBySiteId($siteId);

    if ($ouverte !== null) {
        $journeeService->cloturer((int) $ouverte['id'], $responsableId, 'Clôture avant test');
        echo "Journée {$ouverte['date_jour']} clôturée avant le test." . PHP_EOL;
    }

    $date = $argv[1] ?? (string) valeur(
        $db,
        'SELECT COALESCE(MAX(date_jour), CURRENT_DATE - 1) + 1 FROM journees WHERE site_id = :site',
        ['site' => $siteId]
    );

    $journeeId = $journeeService->ouvrir($siteId, $date, $responsableId, 'Second test');
    echo "Journée {$date} ouverte (id={$journeeId})." . PHP_EOL;

    // --- A. Écart avec motif : 40 petites reçues, aucun retour, 19 000 remis.
    echo PHP_EOL . '--- A. Écart avec motif' . PHP_EOL;

    $activiteA = $demarrage->demarrer($journeeId, $vendeur3, $sacs['DEMO-02'], 'journee', $responsableId);
    $ravitaillement->enregistrer($activiteA, $responsableId, [['produit_id' => $petiteId, 'quantite' => 40]]);
    $retour->enregistrer($activiteA, $responsableId, []);

    verifierRefus('Écart sans motif', fn () => $cloture->cloturer($activiteA, $responsableId, 19000), InvalidArgumentException::class);
    verifier('Activité toujours en cours après le refus', valeur($db, 'SELECT statut FROM activites WHERE id = :id', ['id' => $activiteA]), 'en_cours');
    verifier('Aucun contrôle enregistré après le refus', valeur($db, 'SELECT COUNT(*) FROM controles_financiers WHERE activite_id = :id', ['id' => $activiteA]), 0);

    $resume = $cloture->cloturer($activiteA, $responsableId, 19000, 'Manque de caisse');

    verifier('CA théorique', $resume['ca_theorique'], 20000);
    verifier('Écart (remis - CA)', $resume['ecart'], -1000);
    verifier('Statut du contrôle', $resume['statut_controle'], 'ecart_a_expliquer');
    verifier('Commission sur le CA théorique, pas sur le remis', $resume['commission'], 5000);
    verifier('Bénéfice (écart non déduit)', $resume['benefice'], 6000);
    verifier('Motif enregistré', valeur($db, 'SELECT motif_ecart FROM controles_financiers WHERE activite_id = :id', ['id' => $activiteA]), 'Manque de caisse');
    verifier('Sac libéré', valeur($db, 'SELECT statut FROM sacs WHERE id = :id', ['id' => $sacs['DEMO-02']]), 'libre');
    verifier(
        'Audit de clôture avec raison',
        valeur($db, "SELECT COUNT(*) FROM audit_logs WHERE action = 'activite_cloturee' AND enregistrement_id = :id AND raison = 'Manque de caisse'", ['id' => $activiteA]),
        1
    );

    // --- B. Retour obligatoire avant la clôture.
    echo PHP_EOL . '--- B. Retour obligatoire' . PHP_EOL;

    $activiteB = $demarrage->demarrer($journeeId, $vendeur1, $sacs['DEMO-01'], 'matin', $responsableId);
    $ravitaillement->enregistrer($activiteB, $responsableId, [['produit_id' => $grandeId, 'quantite' => 20]]);

    verifierRefus('Clôture sans retour', fn () => $cloture->cloturer($activiteB, $responsableId, 20000));

    $retour->enregistrer($activiteB, $responsableId, []);

    verifierRefus('Deuxième retour pour la même activité', fn () => $retour->enregistrer($activiteB, $responsableId, []));
    verifierRefus('Ravitaillement : quantité nulle', fn () => $ravitaillement->enregistrer($activiteB, $responsableId, [['produit_id' => $petiteId, 'quantite' => 0]]), InvalidArgumentException::class);

    $resume = $cloture->cloturer($activiteB, $responsableId, 20000);

    verifier('Retour à zéro : CA', $resume['ca_theorique'], 20000);
    verifier('Retour à zéro : aucun écart', $resume['statut_controle'], 'aucun_ecart');

    // --- C. Activité sans ravitaillement (sac Russell).
    echo PHP_EOL . '--- C. Activité sans ravitaillement, sac Russell' . PHP_EOL;

    $activiteC = $demarrage->demarrer($journeeId, $vendeur2, $sacs['19'], 'soir', $responsableId);
    $retour->enregistrer($activiteC, $responsableId, []);
    $resume = $cloture->cloturer($activiteC, $responsableId, 0);

    verifier('Activité vide : CA', $resume['ca_theorique'], 0);
    verifier('Activité vide : commission', $resume['commission'], 0);
    verifier('Sac Russell : bénéficiaire', $resume['beneficiaire_benefice'], 'russell');

    // --- D. Opérations refusées sur une activité terminée.
    echo PHP_EOL . '--- D. Activité terminée' . PHP_EOL;

    verifierRefus('Ravitaillement sur une activité terminée', fn () => $ravitaillement->enregistrer($activiteA, $responsableId, [['produit_id' => $petiteId, 'quantite' => 5]]));
    verifierRefus('Retour sur une activité terminée', fn () => $retour->enregistrer($activiteA, $responsableId, []));

    // ------------------------------------------------------------
    echo PHP_EOL . '=== 4. Clôture et réouverture de la journée ===' . PHP_EOL;
    // ------------------------------------------------------------

    $journeeService->cloturer($journeeId, $responsableId, 'Fin du second test');
    verifier('Journée clôturée', valeur($db, 'SELECT statut FROM journees WHERE id = :id', ['id' => $journeeId]), 'cloturee');

    verifierRefus('Activité dans une journée clôturée', fn () => $demarrage->demarrer($journeeId, $vendeur1, $sacs['DEMO-01'], 'matin', $responsableId));
    verifierRefus('Réouverture par un responsable', fn () => $journeeService->rouvrir($journeeId, $responsableId, 'Test'));
    verifierRefus('Réouverture sans raison', fn () => $journeeService->rouvrir($journeeId, $adminId, '   '), InvalidArgumentException::class);

    $raison = 'Erreur de saisie à corriger';
    $journeeService->rouvrir($journeeId, $adminId, $raison);

    verifier('Journée rouverte', valeur($db, 'SELECT statut FROM journees WHERE id = :id', ['id' => $journeeId]), 'ouverte');
    verifier(
        'Réouverture tracée dans l’audit (JourneeService.php à jour ?)',
        valeur(
            $db,
            "SELECT COUNT(*) FROM audit_logs WHERE action = 'journee_rouverte' AND enregistrement_id = :id AND raison = :raison",
            ['id' => $journeeId, 'raison' => $raison]
        ),
        1
    );

    $journeeService->cloturer($journeeId, $responsableId, 'Fin définitive du second test');
    echo PHP_EOL . "Journée {$date} clôturée." . PHP_EOL;

    echo PHP_EOL . ($echecs === 0
        ? "Tous les tests sont passés ({$total} vérifications)."
        : "{$echecs} vérification(s) en échec sur {$total}.") . PHP_EOL;

    exit($echecs === 0 ? 0 : 1);
} catch (Throwable $e) {
    echo PHP_EOL . 'Erreur : ' . get_class($e) . ' - ' . $e->getMessage() . PHP_EOL;
    echo $e->getFile() . ':' . $e->getLine() . PHP_EOL;
    exit(1);
}
