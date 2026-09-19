<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class Produit
{
    private PDO $db;

    public function __construct()
    {
        $this->db = getDatabaseConnection();
    }

    /**
     * Récupère tous les produits actifs.
     */
    public function getActiveProduits(): array
    {
        $sql = "
            SELECT
                id,
                code,
                nom,
                description,
                unite,
                actif,
                created_at,
                updated_at
            FROM produits
            WHERE actif = TRUE
            ORDER BY nom ASC
        ";

        $stmt = $this->db->query($sql);

        return $stmt->fetchAll();
    }

    /**
     * Récupère un produit par son identifiant.
     */
    public function findById(int $id): ?array
    {
        $sql = "
            SELECT
                id,
                code,
                nom,
                description,
                unite,
                actif,
                created_at,
                updated_at
            FROM produits
            WHERE id = :id
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'id' => $id
        ]);

        $produit = $stmt->fetch();

        return $produit ?: null;
    }

    /**
     * Recherche un produit par son code.
     */
    public function findByCode(string $code): ?array
    {
        $sql = "
            SELECT
                id,
                code,
                nom,
                description,
                unite,
                actif,
                created_at,
                updated_at
            FROM produits
            WHERE code = :code
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'code' => $code
        ]);

        $produit = $stmt->fetch();

        return $produit ?: null;
    }
}
