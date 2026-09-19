<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class Commission
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
                c.id,
                c.activite_id,
                c.vendeur_id,
                c.ca,
                c.taux,
                c.montant,
                c.date,
                c.created_at,

                v.code AS vendeur_code,
                v.nom AS vendeur_nom,
                v.prenom AS vendeur_prenom

            FROM commissions c
            INNER JOIN vendeurs v
                ON v.id = c.vendeur_id
            WHERE c.id = :id
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'id' => $id
        ]);

        $commission = $stmt->fetch();

        return $commission ?: null;
    }

    public function findByActiviteId(int $activiteId): ?array
    {
        $sql = "
            SELECT
                c.id,
                c.activite_id,
                c.vendeur_id,
                c.ca,
                c.taux,
                c.montant,
                c.date,
                c.created_at,

                v.code AS vendeur_code,
                v.nom AS vendeur_nom,
                v.prenom AS vendeur_prenom

            FROM commissions c
            INNER JOIN vendeurs v
                ON v.id = c.vendeur_id
            WHERE c.activite_id = :activite_id
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'activite_id' => $activiteId
        ]);

        $commission = $stmt->fetch();

        return $commission ?: null;
    }

    public function getByVendeurId(int $vendeurId): array
    {
        $sql = "
            SELECT
                c.id,
                c.activite_id,
                c.vendeur_id,
                c.ca,
                c.taux,
                c.montant,
                c.date,
                c.created_at
            FROM commissions c
            WHERE c.vendeur_id = :vendeur_id
            ORDER BY c.date DESC, c.id DESC
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'vendeur_id' => $vendeurId
        ]);

        return $stmt->fetchAll();
    }

    public function getByJourneeId(int $journeeId): array
    {
        $sql = "
            SELECT
                c.id,
                c.activite_id,
                c.vendeur_id,
                c.ca,
                c.taux,
                c.montant,
                c.date,
                c.created_at,

                v.code AS vendeur_code,
                v.nom AS vendeur_nom,
                v.prenom AS vendeur_prenom,

                a.sac_id,
                s.numero AS sac_numero,
                s.categorie AS sac_categorie

            FROM commissions c

            INNER JOIN activites a
                ON a.id = c.activite_id

            INNER JOIN vendeurs v
                ON v.id = c.vendeur_id

            INNER JOIN sacs s
                ON s.id = a.sac_id

            WHERE a.journee_id = :journee_id

            ORDER BY c.date ASC, c.id ASC
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'journee_id' => $journeeId
        ]);

        return $stmt->fetchAll();
    }

    public function getTotalByJourneeId(int $journeeId): float
    {
        $sql = "
            SELECT COALESCE(SUM(c.montant), 0)
            FROM commissions c
            INNER JOIN activites a
                ON a.id = c.activite_id
            WHERE a.journee_id = :journee_id
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'journee_id' => $journeeId
        ]);

        return (float) $stmt->fetchColumn();
    }
}
