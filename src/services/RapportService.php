<?php

declare(strict_types=1);

require_once __DIR__ . '/ServiceBase.php';
require_once __DIR__ . '/ActiviteService.php';

class RapportService extends ServiceBase
{
    public function pourJournee(int $journeeId, int $userId): array
    {
        $journee = $this->fetchOne(
            "
            SELECT j.id, j.site_id, j.date_jour, j.statut, s.code AS site_code, s.nom AS site_nom
            FROM journees j
            INNER JOIN sites s ON s.id = j.site_id
            WHERE j.id = :id
            ",
            ['id' => $journeeId]
        );

        if ($journee === null) {
            throw new RuntimeException('Journée introuvable.');
        }

        $this->verifierAccesSite($userId, (int) $journee['site_id']);

        $activites = $this->fetchAllRows(
            'SELECT id FROM activites WHERE journee_id = :id ORDER BY id',
            ['id' => $journeeId]
        );
        $resume = [
            'journee' => $journee,
            'activites' => [],
            'vendeurs' => 0,
            'ventes' => 0.0,
            'transport' => 0.0,
            'nb_ravitaillements' => 0,
            'commissions' => 0.0,
            'consommables' => 0.0,
            'benefice_activites' => 0.0,
            'benefice_entreprise' => 0.0,
            'benefice_russell' => 0.0,
            'depenses_site' => 0.0,
            'benefice_apres_depenses_site' => 0.0,
            'ecarts' => [],
        ];

        $nbVendeurs = $this->fetchOne(
            'SELECT COUNT(DISTINCT vendeur_id) AS n FROM activites WHERE journee_id = :id',
            ['id' => $journeeId]
        );
        $resume['vendeurs'] = (int) $nbVendeurs['n'];

        $calculateur = new ActiviteService();

        foreach ($activites as $activite) {
            $calcul = $calculateur->calculer((int) $activite['id']);
            $resume['activites'][] = $calcul;
            $resume['ventes'] += $calcul['ca'];
            $resume['transport'] += $calcul['transport'];
            $resume['nb_ravitaillements'] += $calcul['nb_ravitaillements'];
            $resume['commissions'] += $calcul['commission'];
            $resume['consommables'] += $calcul['consommables'];
            $resume['benefice_activites'] += $calcul['benefice'];

            if ($calcul['beneficiaire_benefice'] === 'russell') {
                $resume['benefice_russell'] += $calcul['benefice'];
            } else {
                $resume['benefice_entreprise'] += $calcul['benefice'];
            }
        }

        $depenses = $this->fetchOne(
            "
            SELECT COALESCE(SUM(montant), 0) AS total
            FROM depenses
            WHERE site_id = :site_id AND date_depense = :date_jour
            ",
            ['site_id' => $journee['site_id'], 'date_jour' => $journee['date_jour']]
        );
        $resume['depenses_site'] = (float) $depenses['total'];
        $resume['benefice_apres_depenses_site'] = round(
            $resume['benefice_activites'] - $resume['depenses_site'],
            2
        );
        $resume['ecarts'] = $this->fetchAllRows(
            "
            SELECT cf.*
            FROM controles_financiers cf
            INNER JOIN activites a ON a.id = cf.activite_id
            WHERE a.journee_id = :journee_id AND cf.ecart <> 0
            ORDER BY cf.id
            ",
            ['journee_id' => $journeeId]
        );

        foreach (['ventes', 'transport', 'commissions', 'consommables', 'benefice_activites', 'benefice_entreprise', 'benefice_russell'] as $cle) {
            $resume[$cle] = round($resume[$cle], 2);
        }

        return $resume;
    }

