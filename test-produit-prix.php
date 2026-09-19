<?php

declare(strict_types=1);

require_once __DIR__ . '/src/models/ProduitPrix.php';

try {
    $produitPrix = new ProduitPrix();

    $prixActuels = $produitPrix->getCurrentPrices();

    echo "Prix actuels : " . count($prixActuels) . PHP_EOL;

    foreach ($prixActuels as $prix) {
        echo "- {$prix['produit_nom']} : {$prix['prix']} FC" . PHP_EOL;
    }
} catch (Throwable $e) {
    echo "Erreur : " . $e->getMessage() . PHP_EOL;
}

