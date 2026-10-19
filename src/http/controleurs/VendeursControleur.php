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
        $siteId = $req->entier('site_id');
        $this->verifierSite($req, $siteId);
        $id = (new VendeurService())->creer(
            $req->texte('nom', 100),
            $req->texteOptionnel('prenom', 100),
            $req->texteOptionnel('telephone', 30),
            $req->entier('categorie_transport_id'),
            $req->choixOptionnel('periode_habituelle', ['matin', 'soir', 'journee']),
            $this->utilisateurId($req),
            $siteId
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

    /** POST /api/vendeurs/{id}/affectation {site_id} — réservé à Russell. */
    public function affecter(Requete $req): array
    {
        if (!$this->estSuperAdmin($req)) {
            throw new AccesRefuseException('Seul Russell peut affecter un vendeur existant à un autre site.');
        }

        $vendeurId = $req->parametreChemin('id');
        $siteId = $req->entier('site_id');
        $this->verifierSite($req, $siteId);

        $vendeur = $this->uneLigne('SELECT id FROM vendeurs WHERE id = :id AND actif = TRUE', ['id' => $vendeurId]);
        if ($vendeur === null) {
            throw new HttpException(404, 'introuvable', 'Vendeur introuvable ou désactivé.');
        }

        $db = getDatabaseConnection();
        $stmt = $db->prepare("
            INSERT INTO site_vendeurs (site_id, vendeur_id, affecte_par, actif, date_desaffectation)
            VALUES (:site_id, :vendeur_id, :user_id, TRUE, NULL)
            ON CONFLICT (site_id, vendeur_id)
            DO UPDATE SET actif = TRUE, affecte_par = EXCLUDED.affecte_par,
                          date_affectation = NOW(), desaffecte_par = NULL, date_desaffectation = NULL
        ");
        $stmt->execute([
            'site_id' => $siteId,
            'vendeur_id' => $vendeurId,
            'user_id' => $this->utilisateurId($req),
        ]);

        return ['vendeur_id' => $vendeurId, 'site_id' => $siteId, 'statut' => 'affecte'];
    }

    /** POST /api/vendeurs/{id}/desactivation {motif} */
    public function desactiver(Requete $req): array
    {
        $id = $req->parametreChemin('id');
        $siteId = $req->entier('site_id');
        $this->verifierSite($req, $siteId);

        (new VendeurService())->desactiver(
            $id,
            $siteId,
            $this->utilisateurId($req),
            $req->texte('motif')
        );

        return ['vendeur_id' => $id, 'statut' => 'desactive'];
    }
}
