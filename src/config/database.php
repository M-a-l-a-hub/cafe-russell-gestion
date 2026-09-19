<?php

declare(strict_types=1);

use Dotenv\Dotenv;

require_once __DIR__ . '/../../vendor/autoload.php';

$dotenv = Dotenv::createImmutable(dirname(__DIR__, 2));
$dotenv->safeLoad();

function getDatabaseConnection(): PDO
{
    $databaseUrl = $_ENV['DATABASE_URL'] ?? '';

    if ($databaseUrl === '') {
        throw new RuntimeException(
            'La variable DATABASE_URL est absente.'
        );
    }

    $parts = parse_url($databaseUrl);

    if (
        $parts === false ||
        !isset($parts['host'], $parts['port'], $parts['path'])
    ) {
        throw new RuntimeException(
            'DATABASE_URL est invalide.'
        );
    }

    $host = $parts['host'];
    $port = $parts['port'];
    $dbname = ltrim($parts['path'], '/');

    $user = isset($parts['user'])
        ? urldecode($parts['user'])
        : '';

    $password = isset($parts['pass'])
        ? urldecode($parts['pass'])
        : '';

    $dsn = sprintf(
        'pgsql:host=%s;port=%s;dbname=%s',
        $host,
        $port,
        $dbname
    );

    return new PDO(
        $dsn,
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
}