    /**
     * Calcule en une seule requête les indicateurs de toutes les journées
     * d'une période. Les formules reproduisent ActiviteService::calculer()
     * pour éviter un grand nombre de requêtes sur les bilans annuels.
     *
     * @return array<int, array<string, mixed>>
     */
    public function pourPeriode(
        string $dateDebut,
        string $dateFinExclusive,
        int $userId,
        ?int $siteId = null
    ): array {
        $params = [
            'date_debut' => $dateDebut,
            'date_fin_exclue' => $dateFinExclusive,
        ];
        $filtreSite = '';

        if ($siteId !== null) {
            $filtreSite = ' AND j.site_id = :site_id';
            $params['site_id'] = $siteId;
        }

        // Vérification des droits pour chaque site réellement présent dans la période.
        $sites = $this->fetchAllRows(
            "
            SELECT DISTINCT j.site_id
            FROM journees j
            WHERE j.date_jour >= CAST(:date_debut AS DATE)
              AND j.date_jour < CAST(:date_fin_exclue AS DATE)
              {$filtreSite}
            ",
            $params
        );

        foreach ($sites as $site) {
            $this->verifierAccesSite($userId, (int) $site['site_id']);
        }

        $lignes = $this->fetchAllRows(
            "
            WITH quantites_par_produit AS (
                SELECT
                    r.activite_id,
                    rl.produit_id,
                    SUM(rl.quantite) AS quantite_recue,
                    SUM(rl.quantite * rl.prix_unitaire) AS valeur_vente,
                    SUM(rl.quantite * rl.cout_unitaire) AS consommables_bruts
                FROM ravitaillements r
                INNER JOIN ravitaillement_lignes rl
                    ON rl.ravitaillement_id = r.id
                GROUP BY r.activite_id, rl.produit_id
            ),
            retours_par_produit AS (
                SELECT
                    r.activite_id,
                    rl.produit_id,
                    SUM(rl.quantite) AS quantite_retournee
                FROM retours r
                INNER JOIN retour_lignes rl
                    ON rl.retour_id = r.id
                GROUP BY r.activite_id, rl.produit_id
            ),
            produits_par_activite AS (
                SELECT
                    q.activite_id,
                    SUM(
                        (q.quantite_recue - COALESCE(rt.quantite_retournee, 0))
                        * (q.valeur_vente / NULLIF(q.quantite_recue, 0))
                    ) AS ca_brut,
                    SUM(q.consommables_bruts) AS consommables_bruts,
                    SUM(
                        CASE
                            WHEN q.quantite_recue < COALESCE(rt.quantite_retournee, 0)
                            THEN 1 ELSE 0
                        END
                    ) AS anomalies_quantite
                FROM quantites_par_produit q
                LEFT JOIN retours_par_produit rt
                    ON rt.activite_id = q.activite_id
                   AND rt.produit_id = q.produit_id
                GROUP BY q.activite_id
            ),
            retours_sans_reception AS (
                SELECT
                    rt.activite_id,
                    COUNT(*) AS nombre
                FROM retours_par_produit rt
                LEFT JOIN quantites_par_produit q
                    ON q.activite_id = rt.activite_id
                   AND q.produit_id = rt.produit_id
                WHERE q.activite_id IS NULL
                  AND rt.quantite_retournee > 0
                GROUP BY rt.activite_id
            ),
            transport_par_activite AS (
                SELECT
                    activite_id,
                    SUM(transport_par_ravitaillement) AS transport_brut,
                    COUNT(*) AS nb_ravitaillements
                FROM ravitaillements
                GROUP BY activite_id
            ),
            resume_activites AS (
                SELECT
                    a.id AS activite_id,
                    a.journee_id,
                    a.sac_categorie,
                    COALESCE(p.ca_brut, 0) AS ca_brut,
                    ROUND(COALESCE(p.ca_brut, 0), 2) AS ventes,
                    ROUND(COALESCE(t.transport_brut, 0), 2) AS transport,
                    ROUND(COALESCE(p.consommables_bruts, 0), 2) AS consommables,
                    ROUND(COALESCE(p.ca_brut, 0) * 25 / 100, 2) AS commission,
                    ROUND(
                        COALESCE(p.ca_brut, 0)
                        - COALESCE(t.transport_brut, 0)
                        - ROUND(COALESCE(p.ca_brut, 0) * 25 / 100, 2)
                        - COALESCE(p.consommables_bruts, 0),
                        2
                    ) AS benefice,
                    COALESCE(t.nb_ravitaillements, 0) AS nb_ravitaillements,
                    (COALESCE(p.anomalies_quantite, 0) + COALESCE(rsr.nombre, 0)) AS anomalies_quantite
                FROM activites a
                LEFT JOIN produits_par_activite p ON p.activite_id = a.id
                LEFT JOIN transport_par_activite t ON t.activite_id = a.id
                LEFT JOIN retours_sans_reception rsr ON rsr.activite_id = a.id
            )
            SELECT
                j.id,
                j.site_id,
                j.date_jour::text AS date_jour,
                j.statut::text AS statut,
                COALESCE(SUM(a.ventes), 0) AS ventes,
                COALESCE(SUM(a.commission), 0) AS commissions,
                COALESCE(SUM(a.transport), 0) AS transport,
                COALESCE(SUM(a.consommables), 0) AS consommables,
                COALESCE(SUM(a.benefice), 0) AS benefice_activites,
                COALESCE(SUM(
                    CASE WHEN a.sac_categorie = 'russell' THEN a.benefice ELSE 0 END
                ), 0) AS benefice_russell,
                COALESCE(SUM(
                    CASE WHEN a.sac_categorie = 'russell' THEN 0 ELSE a.benefice END
                ), 0) AS benefice_entreprise,
                COALESCE(SUM(a.nb_ravitaillements), 0) AS nb_ravitaillements,
                COALESCE(SUM(a.anomalies_quantite), 0) AS anomalies_quantite
            FROM journees j
            LEFT JOIN resume_activites a ON a.journee_id = j.id
            WHERE j.date_jour >= CAST(:date_debut AS DATE)
              AND j.date_jour < CAST(:date_fin_exclue AS DATE)
              {$filtreSite}
            GROUP BY j.id, j.site_id, j.date_jour, j.statut
            ORDER BY j.date_jour, j.site_id
            ",
            $params
        );

        foreach ($lignes as &$ligne) {
            if ((int) ($ligne['anomalies_quantite'] ?? 0) > 0) {
                throw new RuntimeException(
                    'Un retour dépasse la quantité reçue dans cette période. '
                    . 'Corrige les données de la journée concernée avant de consulter le bilan.'
                );
            }

            foreach ([
                'ventes',
                'commissions',
                'transport',
                'consommables',
                'benefice_activites',
                'benefice_entreprise',
                'benefice_russell',
            ] as $cle) {
                $ligne[$cle] = round((float) $ligne[$cle], 2);
            }
            $ligne['nb_ravitaillements'] = (int) $ligne['nb_ravitaillements'];
        }

        unset($ligne);

        return $lignes;
    }

