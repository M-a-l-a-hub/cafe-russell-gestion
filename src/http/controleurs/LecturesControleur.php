<?php

declare(strict_types=1);

require_once __DIR__ . '/../ControleurBase.php';
require_once __DIR__ . '/../../services/ActiviteService.php';
require_once __DIR__ . '/../../services/SoldeVendeurService.php';
require_once __DIR__ . '/../../services/JourneeService.php';

/**
 * Lectures seules : listes de référence, détail d'une activité,
 * solde d'un vendeur, écarts et anomalies à traiter.
 * Un responsable ne voit que les données de ses sites.
 */
final class LecturesControleur extends ControleurBase
{
    /** GET /api/sites */
    public function sites(Requete $req): array
    {
        [$restriction, $params] = $this->restrictionSites($req, 's.id');

        return $this->lignes(
            "
                        SELECT
                                s.id,
                                s.code,
                                s.nom,
                                s.adresse,
                                s.description,
                                (
                                        SELECT CONCAT_WS(' ', u.prenom, u.nom)
                                        FROM site_responsables sr
                                        INNER JOIN users u ON u.id = sr.user_id
                                        WHERE sr.site_id = s.id
                                            AND sr.type = 'principal'
                                            AND sr.actif = TRUE
                                        LIMIT 1
                                ) AS responsable_principal
            FROM sites s
            WHERE s.actif = TRUE{$restriction}
            ORDER BY s.nom
            ",
            $params
        );
    }

    /** GET /api/produits : prix du jour ; le coût n'est visible que par Russell. */
    public function produits(Requete $req): array
    {
        $cout = '';

        if ($this->estSuperAdmin($req)) {
            $cout = ",
                (
                    SELECT pc.cout_unitaire
                    FROM produit_couts pc
                    WHERE pc.produit_id = p.id
                      AND pc.date_debut <= CURRENT_DATE
                      AND (pc.date_fin IS NULL OR pc.date_fin >= CURRENT_DATE)
                    LIMIT 1
                ) AS cout_actuel";
        }

        $lignes = $this->lignes(
            "
            SELECT
                p.id,
                p.code,
                p.nom,
                p.unite,
                (
                    SELECT pp.prix
                    FROM produit_prix pp
                    WHERE pp.produit_id = p.id
                      AND pp.date_debut <= CURRENT_DATE
                      AND (pp.date_fin IS NULL OR pp.date_fin >= CURRENT_DATE)
                    LIMIT 1
                ) AS prix_actuel{$cout}
            FROM produits p
            WHERE p.actif = TRUE
            ORDER BY p.nom
            "
        );

        return $this->versFlottants($lignes, ['prix_actuel', 'cout_actuel']);
    }

    /** GET /api/vendeurs */
    /** GET /api/vendeurs?tous=1 : ?tous=1 inclut aussi les vendeurs désactivés. */
    public function vendeurs(Requete $req): array
    {
        $tous = $req->query('tous') === '1';
        $siteId = $req->queryEntierRequis('site_id');
        $this->verifierSite($req, $siteId);

        if ($tous && !$this->estSuperAdmin($req)) {
            throw new AccesRefuseException(
                'La liste des vendeurs désactivés est réservée au super administrateur.'
            );
        }

        return $this->lignes(
            "
            SELECT
                v.id,
                v.code,
                v.nom,
                v.prenom,
                v.telephone,
                v.periode_habituelle,
                v.actif,
                v.categorie_transport_id,
                ct.code AS categorie_transport,
                sv.actif AS affectation_active,
                sv.site_id
            FROM vendeurs v
            INNER JOIN categories_transport ct
                ON ct.id = v.categorie_transport_id
            INNER JOIN site_vendeurs sv
                ON sv.vendeur_id = v.id AND sv.site_id = :site_id
            " . ($tous ? '' : 'WHERE v.actif = TRUE AND sv.actif = TRUE') . "
            ORDER BY v.nom, v.prenom
            ",
            ['site_id' => $siteId]
        );
    }

    /** GET /api/categories-depenses */
    public function categoriesDepenses(Requete $req): array
    {
        return $this->lignes(
            "
            SELECT id, code, nom
            FROM categories_depenses
            WHERE actif = TRUE
            ORDER BY nom
            "
        );
    }

