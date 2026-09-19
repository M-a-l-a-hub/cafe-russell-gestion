<?php

declare(strict_types=1);

require_once __DIR__ . '/src/models/User.php';

$userModel = new User();

$users = $userModel->getActiveUsers();

echo "Utilisateurs actifs : "
    . count($users)
    . PHP_EOL;

foreach ($users as $user) {
    echo "- {$user['nom']} {$user['prenom']} | ";
    echo "Téléphone : {$user['telephone']} | ";
    echo "Rôle : {$user['role']}"
        . PHP_EOL;
}