    public function pourVendeur(int $vendeurId, int $userId): array
    {
        $activites = $this->fetchAllRows(
            "
            SELECT a.id, j.site_id
            FROM activites a
            INNER JOIN journees j ON j.id = a.journee_id
            WHERE a.vendeur_id = :vendeur_id
            ORDER BY a.date_debut
            ",
            ['vendeur_id' => $vendeurId]
        );

        $sitesAutorises = [];
        foreach ($activites as $activite) {
            $siteId = (int) $activite['site_id'];

            if (!isset($sitesAutorises[$siteId])) {
                $this->verifierAccesSite($userId, $siteId);
                $sitesAutorises[$siteId] = true;
            }
        }
        $resume = [
            'vendeur_id' => $vendeurId,
            'activites' => [],
            'ventes' => 0.0,
            'commissions' => 0.0,
            'transport' => 0.0,
            'consommables' => 0.0,
            'benefice_activites' => 0.0,
        ];
        $calculateur = new ActiviteService();

        foreach ($activites as $activite) {
            $calcul = $calculateur->calculer((int) $activite['id']);
            $resume['activites'][] = $calcul;
            $resume['ventes'] += $calcul['ca'];
            $resume['commissions'] += $calcul['commission'];
            $resume['transport'] += $calcul['transport'];
            $resume['consommables'] += $calcul['consommables'];
            $resume['benefice_activites'] += $calcul['benefice'];
        }

        foreach (['ventes', 'commissions', 'transport', 'consommables', 'benefice_activites'] as $cle) {
            $resume[$cle] = round($resume[$cle], 2);
        }

        return $resume;
    }
}