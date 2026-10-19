<?php

declare(strict_types=1);

/**
 * Charge la configuration, la couche HTTP et les contrôleurs.
 * (Le projet utilise des require_once, pas l'autoload de Composer.)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/AccesRefuseException.php';
require_once __DIR__ . '/HttpException.php';
require_once __DIR__ . '/Requete.php';
require_once __DIR__ . '/Reponse.php';
require_once __DIR__ . '/SessionHttp.php';
require_once __DIR__ . '/Routeur.php';
require_once __DIR__ . '/Application.php';
require_once __DIR__ . '/ControleurBase.php';

foreach (['Auth', 'MotDePasse', 'Lectures', 'Journees', 'Activites', 'Finances', 'Sacs', 'Vendeurs', 'Sites', 'Responsables', 'TableauDeBord', 'Bilans'] as $nom) {
    require_once __DIR__ . "/controleurs/{$nom}Controleur.php";
}
