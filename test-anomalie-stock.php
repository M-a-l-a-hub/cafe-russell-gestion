<?php

declare(strict_types=1);

require_once __DIR__ . '/src/models/AnomalieStock.php';

$anomalieModel = new AnomalieStock();

$anomalies = $anomalieModel->getByActiviteId(1);

echo "Anomalies de l'activité : "
    . count($anomalies)
    . PHP_EOL;

foreach ($anomalies as $anomalie) {
    echo "- {$anomalie['produit_nom']} | ";
    echo "Attendue : {$anomalie['quantite_attendue']} | ";
    echo "Constatée : {$anomalie['quantite_constatee']} | ";
    echo "Écart : {$anomalie['ecart']} | ";
    echo "Statut : {$anomalie['statut']}"
        . PHP_EOL;
}

$nonResolues = $anomalieModel->getNonResolues();

echo PHP_EOL;

echo "Anomalies non résolues : "
    . count($nonResolues)
    . PHP_EOL;
