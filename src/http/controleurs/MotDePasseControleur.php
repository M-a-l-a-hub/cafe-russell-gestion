<?php

declare(strict_types=1);

require_once __DIR__ . '/../ControleurBase.php';
require_once __DIR__ . '/../SessionHttp.php';
require_once __DIR__ . '/../../services/MotDePasseService.php';

final class MotDePasseControleur extends ControleurBase
{
    /** POST /api/mot-de-passe : modification du mot de passe du compte connecté. */
    public function changer(Requete $req): array
    {
        $userId = $this->utilisateurId($req);

        (new MotDePasseService())->changer(
            $userId,
            $req->secret('mot_de_passe_actuel'),
            $req->secret('nouveau_mot_de_passe'),
            $req->secret('confirmation_mot_de_passe')
        );

        // Renouvelle l'identifiant de session et le jeton CSRF après le changement.
        $csrf = SessionHttp::ouvrir($userId);

        return [
            'mot_de_passe_modifie' => true,
            'csrf' => $csrf,
        ];
    }
}
