<?php

declare(strict_types=1);

require_once __DIR__ . '/src/models/Depense.php';

$depenseModel = new Depense();

$depenses = $depenseModel->getBySiteId(1);

echo "Dépenses du site : " . count($depenses) . PHP_EOL;

foreach ($depenses as $depense) {
    echo "- {$depense['libelle']} | ";
    echo "{$depense['montant']} FC | ";
    echo "{$depense['categorie_nom']} | ";
    echo "{$depense['date_depense']}" . PHP_EOL;
}

$globales = $depenseModel->getGlobales();

echo PHP_EOL;
echo "Dépenses globales : " . count($globales) . PHP_EOL;

foreach ($globales as $depense) {
    echo "- {$depense['libelle']} | ";
    echo "{$depense['montant']} FC | ";
    echo "{$depense['categorie_nom']} | ";
    echo "{$depense['date_depense']}" . PHP_EOL;
}
