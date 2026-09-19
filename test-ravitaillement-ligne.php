<?php

declare(strict_types=1);

require_once __DIR__ . '/src/models/RavitaillementLigne.php';

try {
    $ligneModel = new RavitaillementLigne();

    $ravitaillementId = 1;

    $lignes = $ligneModel
        ->getByRavitaillementId($ravitaillementId);

    echo "Lignes du ravitaillement : "
        . count($lignes)
        . "\n\n";

    foreach ($lignes as $ligne) {
        echo "- {$ligne['produit_nom']}";
        echo " | Quantité : {$ligne['quantite']}";
        echo " | Prix : {$ligne['prix_unitaire']} FC";
        echo " | Coût : {$ligne['cout_unitaire']} FC";
        echo "\n";
    }

    echo "\nValeur ventes : "
        . $ligneModel->getTotalVentes($ravitaillementId)
        . " FC\n";

    echo "Coût total : "
        . $ligneModel->getTotalCouts($ravitaillementId)
        . " FC\n";

} catch (Throwable $e) {
    echo "Erreur : " . $e->getMessage() . "\n";
}
