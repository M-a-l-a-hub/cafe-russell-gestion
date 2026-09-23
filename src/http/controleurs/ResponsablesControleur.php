<?php

declare(strict_types=1);

require_once __DIR__ . '/../ControleurBase.php';
require_once __DIR__ . '/../../services/ResponsableService.php';

final class ResponsablesControleur extends ControleurBase
{
    /** POST /api/responsables {nom, prenom?, telephone, email?, mot_de_passe} */
    public function creer(Requete $req): Resultat
    {
        $id = (new ResponsableService())->creer(
            $req->texte('nom', 100),
            $req->texteOptionnel('prenom', 100),
            $req->texte('telephone', 30),
            $req->texteOptionnel('email', 255),
            $req->secret('mot_de_passe'),
            $this->utilisateurId($req)
        );

        return new Resultat(201, ['responsable_id' => $id]);
    }
}