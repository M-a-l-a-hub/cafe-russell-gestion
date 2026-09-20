<?php

declare(strict_types=1);

/**
 * Test d'intégration de la gestion quotidienne :
 * ouverture de journée -> ravitaillement -> retour -> clôture
 * de l'activité -> clôture de la journée.
 *
 * Usage : php test_gestion.php [AAAA-MM-JJ]
 *
 * Prérequis : migrations et seeds 001 à 003 chargés, avec une base
 * PostgreSQL configurée dans DATABASE_URL.
 */

require_once __DIR__ . '/src/config/database.php';
require_once __DIR__ . '/src/models/User.php';
require_once __DIR__ . '/src/models/Journee.php';
require_once __DIR__ . '/src/services/JourneeService.php';
require_once __DIR__ . '/src/services/RavitaillementService.php';
require_once __DIR__ . '/src/services/RetourService.php';
require_once __DIR__ . '/src/services/ClotureActiviteService.php';

$echecs = 0;

function verifier(string $libelle, mixed $obtenu, mixed $attendu): void
{
    global $echecs;

    $ok = is_numeric($obtenu) && is_numeric($attendu)
        ? abs((float) $obtenu - (float) $attendu) < 0.005
        : $obtenu === $attendu;

    if (!$ok) {
        $echecs++;
    }

    echo ($ok ? '[OK]     ' : '[ÉCHEC]  ')
        . $libelle
        . ' : obtenu='
        . var_export($obtenu, true)
        . ' attendu='
        . var_export($attendu, true)
        . PHP_EOL;
}

function valeur(PDO $db, string $sql, array $parametres = []): mixed
{
    $stmt = $db->prepare($sql);
    $stmt->execute($parametres);

    return $stmt->fetchColumn();
}

try {
    $db = getDatabaseConnection();
    $utilisateur = (new User())->findByTelephone('+243000000001');

    if ($utilisateur === null) {
        throw new RuntimeException(
            'Utilisateur de démonstration introuvable : charge le seed 002.'
        );
    }

    $userId = (int) $utilisateur['id'];
    $siteId = (int) valeur(
        $db,
        "SELECT id FROM sites WHERE code = 'SITE_DEMO'"
    );
    $vendeurId = (int) valeur(
        $db,
        "SELECT id FROM vendeurs WHERE code = 'VENDEUR_DEMO_03'"
    );
    $sacId = (int) valeur(
        $db,
        "SELECT id FROM sacs
         WHERE site_id = :site_id AND statut = 'libre'
         ORDER BY id LIMIT 1",
        ['site_id' => $siteId]
    );
    $petiteId = (int) valeur(
        $db,
        "SELECT id FROM produits WHERE code = 'PETITE_TASSE'"
    );
    $grandeId = (int) valeur(
        $db,
        "SELECT id FROM produits WHERE code = 'GRANDE_TASSE'"
    );

    if (in_array(0, [$siteId, $vendeurId, $sacId, $petiteId, $grandeId], true)) {
        throw new RuntimeException(
            'Données de démonstration manquantes : charge les seeds 001 à 003.'
        );
    }

    $journeeService = new JourneeService();
    $ravitaillementService = new RavitaillementService();
    $retourService = new RetourService();
    $clotureService = new ClotureActiviteService();

    $journeeOuverte = (new Journee())->getOpenBySiteId($siteId);

    if ($journeeOuverte !== null) {
        $journeeService->cloturer(
            (int) $journeeOuverte['id'],
            $userId,
            'Clôture avant test de gestion'
        );
    }

    $date = $argv[1] ?? (string) valeur(
        $db,
        "SELECT COALESCE(MAX(date_jour), CURRENT_DATE - 1) + 1
         FROM journees WHERE site_id = :site_id",
        ['site_id' => $siteId]
    );

    $journeeId = $journeeService->ouvrir(
        $siteId,
        $date,
        $userId,
        'Test de gestion quotidienne'
    );

    echo "Journée {$date} ouverte (id={$journeeId})." . PHP_EOL;

    $ravitaillement = $ravitaillementService->enregistrerPourVendeur(
        $journeeId,
        $vendeurId,
        $userId,
        [
            ['produit_id' => $petiteId, 'quantite' => 10],
            ['produit_id' => $grandeId, 'quantite' => 5],
        ],
        $sacId,
        'journee',
        'Ravitaillement du test de gestion'
    );

    verifier('Activité créée automatiquement', $ravitaillement['activite_creee'], true);
    $activiteId = (int) $ravitaillement['activite_id'];

    $retourService->enregistrer(
        $activiteId,
        $userId,
        [['produit_id' => $petiteId, 'quantite' => 2]],
        'Retour du test de gestion'
    );

    $calcul = (new ActiviteService())->calculer($activiteId);
    $resume = $clotureService->cloturer(
        $activiteId,
        $userId,
        $calcul['ca']
    );

    echo PHP_EOL . '=== VÉRIFICATIONS ===' . PHP_EOL;
    verifier('Quantité petite tasse vendue', $calcul['quantites'][0]['vendue'], 8);
    verifier('Contrôle financier', $resume['statut_controle'], 'aucun_ecart');
    verifier('Montant remis égal au CA', $resume['montant_remis'], $resume['ca_theorique']);
    verifier(
        'Activité clôturée',
        valeur($db, 'SELECT statut FROM activites WHERE id = :id', ['id' => $activiteId]),
        'terminee'
    );
    verifier(
        'Sac libéré',
        valeur($db, 'SELECT statut FROM sacs WHERE id = :id', ['id' => $sacId]),
        'libre'
    );

    $journeeService->cloturer($journeeId, $userId, 'Fin du test de gestion');
    verifier(
        'Journée clôturée',
        valeur($db, 'SELECT statut FROM journees WHERE id = :id', ['id' => $journeeId]),
        'cloturee'
    );

    echo PHP_EOL . ($echecs === 0
        ? 'Tous les tests de gestion sont passés.'
        : "{$echecs} vérification(s) en échec.") . PHP_EOL;

    exit($echecs === 0 ? 0 : 1);
} catch (Throwable $e) {
    echo PHP_EOL . 'Erreur : ' . $e->getMessage() . PHP_EOL;
    exit(1);
}