    /** GET /api/categories-transport : catégories de distance et leur tarif actuel. */
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
            ORDER BY tarif_actuel NULLS LAST, ct.nom
            "
        );

        return $this->versFlottants($lignes, ['tarif_actuel']);
    }

    /** GET /api/sites/{id}/sacs */
    public function sacsSite(Requete $req): array
    {
        $siteId = $req->parametreChemin('id');
        $this->verifierSite($req, $siteId);

        return $this->lignes(
            "
            SELECT id, numero, categorie, statut, motif_indisponibilite
            FROM sacs
            WHERE site_id = :site_id
            ORDER BY numero
            ",
            ['site_id' => $siteId]
        );
    }

    /** GET /api/sites/{id}/journees?statut=ouverte&limite=30 */
    public function journeesSite(Requete $req): array
    {
        $siteId = $req->parametreChemin('id');
        $this->verifierSite($req, $siteId);

        // Clôture d'abord les journées oubliées de ce site (après 05 h 00 le lendemain).
        (new JourneeService())->cloturerJourneesEnRetard($siteId);

        $statut = $req->queryChoix('statut', ['ouverte', 'cloturee']);
        $limite = max(1, min(100, $req->queryEntier('limite') ?? 30));

        $sql = "
            SELECT
                j.id,
                j.site_id,
                j.date_jour,
                j.statut,
                j.date_ouverture,
                j.date_cloture,
                j.note_ouverture,
                j.note_cloture
            FROM journees j
            WHERE j.site_id = :site_id";

        $params = ['site_id' => $siteId];

        if ($statut !== null) {
            $sql .= " AND j.statut = CAST(:statut AS journee_statut)";
            $params['statut'] = $statut;
        }

        $sql .= " ORDER BY j.date_jour DESC LIMIT {$limite}";

        return $this->lignes($sql, $params);
    }

    /** GET /api/sites/{id}/depenses?date=AAAA-MM-JJ (aujourd'hui par défaut) */
    public function depensesSite(Requete $req): array
    {
        $siteId = $req->parametreChemin('id');
        $this->verifierSite($req, $siteId);

        $date = $req->queryDate('date') ?? date('Y-m-d');

        $lignes = $this->lignes(
            "
            SELECT
                d.id,
                d.categorie_id,
                d.libelle,
                d.montant,
                d.date_depense,
                d.justificatif,
                d.note,
                c.nom AS categorie
            FROM depenses d
            INNER JOIN categories_depenses c
                ON c.id = d.categorie_id
            WHERE d.site_id = :site_id
              AND d.date_depense = CAST(:date_jour AS DATE)
            ORDER BY d.id
            ",
            ['site_id' => $siteId, 'date_jour' => $date]
        );

        return $this->versFlottants($lignes, ['montant']);
    }

    /** GET /api/activites/{id} : détail et calculs financiers. */
    public function activite(Requete $req): array
    {
        $id = $req->parametreChemin('id');

        $ligne = $this->uneLigne(
            "
            SELECT j.site_id
            FROM activites a
            INNER JOIN journees j
                ON j.id = a.journee_id
            WHERE a.id = :id
            ",
            ['id' => $id]
        );

        if ($ligne === null) {
            throw new HttpException(404, 'introuvable', 'Activité introuvable.');
        }

        $this->verifierSite($req, (int) $ligne['site_id']);

        return (new ActiviteService())->calculer($id);
    }

    /** GET /api/vendeurs/{id}/solde?site_id=... */
    public function soldeVendeur(Requete $req): array
    {
        $vendeurId = $req->parametreChemin('id');
        $siteId = $req->queryEntierRequis('site_id');

        $this->verifierSite($req, $siteId);

        return (new SoldeVendeurService())->obtenir($vendeurId, $siteId);
    }

    /** GET /api/ecarts?site_id=... : écarts financiers non résolus. */
    public function ecarts(Requete $req): array
    {
        [$filtre, $params] = $this->filtreSite($req, 'j.site_id');

        $lignes = $this->lignes(
            "
            SELECT
                cf.id,
                cf.activite_id,
                cf.ca_theorique,
                cf.montant_remis,
                cf.ecart,
                cf.statut,
                cf.motif_ecart,
                cf.date_heure,
                j.id AS journee_id,
                j.date_jour,
                j.site_id,
                s.nom AS site_nom,
                v.id AS vendeur_id,
                v.code AS vendeur_code,
                v.nom AS vendeur_nom,
                v.prenom AS vendeur_prenom
            FROM controles_financiers cf
            INNER JOIN activites a ON a.id = cf.activite_id
            INNER JOIN journees j ON j.id = a.journee_id
            INNER JOIN sites s ON s.id = j.site_id
            INNER JOIN vendeurs v ON v.id = a.vendeur_id
            WHERE cf.statut IN ('ecart_a_expliquer', 'ecart_en_cours_de_resolution'){$filtre}
            ORDER BY cf.date_heure DESC
            LIMIT 200
            ",
            $params
        );

        return $this->versFlottants($lignes, ['ca_theorique', 'montant_remis', 'ecart']);
    }

    /** GET /api/anomalies?site_id=... : anomalies de stock non résolues. */
    public function anomalies(Requete $req): array
    {
        [$filtre, $params] = $this->filtreSite($req, 'j.site_id');

        return $this->lignes(
            "
            SELECT
                an.id,
                an.activite_id,
                an.produit_id,
                p.nom AS produit_nom,
                an.quantite_attendue,
                an.quantite_constatee,
                an.ecart,
                an.motif,
                an.statut,
                an.date_signalement,
                j.id AS journee_id,
                j.date_jour,
                j.site_id,
                s.nom AS site_nom,
                v.id AS vendeur_id,
                v.code AS vendeur_code,
                v.nom AS vendeur_nom
            FROM anomalies_stock an
            INNER JOIN activites a ON a.id = an.activite_id
            INNER JOIN journees j ON j.id = a.journee_id
            INNER JOIN sites s ON s.id = j.site_id
            INNER JOIN vendeurs v ON v.id = a.vendeur_id
            INNER JOIN produits p ON p.id = an.produit_id
            WHERE an.statut <> 'resolue'{$filtre}
            ORDER BY an.date_signalement DESC
            LIMIT 200
            ",
            $params
        );
    }

    /**
     * Restreint une requête aux sites de l'utilisateur (aucune
     * restriction pour le super administrateur).
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function restrictionSites(Requete $req, string $colonneSite): array
    {
        if ($this->estSuperAdmin($req)) {
            return ['', []];
        }

        $sql = " AND {$colonneSite} IN (
            SELECT sr.site_id
            FROM site_responsables sr
            WHERE sr.user_id = :utilisateur_id
              AND sr.actif = TRUE
              AND sr.date_debut <= CURRENT_DATE
              AND (sr.date_fin IS NULL OR sr.date_fin >= CURRENT_DATE)
        )";

        return [$sql, ['utilisateur_id' => $this->utilisateurId($req)]];
    }

    /**
     * Filtre par ?site_id= (accès vérifié) ou, à défaut, par les sites
     * de l'utilisateur.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function filtreSite(Requete $req, string $colonneSite): array
    {
        $siteId = $req->queryEntier('site_id');

        if ($siteId !== null) {
            $this->verifierSite($req, $siteId);

            return [" AND {$colonneSite} = :site_id", ['site_id' => $siteId]];
        }

        return $this->restrictionSites($req, $colonneSite);
    }
    /**
     * GET /api/activites/{id}/details-correction
     * Ravitaillements (chacun avec ses lignes) et retour (s'il existe)
     * d'une activité, pour préremplir l'écran de correction.
     */
    public function detailsCorrection(Requete $req): array
    {
        $id = $req->parametreChemin('id');

        $ligne = $this->uneLigne(
            '
            SELECT j.site_id
            FROM activites a
            INNER JOIN journees j ON j.id = a.journee_id
            WHERE a.id = :id
            ',
            ['id' => $id]
        );

        if ($ligne === null) {
            throw new HttpException(404, 'introuvable', 'Activité introuvable.');
        }

        $this->verifierSite($req, (int) $ligne['site_id']);

        $ravitaillements = $this->lignes(
            '
            SELECT id, date_heure, note
            FROM ravitaillements
            WHERE activite_id = :id
            ORDER BY date_heure
            ',
            ['id' => $id]
        );

        foreach ($ravitaillements as &$rav) {
            $rav['lignes'] = $this->lignes(
                '
                SELECT rl.produit_id, p.nom AS produit_nom, rl.quantite
                FROM ravitaillement_lignes rl
                INNER JOIN produits p ON p.id = rl.produit_id
                WHERE rl.ravitaillement_id = :id
                ORDER BY p.nom
                ',
                ['id' => $rav['id']]
            );
        }
        unset($rav);

        $retour = $this->uneLigne(
            'SELECT id, date_heure, note FROM retours WHERE activite_id = :id',
            ['id' => $id]
        );

        if ($retour !== null) {
            $retour['lignes'] = $this->lignes(
                '
                SELECT rl.produit_id, p.nom AS produit_nom, rl.quantite
                FROM retour_lignes rl
                INNER JOIN produits p ON p.id = rl.produit_id
                WHERE rl.retour_id = :id
                ORDER BY p.nom
                ',
                ['id' => $retour['id']]
            );
        }

        return ['ravitaillements' => $ravitaillements, 'retour' => $retour];
    }
}
