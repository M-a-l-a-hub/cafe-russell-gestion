<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class Retrait
{
    private PDO $db;

    public function __construct()
    {
        $this->db = getDatabaseConnection();
    }

    public function findById(int $id): ?array
    {
        $sql = "
            SELECT
                r.id,
                r.vendeur_id,
                r.montant,
                r.effectue_par,
                r.date,
                r.motif,
                r.created_at,

                v.code AS vendeur_code,
                v.nom AS vendeur_nom,
                v.prenom AS vendeur_prenom,

                u.nom AS utilisateur_nom,
                u.prenom AS utilisateur_prenom

            FROM retraits r
            INNER JOIN vendeurs v
                ON v.id = r.vendeur_id
            INNER JOIN users u
                ON u.id = r.effectue_par
            WHERE r.id = :id
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'id' => $id
        ]);

        $retrait = $stmt->fetch();

        return $retrait ?: null;
    }

    public function getByVendeurId(int $vendeurId): array
    {
        $sql = "
            SELECT
                r.id,
                r.vendeur_id,
                r.montant,
                r.effectue_par,
                r.date,
                r.motif,
                r.created_at,

                u.nom AS utilisateur_nom,
                u.prenom AS utilisateur_prenom

            FROM retraits r
            INNER JOIN users u
                ON u.id = r.effectue_par
            WHERE r.vendeur_id = :vendeur_id
            ORDER BY r.date DESC, r.id DESC
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'vendeur_id' => $vendeurId
        ]);

        return $stmt->fetchAll();
    }

    public function getTotalByVendeurId(int $vendeurId): float
    {
        $sql = "
            SELECT COALESCE(SUM(montant), 0)
            FROM retraits
            WHERE vendeur_id = :vendeur_id
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'vendeur_id' => $vendeurId
        ]);

        return (float) $stmt->fetchColumn();
    }

    public function getTotalByVendeurAndPeriod(
        int $vendeurId,
        string $dateDebut,
        string $dateFin
    ): float {
        $sql = "
            SELECT COALESCE(SUM(montant), 0)
            FROM retraits
            WHERE vendeur_id = :vendeur_id
              AND date >= :date_debut
              AND date < :date_fin
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'vendeur_id' => $vendeurId,
            'date_debut' => $dateDebut,
            'date_fin' => $dateFin
        ]);

        return (float) $stmt->fetchColumn();
    }
}
