<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class Ravitaillement
{
    private PDO $db;

    public function __construct()
    {
        $this->db = getDatabaseConnection();
    }

    /**
     * Récupère un ravitaillement par son identifiant.
     */
    public function findById(int $id): ?array
    {
        $sql = "
            SELECT
                r.id,
                r.activite_id,
                r.effectue_par,
                r.date_heure,
                r.transport_par_ravitaillement,
                r.note,
                r.created_at,

                u.nom AS utilisateur_nom,
                u.prenom AS utilisateur_prenom,

                a.journee_id,
                a.vendeur_id,
                a.sac_id,
                a.periode,
                a.statut AS activite_statut

            FROM ravitaillements r

            INNER JOIN users u
                ON u.id = r.effectue_par

            INNER JOIN activites a
                ON a.id = r.activite_id

            WHERE r.id = :id
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'id' => $id
        ]);

        $ravitaillement = $stmt->fetch();

        return $ravitaillement ?: null;
    }

    /**
     * Récupère tous les ravitaillements d'une activité.
     */
    public function getByActiviteId(int $activiteId): array
    {
        $sql = "
            SELECT
                r.id,
                r.activite_id,
                r.effectue_par,
                r.date_heure,
                r.transport_par_ravitaillement,
                r.note,
                r.created_at,

                u.nom AS utilisateur_nom,
                u.prenom AS utilisateur_prenom

            FROM ravitaillements r

            INNER JOIN users u
                ON u.id = r.effectue_par

            WHERE r.activite_id = :activite_id

            ORDER BY r.date_heure ASC, r.id ASC
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'activite_id' => $activiteId
        ]);

        return $stmt->fetchAll();
    }

    /**
     * Calcule le transport total d'une activité.
     */
    public function getTotalTransport(int $activiteId): float
    {
        $sql = "
            SELECT
                COALESCE(
                    SUM(transport_par_ravitaillement),
                    0
                ) AS total_transport
            FROM ravitaillements
            WHERE activite_id = :activite_id
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'activite_id' => $activiteId
        ]);

        return (float) $stmt->fetchColumn();
    }

    /**
     * Compte le nombre de ravitaillements d'une activité.
     */
    public function countByActiviteId(int $activiteId): int
    {
        $sql = "
            SELECT COUNT(*)
            FROM ravitaillements
            WHERE activite_id = :activite_id
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'activite_id' => $activiteId
        ]);

        return (int) $stmt->fetchColumn();
    }
}
