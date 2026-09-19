<?php

declare(strict_types=1);

require_once __DIR__ . '/src/models/Retrait.php';

$retraitModel = new Retrait();

$retraits = $retraitModel->getByVendeurId(1);

echo "Retraits du vendeur : " . count($retraits) . PHP_EOL;

foreach ($retraits as $retrait) {
    echo "- ID : {$retrait['id']} | ";
    echo "Montant : {$retrait['montant']} FC | ";
    echo "Date : {$retrait['date']}";

    if ($retrait['motif'] !== null) {
        echo " | Motif : {$retrait['motif']}";
    }

    echo PHP_EOL;
}

$total = $retraitModel->getTotalByVendeurId(1);

echo "Total des retraits : {$total} FC" . PHP_EOL;
