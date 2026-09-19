<?php

declare(strict_types=1);

require_once __DIR__ . '/src/models/SiteResponsable.php';

$responsableModel = new SiteResponsable();

$responsables = $responsableModel->getBySiteId(1);

echo "Responsables du site 1 : "
    . count($responsables)
    . PHP_EOL;

foreach ($responsables as $responsable) {
    echo "- {$responsable['utilisateur_nom']} ";
    echo "{$responsable['utilisateur_prenom']} | ";
    echo "Type : {$responsable['type']} | ";
    echo "Actif : "
        . ($responsable['actif'] ? 'oui' : 'non')
        . PHP_EOL;
}

$principal = $responsableModel->getPrincipalBySiteId(1);

echo PHP_EOL;

if ($principal === null) {
    echo "Aucun responsable principal actif."
        . PHP_EOL;
} else {
    echo "Responsable principal : ";
    echo "{$principal['utilisateur_nom']} ";
    echo "{$principal['utilisateur_prenom']}"
        . PHP_EOL;
}
