<?php

declare(strict_types=1);

require_once __DIR__ . '/src/models/Produit.php';

try {
    $produitModel = new Produit();

    $produits = $produitModel->getActiveProduits();

    echo "Produits actifs : " . count($produits) . PHP_EOL;

    foreach ($produits as $produit) {
        echo "- {$produit['nom']}";
        echo " [{$produit['code']}]";
        echo " — unité : {$produit['unite']}";
        echo PHP_EOL;
    }
} catch (Throwable $e) {
    echo "Erreur : " . $e->getMessage() . PHP_EOL;
}
