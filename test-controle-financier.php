<?php

declare(strict_types=1);

require_once __DIR__ . '/src/models/ControleFinancier.php';

$controleFinancier = new ControleFinancier();

$controle = $controleFinancier->findByActiviteId(1);

if ($controle === null) {
    echo "Aucun contrôle financier trouvé." . PHP_EOL;
    exit;
}

echo "Contrôle financier trouvé" . PHP_EOL;
echo "Activité : {$controle['activite_id']}" . PHP_EOL;
echo "CA théorique : {$controle['ca_theorique']} FC" . PHP_EOL;
echo "Montant remis : {$controle['montant_remis']} FC" . PHP_EOL;
echo "Écart : {$controle['ecart']} FC" . PHP_EOL;
echo "Statut : {$controle['statut']}" . PHP_EOL;

if ($controle['motif_ecart'] !== null) {
    echo "Motif : {$controle['motif_ecart']}" . PHP_EOL;
}

echo "Contrôlé par : ";
echo $controle['controleur_nom'] . ' ';
echo $controle['controleur_prenom'] . PHP_EOL;
