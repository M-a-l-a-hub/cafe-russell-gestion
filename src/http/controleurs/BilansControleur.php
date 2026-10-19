<?php

declare(strict_types=1);

require_once __DIR__ . '/../ControleurBase.php';
require_once __DIR__ . '/../../services/RapportService.php';

/**
 * Bilans hebdomadaires, mensuels et annuels.
 * Les calculs d'activité réutilisent RapportService afin de conserver
 * les règles financières du bilan journalier.
 */
final class BilansControleur extends ControleurBase
{
    /** GET /api/bilans?periode=hebdomadaire|mensuel|annuel&date=AAAA-MM-JJ&site_id=ID */
    public function afficher(Requete $req): array
    {
        $periode = $req->queryChoix('periode', ['hebdomadaire', 'mensuel', 'annuel'])
            ?? 'hebdomadaire';
        $timezone = new DateTimeZone('Africa/Kinshasa');
        $dateReference = new DateTimeImmutable(
            $req->queryDate('date') ?? (new DateTimeImmutable('now', $timezone))->format('Y-m-d'),
            $timezone
        );

        [$debut, $fin] = $this->calculerPeriode($periode, $dateReference);
        $finExclusive = $fin->modify('+1 day');
        $siteId = $req->queryEntier('site_id');
        $userId = $this->utilisateurId($req);
        $superAdmin = $this->estSuperAdmin($req);

        if (!$superAdmin && $siteId === null) {
            $affectation = $this->uneLigne(
                "
                SELECT site_id
                FROM site_responsables
                WHERE user_id = :user_id
                  AND actif = TRUE
                  AND date_debut <= CURRENT_DATE
                  AND (date_fin IS NULL OR date_fin >= CURRENT_DATE)
                ORDER BY type = 'principal' DESC, site_id
                LIMIT 1
                ",
                ['user_id' => $userId]
            );

            if ($affectation === null) {
                throw new AccesRefuseException('Aucun site ne vous est attribué.');
            }

            $siteId = (int) $affectation['site_id'];
        }

        if ($siteId !== null) {
            $this->verifierSite($req, $siteId);
            $sites = $this->lignes(
                'SELECT id, code, nom FROM sites WHERE id = :id AND actif = TRUE',
                ['id' => $siteId]
            );

            if ($sites === []) {
                throw new HttpException(404, 'site_introuvable', 'Site introuvable.');
            }
        } else {
            $sites = $this->lignes(
                'SELECT id, code, nom FROM sites WHERE actif = TRUE ORDER BY nom'
            );
        }

        $totauxSite = [];
        $joursSite = [];
        foreach ($sites as $site) {
            $id = (int) $site['id'];
            $totauxSite[$id] = $this->totauxVides();
            $joursSite[$id] = [];
        }

        $paramsJournees = [
            'date_debut' => $debut->format('Y-m-d'),
            'date_fin_exclue' => $finExclusive->format('Y-m-d'),
        ];
        $filtreSite = '';
        if ($siteId !== null) {
            $filtreSite = ' AND j.site_id = :site_id';
            $paramsJournees['site_id'] = $siteId;
        }

        $journees = $this->lignes(
            "
            SELECT j.id, j.site_id, j.date_jour, j.statut
            FROM journees j
            WHERE j.date_jour >= CAST(:date_debut AS DATE)
              AND j.date_jour < CAST(:date_fin_exclue AS DATE)
              {$filtreSite}
            ORDER BY j.date_jour, j.site_id
            ",
            $paramsJournees
        );

        $rapportService = new RapportService();
        $clesRapport = [
            'ventes',
            'commissions',
            'transport',
            'consommables',
            'benefice_activites',
            'benefice_entreprise',
            'benefice_russell',
            'nb_ravitaillements',
        ];

        foreach ($journees as $journee) {
            $idSite = (int) $journee['site_id'];
            $date = (string) $journee['date_jour'];
            if (!isset($totauxSite[$idSite])) {
                continue;
            }

            $rapport = $rapportService->pourJournee((int) $journee['id'], $userId);
            $jour = $this->totauxVides();
            $jour['date'] = $date;
            $jour['statut_journee'] = (string) $journee['statut'];

            foreach ($clesRapport as $cle) {
                $valeur = (float) ($rapport[$cle] ?? 0);
                $jour[$cle] = $valeur;
                $totauxSite[$idSite][$cle] += $valeur;
            }

            $joursSite[$idSite][$date] = $jour;
        }

        // Les dépenses sont lues indépendamment des journées : une dépense
        // enregistrée sur une date sans journée ouverte reste dans le bilan.
        $paramsDepenses = [
            'date_debut' => $debut->format('Y-m-d'),
            'date_fin_exclue' => $finExclusive->format('Y-m-d'),
        ];
        $filtreDepenses = '';
        if ($siteId !== null) {
            $filtreDepenses = ' AND d.site_id = :site_id';
            $paramsDepenses['site_id'] = $siteId;
        } elseif (!$superAdmin) {
            // Garde-fou supplémentaire : les responsables ne voient jamais
            // les dépenses générales ni celles d'autres sites.
            $filtreDepenses = ' AND d.site_id = :site_id';
            $paramsDepenses['site_id'] = (int) $sites[0]['id'];
        }

        $depenses = $this->lignes(
            "
            SELECT d.site_id, d.date_depense::text AS date_depense,
                   COALESCE(SUM(d.montant), 0) AS total
            FROM depenses d
            WHERE d.date_depense >= CAST(:date_debut AS DATE)
              AND d.date_depense < CAST(:date_fin_exclue AS DATE)
              {$filtreDepenses}
            GROUP BY d.site_id, d.date_depense
            ORDER BY d.date_depense
            ",
            $paramsDepenses
        );

        $depensesGenerales = 0.0;
        $depensesGeneralesParDate = [];
        foreach ($depenses as $depense) {
            $montant = (float) $depense['total'];
            $date = (string) $depense['date_depense'];
            if ($depense['site_id'] === null) {
                // Uniquement visible et comptabilisé dans la vue globale Russell.
                if ($superAdmin && $siteId === null) {
                    $depensesGenerales += $montant;
                    $depensesGeneralesParDate[$date] =
                        ($depensesGeneralesParDate[$date] ?? 0.0) + $montant;
                }
                continue;
            }

            $idSite = (int) $depense['site_id'];
            if (!isset($totauxSite[$idSite])) {
                continue;
            }

            $totauxSite[$idSite]['depenses_site'] += $montant;
            if (!isset($joursSite[$idSite][$date])) {
                $joursSite[$idSite][$date] = $this->totauxVides();
                $joursSite[$idSite][$date]['date'] = $date;
                $joursSite[$idSite][$date]['statut_journee'] = null;
            }
            $joursSite[$idSite][$date]['depenses_site'] += $montant;
        }

        $totaux = $this->totauxVides();
        $joursGlobaux = [];
        $sitesResultat = [];

        foreach ($sites as $site) {
            $idSite = (int) $site['id'];
            $totauxLocaux = $totauxSite[$idSite];

            foreach ($totauxLocaux as $cle => $valeur) {
                $totaux[$cle] += $valeur;
            }
            $totauxLocaux['resultat_estime'] = round(
                $totauxLocaux['benefice_activites'] - $totauxLocaux['depenses_site'],
                2
            );

            foreach ($joursSite[$idSite] as $date => $jour) {
                $jour['resultat_estime'] = round(
                    $jour['benefice_activites'] - $jour['depenses_site'],
                    2
                );
                $jour['jour_ouvrable'] = (int) (new DateTimeImmutable($date, $timezone))->format('N') !== 7;
                if (!isset($joursGlobaux[$date])) {
                    $joursGlobaux[$date] = $this->totauxVides();
                    $joursGlobaux[$date]['date'] = $date;
                    $joursGlobaux[$date]['statut_journee'] = $jour['statut_journee'];
                } elseif ($jour['statut_journee'] === 'ouverte') {
                    $joursGlobaux[$date]['statut_journee'] = 'ouverte';
                } elseif ($joursGlobaux[$date]['statut_journee'] === null) {
                    $joursGlobaux[$date]['statut_journee'] = $jour['statut_journee'];
                }

                foreach ($this->clesTotaux() as $cle) {
                    $joursGlobaux[$date][$cle] += (float) ($jour[$cle] ?? 0);
                }
            }

            $sitesResultat[] = [
                'site' => $site,
                'totaux' => $totauxLocaux,
            ];
        }

        foreach ($depensesGeneralesParDate as $date => $montant) {
            if (!isset($joursGlobaux[$date])) {
                $joursGlobaux[$date] = $this->totauxVides();
                $joursGlobaux[$date]['date'] = $date;
                $joursGlobaux[$date]['statut_journee'] = null;
            }
            $joursGlobaux[$date]['depenses_generales'] += $montant;
        }

        foreach ($joursGlobaux as &$jour) {
            $jour['resultat_estime'] = round(
                $jour['benefice_activites'] - $jour['depenses_site'] - $jour['depenses_generales'],
                2
            );
            $jour['jour_ouvrable'] = (int) (new DateTimeImmutable($jour['date'], $timezone))->format('N') !== 7;
            foreach ($this->clesTotaux() as $cle) {
                $jour[$cle] = round((float) ($jour[$cle] ?? 0), 2);
            }
        }
        unset($jour);

        $totaux['depenses_generales'] = round($depensesGenerales, 2);
        $totaux['resultat_estime'] = round(
            $totaux['benefice_activites'] - $totaux['depenses_site'] - $depensesGenerales,
            2
        );

        foreach ($this->clesTotaux() as $cle) {
            $totaux[$cle] = round((float) ($totaux[$cle] ?? 0), 2);
        }

        foreach ($sitesResultat as &$siteResultat) {
            foreach ($this->clesTotaux() as $cle) {
                $siteResultat['totaux'][$cle] = round(
                    (float) ($siteResultat['totaux'][$cle] ?? 0),
                    2
                );
            }
        }
        unset($siteResultat);

        ksort($joursGlobaux);

        return [
            'periode' => $periode,
            'date_debut' => $debut->format('Y-m-d'),
            'date_fin' => $fin->format('Y-m-d'),
            'site_id' => $siteId,
            'sites' => $sitesResultat,
            'totaux' => $totaux,
            'jours' => array_values($joursGlobaux),
        ];
    }

