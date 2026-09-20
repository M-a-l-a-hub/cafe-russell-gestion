<?php

declare(strict_types=1);

/**
 * Test du cycle complet d'une activité sur SITE_DEMO :
 * ouverture de journée -> démarrage -> ravitaillement -> retour
 * -> clôture de l'activité -> clôture de la journée.
 *
 * Usage : php test_cycle_activite.php [AAAA-MM-JJ]
 *
 * Prérequis : migrations + seeds 001, 002 et 003 chargés.
 * Le test ferme la journée ouverte du seed 003 (si elle l'est encore),
 * puis crée une nouvelle journée à la date donnée (aujourd'hui par
 * défaut). Une date ne peut servir qu'une fois : relance avec une
 * autre date.
 */

require_once __DIR__ . '/src/config/database.php';
require_once __DIR__ . '/src/models/User.php';
require_once __DIR__ . '/src/models/Journee.php';
require_once __DIR__ . '/src/services/JourneeService.php';
require_once __DIR__ . '/src/services/DemarrageActiviteService.php';
require_once __DIR__ . '/src/services/RavitaillementService.php';
require_once __DIR__ . '/src/services/RetourService.php';
require_once __DIR__ . '/src/services/ClotureActiviteService.php';

$echecs = 0;

function verifier(string $libelle, float|int|string $obtenu, float|int|string $attendu): void
{
    global $echecs;

    $ok = is_numeric($obtenu) && is_numeric($attendu)
        ? abs((float) $obtenu - (float) $attendu) < 0.005
        : $obtenu === $attendu;

    if (!$ok) {
        $echecs++;
    }

    echo ($ok ? '[OK]     ' : '[ÉCHEC]  ')
        . "{$libelle} : obtenu={$obtenu} attendu={$attendu}" . PHP_EOL;
}

try {
    $date = $argv[1] ?? date('Y-m-d');

    $db = getDatabaseConnection();

    $userModel = new User();
    $responsable = $userModel->findByTelephone('+243000000002');

    if ($responsable === null) {
        throw new RuntimeException('Responsable de démo introuvable : charge le seed 002.');
    }

    $userId = (int) $responsable['id'];

    $siteId = (int) $db->query(
        "SELECT id FROM sites WHERE code = 'SITE_DEMO'"
    )->fetchColumn();

    $vendeurId = (int) $db->query(
        "SELECT id FROM vendeurs WHERE code = 'VENDEUR_DEMO_03'"
    )->fetchColumn();

    $sacId = (int) $db->query(
        "SELECT id FROM sacs WHERE site_id = {$siteId} AND numero = 'DEMO-02'"
    )->fetchColumn();

    $petiteId = (int) $db->query(
        "SELECT id FROM produits WHERE code = 'PETITE_TASSE'"
    )->fetchColumn();

    $grandeId = (int) $db->query(
        "SELECT id FROM produits WHERE code = 'GRANDE_TASSE'"
    )->fetchColumn();

    if (!$siteId || !$vendeurId || !$sacId || !$petiteId || !$grandeId) {
        throw new RuntimeException('Données de démo manquantes : charge les seeds 001 et 002.');
    }

    $journeeService = new JourneeService();

    // 0. Ferme la journée encore ouverte (seed 003).
    $ouverte = (new Journee())->getOpenBySiteId($siteId);

    if ($ouverte !== null) {
        $journeeService->cloturer((int) $ouverte['id'], $userId, 'Clôture avant test');
        echo "Journée {$ouverte['date_jour']} clôturée avant le test." . PHP_EOL;
    }

    // 1. Ouverture de la journée.
    $journeeId = $journeeService->ouvrir($siteId, $date, $userId, 'Test cycle activité');
    echo "Journée {$date} ouverte (id={$journeeId})." . PHP_EOL;

    // 2. Démarrage de l'activité de Grâce avec le sac DEMO-02.
    $activiteId = (new DemarrageActiviteService())
        ->demarrer($journeeId, $vendeurId, $sacId, 'journee', $userId);

    echo "Activité démarrée (id={$activiteId})." . PHP_EOL;

    // 3. Ravitaillement : 30 petites + 20 grandes.
    $ravitaillementId = (new RavitaillementService())->enregistrer(
        $activiteId,
        $userId,
        [
            ['produit_id' => $petiteId, 'quantite' => 30],
            ['produit_id' => $grandeId, 'quantite' => 20],
        ],
        'Ravitaillement de test'
    );

    echo "Ravitaillement enregistré (id={$ravitaillementId})." . PHP_EOL;

    // 4. Retour : 5 petites reviennent.
    $retourId = (new RetourService())->enregistrer(
        $activiteId,
        $userId,
        [['produit_id' => $petiteId, 'quantite' => 5]],
        'Retour de test'
    );

    echo "Retour enregistré (id={$retourId})." . PHP_EOL;

    // 5. Clôture : CA attendu = 25 x 500 + 20 x 1000 = 32 500 FC.
    $resume = (new ClotureActiviteService())->cloturer(
        $activiteId,
        $userId,
        32500
    );

    echo PHP_EOL . '=== VÉRIFICATIONS ===' . PHP_EOL;

    verifier('CA théorique', $resume['ca_theorique'], 32500);
    verifier('Montant remis', $resume['montant_remis'], 32500);
    verifier('Écart', $resume['ecart'], 0);
    verifier('Statut du contrôle', $resume['statut_controle'], 'aucun_ecart');
    verifier('Transport (LONGUE)', $resume['transport'], 3000);
    verifier('Commission 25 %', $resume['commission'], 8125);
    verifier('Consommables', $resume['consommables'], 8750);
    verifier('Bénéfice', $resume['benefice'], 12625);
    verifier('Bénéficiaire', $resume['beneficiaire_benefice'], 'entreprise');

    $statutActivite = (string) $db->query(
        "SELECT statut FROM activites WHERE id = {$activiteId}"
    )->fetchColumn();

    $statutSac = (string) $db->query(
        "SELECT statut FROM sacs WHERE id = {$sacId}"
    )->fetchColumn();

    verifier('Statut activité', $statutActivite, 'terminee');
    verifier('Statut sac', $statutSac, 'libre');

    // 6. Une deuxième clôture doit être refusée.
    try {
        (new ClotureActiviteService())->cloturer($activiteId, $userId, 32500);
        verifier('Double clôture refusée', 'acceptée', 'refusée');
    } catch (RuntimeException $e) {
        verifier('Double clôture refusée', 'refusée', 'refusée');
    }

    // 7. Clôture de la journée.
    $journeeService->cloturer($journeeId, $userId, 'Fin du test');
    echo PHP_EOL . "Journée {$date} clôturée." . PHP_EOL;

    echo PHP_EOL . ($echecs === 0
        ? 'Tous les tests sont passés.'
        : "{$echecs} vérification(s) en échec.") . PHP_EOL;
} catch (Throwable $e) {
    echo PHP_EOL . 'Erreur : ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
