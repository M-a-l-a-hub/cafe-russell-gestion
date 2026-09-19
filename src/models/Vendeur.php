<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class Vendeur
{
    private PDO $db;

    public function __construct()
    {
        $this->db = getDatabaseConnection();
    }

    /**
     * Récupère tous les vendeurs actifs.
     */
    public function getActiveVendeurs(): array
    {
        $sql = "
            SELECT
                v.id,
                v.code,
                v.nom,
                v.prenom,
                v.telephone,
                v.photo,
                v.periode_habituelle,
                v.categorie_transport_id,
                v.actif,
                v.created_at,
                v.updated_at,
                ct.code AS categorie_transport_code,
                ct.nom AS categorie_transport_nom
            FROM vendeurs v
            INNER JOIN categories_transport ct
                ON ct.id = v.categorie_transport_id
            WHERE v.actif = TRUE
            ORDER BY v.nom ASC, v.prenom ASC
        ";

        $stmt = $this->db->query($sql);

        return $stmt->fetchAll();
    }

    /**
     * Récupère un vendeur par son identifiant.
     */
    public function findById(int $id): ?array
    {
        $sql = "
            SELECT
                v.id,
                v.code,
                v.nom,
                v.prenom,
                v.telephone,
                v.photo,
                v.periode_habituelle,
                v.categorie_transport_id,
                v.actif,
                v.created_at,
                v.updated_at,
                ct.code AS categorie_transport_code,
                ct.nom AS categorie_transport_nom
            FROM vendeurs v
            INNER JOIN categories_transport ct
                ON ct.id = v.categorie_transport_id
            WHERE v.id = :id
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'id' => $id
        ]);

        $vendeur = $stmt->fetch();

        return $vendeur ?: null;
    }

    /**
     * Recherche un vendeur par son code.
     */
    public function findByCode(string $code): ?array
    {
        $sql = "
            SELECT
                v.id,
                v.code,
                v.nom,
                v.prenom,
                v.telephone,
                v.photo,
                v.periode_habituelle,
                v.categorie_transport_id,
                v.actif,
                v.created_at,
                v.updated_at,
                ct.code AS categorie_transport_code,
                ct.nom AS categorie_transport_nom
            FROM vendeurs v
            INNER JOIN categories_transport ct
                ON ct.id = v.categorie_transport_id
            WHERE v.code = :code
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'code' => $code
        ]);

        $vendeur = $stmt->fetch();

        return $vendeur ?: null;
    }
}
