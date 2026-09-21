<?php

declare(strict_types=1);

require_once __DIR__ . '/../ControleurBase.php';
require_once __DIR__ . '/../SessionHttp.php';
require_once __DIR__ . '/../../services/AuthService.php';

final class AuthControleur extends ControleurBase
{
    /** POST /api/login {telephone, mot_de_passe} */
    public function connexion(Requete $req): array
    {
        $utilisateur = (new AuthService())->connecter(
            $req->texte('telephone', 30),
            $req->secret('mot_de_passe'),
            $req->ip()
        );

        $jeton = SessionHttp::ouvrir((int) $utilisateur['id']);

        return ['utilisateur' => $utilisateur, 'csrf' => $jeton];
    }

    /** POST /api/logout */
    public function deconnexion(Requete $req): array
    {
        SessionHttp::fermer();

        return ['deconnecte' => true];
    }

    /** GET /api/moi : à appeler au chargement de l'application. */
    public function moi(Requete $req): array
    {
        return [
            'utilisateur' => $req->utilisateur,
            'csrf' => SessionHttp::jetonCsrf(),
        ];
    }

    /** GET /api/sante : vérification pour l'hébergeur (public). */
    public function sante(Requete $req): array
    {
        getDatabaseConnection()->query('SELECT 1');

        return ['statut' => 'ok', 'date' => date('c')];
    }
}
