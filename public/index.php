<?php

declare(strict_types=1);

if (PHP_SAPI === 'cli-server') {
    $chemin = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);

    if (is_string($chemin) && $chemin !== '' && $chemin !== '/') {
        $fichier = __DIR__ . $chemin;

        if (is_file($fichier)) {
            $extension = strtolower(pathinfo($fichier, PATHINFO_EXTENSION));
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

            header('Content-Type: ' . ($mimes[$extension] ?? 'application/octet-stream'));
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
