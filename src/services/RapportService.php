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
            $resume['benefice_entreprise'] - $resume['depenses_site'],
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