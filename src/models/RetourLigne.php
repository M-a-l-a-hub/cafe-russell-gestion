<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class RetourLigne
{
    private PDO $db;

    public function __construct()
    {
        $this->db = getDatabaseConnection();
    }

    public function getByRetourId(int $retourId): array
    {
        $sql = "
            SELECT
                rl.id,
                rl.retour_id,
                rl.produit_id,
                rl.quantite,
                rl.created_at,
                p.code AS produit_code,
                p.nom AS produit_nom,
                p.unite AS produit_unite
            FROM retour_lignes rl
            INNER JOIN produits p
                ON p.id = rl.produit_id
            WHERE rl.retour_id = :retour_id
            ORDER BY p.nom ASC
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'retour_id' => $retourId
        ]);

        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $sql = "
            SELECT
                rl.id,
                rl.retour_id,
                rl.produit_id,
                rl.quantite,
                rl.created_at,
                p.code AS produit_code,
                p.nom AS produit_nom,
                p.unite AS produit_unite
            FROM retour_lignes rl
            INNER JOIN produits p
                ON p.id = rl.produit_id
            WHERE rl.id = :id
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'id' => $id
        ]);

        $ligne = $stmt->fetch();

        return $ligne ?: null;
    }

    public function getQuantiteRetournee(
        int $retourId,
        int $produitId
    ): int {
        $sql = "
            SELECT COALESCE(quantite, 0)
            FROM retour_lignes
            WHERE retour_id = :retour_id
              AND produit_id = :produit_id
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'retour_id' => $retourId,
            'produit_id' => $produitId
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function getTotalQuantites(int $retourId): int
    {
        $sql = "
            SELECT COALESCE(SUM(quantite), 0)
            FROM retour_lignes
            WHERE retour_id = :retour_id
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'retour_id' => $retourId
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function getByActiviteId(
        int $activiteId
    ): array {
        $sql = "
            SELECT
                rl.id,
                rl.retour_id,
                rl.produit_id,
                rl.quantite,
                rl.created_at,

                p.code AS produit_code,
                p.nom AS produit_nom,
                p.unite AS produit_unite

            FROM retour_lignes rl

            INNER JOIN retours r
                ON r.id = rl.retour_id

            INNER JOIN produits p
                ON p.id = rl.produit_id

            WHERE r.activite_id = :activite_id

            ORDER BY r.date_heure ASC, rl.id ASC
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'activite_id' => $activiteId
        ]);

        return $stmt->fetchAll();
    }

}
