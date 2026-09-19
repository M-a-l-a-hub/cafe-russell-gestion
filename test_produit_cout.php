<?php

declare(strict_types=1);

require_once __DIR__ . '/src/models/ProduitCout.php';

$coutModel = new ProduitCout();

$produits = [1, 2];

foreach ($produits as $produitId) {
    $cout = $coutModel->getCurrentCost($produitId);

    if ($cout === null) {
        echo "Produit {$produitId} : aucun coût actuel"
            . PHP_EOL;
        continue;
    }

    echo "{$cout['produit_nom']} | ";
    echo "Coût actuel : {$cout['cout_unitaire']} FC | ";
    echo "Depuis : {$cout['date_debut']}"
        . PHP_EOL;
}

echo PHP_EOL;

$historique = $coutModel->getHistory(1);

echo "Historique des coûts du produit 1 : "
    . count($historique)
    . PHP_EOL;

foreach ($historique as $cout) {
    echo "- {$cout['cout_unitaire']} FC | ";
    echo "{$cout['date_debut']} → ";
    echo ($cout['date_fin'] ?? 'actuel')
        . PHP_EOL;
}
