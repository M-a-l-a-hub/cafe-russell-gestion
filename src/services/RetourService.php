<?php

declare(strict_types=1);

require_once __DIR__ . '/ServiceBase.php';
require_once __DIR__ . '/AuditService.php';

class RetourService extends ServiceBase
{
    /**
     * Enregistre le retour de stock d'une activité en cours.
     *
     * Un seul retour par activité. Les produits non listés
     * sont considérés comme retournés à 0. Une liste vide
     * signifie « rien n'est revenu » (retour à zéro explicite).
     *
     * @param array<int, array{produit_id: int, quantite: int}> $lignes
     *
     * @return int Identifiant du retour créé.
     */
    public function enregistrer(
        int $activiteId,
        int $userId,
        array $lignes,
        ?string $note = null
    ): int {
        $lignes = $this->normaliserLignes($lignes, true);

        return $this->transaction(
            function () use ($activiteId, $userId, $lignes, $note): int {
                $this->verifierUtilisateur($userId);

                $activite = $this->fetchOne(
                    "
                    SELECT
                        a.id,
                        a.statut,
                        j.site_id,
                        j.statut AS journee_statut
                    FROM activites a
                    INNER JOIN journees j
                        ON j.id = a.journee_id
                    WHERE a.id = :id
                    FOR UPDATE OF a
                    ",
                    ['id' => $activiteId]
                );

                if ($activite === null) {
                    throw new RuntimeException('Activité introuvable.');
                }

                if ($activite['statut'] !== 'en_cours') {
                    throw new RuntimeException(
                        'Cette activité n’est plus en cours : '
                        . 'impossible d’enregistrer un retour.'
                    );
                }

                if ($activite['journee_statut'] !== 'ouverte') {
                    throw new RuntimeException(
                        'La journée est clôturée : '
                        . 'aucun retour possible.'
                    );
                }

                $existant = $this->fetchOne(
                    "
                    SELECT id
                    FROM retours
                    WHERE activite_id = :id
                    ",
                    ['id' => $activiteId]
                );

                if ($existant !== null) {
                    throw new RuntimeException(
                        'Un retour existe déjà pour cette activité.'
                    );
                }

                // Quantités reçues par produit, tous ravitaillements confondus.
                $recues = [];

                $rows = $this->fetchAllRows(
                    "
                    SELECT
                        rl.produit_id,
                        SUM(rl.quantite) AS recue
                    FROM ravitaillement_lignes rl
                    INNER JOIN ravitaillements r
                        ON r.id = rl.ravitaillement_id
                    WHERE r.activite_id = :id
                    GROUP BY rl.produit_id
                    ",
                    ['id' => $activiteId]
                );

                foreach ($rows as $row) {
                    $recues[(int) $row['produit_id']] = (int) $row['recue'];
                }

                foreach ($lignes as $ligne) {
                    $produitId = $ligne['produit_id'];

                    if (!isset($recues[$produitId])) {
                        throw new RuntimeException(
                            "Le produit {$produitId} n’a jamais été "
                            . "ravitaillé pour cette activité."
                        );
                    }

                    if ($ligne['quantite'] > $recues[$produitId]) {
                        throw new RuntimeException(
                            "Retour supérieur à la quantité reçue pour le "
                            . "produit {$produitId} "
                            . "({$ligne['quantite']} > {$recues[$produitId]})."
                        );
                    }
                }

                $stmt = $this->db->prepare(
                    "
                    INSERT INTO retours (
                        activite_id,
                        effectue_par,
                        note
                    )
                    VALUES (
                        :activite_id,
                        :effectue_par,
                        :note
                    )
                    RETURNING id
                    "
                );

                $stmt->execute([
                    'activite_id' => $activiteId,
                    'effectue_par' => $userId,
                    'note' => $note,
                ]);

                $retourId = (int) $stmt->fetchColumn();

                $stmtLigne = $this->db->prepare(
                    "
                    INSERT INTO retour_lignes (
                        retour_id,
                        produit_id,
                        quantite
                    )
                    VALUES (
                        :retour_id,
                        :produit_id,
                        :quantite
                    )
                    "
                );

                foreach ($lignes as $ligne) {
                    $stmtLigne->execute([
                        'retour_id' => $retourId,
                        'produit_id' => $ligne['produit_id'],
                        'quantite' => $ligne['quantite'],
                    ]);
                }

                AuditService::enregistrer(
                    $this->db,
                    $userId,
                    'retour_cree',
                    'retours',
                    $retourId,
                    (int) $activite['site_id'],
                    null,
                    [
                        'activite_id' => $activiteId,
                        'lignes' => $lignes,
                    ]
                );

                return $retourId;
            }
        );
    }
}
