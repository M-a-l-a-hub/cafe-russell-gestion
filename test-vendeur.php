<?php

declare(strict_types=1);

require_once __DIR__ . '/src/models/Vendeur.php';

try {
    $vendeurModel = new Vendeur();

    $vendeurs = $vendeurModel->getActiveVendeurs();

    echo "Vendeurs actifs : " . count($vendeurs) . PHP_EOL;

    foreach ($vendeurs as $vendeur) {
        $nomComplet = trim(
            $vendeur['nom'] . ' ' . ($vendeur['prenom'] ?? '')
        );

        echo "- {$nomComplet}";
        echo " [{$vendeur['code']}]";
        echo " — {$vendeur['categorie_transport_nom']}";
        echo PHP_EOL;
    }
} catch (Throwable $e) {
    echo "Erreur : " . $e->getMessage() . PHP_EOL;
}
