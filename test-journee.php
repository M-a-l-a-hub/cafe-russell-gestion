<?php

declare(strict_types=1);

require_once __DIR__ . '/src/models/Journee.php';

try {
    $journee = new Journee();

    $siteId = 1;

    $ouverte = $journee->getOpenBySiteId($siteId);

    if ($ouverte === null) {
        echo "Aucune journée ouverte pour ce site.\n";
    } else {
        echo "Journée ouverte trouvée.\n";
        echo "Site : {$ouverte['site_nom']}\n";
        echo "Date : {$ouverte['date_jour']}\n";
        echo "Statut : {$ouverte['statut']}\n";
    }

    echo "\nHistorique des journées :\n";

    $journees = $journee->getBySiteId($siteId);

    foreach ($journees as $item) {
        echo "- {$item['date_jour']} | {$item['statut']}\n";
    }

} catch (Throwable $e) {
    echo "Erreur : " . $e->getMessage() . "\n";
}
