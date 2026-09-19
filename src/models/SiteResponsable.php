<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class SiteResponsable
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
                sr.id,
                sr.site_id,
                sr.user_id,
                sr.type,
                sr.date_debut,
                sr.date_fin,
                sr.actif,
                sr.created_at,
                sr.updated_at,

                s.code AS site_code,
                s.nom AS site_nom,

                u.nom AS utilisateur_nom,
                u.prenom AS utilisateur_prenom,
                u.telephone AS utilisateur_telephone,
                u.email AS utilisateur_email,
                u.role AS utilisateur_role

            FROM site_responsables sr

            INNER JOIN sites s
                ON s.id = sr.site_id

            INNER JOIN users u
                ON u.id = sr.user_id

            WHERE sr.id = :id
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'id' => $id
        ]);

        $responsable = $stmt->fetch();

        return $responsable ?: null;
    }

    public function getBySiteId(int $siteId): array
    {
        $sql = "
            SELECT
                sr.id,
                sr.site_id,
                sr.user_id,
                sr.type,
                sr.date_debut,
                sr.date_fin,
                sr.actif,
                sr.created_at,
                sr.updated_at,

                u.nom AS utilisateur_nom,
                u.prenom AS utilisateur_prenom,
                u.telephone AS utilisateur_telephone,
                u.email AS utilisateur_email,
                u.role AS utilisateur_role

            FROM site_responsables sr

            INNER JOIN users u
                ON u.id = sr.user_id

            WHERE sr.site_id = :site_id

            ORDER BY sr.actif DESC, sr.type ASC, sr.date_debut DESC
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'site_id' => $siteId
        ]);

        return $stmt->fetchAll();
    }

    public function getActiveBySiteId(int $siteId): array
    {
        $sql = "
            SELECT
                sr.id,
                sr.site_id,
                sr.user_id,
                sr.type,
                sr.date_debut,
                sr.date_fin,
                sr.actif,

                u.nom AS utilisateur_nom,
                u.prenom AS utilisateur_prenom,
                u.telephone AS utilisateur_telephone,
                u.email AS utilisateur_email,
                u.role AS utilisateur_role

            FROM site_responsables sr

            INNER JOIN users u
                ON u.id = sr.user_id

            WHERE sr.site_id = :site_id
              AND sr.actif = TRUE

            ORDER BY
                CASE
                    WHEN sr.type = 'principal' THEN 1
                    ELSE 2
                END,
                sr.date_debut ASC
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'site_id' => $siteId
        ]);

        return $stmt->fetchAll();
    }

    public function getPrincipalBySiteId(int $siteId): ?array
    {
        $sql = "
            SELECT
                sr.id,
                sr.site_id,
                sr.user_id,
                sr.type,
                sr.date_debut,
                sr.date_fin,
                sr.actif,

                u.nom AS utilisateur_nom,
                u.prenom AS utilisateur_prenom,
                u.telephone AS utilisateur_telephone,
                u.email AS utilisateur_email,
                u.role AS utilisateur_role

            FROM site_responsables sr

            INNER JOIN users u
                ON u.id = sr.user_id

            WHERE sr.site_id = :site_id
              AND sr.type = 'principal'
              AND sr.actif = TRUE

            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'site_id' => $siteId
        ]);

        $responsable = $stmt->fetch();

        return $responsable ?: null;
    }

    public function getByUserId(int $userId): array
    {
        $sql = "
            SELECT
                sr.id,
                sr.site_id,
                sr.user_id,
                sr.type,
                sr.date_debut,
                sr.date_fin,
                sr.actif,
                sr.created_at,
                sr.updated_at,

                s.code AS site_code,
                s.nom AS site_nom

            FROM site_responsables sr

            INNER JOIN sites s
                ON s.id = sr.site_id

            WHERE sr.user_id = :user_id

            ORDER BY sr.actif DESC, sr.date_debut DESC
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'user_id' => $userId
        ]);

        return $stmt->fetchAll();
    }
}
