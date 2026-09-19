<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class Depense
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
                d.id,
                d.site_id,
                d.categorie_id,
                d.libelle,
                d.montant,
                d.date_depense,
                d.effectue_par,
                d.justificatif,
                d.note,
                d.created_at,
                d.updated_at,

                s.code AS site_code,
                s.nom AS site_nom,

                cd.code AS categorie_code,
                cd.nom AS categorie_nom,

                u.nom AS utilisateur_nom,
                u.prenom AS utilisateur_prenom

            FROM depenses d

            INNER JOIN categories_depenses cd
                ON cd.id = d.categorie_id

            INNER JOIN users u
                ON u.id = d.effectue_par

            LEFT JOIN sites s
                ON s.id = d.site_id

            WHERE d.id = :id
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'id' => $id
        ]);

        $depense = $stmt->fetch();

        return $depense ?: null;
    }

    public function getBySiteId(int $siteId): array
    {
        $sql = "
            SELECT
                d.id,
                d.site_id,
                d.categorie_id,
                d.libelle,
                d.montant,
                d.date_depense,
                d.effectue_par,
                d.justificatif,
                d.note,
                d.created_at,
                d.updated_at,

                cd.code AS categorie_code,
                cd.nom AS categorie_nom,

                u.nom AS utilisateur_nom,
                u.prenom AS utilisateur_prenom

            FROM depenses d

            INNER JOIN categories_depenses cd
                ON cd.id = d.categorie_id

            INNER JOIN users u
                ON u.id = d.effectue_par

            WHERE d.site_id = :site_id

            ORDER BY d.date_depense DESC, d.id DESC
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'site_id' => $siteId
        ]);

        return $stmt->fetchAll();
    }

    public function getGlobales(): array
    {
        $sql = "
            SELECT
                d.id,
                d.site_id,
                d.categorie_id,
                d.libelle,
                d.montant,
                d.date_depense,
                d.effectue_par,
                d.justificatif,
                d.note,
                d.created_at,
                d.updated_at,

                cd.code AS categorie_code,
                cd.nom AS categorie_nom,

                u.nom AS utilisateur_nom,
                u.prenom AS utilisateur_prenom

            FROM depenses d

            INNER JOIN categories_depenses cd
                ON cd.id = d.categorie_id

            INNER JOIN users u
                ON u.id = d.effectue_par

            WHERE d.site_id IS NULL

            ORDER BY d.date_depense DESC, d.id DESC
        ";

        $stmt = $this->db->query($sql);

        return $stmt->fetchAll();
    }

    public function getAll(): array
    {
        $sql = "
            SELECT
                d.id,
                d.site_id,
                d.categorie_id,
                d.libelle,
                d.montant,
                d.date_depense,
                d.effectue_par,
                d.justificatif,
                d.note,
                d.created_at,
                d.updated_at,

                s.code AS site_code,
                s.nom AS site_nom,

                cd.code AS categorie_code,
                cd.nom AS categorie_nom,

                u.nom AS utilisateur_nom,
                u.prenom AS utilisateur_prenom

            FROM depenses d

            INNER JOIN categories_depenses cd
                ON cd.id = d.categorie_id

            INNER JOIN users u
                ON u.id = d.effectue_par

            LEFT JOIN sites s
                ON s.id = d.site_id

            ORDER BY d.date_depense DESC, d.id DESC
        ";

        $stmt = $this->db->query($sql);

        return $stmt->fetchAll();
    }

    public function getTotalBySiteAndPeriod(
        int $siteId,
        string $dateDebut,
        string $dateFin
    ): float {
        $sql = "
            SELECT COALESCE(SUM(montant), 0)
            FROM depenses
            WHERE site_id = :site_id
              AND date_depense >= :date_debut
              AND date_depense < :date_fin
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'site_id' => $siteId,
            'date_debut' => $dateDebut,
            'date_fin' => $dateFin
        ]);

        return (float) $stmt->fetchColumn();
    }

    public function getTotalGlobalAndPeriod(
        string $dateDebut,
        string $dateFin
    ): float {
        $sql = "
            SELECT COALESCE(SUM(montant), 0)
            FROM depenses
            WHERE site_id IS NULL
              AND date_depense >= :date_debut
              AND date_depense < :date_fin
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'date_debut' => $dateDebut,
            'date_fin' => $dateFin
        ]);

        return (float) $stmt->fetchColumn();
    }
}
