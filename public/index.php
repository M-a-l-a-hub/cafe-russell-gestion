<?php

declare(strict_types=1);

if (PHP_SAPI === 'cli-server') {
    $chemin = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);

    if (is_string($chemin) && $chemin !== '' && $chemin !== '/') {
        // Liste fermée : seuls ces types de fichiers sont servis en direct
        // (jamais de .php, .env, .sql, etc.).
        $mimes = [
            'css' => 'text/css; charset=utf-8',
            'js' => 'application/javascript; charset=utf-8',
            'json' => 'application/json; charset=utf-8',
            'html' => 'text/html; charset=utf-8',
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'ico' => 'image/x-icon',
        ];

        $extension = strtolower(pathinfo($chemin, PATHINFO_EXTENSION));
        $racine = realpath(__DIR__);
        $fichier = realpath(__DIR__ . $chemin);

        // realpath() résout les "../" : le fichier doit rester dans public/.
        if (
            isset($mimes[$extension])
            && $racine !== false
            && $fichier !== false
            && str_starts_with($fichier, $racine . DIRECTORY_SEPARATOR)
            && is_file($fichier)
        ) {
            header('Content-Type: ' . $mimes[$extension]);
            header('Cache-Control: no-cache');
            readfile($fichier);
            exit;
        }
    }

    if ($chemin === '/' || $chemin === '') {
        header('Content-Type: text/html; charset=utf-8');
        readfile(__DIR__ . '/app.html');
        exit;
    }
}

require_once __DIR__ . '/../src/http/bootstrap.php';

(new Application(require __DIR__ . '/../src/http/routes.php'))->executer();
