<?php

declare(strict_types=1);

require_once __DIR__ . '/../ControleurBase.php';
require_once __DIR__ . '/../../services/SiteService.php';

final class SitesControleur extends ControleurBase
{
    /** GET /api/responsables */
    public function responsables(Requete $req): array
    {
        return (new SiteService())->listerResponsables($this->utilisateurId($req));
    }

    /** POST /api/sites {code, nom, adresse?, description?} */
    public function creer(Requete $req): Resultat
    {
        $id = (new SiteService())->creer(
            $req->texte('code', 50),
            $req->texte('nom', 150),
            $req->texteOptionnel('adresse', 500),
            $req->texteOptionnel('description', 1000),
            $this->utilisateurId($req)
        );

        return new Resultat(201, ['site_id' => $id]);
    }

    /** POST /api/sites/{id}/modification */
    public function modifier(Requete $req): Resultat
    {
        (new SiteService())->modifier(
            $req->parametreChemin('id'),
            $req->texte('code', 50),
            $req->texte('nom', 150),
            $req->texteOptionnel('adresse', 500),
            $req->texteOptionnel('description', 1000),
            $this->utilisateurId($req)
        );

        return new Resultat(200, ['modifie' => true]);
    }

    /** POST /api/sites/{id}/desactivation */
    public function desactiver(Requete $req): Resultat
    {
        (new SiteService())->desactiver(
            $req->parametreChemin('id'),
            $this->utilisateurId($req)
        );

        return new Resultat(200, ['desactive' => true]);
    }

    /** POST /api/sites/{id}/responsable {responsable_id, type} */
    public function affecterResponsable(Requete $req): Resultat
    {
        $id = (new SiteService())->affecterResponsable(
            $req->parametreChemin('id'),
            $req->entier('responsable_id'),
            $req->texte('type', 20),
            $this->utilisateurId($req)
        );

        return new Resultat(201, ['affectation_id' => $id]);
    }
}
