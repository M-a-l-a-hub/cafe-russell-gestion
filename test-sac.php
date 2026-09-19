<?php

declare(strict_types=1);

require_once __DIR__ . '/src/models/Sac.php';

try {
    $sacModel = new Sac();

    $sacs = $sacModel->getBySiteId(1);

    echo "Sacs du site 1 : " . count($sacs) . PHP_EOL;

    foreach ($sacs as $sac) {
        echo "- Sac {$sac['numero']}";
        echo " | catégorie : {$sac['categorie']}";
        echo " | statut : {$sac['statut']}";
        echo PHP_EOL;
    }
} catch (Throwable $e) {
    echo "Erreur : " . $e->getMessage() . PHP_EOL;
}
