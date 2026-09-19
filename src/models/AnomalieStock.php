<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class AnomalieStock
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
                a.id,
                a.activite_id,
                a.produit_id,
                a.quantite_attendue,
                a.quantite_constatee,
                a.ecart,
                a.motif,
                a.statut,
                a.signalee_par,
                a.resolue_par,
                a.date_signalement,
                a.date_resolution,
                a.note_resolution,
                a.created_at,
                a.updated_at,

                p.code AS produit_code,
                p.nom AS produit_nom,

                u1.nom AS signaleur_nom,
                u1.prenom AS signaleur_prenom,

                u2.nom AS resolueur_nom,
                u2.prenom AS resolueur_prenom

            FROM anomalies_stock a

            INNER JOIN produits p
                ON p.id = a.produit_id

            INNER JOIN users u1
                ON u1.id = a.signalee_par

            LEFT JOIN users u2
                ON u2.id = a.resolue_par

            WHERE a.id = :id
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'id' => $id
        ]);

        $anomalie = $stmt->fetch();

        return $anomalie ?: null;
    }

    public function getByActiviteId(int $activiteId): array
    {
        $sql = "
            SELECT
                a.id,
                a.activite_id,
                a.produit_id,
                a.quantite_attendue,
                a.quantite_constatee,
                a.ecart,
                a.motif,
                a.statut,
                a.signalee_par,
                a.resolue_par,
                a.date_signalement,
                a.date_resolution,
                a.note_resolution,
                a.created_at,
                a.updated_at,

                p.code AS produit_code,
                p.nom AS produit_nom,

                u1.nom AS signaleur_nom,
                u1.prenom AS signaleur_prenom,

                u2.nom AS resolueur_nom,
                u2.prenom AS resolueur_prenom

            FROM anomalies_stock a

            INNER JOIN produits p
                ON p.id = a.produit_id

            INNER JOIN users u1
                ON u1.id = a.signalee_par

            LEFT JOIN users u2
                ON u2.id = a.resolue_par

            WHERE a.activite_id = :activite_id

            ORDER BY a.date_signalement DESC, a.id DESC
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'activite_id' => $activiteId
        ]);

        return $stmt->fetchAll();
    }

    public function getByStatut(string $statut): array
    {
        $sql = "
            SELECT
                a.id,
                a.activite_id,
                a.produit_id,
                a.quantite_attendue,
                a.quantite_constatee,
                a.ecart,
                a.motif,
                a.statut,
                a.signalee_par,
                a.resolue_par,
                a.date_signalement,
                a.date_resolution,
                a.note_resolution,
                a.created_at,
                a.updated_at,

                p.code AS produit_code,
                p.nom AS produit_nom,

                u1.nom AS signaleur_nom,
                u1.prenom AS signaleur_prenom,

                u2.nom AS resolueur_nom,
                u2.prenom AS resolueur_prenom

            FROM anomalies_stock a

            INNER JOIN produits p
                ON p.id = a.produit_id

            INNER JOIN users u1
                ON u1.id = a.signalee_par

            LEFT JOIN users u2
                ON u2.id = a.resolue_par

            WHERE a.statut = :statut

            ORDER BY a.date_signalement DESC, a.id DESC
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'statut' => $statut
        ]);

        return $stmt->fetchAll();
    }

    public function getNonResolues(): array
    {
        $sql = "
            SELECT
                a.id,
                a.activite_id,
                a.produit_id,
                a.quantite_attendue,
                a.quantite_constatee,
                a.ecart,
                a.motif,
                a.statut,
                a.signalee_par,
                a.date_signalement,

                p.code AS produit_code,
                p.nom AS produit_nom,

                u.nom AS signaleur_nom,
                u.prenom AS signaleur_prenom

            FROM anomalies_stock a

            INNER JOIN produits p
                ON p.id = a.produit_id

            INNER JOIN users u
                ON u.id = a.signalee_par

            WHERE a.statut <> 'resolue'

            ORDER BY a.date_signalement ASC, a.id ASC
        ";

        $stmt = $this->db->query($sql);

        return $stmt->fetchAll();
    }
}
