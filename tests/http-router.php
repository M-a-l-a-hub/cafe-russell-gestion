<?php
declare(strict_types=1);

// Le test HTTP utilise les mêmes routes que public/index.php, avec la
// variable de connexion explicitement copiée dans $_ENV pour le CLI.
$_ENV['DATABASE_URL'] = getenv('DATABASE_URL') ?: '';
$_ENV['DB_EMULATE_PREPARES'] = getenv('DB_EMULATE_PREPARES') ?: 'true';
require __DIR__ . '/../public/index.php';
