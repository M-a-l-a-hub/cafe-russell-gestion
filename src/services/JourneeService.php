<?php

declare(strict_types=1);

require_once __DIR__ . '/../models/Journee.php';
require_once __DIR__ . '/../models/Site.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/AuditService.php';
require_once __DIR__ . '/AutorisationService.php';

class JourneeService
{
    private PDO $db;
    private Journee $journee;
    private Site $site;
    private User $user;

    public function __construct()
    {
        $this->db = getDatabaseConnection();
        $this->journee = new Journee();
        $this->site = new Site();
        $this->user = new User();
    }

    /**
     * Ouvre une nouvelle journée pour un site.
     */
    public function ouvrir(
        int $siteId,
        string $date,
        int $userId,
        ?string $note = null
    ): int {
        // Vérifier que le site existe et est actif.
        $site = $this->site->findById($siteId);

        if (!$site) {
            throw new RuntimeException('Site introuvable.');
        }

        if (!(bool) $site['actif']) {
            throw new RuntimeException('Impossible d’ouvrir une journée pour un site désactivé.');
        }

        (new AutorisationService($this->db))->verifierSite($userId, $siteId);

        // Vérifier que l'utilisateur existe.
        $user = $this->user->findById($userId);

        if (!$user) {
            throw new RuntimeException('Utilisateur introuvable.');
        }

        // Vérifier qu'une journée existe déjà pour cette date.
        $existante = $this->journee->findBySiteAndDate($siteId, $date);

        if ($existante) {
            throw new RuntimeException(
                'Une journée existe déjà pour ce site à cette date.'
            );
        }

        // Vérifier qu'aucune autre journée n'est ouverte sur le site.
        $ouverte = $this->journee->getOpenBySiteId($siteId);

        if ($ouverte) {
            throw new RuntimeException(
                'Une autre journée est déjà ouverte pour ce site.'
            );
        }

        $sql = "
            INSERT INTO journees (
                site_id,
                date_jour,
                statut,
                ouverte_par,
                date_ouverture,
                note_ouverture
            )
            VALUES (
                :site_id,
                :date_jour,
                'ouverte',
                :ouverte_par,
                NOW(),
                :note_ouverture
            )
            RETURNING id
        ";

        return $this->enTransaction(
            function () use ($sql, $siteId, $date, $userId, $note): int {
                $stmt = $this->db->prepare($sql);

                $stmt->execute([
                    'site_id' => $siteId,
                    'date_jour' => $date,
                    'ouverte_par' => $userId,
                    'note_ouverture' => $note
                ]);

                $journeeId = (int) $stmt->fetchColumn();

                AuditService::enregistrer(
                    $this->db,
                    $userId,
                    'journee_ouverte',
                    'journees',
                    $journeeId,
                    $siteId,
                    null,
                    ['statut' => 'ouverte', 'date_jour' => $date],
                    $note
                );

                return $journeeId;
            }
        );
    }

    /**
     * Clôture une journée ouverte.
     */
    public function cloturer(
        int $journeeId,
        int $userId,
        ?string $note = null
    ): void {
        $this->enTransaction(
            function () use ($journeeId, $userId, $note): void {
                // Verrouille la journée avant de contrôler les activités.
                // DemarrageActiviteService et RavitaillementService prennent
                // le même verrou : aucune activité ne peut démarrer entre
                // le contrôle et la clôture.
                $stmt = $this->db->prepare(
                    "
                    SELECT id, site_id, statut
                    FROM journees
                    WHERE id = :id
                    FOR UPDATE
                    "
                );
                $stmt->execute(['id' => $journeeId]);
                $journee = $stmt->fetch();

                if ($journee === false) {
                    throw new RuntimeException('Journée introuvable.');
                }

                (new AutorisationService($this->db))->verifierSite(
                    $userId,
                    (int) $journee['site_id']
                );

                if ($journee['statut'] !== 'ouverte') {
                    throw new RuntimeException(
                        'Cette journée est déjà clôturée.'
                    );
                }

                $user = $this->user->findById($userId);

                if (!$user) {
                    throw new RuntimeException('Utilisateur introuvable.');
                }

                // Une activité en cours ou en anomalie doit être terminée
                // ou traitée avant la clôture de la journée.
                $stmt = $this->db->prepare(
                    "
                    SELECT COUNT(*)
                    FROM activites
                    WHERE journee_id = :id
                      AND statut IN ('en_cours', 'anomalie')
                    "
                );
                $stmt->execute(['id' => $journeeId]);

                if ((int) $stmt->fetchColumn() > 0) {
                    throw new RuntimeException(
                        'Impossible de clôturer la journée : '
                        . 'des activités sont encore en cours ou en anomalie.'
                    );
                }

                $stmt = $this->db->prepare(
                    "
                    UPDATE journees
                    SET
                        statut = 'cloturee',
                        cloturee_par = :cloturee_par,
                        date_cloture = NOW(),
                        note_cloture = :note_cloture,
                        updated_at = NOW()
                    WHERE id = :id
                      AND statut = 'ouverte'
                    "
                );
                $stmt->execute([
                    'id' => $journeeId,
                    'cloturee_par' => $userId,
                    'note_cloture' => $note,
                ]);

                if ($stmt->rowCount() !== 1) {
                    throw new RuntimeException(
                        'La journée n’a pas pu être clôturée.'
                    );
                }

                AuditService::enregistrer(
                    $this->db,
                    $userId,
                    'journee_cloturee',
                    'journees',
                    $journeeId,
                    (int) $journee['site_id'],
                    ['statut' => 'ouverte'],
                    ['statut' => 'cloturee'],
                    $note
                );
            }
        );
    }

