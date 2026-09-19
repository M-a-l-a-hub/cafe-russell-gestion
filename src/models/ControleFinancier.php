<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class ControleFinancier
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
                cf.id,
                cf.activite_id,
                cf.ca_theorique,
                cf.montant_remis,
                cf.ecart,
                cf.statut,
                cf.motif_ecart,
                cf.controle_par,
                cf.date_heure,
                cf.note,
                cf.created_at,
                cf.updated_at,

                u.nom AS controleur_nom,
                u.prenom AS controleur_prenom

            FROM controles_financiers cf
            INNER JOIN users u
                ON u.id = cf.controle_par
            WHERE cf.id = :id
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'id' => $id
        ]);

        $controle = $stmt->fetch();

        return $controle ?: null;
    }

    public function findByActiviteId(int $activiteId): ?array
    {
        $sql = "
            SELECT
                cf.id,
                cf.activite_id,
                cf.ca_theorique,
                cf.montant_remis,
                cf.ecart,
                cf.statut,
                cf.motif_ecart,
                cf.controle_par,
                cf.date_heure,
                cf.note,
                cf.created_at,
                cf.updated_at,

                u.nom AS controleur_nom,
                u.prenom AS controleur_prenom

            FROM controles_financiers cf
            INNER JOIN users u
                ON u.id = cf.controle_par
            WHERE cf.activite_id = :activite_id
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'activite_id' => $activiteId
        ]);

        $controle = $stmt->fetch();

        return $controle ?: null;
    }

    public function getForJournee(int $journeeId): array
    {
        $sql = "
            SELECT
                cf.id,
                cf.activite_id,
                cf.ca_theorique,
                cf.montant_remis,
                cf.ecart,
                cf.statut,
                cf.motif_ecart,
                cf.controle_par,
                cf.date_heure,
                cf.note,
                cf.created_at,
                cf.updated_at,

                a.vendeur_id,
                a.sac_id,
                a.periode,

                v.code AS vendeur_code,
                v.nom AS vendeur_nom,
                v.prenom AS vendeur_prenom,

                s.numero AS sac_numero,

                u.nom AS controleur_nom,
                u.prenom AS controleur_prenom

            FROM controles_financiers cf

            INNER JOIN activites a
                ON a.id = cf.activite_id

            INNER JOIN vendeurs v
                ON v.id = a.vendeur_id

            INNER JOIN sacs s
                ON s.id = a.sac_id

            INNER JOIN users u
                ON u.id = cf.controle_par

            WHERE a.journee_id = :journee_id

            ORDER BY cf.date_heure ASC, cf.id ASC
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'journee_id' => $journeeId
        ]);

        return $stmt->fetchAll();
    }
}
