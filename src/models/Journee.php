<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class Journee
{
    private PDO $db;

    public function __construct()
    {
        $this->db = getDatabaseConnection();
    }

    /**
     * Récupère une journée par son identifiant.
     */
    public function findById(int $id): ?array
    {
        $sql = "
            SELECT
                j.id,
                j.site_id,
                j.date_jour,
                j.statut,
                j.ouverte_par,
                j.date_ouverture,
                j.cloturee_par,
                j.date_cloture,
                j.note_ouverture,
                j.note_cloture,
                j.created_at,
                j.updated_at,
                s.code AS site_code,
                s.nom AS site_nom
            FROM journees j
            INNER JOIN sites s
                ON s.id = j.site_id
            WHERE j.id = :id
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'id' => $id
        ]);

        $journee = $stmt->fetch();

        return $journee ?: null;
    }

    /**
     * Récupère la journée d'un site pour une date donnée.
     */
    public function findBySiteAndDate(
        int $siteId,
        string $date
    ): ?array {
        $sql = "
            SELECT
                j.id,
                j.site_id,
                j.date_jour,
                j.statut,
                j.ouverte_par,
                j.date_ouverture,
                j.cloturee_par,
                j.date_cloture,
                j.note_ouverture,
                j.note_cloture,
                j.created_at,
                j.updated_at,
                s.code AS site_code,
                s.nom AS site_nom
            FROM journees j
            INNER JOIN sites s
                ON s.id = j.site_id
            WHERE j.site_id = :site_id
              AND j.date_jour = :date
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'site_id' => $siteId,
            'date' => $date
        ]);

        $journee = $stmt->fetch();

        return $journee ?: null;
    }

    /**
     * Récupère la journée actuellement ouverte d'un site.
     */
    public function getOpenBySiteId(int $siteId): ?array
    {
        $sql = "
            SELECT
                j.id,
                j.site_id,
                j.date_jour,
                j.statut,
                j.ouverte_par,
                j.date_ouverture,
                j.cloturee_par,
                j.date_cloture,
                j.note_ouverture,
                j.note_cloture,
                j.created_at,
                j.updated_at,
                s.code AS site_code,
                s.nom AS site_nom
            FROM journees j
            INNER JOIN sites s
                ON s.id = j.site_id
            WHERE j.site_id = :site_id
              AND j.statut = 'ouverte'
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'site_id' => $siteId
        ]);

        $journee = $stmt->fetch();

        return $journee ?: null;
    }

    /**
     * Récupère les journées d'un site.
     *
     * Les plus récentes apparaissent en premier.
     */
    public function getBySiteId(int $siteId): array
    {
        $sql = "
            SELECT
                j.id,
                j.site_id,
                j.date_jour,
                j.statut,
                j.ouverte_par,
                j.date_ouverture,
                j.cloturee_par,
                j.date_cloture,
                j.note_ouverture,
                j.note_cloture,
                j.created_at,
                j.updated_at,
                s.code AS site_code,
                s.nom AS site_nom
            FROM journees j
            INNER JOIN sites s
                ON s.id = j.site_id
            WHERE j.site_id = :site_id
            ORDER BY j.date_jour DESC, j.id DESC
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'site_id' => $siteId
        ]);

        return $stmt->fetchAll();
    }
}
