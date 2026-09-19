<?php

declare(strict_types=1);

require_once __DIR__ . '/src/config/database.php';

try {
    $pdo = getDatabaseConnection();

    echo "Connexion PostgreSQL réussie.\n";
} catch (Throwable $e) {
    echo "Erreur : " . $e->getMessage() . "\n";
}
