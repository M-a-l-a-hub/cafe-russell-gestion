<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class CategorieDepense
{
    private PDO $db;

    public function __construct()
    {
        $this->db = getDatabaseConnection();
    }

    public function getActiveCategories(): array
    {
        $sql = "
            SELECT
                id,
                code,
                nom,
                description,
                actif,
                created_at,
                updated_at
            FROM categories_depenses
            WHERE actif = TRUE
            ORDER BY nom ASC
        ";

        $stmt = $this->db->query($sql);

        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $sql = "
            SELECT
                id,
                code,
                nom,
                description,
                actif,
                created_at,
                updated_at
            FROM categories_depenses
            WHERE id = :id
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'id' => $id
        ]);

        $categorie = $stmt->fetch();

        return $categorie ?: null;
    }

    public function findByCode(string $code): ?array
    {
        $sql = "
            SELECT
                id,
                code,
                nom,
                description,
                actif,
                created_at,
                updated_at
            FROM categories_depenses
            WHERE code = :code
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'code' => $code
        ]);

        $categorie = $stmt->fetch();

        return $categorie ?: null;
    }
}
