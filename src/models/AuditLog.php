<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class AuditLog
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
                al.id,
                al.user_id,
                al.site_id,
                al.action,
                al.table_cible,
                al.enregistrement_id,
                al.anciennes_valeurs,
                al.nouvelles_valeurs,
                al.raison,
                al.date_heure,
                al.created_at,

                u.nom AS utilisateur_nom,
                u.prenom AS utilisateur_prenom,

                s.code AS site_code,
                s.nom AS site_nom

            FROM audit_logs al

            INNER JOIN users u
                ON u.id = al.user_id

            LEFT JOIN sites s
                ON s.id = al.site_id

            WHERE al.id = :id
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'id' => $id
        ]);

        $log = $stmt->fetch();

        return $log ?: null;
    }

    public function getByUserId(int $userId): array
    {
        $sql = "
            SELECT
                al.id,
                al.user_id,
                al.site_id,
                al.action,
                al.table_cible,
                al.enregistrement_id,
                al.anciennes_valeurs,
                al.nouvelles_valeurs,
                al.raison,
                al.date_heure,
                al.created_at,

                s.code AS site_code,
                s.nom AS site_nom

            FROM audit_logs al

            LEFT JOIN sites s
                ON s.id = al.site_id

            WHERE al.user_id = :user_id

            ORDER BY al.date_heure DESC, al.id DESC
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'user_id' => $userId
        ]);

        return $stmt->fetchAll();
    }

    public function getBySiteId(int $siteId): array
    {
        $sql = "
            SELECT
                al.id,
                al.user_id,
                al.site_id,
                al.action,
                al.table_cible,
                al.enregistrement_id,
                al.anciennes_valeurs,
                al.nouvelles_valeurs,
                al.raison,
                al.date_heure,
                al.created_at,

                u.nom AS utilisateur_nom,
                u.prenom AS utilisateur_prenom

            FROM audit_logs al

            INNER JOIN users u
                ON u.id = al.user_id

            WHERE al.site_id = :site_id

            ORDER BY al.date_heure DESC, al.id DESC
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'site_id' => $siteId
        ]);

        return $stmt->fetchAll();
    }

    public function getByTableAndRecord(
        string $tableCible,
        int $enregistrementId
    ): array {
        $sql = "
            SELECT
                al.id,
                al.user_id,
                al.site_id,
                al.action,
                al.table_cible,
                al.enregistrement_id,
                al.anciennes_valeurs,
                al.nouvelles_valeurs,
                al.raison,
                al.date_heure,
                al.created_at,

                u.nom AS utilisateur_nom,
                u.prenom AS utilisateur_prenom,

                s.code AS site_code,
                s.nom AS site_nom

            FROM audit_logs al

            INNER JOIN users u
                ON u.id = al.user_id

            LEFT JOIN sites s
                ON s.id = al.site_id

            WHERE al.table_cible = :table_cible
              AND al.enregistrement_id = :enregistrement_id

            ORDER BY al.date_heure DESC, al.id DESC
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'table_cible' => $tableCible,
            'enregistrement_id' => $enregistrementId
        ]);

        return $stmt->fetchAll();
    }

    public function getRecent(int $limit = 50): array
    {
        $limit = max(1, min($limit, 200));

        $sql = "
            SELECT
                al.id,
                al.user_id,
                al.site_id,
                al.action,
                al.table_cible,
                al.enregistrement_id,
                al.anciennes_valeurs,
                al.nouvelles_valeurs,
                al.raison,
                al.date_heure,
                al.created_at,

                u.nom AS utilisateur_nom,
                u.prenom AS utilisateur_prenom,

                s.code AS site_code,
                s.nom AS site_nom

            FROM audit_logs al

            INNER JOIN users u
                ON u.id = al.user_id

            LEFT JOIN sites s
                ON s.id = al.site_id

            ORDER BY al.date_heure DESC, al.id DESC

            LIMIT {$limit}
        ";

        $stmt = $this->db->query($sql);

        return $stmt->fetchAll();
    }
}
