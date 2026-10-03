<?php

declare(strict_types=1);

use Dotenv\Dotenv;

require_once __DIR__ . '/../../vendor/autoload.php';

$dotenv = Dotenv::createImmutable(dirname(__DIR__, 2));
$dotenv->safeLoad();

/*
 * L'application fonctionne à Kinshasa : toutes les dates métier
 * (PHP et PostgreSQL) suivent ce fuseau horaire.
 */
if (!defined('FUSEAU_HORAIRE_APPLICATION')) {
    define('FUSEAU_HORAIRE_APPLICATION', 'Africa/Kinshasa');
}

date_default_timezone_set(FUSEAU_HORAIRE_APPLICATION);

/**
 * Retourne la connexion PostgreSQL de l'application.
 *
 * La connexion est unique et partagée : les modèles et les services
 * utilisent la même transaction quand une opération en appelle une autre.
 */
function getDatabaseConnection(): PDO
{
    static $connexion = null;

    if ($connexion instanceof PDO) {
        return $connexion;
    }

    $databaseUrl = $_ENV['DATABASE_URL'] ?? '';

    if ($databaseUrl === '') {
        throw new RuntimeException(
            'La variable DATABASE_URL est absente.'
        );
    }

    $parts = parse_url($databaseUrl);

    if (
        $parts === false ||
        !isset($parts['host'], $parts['path'])
    ) {
        throw new RuntimeException(
            'DATABASE_URL est invalide.'
        );
    }

    $host = $parts['host'];
    $port = $parts['port'] ?? 5432;
    parse_str($parts['query'] ?? '', $optionsUrl);
    $sslmode = $optionsUrl['sslmode'] ?? 'prefer';
    $dbname = ltrim($parts['path'], '/');

    $user = isset($parts['user'])
        ? urldecode($parts['user'])
        : '';

    $password = isset($parts['pass'])
        ? urldecode($parts['pass'])
        : '';

    $dsn = sprintf(
        'pgsql:host=%s;port=%s;dbname=%s;sslmode=%s',
        $host,
        $port,
        $dbname,
        $sslmode
    );

    $connexion = new PDO(
        $dsn,
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // DB_EMULATE_PREPARES=true : pas de requêtes préparées côté serveur
            // (évite DEALLOCATE, qui casse certaines transactions derrière le
            // pooler Neon). Faux par défaut.
            PDO::ATTR_EMULATE_PREPARES => filter_var(
                $_ENV['DB_EMULATE_PREPARES'] ?? (getenv('DB_EMULATE_PREPARES') ?: 'false'),
                FILTER_VALIDATE_BOOLEAN
            ),
        ]
    );

    // CURRENT_DATE, dates par défaut, etc. : heure de Kinshasa.
    $connexion->exec(
        "SET TIME ZONE '" . FUSEAU_HORAIRE_APPLICATION . "'"
    );

    return $connexion;
}
