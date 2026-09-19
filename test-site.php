<?php

declare(strict_types=1);

require_once __DIR__ . '/src/models/Site.php';

try {
    $siteModel = new Site();

    $sites = $siteModel->getActiveSites();

    echo "Sites actifs : " . count($sites) . PHP_EOL;

    foreach ($sites as $site) {
        echo "- {$site['nom']} ({$site['code']})" . PHP_EOL;
    }
} catch (Throwable $e) {
    echo "Erreur : " . $e->getMessage() . PHP_EOL;
}
