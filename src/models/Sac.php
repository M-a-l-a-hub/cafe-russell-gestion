<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class Sac
{
    private PDO $db;

    public function __construct()
    {
        $this->db = getDatabaseConnection();
    }

    /**
     * Récupère tous les sacs d'un site.
     */
    public function getBySiteId(int $siteId): array
    {
        $sql = "
            SELECT
                s.id,
                s.site_id,
                s.numero,
                s.categorie,
                s.statut,
                s.motif_indisponibilite,
                s.created_at,
                s.updated_at,
                si.code AS site_code,
                si.nom AS site_nom
            FROM sacs s
            INNER JOIN sites si
                ON si.id = s.site_id
            WHERE s.site_id = :site_id
            ORDER BY s.numero ASC
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'site_id' => $siteId
        ]);

        return $stmt->fetchAll();
    }

    /**
     * Récupère un sac par son identifiant.
     */
    public function findById(int $id): ?array
    {
        $sql = "
            SELECT
                s.id,
                s.site_id,
                s.numero,
                s.categorie,
                s.statut,
                s.motif_indisponibilite,
                s.created_at,
                s.updated_at,
                si.code AS site_code,
                si.nom AS site_nom
            FROM sacs s
            INNER JOIN sites si
                ON si.id = s.site_id
            WHERE s.id = :id
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'id' => $id
        ]);

        $sac = $stmt->fetch();

        return $sac ?: null;
    }

    /**
     * Recherche un sac par son numéro dans un site précis.
     */
    public function findBySiteAndNumero(
        int $siteId,
        string $numero
    ): ?array {
        $sql = "
            SELECT
                s.id,
                s.site_id,
                s.numero,
                s.categorie,
                s.statut,
                s.motif_indisponibilite,
                s.created_at,
                s.updated_at,
                si.code AS site_code,
                si.nom AS site_nom
            FROM sacs s
            INNER JOIN sites si
                ON si.id = s.site_id
            WHERE s.site_id = :site_id
              AND s.numero = :numero
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'site_id' => $siteId,
            'numero' => $numero
        ]);

        $sac = $stmt->fetch();

        return $sac ?: null;
    }

    /**
     * Récupère les sacs actuellement libres d'un site.
     */
    public function getAvailableBySiteId(int $siteId): array
    {
        $sql = "
            SELECT
                s.id,
                s.site_id,
                s.numero,
                s.categorie,
                s.statut,
                s.motif_indisponibilite,
                s.created_at,
                s.updated_at
            FROM sacs s
            WHERE s.site_id = :site_id
              AND s.statut = 'libre'
            ORDER BY s.numero ASC
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'site_id' => $siteId
        ]);

        return $stmt->fetchAll();
    }
}
