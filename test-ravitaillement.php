<?php

declare(strict_types=1);

require_once __DIR__ . '/src/models/Ravitaillement.php';

try {
    $ravitaillementModel = new Ravitaillement();

    $activiteId = 1;

    $ravitaillements = $ravitaillementModel
        ->getByActiviteId($activiteId);

    echo "Ravitaillements : "
        . count($ravitaillements)
        . "\n\n";

    foreach ($ravitaillements as $ravitaillement) {
        echo "- ID : {$ravitaillement['id']}";
        echo " | Transport : "
            . $ravitaillement['transport_par_ravitaillement']
            . " FC";
        echo " | Date : {$ravitaillement['date_heure']}";
        echo "\n";
    }

    echo "\nTransport total : "
        . $ravitaillementModel->getTotalTransport($activiteId)
        . " FC\n";

} catch (Throwable $e) {
    echo "Erreur : " . $e->getMessage() . "\n";
}
