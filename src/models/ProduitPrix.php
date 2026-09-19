<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class ProduitPrix
{
    private PDO $db;

    public function __construct()
    {
        $this->db = getDatabaseConnection();
    }

    /**
     * Récupère le prix actuel d'un produit.
     */
    public function getCurrentPriceByProduitId(int $produitId): ?array
    {
        $sql = "
            SELECT
                pp.id,
                pp.produit_id,
                pp.prix,
                pp.date_debut,
                pp.date_fin,
                pp.created_at,
                p.code AS produit_code,
                p.nom AS produit_nom
            FROM produit_prix pp
            INNER JOIN produits p
                ON p.id = pp.produit_id
            WHERE pp.produit_id = :produit_id
              AND pp.date_fin IS NULL
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'produit_id' => $produitId
        ]);

        $prix = $stmt->fetch();

        return $prix ?: null;
    }

    /**
     * Récupère les prix actuels de tous les produits actifs.
     */
    public function getCurrentPrices(): array
    {
        $sql = "
            SELECT
                pp.id,
                pp.produit_id,
                pp.prix,
                pp.date_debut,
                pp.date_fin,
                pp.created_at,
                p.code AS produit_code,
                p.nom AS produit_nom,
                p.unite
            FROM produit_prix pp
            INNER JOIN produits p
                ON p.id = pp.produit_id
            WHERE pp.date_fin IS NULL
              AND p.actif = TRUE
            ORDER BY p.nom ASC
        ";

        $stmt = $this->db->query($sql);

        return $stmt->fetchAll();
    }

    /**
     * Récupère tout l'historique des prix d'un produit.
     */
    public function getHistoryByProduitId(int $produitId): array
    {
        $sql = "
            SELECT
                pp.id,
                pp.produit_id,
                pp.prix,
                pp.date_debut,
                pp.date_fin,
                pp.created_at
            FROM produit_prix pp
            WHERE pp.produit_id = :produit_id
            ORDER BY pp.date_debut DESC, pp.id DESC
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'produit_id' => $produitId
        ]);

        return $stmt->fetchAll();
    }
}

