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

                $this->verifierAccesSite($userId, (int) $activite['site_id']);

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

                $lignes = $this->verifierLignesRetour($lignes, $recues);

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
    /**
     * Corrige le retour d'une activité : remplace entièrement les
     * quantités retournées (comme à la création, un produit absent de
     * la correction équivaut à un retour à zéro pour ce produit).
     * Recalcule la commission et l'écart si l'activité est déjà
     * clôturée.
     *
     * @param array<int, array{produit_id: int, quantite: int}> $lignes
     */
    public function corriger(
        int $retourId,
        array $lignes,
        int $userId,
        string $motif
    ): void {
        $lignes = $this->normaliserLignes($lignes, true);
        $motif = trim($motif);

        if ($motif === '') {
            throw new InvalidArgumentException('Le motif de la correction est obligatoire.');
        }

        $this->transaction(function () use ($retourId, $lignes, $userId, $motif): void {
            $this->verifierUtilisateur($userId);

            $retour = $this->fetchOne(
                "
                SELECT
                    ret.id, ret.activite_id,
                    j.site_id, j.statut AS journee_statut
                FROM retours ret
                INNER JOIN activites a ON a.id = ret.activite_id
                INNER JOIN journees j ON j.id = a.journee_id
                WHERE ret.id = :id
                FOR UPDATE OF ret
                ",
                ['id' => $retourId]
            );

            if ($retour === null) {
                throw new RuntimeException('Retour introuvable.');
            }

            if ($retour['journee_statut'] !== 'ouverte') {
                throw new RuntimeException(
                    'Cette journée est clôturée : demande à Russell de la '
                    . 'rouvrir avant de corriger ce retour.'
                );
            }

            $this->verifierAccesSite($userId, (int) $retour['site_id']);

            $activiteId = (int) $retour['activite_id'];

            $recues = [];
            $rows = $this->fetchAllRows(
                "
                SELECT rl.produit_id, SUM(rl.quantite) AS recue
                FROM ravitaillement_lignes rl
                INNER JOIN ravitaillements r ON r.id = rl.ravitaillement_id
                WHERE r.activite_id = :id
                GROUP BY rl.produit_id
                ",
                ['id' => $activiteId]
            );
            foreach ($rows as $row) {
                $recues[(int) $row['produit_id']] = (int) $row['recue'];
            }

            $lignes = $this->verifierLignesRetour($lignes, $recues);

            $anciennes = $this->fetchAllRows(
                'SELECT produit_id, quantite FROM retour_lignes WHERE retour_id = :id',
                ['id' => $retourId]
            );

            $stmt = $this->db->prepare('DELETE FROM retour_lignes WHERE retour_id = :id');
            $stmt->execute(['id' => $retourId]);

            $stmtLigne = $this->db->prepare(
                '
                INSERT INTO retour_lignes (retour_id, produit_id, quantite)
                VALUES (:retour_id, :produit_id, :quantite)
                '
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
                'retour_corrige',
                'retours',
                $retourId,
                (int) $retour['site_id'],
                ['lignes' => $anciennes],
                ['lignes' => $lignes],
                $motif
            );

            $this->recalculerFinancesActivite(
                $activiteId,
                (int) $retour['site_id'],
                $userId,
                $motif
            );
        });
    }

    /**
     * Contrôle les lignes d'un retour par rapport aux quantités reçues.
     *
     * - Une ligne à 0 pour un produit jamais ravitaillé est ignorée
     *   (un produit absent du retour vaut déjà « retourné à 0 »).
     * - Une quantité > 0 pour un produit jamais ravitaillé est refusée,
     *   avec le nom du produit.
     * - Une quantité supérieure à celle reçue est refusée.
     *
     * @param array<int, array{produit_id: int, quantite: int}> $lignes
     * @param array<int, int> $recues Quantités reçues, indexées par produit_id.
     *
     * @return array<int, array{produit_id: int, quantite: int}> Lignes retenues.
     */
    private function verifierLignesRetour(array $lignes, array $recues): array
    {
        $retenues = [];

        foreach ($lignes as $ligne) {
            $produitId = $ligne['produit_id'];
            $quantite = $ligne['quantite'];

            if (!isset($recues[$produitId])) {
                if ($quantite === 0) {
                    continue;
                }

                throw new RuntimeException(
                    'Le produit « ' . $this->nomProduit($produitId) . ' » '
                    . 'n’a jamais été ravitaillé pour cette activité : '
                    . 'impossible d’enregistrer un retour pour ce produit.'
                );
            }

            if ($quantite > $recues[$produitId]) {
                throw new RuntimeException(
                    'Retour supérieur à la quantité reçue pour « '
                    . $this->nomProduit($produitId) . ' » '
                    . "({$quantite} > {$recues[$produitId]})."
                );
            }

            $retenues[] = $ligne;
        }

        return $retenues;
    }

    private function nomProduit(int $produitId): string
    {
        $produit = $this->fetchOne(
            'SELECT nom FROM produits WHERE id = :id',
            ['id' => $produitId]
        );

        return $produit !== null ? (string) $produit['nom'] : "n° {$produitId}";
    }
}
