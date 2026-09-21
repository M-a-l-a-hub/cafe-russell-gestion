<?php

declare(strict_types=1);

require_once __DIR__ . '/../ControleurBase.php';
require_once __DIR__ . '/../../services/RavitaillementService.php';
require_once __DIR__ . '/../../services/RetourService.php';
require_once __DIR__ . '/../../services/ClotureActiviteService.php';
require_once __DIR__ . '/../../services/AnnulationActiviteService.php';
require_once __DIR__ . '/../../services/AnomalieStockService.php';

/** Opérations sur une activité (un vendeur avec un sac) et anomalies de stock. */
final class ActivitesControleur extends ControleurBase
{
    /** POST /api/activites/{id}/ravitaillements {lignes: [{produit_id, quantite}], note?} */
    public function ravitailler(Requete $req): Resultat
    {
        $id = (new RavitaillementService())->enregistrer(
            $req->parametreChemin('id'),
            $this->utilisateurId($req),
            $req->liste('lignes'),
            $req->texteOptionnel('note')
        );

        return new Resultat(201, ['ravitaillement_id' => $id]);
    }

    /** POST /api/activites/{id}/retours {lignes: [{produit_id, quantite}], note?} (quantité 0 autorisée) */
    public function retourner(Requete $req): Resultat
    {
        $id = (new RetourService())->enregistrer(
            $req->parametreChemin('id'),
            $this->utilisateurId($req),
            $req->liste('lignes'),
            $req->texteOptionnel('note')
        );

        return new Resultat(201, ['retour_id' => $id]);
    }

    /** POST /api/activites/{id}/cloture {montant_remis, motif_ecart?, note?} */
    public function cloturer(Requete $req): array
    {
        return (new ClotureActiviteService())->cloturer(
            $req->parametreChemin('id'),
            $this->utilisateurId($req),
            $req->montant('montant_remis'),
            $req->texteOptionnel('motif_ecart'),
            $req->texteOptionnel('note')
        );
    }

    /** POST /api/activites/{id}/annulation {motif} (seulement si aucun ravitaillement) */
    public function annuler(Requete $req): array
    {
        $id = $req->parametreChemin('id');

        (new AnnulationActiviteService())->annuler(
            $id,
            $this->utilisateurId($req),
            $req->texte('motif')
        );

        return ['activite_id' => $id, 'statut' => 'annulee'];
    }

    /** POST /api/activites/{id}/anomalies {produit_id, quantite_attendue, quantite_constatee, motif?} */
    public function signalerAnomalie(Requete $req): Resultat
    {
        $id = (new AnomalieStockService())->signaler(
            $req->parametreChemin('id'),
            $req->entier('produit_id'),
            $req->entier('quantite_attendue'),
            $req->entier('quantite_constatee'),
            $this->utilisateurId($req),
            $req->texteOptionnel('motif')
        );

        return new Resultat(201, ['anomalie_id' => $id]);
    }

    /** POST /api/anomalies/{id}/traitement {note?} */
    public function demarrerTraitementAnomalie(Requete $req): array
    {
        $id = $req->parametreChemin('id');

        (new AnomalieStockService())->demarrerTraitement(
            $id,
            $this->utilisateurId($req),
            $req->texteOptionnel('note')
        );

        return ['anomalie_id' => $id, 'statut' => 'en_cours'];
    }

    /** POST /api/anomalies/{id}/resolution {note_resolution} */
    public function resoudreAnomalie(Requete $req): array
    {
        $id = $req->parametreChemin('id');

        (new AnomalieStockService())->resoudre(
            $id,
            $this->utilisateurId($req),
            $req->texte('note_resolution')
        );

        return ['anomalie_id' => $id, 'statut' => 'resolue'];
    }
}
