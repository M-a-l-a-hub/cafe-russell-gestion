<?php

declare(strict_types=1);

require_once __DIR__ . '/src/models/PaiementSolde.php';

$paiementModel = new PaiementSolde();

$paiements = $paiementModel->getByVendeurId(1);

echo "Paiements du vendeur : " . count($paiements) . PHP_EOL;

foreach ($paiements as $paiement) {
    echo "- ID : {$paiement['id']} | ";
    echo "Montant : {$paiement['montant']} FC | ";
    echo "Date : {$paiement['date']}";

    if ($paiement['note'] !== null) {
        echo " | Note : {$paiement['note']}";
    }

    echo PHP_EOL;
}

$total = $paiementModel->getTotalByVendeurId(1);

echo "Total des paiements : {$total} FC" . PHP_EOL;
