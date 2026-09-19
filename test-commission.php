<?php

declare(strict_types=1);

require_once __DIR__ . '/src/models/Commission.php';

$commissionModel = new Commission();

$commission = $commissionModel->findByActiviteId(1);

if ($commission === null) {
    echo "Aucune commission trouvée." . PHP_EOL;
    exit;
}

echo "Commission trouvée" . PHP_EOL;
echo "Activité : {$commission['activite_id']}" . PHP_EOL;
echo "Vendeur : ";
echo $commission['vendeur_nom'] . ' ';
echo $commission['vendeur_prenom'] . PHP_EOL;
echo "CA : {$commission['ca']} FC" . PHP_EOL;
echo "Taux : {$commission['taux']} %" . PHP_EOL;
echo "Montant : {$commission['montant']} FC" . PHP_EOL;
echo "Date : {$commission['date']}" . PHP_EOL;
