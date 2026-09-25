<?php

declare(strict_types=1);

require_once __DIR__ . '/../ControleurBase.php';
require_once __DIR__ . '/../../services/RetraitService.php';
require_once __DIR__ . '/../../services/PaiementSoldeService.php';
require_once __DIR__ . '/../../services/DepenseService.php';
require_once __DIR__ . '/../../services/ControleFinancierService.php';
require_once __DIR__ . '/../../services/TarifService.php';

/** Retraits, paiements de solde, dépenses, écarts financiers, tarifs. */
final class FinancesControleur extends ControleurBase
{
    /** POST /api/retraits {vendeur_id, site_id, montant, motif?} */
    public function retrait(Requete $req): Resultat
    {
        $id = (new RetraitService())->enregistrer(
            $req->entier('vendeur_id'),
            $req->entier('site_id'),
            $this->utilisateurId($req),
            $req->montant('montant'),
            $req->texteOptionnel('motif')
        );

        return new Resultat(201, ['retrait_id' => $id]);
    }

    /** POST /api/paiements-soldes {vendeur_id, site_id, montant, note?} */
    public function paiementSolde(Requete $req): Resultat
    {
        $id = (new PaiementSoldeService())->enregistrer(
            $req->entier('vendeur_id'),
            $req->entier('site_id'),
            $this->utilisateurId($req),
            $req->montant('montant'),
            $req->texteOptionnel('note')
        );

        return new Resultat(201, ['paiement_solde_id' => $id]);
    }

    /**
     * POST /api/depenses
     * {categorie_id, libelle, montant, site_id, date?, justificatif?, note?}
     *
     * Une dépense sans site est réservée à Russell ; un responsable doit
     * indiquer un de ses sites.
     */
    public function depense(Requete $req): Resultat
    {
        $siteId = $req->entierOptionnel('site_id');

        if ($siteId === null && !$this->estSuperAdmin($req)) {
            throw new AccesRefuseException(
                'Une dépense sans site est réservée au super administrateur : indique le site.'
            );
        }

        $id = (new DepenseService())->enregistrer(
            $this->utilisateurId($req),
            $req->entier('categorie_id'),
            $req->texte('libelle', 255),
            $req->montant('montant'),
            $siteId,
            $req->dateOptionnelle('date'),
            $req->texteOptionnel('justificatif'),
            $req->texteOptionnel('note')
        );

        return new Resultat(201, ['depense_id' => $id]);
    }

    /** POST /api/ecarts/{id}/mise-en-resolution {motif} */
    public function mettreEnResolution(Requete $req): array
    {
        $id = $req->parametreChemin('id');

        (new ControleFinancierService())->mettreEnResolution(
            $id,
            $this->utilisateurId($req),
            $req->texte('motif')
        );

        return ['controle_id' => $id, 'statut' => 'ecart_en_cours_de_resolution'];
    }

    /** POST /api/ecarts/{id}/resolution {motif} (Russell uniquement) */
    public function resoudreEcart(Requete $req): array
    {
        $id = $req->parametreChemin('id');

        (new ControleFinancierService())->resoudre(
            $id,
            $this->utilisateurId($req),
            $req->texte('motif')
        );

        return ['controle_id' => $id, 'statut' => 'ecart_resolu'];
    }

    /**
     * POST /api/tarifs {type: prix|cout|transport, entite_id, valeur, date_debut, motif?}
     * Russell uniquement (route réservée) : un tarif est global à tous les sites.
     */
    public function changerTarif(Requete $req): Resultat
    {
        $resultat = (new TarifService())->changer(
            $req->choix('type', ['prix', 'cout', 'transport']),
            $req->entier('entite_id'),
            $req->montant('valeur'),
            $req->date('date_debut'),
            $this->utilisateurId($req),
            $req->texteOptionnel('motif')
        );

        return new Resultat(201, $resultat);
    }
    /** POST /api/depenses/{id}/correction {categorie_id, libelle, montant, justificatif?, note?, motif} */
    public function corrigerDepense(Requete $req): array
    {
        $id = $req->parametreChemin('id');

        (new DepenseService())->corriger(
            $id,
            $req->texte('libelle', 255),
            $req->montant('montant'),
            $req->entier('categorie_id'),
            $req->texteOptionnel('justificatif'),
            $req->texteOptionnel('note'),
            $this->utilisateurId($req),
            $req->texte('motif')
        );

        return ['depense_id' => $id, 'corrige' => true];
    }
}
