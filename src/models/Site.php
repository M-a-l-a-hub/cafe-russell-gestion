<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class Site
{
    private PDO $db;

    public function __construct()
    {
        $this->db = getDatabaseConnection();
    }

    /**
     * Récupère tous les sites actifs.
     */
    public function getActiveSites(): array
    {
        $sql = "
            SELECT
                id,
                code,
                nom,
                adresse,
                description,
                actif,
                created_at,
                updated_at
            FROM sites
            WHERE actif = TRUE
            ORDER BY nom ASC
        ";

        $stmt = $this->db->query($sql);

        return $stmt->fetchAll();
    }

    /**
     * Récupère un site par son identifiant.
     */
    public function findById(int $id): ?array
    {
        $sql = "
            SELECT
                id,
                code,
                nom,
                adresse,
                description,
                actif,
                created_at,
                updated_at
            FROM sites
            WHERE id = :id
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'id' => $id
        ]);

        $site = $stmt->fetch();

        return $site ?: null;
    }
}