    /** @return array{0: DateTimeImmutable, 1: DateTimeImmutable} */
    private function calculerPeriode(string $periode, DateTimeImmutable $reference): array
    {
        if ($periode === 'hebdomadaire') {
            $jourSemaine = (int) $reference->format('N');
            $decalage = $jourSemaine === 7 ? 6 : $jourSemaine - 1;
            $debut = $reference->modify('-' . $decalage . ' days')->setTime(0, 0);
            return [$debut, $debut->modify('+5 days')];
        }

        if ($periode === 'mensuel') {
            $debut = $reference->modify('first day of this month')->setTime(0, 0);
            return [$debut, $debut->modify('last day of this month')];
        }

        $debut = $reference->setDate((int) $reference->format('Y'), 1, 1)->setTime(0, 0);
        return [$debut, $debut->setDate((int) $reference->format('Y'), 12, 31)];
    }

    private function totauxVides(): array
    {
        return [
            'ventes' => 0.0,
            'commissions' => 0.0,
            'transport' => 0.0,
            'consommables' => 0.0,
            'benefice_activites' => 0.0,
            'benefice_entreprise' => 0.0,
            'benefice_russell' => 0.0,
            'nb_ravitaillements' => 0.0,
            'depenses_site' => 0.0,
            'depenses_generales' => 0.0,
        ];
    }

    /** @return string[] */
    private function clesTotaux(): array
    {
        return [
            'ventes',
            'commissions',
            'transport',
            'consommables',
            'benefice_activites',
            'benefice_entreprise',
            'benefice_russell',
            'nb_ravitaillements',
            'depenses_site',
            'depenses_generales',
        ];
    }
}
