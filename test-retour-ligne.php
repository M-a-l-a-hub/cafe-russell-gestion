<?php

declare(strict_types=1);

require_once __DIR__ . '/src/models/RetourLigne.php';

$retourLigne = new RetourLigne();

$lignes = $retourLigne->getByRetourId(1);

echo "Lignes du retour : " . count($lignes) . PHP_EOL;

foreach ($lignes as $ligne) {
    echo "- {$ligne['produit_nom']} | ";
    echo "Quantité : {$ligne['quantite']} | ";
    echo "Unité : {$ligne['produit_unite']}" . PHP_EOL;
}

$total = $retourLigne->getTotalQuantites(1);

echo "Quantité totale retournée : {$total}" . PHP_EOL;
