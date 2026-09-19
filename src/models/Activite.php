<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class Activite
{
    private PDO $db;

    public function __construct()
    {
        $this->db = getDatabaseConnection();
    }

    /**
     * Récupère une activité par son identifiant.
     */
    public function findById(int $id): ?array
    {
        $sql = "
            SELECT
                a.id,
                a.journee_id,
                a.vendeur_id,
                a.sac_id,
                a.periode,
                a.statut,
                a.date_debut,
                a.date_fin,
                a.created_at,
                a.updated_at,

                j.date_jour,
                j.statut AS journee_statut,

                v.code AS vendeur_code,
                v.nom AS vendeur_nom,
                v.prenom AS vendeur_prenom,

                s.numero AS sac_numero,
                s.categorie AS sac_categorie,
                s.statut AS sac_statut

            FROM activites a

            INNER JOIN journees j
                ON j.id = a.journee_id

            INNER JOIN vendeurs v
                ON v.id = a.vendeur_id

            INNER JOIN sacs s
                ON s.id = a.sac_id

            WHERE a.id = :id
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'id' => $id
        ]);

        $activite = $stmt->fetch();

        return $activite ?: null;
    }

    /**
     * Récupère toutes les activités d'une journée.
     */
    public function getByJourneeId(int $journeeId): array
    {
        $sql = "
            SELECT
                a.id,
                a.journee_id,
                a.vendeur_id,
                a.sac_id,
                a.periode,
                a.statut,
                a.date_debut,
                a.date_fin,
                a.created_at,
                a.updated_at,

                v.code AS vendeur_code,
                v.nom AS vendeur_nom,
                v.prenom AS vendeur_prenom,

                s.numero AS sac_numero,
                s.categorie AS sac_categorie

            FROM activites a

            INNER JOIN vendeurs v
                ON v.id = a.vendeur_id

            INNER JOIN sacs s
                ON s.id = a.sac_id

            WHERE a.journee_id = :journee_id

            ORDER BY
                a.date_debut ASC,
                v.nom ASC,
                v.prenom ASC
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'journee_id' => $journeeId
        ]);

        return $stmt->fetchAll();
    }

    /**
     * Récupère l'activité actuellement en cours d'un vendeur
     * pour une journée donnée.
     */
    public function findActiveByJourneeAndVendeur(
        int $journeeId,
        int $vendeurId
    ): ?array {
        $sql = "
            SELECT
                a.id,
                a.journee_id,
                a.vendeur_id,
                a.sac_id,
                a.periode,
                a.statut,
                a.date_debut,
                a.date_fin,
                a.created_at,
                a.updated_at,

                s.numero AS sac_numero,
                s.categorie AS sac_categorie

            FROM activites a

            INNER JOIN sacs s
                ON s.id = a.sac_id

            WHERE a.journee_id = :journee_id
              AND a.vendeur_id = :vendeur_id
              AND a.statut = 'en_cours'

            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'journee_id' => $journeeId,
            'vendeur_id' => $vendeurId
        ]);

        $activite = $stmt->fetch();

        return $activite ?: null;
    }

    /**
     * Récupère l'activité actuellement en cours d'un sac.
     */
    public function findActiveBySacId(int $sacId): ?array
    {
        $sql = "
            SELECT
                a.id,
                a.journee_id,
                a.vendeur_id,
                a.sac_id,
                a.periode,
                a.statut,
                a.date_debut,
                a.date_fin,

                v.code AS vendeur_code,
                v.nom AS vendeur_nom,
                v.prenom AS vendeur_prenom

            FROM activites a

            INNER JOIN vendeurs v
                ON v.id = a.vendeur_id

            WHERE a.sac_id = :sac_id
              AND a.statut = 'en_cours'

            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'sac_id' => $sacId
        ]);

        $activite = $stmt->fetch();

        return $activite ?: null;
    }
}
