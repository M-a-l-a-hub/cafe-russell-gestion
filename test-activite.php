<?php

declare(strict_types=1);

require_once __DIR__ . '/src/models/Activite.php';

try {
    $activiteModel = new Activite();

    $journeeId = 1;

    $activites = $activiteModel->getByJourneeId($journeeId);

    echo "Activités de la journée : " . count($activites) . "\n\n";

    foreach ($activites as $activite) {
        echo "- ";
        echo $activite['vendeur_prenom'] . ' ';
        echo $activite['vendeur_nom'];
        echo " | Sac " . $activite['sac_numero'];
        echo " | Période : " . $activite['periode'];
        echo " | Statut : " . $activite['statut'];
        echo "\n";
    }

} catch (Throwable $e) {
    echo "Erreur : " . $e->getMessage() . "\n";
}
