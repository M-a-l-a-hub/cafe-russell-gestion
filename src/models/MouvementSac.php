<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class MouvementSac
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
                ms.id,
                ms.sac_id,
                ms.site_depart_id,
                ms.site_arrivee_id,
                ms.effectue_par,
                ms.date_heure,
                ms.motif,
                ms.note,
                ms.created_at,

                s.numero AS sac_numero,
                s.categorie AS sac_categorie,

                sd.code AS site_depart_code,
                sd.nom AS site_depart_nom,

                sa.code AS site_arrivee_code,
                sa.nom AS site_arrivee_nom,

                u.nom AS utilisateur_nom,
                u.prenom AS utilisateur_prenom

            FROM mouvements_sacs ms

            INNER JOIN sacs s
                ON s.id = ms.sac_id

            INNER JOIN sites sd
                ON sd.id = ms.site_depart_id

            INNER JOIN sites sa
                ON sa.id = ms.site_arrivee_id

            INNER JOIN users u
                ON u.id = ms.effectue_par

            WHERE ms.id = :id
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id' => $id
        ]);

        $mouvement = $stmt->fetch();

        return $mouvement ?: null;
    }

    public function getBySacId(int $sacId): array
    {
        $sql = "
            SELECT
                ms.id,
                ms.sac_id,
                ms.site_depart_id,
                ms.site_arrivee_id,
                ms.effectue_par,
                ms.date_heure,
                ms.motif,
                ms.note,
                ms.created_at,

                s.numero AS sac_numero,
                s.categorie AS sac_categorie,

                sd.code AS site_depart_code,
                sd.nom AS site_depart_nom,

                sa.code AS site_arrivee_code,
                sa.nom AS site_arrivee_nom,

                u.nom AS utilisateur_nom,
                u.prenom AS utilisateur_prenom

            FROM mouvements_sacs ms

            INNER JOIN sacs s
                ON s.id = ms.sac_id

            INNER JOIN sites sd
                ON sd.id = ms.site_depart_id

            INNER JOIN sites sa
                ON sa.id = ms.site_arrivee_id

            INNER JOIN users u
                ON u.id = ms.effectue_par

            WHERE ms.sac_id = :sac_id

            ORDER BY ms.date_heure DESC, ms.id DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'sac_id' => $sacId
        ]);

        return $stmt->fetchAll();
    }

    public function getBySiteDepartId(int $siteId): array
    {
        $sql = "
            SELECT
                ms.id,
                ms.sac_id,
                ms.site_depart_id,
                ms.site_arrivee_id,
                ms.effectue_par,
                ms.date_heure,
                ms.motif,
                ms.note,
                ms.created_at,

                s.numero AS sac_numero,
                s.categorie AS sac_categorie,

                sa.code AS site_arrivee_code,
                sa.nom AS site_arrivee_nom,

                u.nom AS utilisateur_nom,
                u.prenom AS utilisateur_prenom

            FROM mouvements_sacs ms

            INNER JOIN sacs s
                ON s.id = ms.sac_id

            INNER JOIN sites sa
                ON sa.id = ms.site_arrivee_id

            INNER JOIN users u
                ON u.id = ms.effectue_par

            WHERE ms.site_depart_id = :site_id

            ORDER BY ms.date_heure DESC, ms.id DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'site_id' => $siteId
        ]);

        return $stmt->fetchAll();
    }

    public function getBySiteArriveeId(int $siteId): array
    {
        $sql = "
            SELECT
                ms.id,
                ms.sac_id,
                ms.site_depart_id,
                ms.site_arrivee_id,
                ms.effectue_par,
                ms.date_heure,
                ms.motif,
                ms.note,
                ms.created_at,

                s.numero AS sac_numero,
                s.categorie AS sac_categorie,

                sd.code AS site_depart_code,
                sd.nom AS site_depart_nom,

                u.nom AS utilisateur_nom,
                u.prenom AS utilisateur_prenom

            FROM mouvements_sacs ms

            INNER JOIN sacs s
                ON s.id = ms.sac_id

            INNER JOIN sites sd
                ON sd.id = ms.site_depart_id

            INNER JOIN users u
                ON u.id = ms.effectue_par

            WHERE ms.site_arrivee_id = :site_id

            ORDER BY ms.date_heure DESC, ms.id DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'site_id' => $siteId
        ]);

        return $stmt->fetchAll();
    }

    public function getRecent(int $limit = 50): array
    {
        $limit = max(1, min($limit, 200));

        $sql = "
            SELECT
                ms.id,
                ms.sac_id,
                ms.site_depart_id,
                ms.site_arrivee_id,
                ms.effectue_par,
                ms.date_heure,
                ms.motif,
                ms.note,
                ms.created_at,

                s.numero AS sac_numero,
                s.categorie AS sac_categorie,

                sd.code AS site_depart_code,
                sd.nom AS site_depart_nom,

                sa.code AS site_arrivee_code,
                sa.nom AS site_arrivee_nom,

                u.nom AS utilisateur_nom,
                u.prenom AS utilisateur_prenom

            FROM mouvements_sacs ms

            INNER JOIN sacs s
                ON s.id = ms.sac_id

            INNER JOIN sites sd
                ON sd.id = ms.site_depart_id

            INNER JOIN sites sa
                ON sa.id = ms.site_arrivee_id

            INNER JOIN users u
                ON u.id = ms.effectue_par

            ORDER BY ms.date_heure DESC, ms.id DESC

            LIMIT {$limit}
        ";

        $stmt = $this->db->query($sql);

        return $stmt->fetchAll();
    }
}
