<?php

declare(strict_types=1);

/**
 * En-têtes de sécurité sur toutes les réponses ; la CSP uniquement sur la page
 * HTML (le service worker a besoin de joindre les polices Google).
 */
function envoyerEntetesSecurite(bool $page): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';

    if ($https) {
        header('Strict-Transport-Security: max-age=31536000');
    }

    if ($page) {
        header(
            "Content-Security-Policy: default-src 'self'; script-src 'self'; "
            . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
            . "font-src https://fonts.gstatic.com; img-src 'self' data:; "
            . "connect-src 'self'; manifest-src 'self'; worker-src 'self'; "
            . "object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'"
        );
    }
}

envoyerEntetesSecurite(false);

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
            envoyerEntetesSecurite($extension === 'html');
            header('Content-Type: ' . $mimes[$extension]);
            header('Cache-Control: no-cache');
            readfile($fichier);
            exit;
        }
    }

    if ($chemin === '/' || $chemin === '') {
        envoyerEntetesSecurite(true);
        header('Content-Type: text/html; charset=utf-8');
        readfile(__DIR__ . '/app.html');
        exit;
    }
}

require_once __DIR__ . '/../src/http/bootstrap.php';

(new Application(require __DIR__ . '/../src/http/routes.php'))->executer();
