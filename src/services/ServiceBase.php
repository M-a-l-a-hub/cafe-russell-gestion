<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/AuditService.php';
require_once __DIR__ . '/AutorisationService.php';
require_once __DIR__ . '/ActiviteService.php';

/**
 * Socle commun des services d'écriture.
 *
 * Fournit la connexion, les transactions et quelques
 * vérifications réutilisées par tous les services.
 */
abstract class ServiceBase
{
    protected const PERIODES_ACTIVITE = ['matin', 'soir', 'journee'];

    protected PDO $db;

    protected function verifierAccesSite(int $userId, int $siteId): void
    {
        (new AutorisationService($this->db))->verifierSite($userId, $siteId);
    }

    public function __construct()
    {
        $this->db = getDatabaseConnection();
    }

    /**
     * Exécute une requête et retourne la première ligne (ou null).
     */
    protected function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Exécute une requête et retourne toutes les lignes.
     */
    protected function fetchAllRows(string $sql, array $params = []): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Exécute $callback dans une transaction.
     * Commit si tout va bien, rollback et relance l'exception sinon.
     *
     * Si une transaction est déjà ouverte (opération composée : un
     * service en appelle un autre), on la rejoint : c'est l'appelant
     * initial qui validera ou annulera l'ensemble.
     */
    protected function transaction(callable $callback): mixed
    {
        if ($this->db->inTransaction()) {
            return $callback();
        }

        $this->db->beginTransaction();

        try {
            $resultat = $callback();

            $this->db->commit();

            return $resultat;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }

    /**
     * Vérifie que l'utilisateur existe et est actif.
     */
    protected function verifierUtilisateur(int $userId): array
    {
        $user = $this->fetchOne(
            "
            SELECT id, nom, prenom, role, actif
            FROM users
            WHERE id = :id
            ",
            ['id' => $userId]
        );

        if ($user === null) {
            throw new RuntimeException('Utilisateur introuvable.');
        }

        if (!(bool) $user['actif']) {
            throw new RuntimeException('Cet utilisateur est désactivé.');
        }

        return $user;
    }

    /**
     * Valide et normalise des lignes [produit_id, quantite].
     *
     * @return array<int, array{produit_id: int, quantite: int}>
     */
    protected function normaliserLignes(
        array $lignes,
        bool $autoriserZero
    ): array {
        $vus = [];
        $resultat = [];

        foreach (array_values($lignes) as $index => $ligne) {
            $numero = $index + 1;

            if (
                !is_array($ligne)
                || !isset($ligne['produit_id'], $ligne['quantite'])
            ) {
                throw new InvalidArgumentException(
                    "Ligne {$numero} invalide : "
                    . "produit_id et quantite sont obligatoires."
                );
            }

            $produitId = filter_var($ligne['produit_id'], FILTER_VALIDATE_INT);
            $quantite = filter_var($ligne['quantite'], FILTER_VALIDATE_INT);

            if ($produitId === false || $produitId <= 0) {
                throw new InvalidArgumentException(
                    "Ligne {$numero} : produit_id invalide."
                );
            }

            $minimum = $autoriserZero ? 0 : 1;

            if ($quantite === false || $quantite < $minimum) {
                throw new InvalidArgumentException(
                    "Ligne {$numero} : la quantité doit être un entier "
                    . ">= {$minimum}."
                );
            }

            if (isset($vus[$produitId])) {
                throw new InvalidArgumentException(
                    "Le produit {$produitId} apparaît plusieurs fois."
                );
            }

            $vus[$produitId] = true;

            $resultat[] = [
                'produit_id' => $produitId,
                'quantite' => $quantite,
            ];
        }

        return $resultat;
    }

    /**
     * Vérifie qu'une période d'activité est valide.
     */
    protected function verifierPeriodeActivite(string $periode): void
    {
        if (!in_array($periode, self::PERIODES_ACTIVITE, true)) {
            throw new InvalidArgumentException(
                "Période invalide : {$periode} "
                . '(valeurs possibles : matin, soir, journee).'
            );
        }
    }

    /**
     * Crée l'activité d'un vendeur avec un sac dans une journée ouverte
     * et passe le sac en « en_utilisation ».
     *
     * À appeler DANS une transaction déjà ouverte (l'utilisateur doit
     * avoir été vérifié par l'appelant).
     *
     * @return int Identifiant de l'activité créée.
     */
    protected function ouvrirActivite(
        int $journeeId,
        int $vendeurId,
        int $sacId,
        string $periode,
        int $userId
    ): int {
        $this->verifierPeriodeActivite($periode);

        // Verrouille la journée pendant la création.
        $journee = $this->fetchOne(
            "
            SELECT id, site_id, statut
            FROM journees
            WHERE id = :id
            FOR UPDATE
            ",
            ['id' => $journeeId]
        );

        if ($journee === null) {
            throw new RuntimeException('Journée introuvable.');
        }

        if ($journee['statut'] !== 'ouverte') {
            throw new RuntimeException(
                'Cette journée est clôturée : '
                . 'impossible d’y démarrer une activité.'
            );
        }

        $this->verifierAccesSite($userId, (int) $journee['site_id']);

        $vendeur = $this->fetchOne(
            "
            SELECT id, code, nom, prenom, actif
            FROM vendeurs
            WHERE id = :id
            ",
            ['id' => $vendeurId]
        );

        if ($vendeur === null) {
            throw new RuntimeException('Vendeur introuvable.');
        }

        if (!(bool) $vendeur['actif']) {
            throw new RuntimeException('Ce vendeur est désactivé.');
        }

        // Verrouille le sac pour éviter une double attribution.
        $sac = $this->fetchOne(
            "
            SELECT id, site_id, numero, statut
            FROM sacs
            WHERE id = :id
            FOR UPDATE
            ",
            ['id' => $sacId]
        );

        if ($sac === null) {
            throw new RuntimeException('Sac introuvable.');
        }

        if ((int) $sac['site_id'] !== (int) $journee['site_id']) {
            throw new RuntimeException(
                'Ce sac n’appartient pas au site de la journée.'
            );
        }

        if ($sac['statut'] !== 'libre') {
            throw new RuntimeException(
                "Le sac {$sac['numero']} n’est pas disponible "
                . "(statut : {$sac['statut']})."
            );
        }

        // Un seul sac actif par vendeur à un instant donné,
        // quel que soit le site.
        $activiteVendeur = $this->fetchOne(
            "
            SELECT a.id, st.nom AS site_nom
            FROM activites a
            INNER JOIN journees j
                ON j.id = a.journee_id
            INNER JOIN sites st
                ON st.id = j.site_id
            WHERE a.vendeur_id = :vendeur_id
              AND a.statut = 'en_cours'
            LIMIT 1
            ",
            ['vendeur_id' => $vendeurId]
        );

        if ($activiteVendeur !== null) {
            throw new RuntimeException(
                'Ce vendeur a déjà une activité en cours '
                . "(site {$activiteVendeur['site_nom']}) : "
                . 'un seul sac est actif à la fois.'
            );
        }

        // La catégorie du sac est figée dans l'activité par la base
        // (déclencheur de la migration 026).
        $stmt = $this->db->prepare(
            "
            INSERT INTO activites (
                journee_id,
                vendeur_id,
                sac_id,
                periode
            )
            VALUES (
                :journee_id,
                :vendeur_id,
                :sac_id,
                :periode
            )
            RETURNING id
            "
        );

        $stmt->execute([
            'journee_id' => $journeeId,
            'vendeur_id' => $vendeurId,
            'sac_id' => $sacId,
            'periode' => $periode,
        ]);

        $activiteId = (int) $stmt->fetchColumn();

        $stmt = $this->db->prepare(
            "
            UPDATE sacs
            SET statut = 'en_utilisation',
                updated_at = NOW()
            WHERE id = :id
            "
        );

        $stmt->execute(['id' => $sacId]);

        AuditService::enregistrer(
            $this->db,
            $userId,
            'activite_demarree',
            'activites',
            $activiteId,
            (int) $journee['site_id'],
            null,
            [
                'journee_id' => $journeeId,
                'vendeur_id' => $vendeurId,
                'sac_id' => $sacId,
                'periode' => $periode,
            ]
        );

        return $activiteId;
    }

    /**
     * Recalcule le CA, la commission et l'écart d'une activité après la
     * correction d'un ravitaillement ou d'un retour. Ne fait rien si
     * l'activité n'est pas encore clôturée : le rapport lit alors les
     * lignes à jour directement, rien n'est figé.
     *
     * Choix pris ici (à confirmer si besoin) : si l'écart redevient non
     * nul après correction, le statut repasse à 'ecart_a_expliquer' même
     * s'il avait déjà été résolu, car le montant n'est plus le même et
     * mérite une nouvelle explication ; le motif de la correction sert
     * alors de motif d'écart de départ.
     */
    protected function recalculerFinancesActivite(
        int $activiteId,
        ?int $siteId,
        int $userId,
        string $motif
    ): void {
        $activite = $this->fetchOne(
            'SELECT statut FROM activites WHERE id = :id FOR UPDATE',
            ['id' => $activiteId]
        );

        if ($activite === null || $activite['statut'] !== 'terminee') {
            return;
        }

        $calcul = (new ActiviteService())->calculer($activiteId);
        $nouveauCa = $calcul['ca'];

        $commission = $this->fetchOne(
            'SELECT id, taux FROM commissions WHERE activite_id = :id',
            ['id' => $activiteId]
        );
        $controle = $this->fetchOne(
            'SELECT id, montant_remis, ca_theorique, ecart, statut, motif_ecart FROM controles_financiers WHERE activite_id = :id',
            ['id' => $activiteId]
        );

        if ($commission === null || $controle === null) {
            return;
        }

        // Règle métier fixe : la commission vendeur reste à 25 %, y compris pour les sacs Russell.
        $tauxCommission = 25.00;
        $nouvelleCommission = round($nouveauCa * $tauxCommission / 100, 2);

        $stmt = $this->db->prepare(
            'UPDATE commissions SET ca = :ca, taux = :taux, montant = :montant WHERE id = :id'
        );
        $stmt->execute([
            'ca' => $nouveauCa,
            'taux' => $tauxCommission,
            'montant' => $nouvelleCommission,
            'id' => $commission['id'],
        ]);

        $nouvelEcart = round((float) $controle['montant_remis'] - $nouveauCa, 2);
        $nouveauStatut = $nouvelEcart === 0.0 ? 'aucun_ecart' : 'ecart_a_expliquer';
        $nouveauMotifEcart = $nouvelEcart === 0.0 ? null : $motif;

        $stmt = $this->db->prepare(
            "
            UPDATE controles_financiers
            SET ca_theorique = :ca,
                ecart = :ecart,
                statut = CAST(:statut AS controle_financier_statut),
                motif_ecart = :motif_ecart,
                updated_at = NOW()
            WHERE id = :id
            "
        );
        $stmt->execute([
            'ca' => $nouveauCa,
            'ecart' => $nouvelEcart,
            'statut' => $nouveauStatut,
            'motif_ecart' => $nouveauMotifEcart,
            'id' => $controle['id'],
        ]);

        AuditService::enregistrer(
            $this->db,
            $userId,
            'finances_activite_recalculees',
            'activites',
            $activiteId,
            $siteId,
            [
                'commission_taux' => (float) $commission['taux'],
                'ca_theorique' => (float) $controle['ca_theorique'],
                'ecart' => (float) $controle['ecart'],
                'statut' => $controle['statut'],
            ],
            [
                'commission_taux' => $tauxCommission,
                'commission_montant' => $nouvelleCommission,
                'ca_theorique' => $nouveauCa,
                'ecart' => $nouvelEcart,
                'statut' => $nouveauStatut,
            ],
            $motif
        );
    }
}