    /**
     * Rouvre une journée clôturée.
     *
     * Cette opération est réservée au super administrateur Russell.
     */
    public function rouvrir(
        int $journeeId,
        int $userId,
        string $raison
    ): void {
        $journee = $this->journee->findById($journeeId);

        if (!$journee) {
            throw new RuntimeException('Journée introuvable.');
        }

        if ($journee['statut'] !== 'cloturee') {
            throw new RuntimeException(
                'Seule une journée clôturée peut être rouverte.'
            );
        }

        $user = $this->user->findById($userId);

        if (!$user) {
            throw new RuntimeException('Utilisateur introuvable.');
        }

        if ($user['role'] !== 'super_admin') {
            throw new RuntimeException(
                'Seul le super administrateur peut rouvrir une journée.'
            );
        }

        if (trim($raison) === '') {
            throw new InvalidArgumentException(
                'Une raison est obligatoire pour rouvrir une journée.'
            );
        }

        // Vérifier qu'aucune autre journée ouverte n'existe sur le site.
        $ouverte = $this->journee->getOpenBySiteId(
            (int) $journee['site_id']
        );

        if ($ouverte) {
            throw new RuntimeException(
                'Une autre journée est déjà ouverte sur ce site.'
            );
        }

        $this->enTransaction(
            function () use ($journeeId, $userId, $raison, $journee): void {
                $sql = "
                    UPDATE journees
                    SET
                        statut = 'ouverte',
                        cloturee_par = NULL,
                        date_cloture = NULL,
                        note_cloture = NULL,
                        updated_at = NOW()
                    WHERE id = :id
                      AND statut = 'cloturee'
                ";

                $stmt = $this->db->prepare($sql);

                $stmt->execute([
                    'id' => $journeeId
                ]);

                if ($stmt->rowCount() !== 1) {
                    throw new RuntimeException(
                        'La journée n’a pas pu être rouverte.'
                    );
                }

                // La réouverture doit rester traçable : l'UPDATE efface
                // les informations de clôture, l'audit les conserve.
                AuditService::enregistrer(
                    $this->db,
                    $userId,
                    'journee_rouverte',
                    'journees',
                    $journeeId,
                    (int) $journee['site_id'],
                    [
                        'statut' => 'cloturee',
                        'cloturee_par' => $journee['cloturee_par'],
                        'date_cloture' => $journee['date_cloture'],
                    ],
                    ['statut' => 'ouverte'],
                    trim($raison)
                );
            }
        );
    }

    /**
     * Exécute $callback dans une transaction, ou rejoint celle déjà
     * ouverte (même règle que ServiceBase::transaction) : c'est ce qui
     * permet d'appeler ce service depuis une requête rejouable, déjà
     * enveloppée dans une transaction par Application.
     */
    private function enTransaction(callable $callback): mixed
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
     * Heure (Kinshasa) du lendemain à partir de laquelle une journée
     * restée ouverte est clôturée automatiquement. 05 h 00 laisse finir
     * un service de nuit (soirée, mariage) sans le couper à minuit.
     */
    public const HEURE_COUPURE_AUTO = 5;

    /**
     * Une journée ouverte est « en retard » quand son heure de coupure
     * (lendemain, HEURE_COUPURE_AUTO) est passée.
     */
    public static function estEnRetard(string $dateJour, string $statut): bool
    {
        if ($statut !== 'ouverte') {
            return false;
        }

        return new DateTimeImmutable('now') >= self::heureCoupure($dateJour);
    }

    /**
     * Instant exact (fuseau de l'application) où la journée $dateJour doit
     * être clôturée : le lendemain à HEURE_COUPURE_AUTO. Le fuseau explicite
     * rend l'instant non ambigu pour une colonne TIMESTAMPTZ.
     */
    public static function heureCoupure(string $dateJour): DateTimeImmutable
    {
        return (new DateTimeImmutable(
            substr($dateJour, 0, 10) . ' 00:00:00',
            new DateTimeZone(FUSEAU_HORAIRE_APPLICATION)
        ))
            ->modify('+1 day')
            ->setTime(self::HEURE_COUPURE_AUTO, 0);
    }

