<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class RavitaillementLigne
{
    private PDO $db;

    public function __construct()
    {
        $this->db = getDatabaseConnection();
    }

    /**
     * Récupère toutes les lignes d'un ravitaillement.
     */
    public function getByRavitaillementId(int $ravitaillementId): array
    {
        $sql = "
            SELECT
                rl.id,
                rl.ravitaillement_id,
                rl.produit_id,
                rl.quantite,
                rl.prix_unitaire,
                rl.cout_unitaire,
                rl.created_at,

                p.code AS produit_code,
                p.nom AS produit_nom,
                p.unite AS produit_unite

            FROM ravitaillement_lignes rl

            INNER JOIN produits p
                ON p.id = rl.produit_id

            WHERE rl.ravitaillement_id = :ravitaillement_id

            ORDER BY p.nom ASC
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'ravitaillement_id' => $ravitaillementId
        ]);

        return $stmt->fetchAll();
    }

    /**
     * Récupère une ligne par son identifiant.
     */
    public function findById(int $id): ?array
    {
        $sql = "
            SELECT
                rl.id,
                rl.ravitaillement_id,
                rl.produit_id,
                rl.quantite,
                rl.prix_unitaire,
                rl.cout_unitaire,
                rl.created_at,

                p.code AS produit_code,
                p.nom AS produit_nom,
                p.unite AS produit_unite

            FROM ravitaillement_lignes rl

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

    /**
     * Calcule la valeur totale des ventes potentielles
     * d'un ravitaillement.
     */
    public function getTotalVentes(
        int $ravitaillementId
    ): float {
        $sql = "
            SELECT
                COALESCE(
                    SUM(quantite * prix_unitaire),
                    0
                ) AS total_ventes
            FROM ravitaillement_lignes
            WHERE ravitaillement_id = :ravitaillement_id
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'ravitaillement_id' => $ravitaillementId
        ]);

        return (float) $stmt->fetchColumn();
    }

    /**
     * Calcule le coût total des consommables
     * correspondant au ravitaillement.
     */
    public function getTotalCouts(
        int $ravitaillementId
    ): float {
        $sql = "
            SELECT
                COALESCE(
                    SUM(quantite * cout_unitaire),
                    0
                ) AS total_couts
            FROM ravitaillement_lignes
            WHERE ravitaillement_id = :ravitaillement_id
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'ravitaillement_id' => $ravitaillementId
        ]);

        return (float) $stmt->fetchColumn();
    }
    
    public function getByActiviteId(int 
        $activiteId
    ): array {
        $sql = "
            SELECT
                rl.id,
                rl.ravitaillement_id,
                rl.produit_id,
                rl.quantite,
                rl.prix_unitaire,
                rl.cout_unitaire,
                rl.created_at,

                p.code AS produit_code,
                p.nom AS produit_nom,
                p.unite AS produit_unite

            FROM ravitaillement_lignes rl

            INNER JOIN ravitaillements r
                ON r.id = rl.ravitaillement_id

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
