<?php

declare(strict_types=1);

require_once __DIR__ . '/src/models/AuditLog.php';

$auditModel = new AuditLog();

$logs = $auditModel->getRecent();

echo "Logs d'audit : " . count($logs) . PHP_EOL;

foreach ($logs as $log) {
    echo "- ID : {$log['id']} | ";
    echo "Action : {$log['action']}";

    if ($log['table_cible'] !== null) {
        echo " | Table : {$log['table_cible']}";
    }

    if ($log['enregistrement_id'] !== null) {
        echo " | ID : {$log['enregistrement_id']}";
    }

    echo " | Utilisateur : "
        . $log['utilisateur_nom']
        . ' '
        . $log['utilisateur_prenom'];

    echo " | Date : {$log['date_heure']}"
        . PHP_EOL;
}
