<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class PaiementSolde
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
                ps.id,
                ps.vendeur_id,
                ps.montant,
                ps.effectue_par,
                ps.date,
                ps.note,
                ps.created_at,

                v.code AS vendeur_code,
                v.nom AS vendeur_nom,
                v.prenom AS vendeur_prenom,

                u.nom AS utilisateur_nom,
                u.prenom AS utilisateur_prenom

            FROM paiements_soldes ps

            INNER JOIN vendeurs v
                ON v.id = ps.vendeur_id

            INNER JOIN users u
                ON u.id = ps.effectue_par

            WHERE ps.id = :id
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'id' => $id
        ]);

        $paiement = $stmt->fetch();

        return $paiement ?: null;
    }

    public function getByVendeurId(int $vendeurId): array
    {
        $sql = "
            SELECT
                ps.id,
                ps.vendeur_id,
                ps.montant,
                ps.effectue_par,
                ps.date,
                ps.note,
                ps.created_at,

                u.nom AS utilisateur_nom,
                u.prenom AS utilisateur_prenom

            FROM paiements_soldes ps

            INNER JOIN users u
                ON u.id = ps.effectue_par

            WHERE ps.vendeur_id = :vendeur_id

            ORDER BY ps.date DESC, ps.id DESC
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
            FROM paiements_soldes
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
            FROM paiements_soldes
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
