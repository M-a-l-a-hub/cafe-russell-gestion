<?php

declare(strict_types=1);

require_once __DIR__ . '/src/models/CategorieDepense.php';

$categorieModel = new CategorieDepense();

$categories = $categorieModel->getActiveCategories();

echo "Catégories de dépenses actives : "
    . count($categories)
    . PHP_EOL;

foreach ($categories as $categorie) {
    echo "- {$categorie['nom']} "
        . "[{$categorie['code']}]";

    if ($categorie['description'] !== null) {
        echo " | {$categorie['description']}";
    }

    echo PHP_EOL;
}
