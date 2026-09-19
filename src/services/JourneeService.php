<?php

declare(strict_types=1);

require_once __DIR__ . '/../models/Journee.php';
require_once __DIR__ . '/../models/Site.php';
require_once __DIR__ . '/../models/User.php';

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

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'site_id' => $siteId,
            'date_jour' => $date,
            'ouverte_par' => $userId,
            'note_ouverture' => $note
        ]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Clôture une journée ouverte.
     */
    public function cloturer(
        int $journeeId,
        int $userId,
        ?string $note = null
    ): void {
        $journee = $this->journee->findById($journeeId);

        if (!$journee) {
            throw new RuntimeException('Journée introuvable.');
        }

        if ($journee['statut'] !== 'ouverte') {
            throw new RuntimeException(
                'Cette journée est déjà clôturée.'
            );
        }

        $user = $this->user->findById($userId);

        if (!$user) {
            throw new RuntimeException('Utilisateur introuvable.');
        }

        $sql = "
            UPDATE journees
            SET
                statut = 'cloturee',
                cloturee_par = :cloturee_par,
                date_cloture = NOW(),
                note_cloture = :note_cloture,
                updated_at = NOW()
            WHERE id = :id
              AND statut = 'ouverte'
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'id' => $journeeId,
            'cloturee_par' => $userId,
            'note_cloture' => $note
        ]);

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException(
                'La journée n’a pas pu être clôturée.'
            );
        }
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
