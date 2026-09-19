<?php

declare(strict_types=1);

require_once __DIR__ . '/src/models/MouvementSac.php';

$mouvementModel = new MouvementSac();

$mouvements = $mouvementModel->getRecent();

echo "Mouvements de sacs : "
    . count($mouvements)
    . PHP_EOL;

foreach ($mouvements as $mouvement) {
    echo "- Sac {$mouvement['sac_numero']} | ";
    echo "{$mouvement['site_depart_nom']} → ";
    echo "{$mouvement['site_arrivee_nom']} | ";
    echo "Motif : {$mouvement['motif']}"
        . PHP_EOL;
}
