<?php

declare(strict_types=1);

/**
 * Troisième test : DepenseService et SacService.
 *
 * Usage : php test_depense_sac.php
 *
 * Prérequis : migrations + seeds 001 à 003, et src/services à jour.
 *
 * Le test nettoie ses données (dépenses, sacs et catégorie de test
 * supprimés à la fin). Seules les lignes d'audit restent.
 */

require_once __DIR__ . '/src/config/database.php';
require_once __DIR__ . '/src/models/User.php';
require_once __DIR__ . '/src/services/DepenseService.php';
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

$depensesCreees = [];
$sacsCrees = [];
$categorieTest = null;

try {
    $db = getDatabaseConnection();

    $admin = (new User())->findByTelephone('+243000000001');

    if ($admin === null) {
        throw new RuntimeException('Utilisateur de démo introuvable : charge le seed 002.');
    }

    $adminId = (int) $admin['id'];
    $siteId = (int) valeur($db, "SELECT id FROM sites WHERE code = 'SITE_DEMO'");
    $laitId = (int) valeur($db, "SELECT id FROM categories_depenses WHERE code = 'LAIT'");
    $loyerId = (int) valeur($db, "SELECT id FROM categories_depenses WHERE code = 'LOYER'");

    if ($siteId === 0 || $laitId === 0 || $loyerId === 0) {
        throw new RuntimeException('Données de démo manquantes : charge les seeds 001 et 002.');
    }

    $suffixe = date('His') . random_int(10, 99);

    // ------------------------------------------------------------
    echo PHP_EOL . '=== 1. DepenseService ===' . PHP_EOL;
    // ------------------------------------------------------------

    $depenses = new DepenseService();

    $id = $depenses->enregistrer($adminId, $laitId, 'Achat de lait', '12 500,50', $siteId, '2026-09-20', 'Reçu n°123', 'Test');
    $depensesCreees[] = $id;

    verifier('Dépense de site : montant', valeur($db, 'SELECT montant FROM depenses WHERE id = :id', ['id' => $id]), 12500.50);
    verifier('Dépense de site : site', valeur($db, 'SELECT site_id FROM depenses WHERE id = :id', ['id' => $id]), $siteId);
    verifier('Dépense de site : date', valeur($db, 'SELECT date_depense FROM depenses WHERE id = :id', ['id' => $id]), '2026-09-20');
    verifier('Dépense de site : effectuée par', valeur($db, 'SELECT effectue_par FROM depenses WHERE id = :id', ['id' => $id]), $adminId);
    verifier(
        'Dépense de site : audit',
        valeur($db, "SELECT COUNT(*) FROM audit_logs WHERE action = 'depense_creee' AND enregistrement_id = :id AND site_id = :site", ['id' => $id, 'site' => $siteId]),
        1
    );

    $id = $depenses->enregistrer($adminId, $loyerId, 'Loyer du siège', 300000);
    $depensesCreees[] = $id;

    verifier('Dépense globale : pas de site', valeur($db, 'SELECT COUNT(*) FROM depenses WHERE id = :id AND site_id IS NULL', ['id' => $id]), 1);
    verifier('Dépense sans date : aujourd’hui', valeur($db, 'SELECT date_depense FROM depenses WHERE id = :id', ['id' => $id]), valeur($db, 'SELECT CURRENT_DATE'));
    verifier('Dépense globale : marquée globale dans l’audit', valeur($db, "SELECT nouvelles_valeurs->>'globale' FROM audit_logs WHERE action = 'depense_creee' AND enregistrement_id = :id", ['id' => $id]), 'true');

    verifierRefus('Dépense : libellé vide', fn () => $depenses->enregistrer($adminId, $laitId, '   ', 1000, $siteId), InvalidArgumentException::class);
    verifierRefus('Dépense : montant nul', fn () => $depenses->enregistrer($adminId, $laitId, 'Test', 0, $siteId), InvalidArgumentException::class);
    verifierRefus('Dépense : montant négatif', fn () => $depenses->enregistrer($adminId, $laitId, 'Test', -100, $siteId), InvalidArgumentException::class);
    verifierRefus('Dépense : montant invalide', fn () => $depenses->enregistrer($adminId, $laitId, 'Test', '12x', $siteId), InvalidArgumentException::class);
    verifierRefus('Dépense : date invalide', fn () => $depenses->enregistrer($adminId, $laitId, 'Test', 1000, $siteId, '2026-02-30'), InvalidArgumentException::class);
    verifierRefus('Dépense : catégorie inexistante', fn () => $depenses->enregistrer($adminId, 999999999, 'Test', 1000, $siteId));
    verifierRefus('Dépense : site inexistant', fn () => $depenses->enregistrer($adminId, $laitId, 'Test', 1000, 999999999));
    verifierRefus('Dépense : utilisateur inexistant', fn () => $depenses->enregistrer(999999999, $laitId, 'Test', 1000, $siteId));

    $stmt = $db->prepare("INSERT INTO categories_depenses (code, nom, actif) VALUES (:code, 'Catégorie de test', FALSE) RETURNING id");
    $stmt->execute(['code' => "TEST_DEP_{$suffixe}"]);
    $categorieTest = (int) $stmt->fetchColumn();

    verifierRefus('Dépense : catégorie désactivée', fn () => $depenses->enregistrer($adminId, $categorieTest, 'Test', 1000, $siteId));

    // ------------------------------------------------------------
    echo PHP_EOL . '=== 2. SacService ===' . PHP_EOL;
    // ------------------------------------------------------------

    $sacService = new SacService();
    $numero = "TEST-{$suffixe}";

    $sacId = $sacService->creer($siteId, $numero, 'standard', $adminId);
    $sacsCrees[] = $sacId;

    verifier('Sac créé : statut libre', valeur($db, 'SELECT statut FROM sacs WHERE id = :id', ['id' => $sacId]), 'libre');
    verifier('Sac créé : catégorie', valeur($db, 'SELECT categorie FROM sacs WHERE id = :id', ['id' => $sacId]), 'standard');
    verifier('Sac créé : audit', valeur($db, "SELECT COUNT(*) FROM audit_logs WHERE action = 'sac_cree' AND enregistrement_id = :id", ['id' => $sacId]), 1);

    $sacRussell = $sacService->creer($siteId, "TESTR-{$suffixe}", 'russell', $adminId);
    $sacsCrees[] = $sacRussell;

    verifier('Sac Russell créé : catégorie', valeur($db, 'SELECT categorie FROM sacs WHERE id = :id', ['id' => $sacRussell]), 'russell');

    verifierRefus('Sac : numéro déjà présent sur le site', fn () => $sacService->creer($siteId, $numero, 'standard', $adminId));
    verifierRefus('Sac : numéro vide', fn () => $sacService->creer($siteId, '   ', 'standard', $adminId), InvalidArgumentException::class);
    verifierRefus('Sac : catégorie invalide', fn () => $sacService->creer($siteId, "X-{$suffixe}", 'vip', $adminId), InvalidArgumentException::class);
    verifierRefus('Sac : site inexistant', fn () => $sacService->creer(999999999, "X-{$suffixe}", 'standard', $adminId));

    verifierRefus('Indisponible sans motif', fn () => $sacService->marquerIndisponible($sacId, '   ', $adminId), InvalidArgumentException::class);
    verifier('Sac resté libre après le refus', valeur($db, 'SELECT statut FROM sacs WHERE id = :id', ['id' => $sacId]), 'libre');

    $sacService->marquerIndisponible($sacId, 'Sac déchiré', $adminId);

    verifier('Indisponible : statut', valeur($db, 'SELECT statut FROM sacs WHERE id = :id', ['id' => $sacId]), 'indisponible');
    verifier('Indisponible : motif enregistré', valeur($db, 'SELECT motif_indisponibilite FROM sacs WHERE id = :id', ['id' => $sacId]), 'Sac déchiré');
    verifier(
        'Indisponible : audit avec raison',
        valeur($db, "SELECT COUNT(*) FROM audit_logs WHERE action = 'sac_indisponible' AND enregistrement_id = :id AND raison = 'Sac déchiré'", ['id' => $sacId]),
        1
    );

    verifierRefus('Déjà indisponible', fn () => $sacService->marquerIndisponible($sacId, 'Autre motif', $adminId));

    $sacService->rendreDisponible($sacId, $adminId, 'Réparé');

    verifier('Disponible : statut', valeur($db, 'SELECT statut FROM sacs WHERE id = :id', ['id' => $sacId]), 'libre');
    verifier('Disponible : motif effacé', valeur($db, 'SELECT COUNT(*) FROM sacs WHERE id = :id AND motif_indisponibilite IS NULL', ['id' => $sacId]), 1);
    verifier(
        'Disponible : ancien motif conservé dans l’audit',
        valeur($db, "SELECT anciennes_valeurs->>'motif_indisponibilite' FROM audit_logs WHERE action = 'sac_disponible' AND enregistrement_id = :id", ['id' => $sacId]),
        'Sac déchiré'
    );

    verifierRefus('Rendre disponible un sac déjà libre', fn () => $sacService->rendreDisponible($sacId, $adminId));

    executer($db, "UPDATE sacs SET statut = 'en_utilisation' WHERE id = :id", ['id' => $sacRussell]);
    verifierRefus('Indisponible : sac en cours d’utilisation', fn () => $sacService->marquerIndisponible($sacRussell, 'Test', $adminId));
    verifierRefus('Disponible : sac en cours d’utilisation', fn () => $sacService->rendreDisponible($sacRussell, $adminId));
    executer($db, "UPDATE sacs SET statut = 'libre' WHERE id = :id", ['id' => $sacRussell]);

    verifierRefus('Sac inexistant', fn () => $sacService->marquerIndisponible(999999999, 'Test', $adminId));

    echo PHP_EOL . ($echecs === 0
        ? "Tous les tests sont passés ({$total} vérifications)."
        : "{$echecs} vérification(s) en échec sur {$total}.") . PHP_EOL;
} catch (Throwable $e) {
    $echecs++;

    echo PHP_EOL . 'Erreur : ' . get_class($e) . ' - ' . $e->getMessage() . PHP_EOL;
    echo $e->getFile() . ':' . $e->getLine() . PHP_EOL;
} finally {
    // Nettoyage des données de test (les lignes d'audit restent).
    if (isset($db)) {
        foreach ($depensesCreees as $depenseId) {
            executer($db, 'DELETE FROM depenses WHERE id = :id', ['id' => $depenseId]);
        }

        foreach ($sacsCrees as $sacCreeId) {
            executer($db, 'DELETE FROM sacs WHERE id = :id', ['id' => $sacCreeId]);
        }

        if ($categorieTest !== null) {
            executer($db, 'DELETE FROM categories_depenses WHERE id = :id', ['id' => $categorieTest]);
        }
    }
}

exit($echecs === 0 ? 0 : 1);
