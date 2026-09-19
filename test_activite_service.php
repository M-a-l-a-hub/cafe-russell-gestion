<?php

declare(strict_types=1);

require_once __DIR__ . '/src/services/ActiviteService.php';

$service = new ActiviteService();

$resultat = $service->calculer(1);

$activite = $resultat['activite'];

echo "=== ACTIVITÉ ===" . PHP_EOL;
echo "ID : {$activite['id']}" . PHP_EOL;
echo "Vendeur : {$activite['vendeur_prenom']} {$activite['vendeur_nom']}" . PHP_EOL;
echo "Sac : {$activite['sac_numero']}" . PHP_EOL;
echo "Catégorie : {$activite['sac_categorie']}" . PHP_EOL;
echo "Période : {$activite['periode']}" . PHP_EOL;

echo PHP_EOL;
echo "=== QUANTITÉS ===" . PHP_EOL;

foreach ($resultat['quantites'] as $quantite) {
    echo "- {$quantite['nom']} : ";
    echo "reçue={$quantite['recue']}, ";
    echo "retournée={$quantite['retournee']}, ";
    echo "vendue={$quantite['vendue']}"
        . PHP_EOL;
}

echo PHP_EOL;
echo "=== CALCUL FINANCIER ===" . PHP_EOL;
echo "CA : {$resultat['ca']} FC" . PHP_EOL;
echo "Transport : {$resultat['transport']} FC" . PHP_EOL;
echo "Commission : {$resultat['commission']} FC" . PHP_EOL;
echo "Taux commission : {$resultat['taux_commission']} %" . PHP_EOL;
echo "Consommables : {$resultat['consommables']} FC" . PHP_EOL;
echo "Bénéfice : {$resultat['benefice']} FC" . PHP_EOL;

echo PHP_EOL;
echo "=== BÉNÉFICIAIRE ===" . PHP_EOL;
echo "Bénéficiaire du bénéfice : "
    . $resultat['beneficiaire_benefice']
    . PHP_EOL;
