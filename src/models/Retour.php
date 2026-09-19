<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class Retour
{
    private PDO $db;

    public function __construct()
    {
        $this->db = getDatabaseConnection();
    }

    /**
     * Récupère le retour d'une activité.
     */
    public function findByActiviteId(int $activiteId): ?array
    {
        $sql = "
            SELECT
                r.id,
                r.activite_id,
                r.effectue_par,
                r.date_heure,
                r.note,
                r.created_at,
                r.updated_at,

                u.nom AS utilisateur_nom,
                u.prenom AS utilisateur_prenom

            FROM retours r

            INNER JOIN users u
                ON u.id = r.effectue_par

            WHERE r.activite_id = :activite_id
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'activite_id' => $activiteId
        ]);

        $retour = $stmt->fetch();

        return $retour ?: null;
    }

    /**
     * Récupère un retour par son identifiant.
     */
    public function findById(int $id): ?array
    {
        $sql = "
            SELECT
                r.id,
                r.activite_id,
                r.effectue_par,
                r.date_heure,
                r.note,
                r.created_at,
                r.updated_at,

                u.nom AS utilisateur_nom,
                u.prenom AS utilisateur_prenom

            FROM retours r

            INNER JOIN users u
                ON u.id = r.effectue_par

            WHERE r.id = :id
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'id' => $id
        ]);

        $retour = $stmt->fetch();

        return $retour ?: null;
    }
}
