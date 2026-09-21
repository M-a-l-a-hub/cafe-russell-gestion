<?php

declare(strict_types=1);

require_once __DIR__ . '/../ControleurBase.php';
require_once __DIR__ . '/../../services/JourneeService.php';
require_once __DIR__ . '/../../services/RapportService.php';
require_once __DIR__ . '/../../services/RavitaillementService.php';
require_once __DIR__ . '/../../services/DemarrageActiviteService.php';

final class JourneesControleur extends ControleurBase
{
    private const PERIODES = ['matin', 'soir', 'journee'];

    /** POST /api/journees {site_id, date, note?} */
    public function ouvrir(Requete $req): Resultat
    {
        $id = (new JourneeService())->ouvrir(
            $req->entier('site_id'),
            $req->date('date'),
            $this->utilisateurId($req),
            $req->texteOptionnel('note')
        );

        return new Resultat(201, ['journee_id' => $id]);
    }

    /** POST /api/journees/{id}/cloture {note?} */
    public function cloturer(Requete $req): array
    {
        $id = $req->parametreChemin('id');

        (new JourneeService())->cloturer(
            $id,
            $this->utilisateurId($req),
            $req->texteOptionnel('note')
        );

        return ['journee_id' => $id, 'statut' => 'cloturee'];
    }

    /** POST /api/journees/{id}/reouverture {raison} (Russell uniquement, contrôlé par le service) */
    public function rouvrir(Requete $req): array
    {
        $id = $req->parametreChemin('id');

        (new JourneeService())->rouvrir(
            $id,
            $this->utilisateurId($req),
            $req->texte('raison')
        );

        return ['journee_id' => $id, 'statut' => 'ouverte'];
    }

    /** GET /api/journees/{id}/rapport */
    public function rapport(Requete $req): array
    {
        return (new RapportService())->pourJournee(
            $req->parametreChemin('id'),
            $this->utilisateurId($req)
        );
    }

    /**
     * POST /api/journees/{id}/ravitaillements
     * {vendeur_id, lignes: [{produit_id, quantite}], sac_id?, periode?, note?}
     *
     * Le premier ravitaillement d'un vendeur crée son activité (sac requis).
     */
    public function ravitaillerVendeur(Requete $req): Resultat
    {
        $resultat = (new RavitaillementService())->enregistrerPourVendeur(
            $req->parametreChemin('id'),
            $req->entier('vendeur_id'),
            $this->utilisateurId($req),
            $req->liste('lignes'),
            $req->entierOptionnel('sac_id'),
            $req->choixOptionnel('periode', self::PERIODES),
            $req->texteOptionnel('note')
        );

        return new Resultat(201, $resultat);
    }

    /** POST /api/journees/{id}/activites {vendeur_id, sac_id, periode} : démarrage explicite. */
    public function demarrerActivite(Requete $req): Resultat
    {
        $id = (new DemarrageActiviteService())->demarrer(
            $req->parametreChemin('id'),
            $req->entier('vendeur_id'),
            $req->entier('sac_id'),
            $req->choix('periode', self::PERIODES),
            $this->utilisateurId($req)
        );

        return new Resultat(201, ['activite_id' => $id]);
    }
}
