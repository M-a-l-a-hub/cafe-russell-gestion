<?php

declare(strict_types=1);

require_once __DIR__ . '/../ControleurBase.php';
require_once __DIR__ . '/../../services/SacService.php';
require_once __DIR__ . '/../../services/MouvementSacService.php';

final class SacsControleur extends ControleurBase
{
    /** POST /api/sacs {site_id, numero, categorie: standard|russell} */
    public function creer(Requete $req): Resultat
    {
        $id = (new SacService())->creer(
            $req->entier('site_id'),
            $req->texte('numero', 50),
            $req->choix('categorie', ['standard', 'russell']),
            $this->utilisateurId($req)
        );

        return new Resultat(201, ['sac_id' => $id]);
    }

    /** POST /api/sacs/{id}/indisponibilite {motif} */
    public function rendreIndisponible(Requete $req): array
    {
        $id = $req->parametreChemin('id');
        $this->verifierSiteDuSac($req, $id);

        (new SacService())->marquerIndisponible(
            $id,
            $req->texte('motif'),
            $this->utilisateurId($req)
        );

        return ['sac_id' => $id, 'statut' => 'indisponible'];
    }

    /** POST /api/sacs/{id}/disponibilite {note?} */
    public function rendreDisponible(Requete $req): array
    {
        $id = $req->parametreChemin('id');
        $this->verifierSiteDuSac($req, $id);

        (new SacService())->rendreDisponible(
            $id,
            $this->utilisateurId($req),
            $req->texteOptionnel('note')
        );

        return ['sac_id' => $id, 'statut' => 'libre'];
    }

    /** POST /api/sacs/{id}/deplacement {site_arrivee_id, motif, note?} */
    public function deplacer(Requete $req): Resultat
    {
        $id = (new MouvementSacService())->deplacer(
            $req->parametreChemin('id'),
            $req->entier('site_arrivee_id'),
            $this->utilisateurId($req),
            $req->texte('motif'),
            $req->texteOptionnel('note')
        );

        return new Resultat(201, ['mouvement_id' => $id]);
    }

    /** POST /api/sacs/{id}/categorie {categorie: standard|russell, motif?} (Russell) */
    public function changerCategorie(Requete $req): array
    {
        $id = $req->parametreChemin('id');

        (new SacService())->changerCategorie(
            $id,
            $req->choix('categorie', ['standard', 'russell']),
            $this->utilisateurId($req),
            $req->texteOptionnel('motif')
        );

        return ['sac_id' => $id];
    }

    /**
     * SacService ne vérifie pas le site pour ces deux opérations : le
     * contrôle est fait ici pour qu'un responsable ne touche que les
     * sacs de son site.
     */
    private function verifierSiteDuSac(Requete $req, int $sacId): void
    {
        $sac = $this->uneLigne(
            'SELECT site_id FROM sacs WHERE id = :id',
            ['id' => $sacId]
        );

        if ($sac === null) {
            throw new HttpException(404, 'introuvable', 'Sac introuvable.');
        }

        $this->verifierSite($req, (int) $sac['site_id']);
    }
}
