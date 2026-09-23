<?php

declare(strict_types=1);

require_once __DIR__ . '/../ControleurBase.php';
require_once __DIR__ . '/../../services/VendeurService.php';

final class VendeursControleur extends ControleurBase
{
    /** GET /api/categories-transport : catégories avec leur tarif actuel. */
    public function categoriesTransport(Requete $req): array
    {
        $lignes = $this->lignes(
            "
            SELECT
                ct.id,
                ct.code,
                ct.nom,
                (
                    SELECT tp.montant_par_ravitaillement
                    FROM transport_prix tp
                    WHERE tp.categorie_transport_id = ct.id
                      AND tp.date_debut <= CURRENT_DATE
                      AND (tp.date_fin IS NULL OR tp.date_fin >= CURRENT_DATE)
                    LIMIT 1
                ) AS tarif_actuel
            FROM categories_transport ct
            WHERE ct.actif = TRUE
            ORDER BY tarif_actuel
            "
        );

        return $this->versFlottants($lignes, ['tarif_actuel']);
    }

    /** POST /api/vendeurs {nom, prenom?, telephone?, categorie_transport_id, periode_habituelle?} */
    public function creer(Requete $req): Resultat
    {
        $id = (new VendeurService())->creer(
            $req->texte('nom', 100),
            $req->texteOptionnel('prenom', 100),
            $req->texteOptionnel('telephone', 30),
            $req->entier('categorie_transport_id'),
            $req->choixOptionnel('periode_habituelle', ['matin', 'soir', 'journee']),
            $this->utilisateurId($req)
        );

        return new Resultat(201, ['vendeur_id' => $id]);
    }

    /** POST /api/vendeurs/{id}/categorie-transport {categorie_transport_id, motif?} */
    public function changerCategorieTransport(Requete $req): array
    {
        $id = $req->parametreChemin('id');

        (new VendeurService())->changerCategorieTransport(
            $id,
            $req->entier('categorie_transport_id'),
            $this->utilisateurId($req),
            $req->texteOptionnel('motif')
        );

        return ['vendeur_id' => $id];
    }

    /** POST /api/vendeurs/{id}/desactivation {motif} */
    public function desactiver(Requete $req): array
    {
        $id = $req->parametreChemin('id');

        (new VendeurService())->desactiver(
            $id,
            $this->utilisateurId($req),
            $req->texte('motif')
        );

        return ['vendeur_id' => $id, 'statut' => 'desactive'];
    }
}
