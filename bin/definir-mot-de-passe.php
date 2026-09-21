<?php

declare(strict_types=1);

/**
 * Définit (ou remplace) le mot de passe d'un utilisateur.
 *
 * Usage : php bin/definir-mot-de-passe.php +243000000001
 *
 * Les utilisateurs des seeds ont un faux hash : ils ne peuvent pas se
 * connecter tant que ce script n'a pas été lancé pour eux.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../src/config/database.php';

function lireSecret(string $invite): string
{
    fwrite(STDOUT, $invite);

    $etatTerminal = @shell_exec('stty -g 2>/dev/null');
    $masque = is_string($etatTerminal) && trim($etatTerminal) !== '';

    if ($masque) {
        @shell_exec('stty -echo');
    }

    $ligne = fgets(STDIN);

    if ($masque) {
        @shell_exec('stty ' . escapeshellarg(trim((string) $etatTerminal)));
        fwrite(STDOUT, PHP_EOL);
    }

    return $ligne === false ? '' : rtrim($ligne, "\r\n");
}

$telephone = (string) preg_replace('/[\s.\-()]/', '', (string) ($argv[1] ?? ''));

if ($telephone === '') {
    fwrite(STDERR, "Usage : php bin/definir-mot-de-passe.php +243000000001\n");
    exit(1);
}

$db = getDatabaseConnection();

$stmt = $db->prepare(
    'SELECT id, nom, prenom, role, actif FROM users WHERE telephone = :telephone'
);
$stmt->execute(['telephone' => $telephone]);
$user = $stmt->fetch();

if ($user === false) {
    fwrite(STDERR, "Aucun utilisateur avec le numéro {$telephone}.\n");
    exit(1);
}

fwrite(
    STDOUT,
    sprintf(
        "Utilisateur : %s %s (%s)\n",
        (string) $user['prenom'],
        (string) $user['nom'],
        (string) $user['role']
    )
);

$motDePasse = lireSecret('Nouveau mot de passe (10 caractères minimum) : ');
$confirmation = lireSecret('Confirme le mot de passe : ');

if ($motDePasse !== $confirmation) {
    fwrite(STDERR, "Les deux saisies sont différentes : rien n'a été modifié.\n");
    exit(1);
}

if (mb_strlen($motDePasse) < 10) {
    fwrite(STDERR, "Mot de passe trop court (10 caractères minimum) : rien n'a été modifié.\n");
    exit(1);
}

$stmt = $db->prepare(
    '
    UPDATE users
    SET password_hash = :hash, updated_at = NOW()
    WHERE id = :id
    '
);
$stmt->execute([
    'hash' => password_hash($motDePasse, PASSWORD_DEFAULT),
    'id' => $user['id'],
]);

fwrite(STDOUT, "Mot de passe enregistré.\n");