    /**
     * Clôture les journées restées ouvertes après leur heure de coupure
     * (toutes, ou celles d'un seul site). Appelée à l'ouverture de
     * l'application et du tableau de bord : le service gratuit s'endort
     * la nuit, donc la vérification se fait à la première requête.
     *
     * Une journée dont des activités sont encore en cours ou en anomalie
     * n'est pas touchée : elle reste ouverte et apparaît « à clôturer ».
     * Une erreur sur une journée n'empêche jamais la requête en cours.
     *
     * @return int Nombre de journées clôturées.
     */
    public function cloturerJourneesEnRetard(?int $siteId = null): int
    {
        $sql = "
            SELECT j.id
            FROM journees j
            WHERE j.statut = 'ouverte'
              AND CAST(j.date_jour AS TIMESTAMP)
                  + INTERVAL '1 day'
                  + (CAST(:heure AS INTEGER) * INTERVAL '1 hour')
                  <= CAST(:maintenant AS TIMESTAMP)";

        $params = [
            'heure' => self::HEURE_COUPURE_AUTO,
            'maintenant' => date('Y-m-d H:i:s'),
        ];

        if ($siteId !== null) {
            $sql .= ' AND j.site_id = :site_id';
            $params['site_id'] = $siteId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $nombre = 0;

        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $journeeId) {
            try {
                if ($this->cloturerAutomatiquement((int) $journeeId)) {
                    $nombre++;
                }
            } catch (Throwable $e) {
                error_log(
                    'Clôture automatique de la journée ' . $journeeId
                    . ' impossible : ' . $e->getMessage()
                );
            }
        }

        return $nombre;
    }

    /**
     * Clôture une journée oubliée. La table journees et le journal d'audit
     * exigent un utilisateur : c'est celui qui a ouvert la journée, et la
     * note « Clôture automatique » permet de reconnaître ce cas.
     *
     * @return bool false si la journée n'est plus ouverte ou si des
     *              activités sont encore en cours ou en anomalie.
     */
    private function cloturerAutomatiquement(int $journeeId): bool
    {
        return (bool) $this->enTransaction(
            function () use ($journeeId): bool {
                $stmt = $this->db->prepare(
                    "
                    SELECT id, site_id, statut, ouverte_par, date_jour, date_ouverture
                    FROM journees
                    WHERE id = :id
                    FOR UPDATE
                    "
                );
                $stmt->execute(['id' => $journeeId]);
                $journee = $stmt->fetch();

                if ($journee === false || $journee['statut'] !== 'ouverte') {
                    return false;
                }

                // L'heure de clôture enregistrée est l'heure de coupure
                // (05 h 00), pas l'instant où le serveur, endormi la nuit,
                // s'en aperçoit. L'exécution réelle reste dans l'audit.
                $coupure = self::heureCoupure((string) $journee['date_jour']);
                $executeeLe = new DateTimeImmutable('now');

                // Journée d'une date passée ouverte après sa coupure : on ne
                // clôture jamais avant l'ouverture.
                $ouverteLe = new DateTimeImmutable((string) $journee['date_ouverture']);

                if ($coupure < $ouverteLe) {
                    $coupure = $executeeLe;
                }

                $stmt = $this->db->prepare(
                    "
                    SELECT COUNT(*)
                    FROM activites
                    WHERE journee_id = :id
                      AND statut IN ('en_cours', 'anomalie')
                    "
                );
                $stmt->execute(['id' => $journeeId]);

                if ((int) $stmt->fetchColumn() > 0) {
                    return false;
                }

                $note = sprintf(
                    'Clôture automatique à %02d h 00 le lendemain '
                    . '(journée non clôturée par le responsable).',
                    self::HEURE_COUPURE_AUTO
                );

                $stmt = $this->db->prepare(
                    "
                    UPDATE journees
                    SET
                        statut = 'cloturee',
                        cloturee_par = ouverte_par,
                        date_cloture = CAST(:coupure AS TIMESTAMPTZ),
                        note_cloture = :note,
                        updated_at = NOW()
                    WHERE id = :id
                      AND statut = 'ouverte'
                    "
                );
                $stmt->execute([
                    'id' => $journeeId,
                    'note' => $note,
                    'coupure' => $coupure->format('c'),
                ]);

                if ($stmt->rowCount() !== 1) {
                    return false;
                }

                AuditService::enregistrer(
                    $this->db,
                    (int) $journee['ouverte_par'],
                    'journee_cloturee_auto',
                    'journees',
                    $journeeId,
                    (int) $journee['site_id'],
                    ['statut' => 'ouverte'],
                    [
                        'statut' => 'cloturee',
                        'date_cloture' => $coupure->format('c'),
                        'executee_le' => $executeeLe->format('c'),
                    ],
                    $note
                );

                return true;
            }
        );
    }

    /**
     * Vérifie si une journée peut encore recevoir des opérations.
     */
    public function verifierOuverte(int $journeeId): array
    {
        $journee = $this->journee->findById($journeeId);

        if (!$journee) {
            throw new RuntimeException('Journée introuvable.');
        }

        if ($journee['statut'] !== 'ouverte') {
            throw new RuntimeException(
                'Cette journée est clôturée. Aucune nouvelle opération normale n’est autorisée.'
            );
        }

        return $journee;
    }
}
