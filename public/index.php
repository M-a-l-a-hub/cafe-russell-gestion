<?php

declare(strict_types=1);

// Serveur intégré de PHP : laisse passer les fichiers statiques existants.
if (PHP_SAPI === 'cli-server') {
    $chemin = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);

    if (is_string($chemin) && $chemin !== '/' && is_file(__DIR__ . $chemin)) {
        return false;
    }
}

require_once __DIR__ . '/../src/http/bootstrap.php';

(new Application(require __DIR__ . '/../src/http/routes.php'))->executer();
