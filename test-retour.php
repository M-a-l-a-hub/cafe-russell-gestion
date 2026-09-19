<?php

declare(strict_types=1);

require_once __DIR__ . '/src/models/Retour.php';

try {
    $retourModel = new Retour();

    $activiteId = 1;

    $retour = $retourModel->findByActiviteId($activiteId);

    if ($retour === null) {
        echo "Aucun retour trouvé pour cette activité.\n";
        exit;
    }

    echo "Retour trouvé\n";
    echo "ID : {$retour['id']}\n";
    echo "Activité : {$retour['activite_id']}\n";
    echo "Effectué par : "
        . $retour['utilisateur_prenom']
        . ' '
        . $retour['utilisateur_nom']
        . "\n";
    echo "Date : {$retour['date_heure']}\n";

} catch (Throwable $e) {
    echo "Erreur : " . $e->getMessage() . "\n";
}
