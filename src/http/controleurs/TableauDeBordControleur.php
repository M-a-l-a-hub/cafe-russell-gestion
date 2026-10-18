<?php

declare(strict_types=1);

require_once __DIR__ . '/../ControleurBase.php';
require_once __DIR__ . '/../../services/RapportService.php';

/**
 * Vue d'ensemble multi-sites pour Russell : statut du jour par site,
 * totaux, et compteurs d'écarts/anomalies en attente.
 */
final class TableauDeBordControleur extends ControleurBase
{
    /** GET /api/tableau-de-bord?date=AAAA-MM-JJ (journée la plus récente par défaut) */
    public function afficher(Requete $req): array
    {
        $date = $req->queryDate('date');

        if ($date === null) {
            // Sans date choisie : la journée la plus récente (aujourd'hui ou
            // avant), pour ne pas afficher un tableau vide quand les sites
            // n'ont pas encore ouvert de journée aujourd'hui.
            $derniere = $this->uneLigne(
                'SELECT MAX(date_jour) AS d FROM journees WHERE date_jour <= :aujourdhui',
                ['aujourdhui' => date('Y-m-d')]
            );

            $date = $derniere['d'] ?? date('Y-m-d');
        }

        $sites = $this->lignes('SELECT id, code, nom FROM sites WHERE actif = TRUE ORDER BY nom');

        $rapportService = new RapportService();
        $userId = $this->utilisateurId($req);

        $totaux = [
            'ventes' => 0.0,
            'benefice_entreprise' => 0.0,
            'benefice_russell' => 0.0,
            'depenses_site' => 0.0,
            'vendeurs_site' => 0,
            'nb_ravitaillements' => 0,
            'commissions' => 0.0,
            'transport' => 0.0,
            'consommables' => 0.0,
        ];

        $resultats = [];

        foreach ($sites as $site) {
            $journee = $this->uneLigne(
                'SELECT id, statut FROM journees WHERE site_id = :site_id AND date_jour = :date',
                ['site_id' => $site['id'], 'date' => $date]
            );

            $entree = [
                'site' => $site,
                'journee' => $journee,
                'ventes' => 0.0,
                'benefice_entreprise' => 0.0,
                'benefice_russell' => 0.0,
                'depenses_site' => 0.0,
                'vendeurs_site' => 0,
                'nb_ravitaillements' => 0,
                'commissions' => 0.0,
                'transport' => 0.0,
                'consommables' => 0.0,
                'ecarts_en_attente' => 0,
                'anomalies_en_attente' => 0,
            ];

            if ($journee !== null) {
                $rapport = $rapportService->pourJournee((int) $journee['id'], $userId);

                $entree['ventes'] = $rapport['ventes'];
                $entree['benefice_entreprise'] = $rapport['benefice_entreprise'];
                $entree['benefice_russell'] = $rapport['benefice_russell'];
                $entree['depenses_site'] = $rapport['depenses_site'];
                $entree['vendeurs_site'] = $rapport['vendeurs'];
                $entree['nb_ravitaillements'] = $rapport['nb_ravitaillements'];
                $entree['commissions'] = $rapport['commissions'];
                $entree['transport'] = $rapport['transport'];
                $entree['consommables'] = $rapport['consommables'];

                $totaux['ventes'] += $rapport['ventes'];
                $totaux['benefice_entreprise'] += $rapport['benefice_entreprise'];
                $totaux['benefice_russell'] += $rapport['benefice_russell'];
                $totaux['depenses_site'] += $rapport['depenses_site'];
                $totaux['vendeurs_site'] += $rapport['vendeurs'];
                $totaux['nb_ravitaillements'] += $rapport['nb_ravitaillements'];
                $totaux['commissions'] += $rapport['commissions'];
                $totaux['transport'] += $rapport['transport'];
                $totaux['consommables'] += $rapport['consommables'];
            }

            $ecarts = $this->uneLigne(
                "
                SELECT COUNT(*) AS n
                FROM controles_financiers cf
                INNER JOIN activites a ON a.id = cf.activite_id
                INNER JOIN journees j ON j.id = a.journee_id
                WHERE j.site_id = :site_id
                  AND cf.statut IN ('ecart_a_expliquer', 'ecart_en_cours_de_resolution')
                ",
                ['site_id' => $site['id']]
            );
            $entree['ecarts_en_attente'] = (int) $ecarts['n'];

            $anomalies = $this->uneLigne(
                "
                SELECT COUNT(*) AS n
                FROM anomalies_stock an
                INNER JOIN activites a ON a.id = an.activite_id
                INNER JOIN journees j ON j.id = a.journee_id
                WHERE j.site_id = :site_id AND an.statut <> 'resolue'
                ",
                ['site_id' => $site['id']]
            );
            $entree['anomalies_en_attente'] = (int) $anomalies['n'];

            $resultats[] = $entree;
        }

        foreach ($totaux as $cle => $valeur) {
            $totaux[$cle] = round($valeur, 2);
        }

        return ['date' => $date, 'sites' => $resultats, 'totaux' => $totaux];
    }
}
