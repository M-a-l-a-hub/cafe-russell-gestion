<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class ProduitCout
{
    private PDO $db;

    public function __construct()
    {
        $this->db = getDatabaseConnection();
    }

    public function getCurrentCost(int $produitId): ?array
    {
        $sql = "
            SELECT
                pc.id,
                pc.produit_id,
                pc.cout_unitaire,
                pc.date_debut,
                pc.date_fin,
                pc.created_at,

                p.code AS produit_code,
                p.nom AS produit_nom

            FROM produit_couts pc

            INNER JOIN produits p
                ON p.id = pc.produit_id

            WHERE pc.produit_id = :produit_id
              AND pc.date_fin IS NULL

            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'produit_id' => $produitId
        ]);

        $cout = $stmt->fetch();

        return $cout ?: null;
    }

    public function getCostAtDate(
        int $produitId,
        string $date
    ): ?array {
        $sql = "
            SELECT
                pc.id,
                pc.produit_id,
                pc.cout_unitaire,
                pc.date_debut,
                pc.date_fin,
                pc.created_at,

                p.code AS produit_code,
                p.nom AS produit_nom

            FROM produit_couts pc

            INNER JOIN produits p
                ON p.id = pc.produit_id

            WHERE pc.produit_id = :produit_id
              AND pc.date_debut <= :date
              AND (
                  pc.date_fin IS NULL
                  OR pc.date_fin >= :date
              )

            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'produit_id' => $produitId,
            'date' => $date
        ]);

        $cout = $stmt->fetch();

        return $cout ?: null;
    }

    public function getHistory(int $produitId): array
    {
        $sql = "
            SELECT
                pc.id,
                pc.produit_id,
                pc.cout_unitaire,
                pc.date_debut,
                pc.date_fin,
                pc.created_at

            FROM produit_couts pc

            WHERE pc.produit_id = :produit_id

            ORDER BY pc.date_debut DESC
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'produit_id' => $produitId
        ]);

        return $stmt->fetchAll();
    }
}
