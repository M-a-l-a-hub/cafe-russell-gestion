<?php

declare(strict_types=1);

require_once __DIR__ . '/ServiceBase.php';
require_once __DIR__ . '/AuditService.php';

class RavitaillementService extends ServiceBase
{
    /**
     * Enregistre un ravitaillement pour une activité en cours.
     *
     * Le transport, les prix et les coûts sont figés à la date de la
     * journée, comme dans les données de démonstration. Chaque
     * ravitaillement a son propre transport.
     *
     * @param array<int, array{produit_id: int, quantite: int}> $lignes
     *
     * @return int Identifiant du ravitaillement créé.
     */
    public function enregistrer(
        int $activiteId,
        int $userId,
        array $lignes,
        ?string $note = null
    ): int {
        $lignes = $this->preparerLignes($lignes);

        return $this->transaction(
            function () use ($activiteId, $userId, $lignes, $note): int {
                $this->verifierUtilisateur($userId);

                return $this->ajouterRavitaillement(
                    $activiteId,
                    $userId,
                    $lignes,
                    $note
                );
            }
        );
    }

    /**
     * Enregistre un ravitaillement pour un vendeur dans une journée.
     *
     * Si le vendeur n'a pas encore d'activité en cours dans la journée,
     * son premier ravitaillement crée automatiquement l'activité (sa
     * présence). Il faut alors indiquer le sac. La période est celle
     * donnée, sinon la période habituelle du vendeur : un vendeur peut
     * travailler en dehors de sa période habituelle.
     *
     * S'il a déjà une activité en cours, le ravitaillement lui est ajouté.
     * Un seul sac est actif pour un vendeur à un instant donné.
     *
     * @param array<int, array{produit_id: int, quantite: int}> $lignes
     *
     * @return array{activite_id: int, ravitaillement_id: int, activite_creee: bool}
     */
    public function enregistrerPourVendeur(
        int $journeeId,
        int $vendeurId,
        int $userId,
        array $lignes,
        ?int $sacId = null,
        ?string $periode = null,
        ?string $note = null
    ): array {
        $lignes = $this->preparerLignes($lignes);

        if ($periode !== null) {
            $this->verifierPeriodeActivite($periode);
        }

        return $this->transaction(
            function () use (
                $journeeId,
                $vendeurId,
                $userId,
                $lignes,
                $sacId,
                $periode,
                $note
            ): array {
                $this->verifierUtilisateur($userId);

                $journee = $this->fetchOne(
                    "
                    SELECT id, statut
                    FROM journees
                    WHERE id = :id
                    FOR UPDATE
                    ",
                    ['id' => $journeeId]
                );

                if ($journee === null) {
                    throw new RuntimeException('Journée introuvable.');
                }

                if ($journee['statut'] !== 'ouverte') {
                    throw new RuntimeException(
                        'La journée est clôturée : '
                        . 'aucun ravitaillement possible.'
                    );
                }

                $vendeur = $this->fetchOne(
                    "
                    SELECT id, code, actif, periode_habituelle
                    FROM vendeurs
                    WHERE id = :id
                    ",
                    ['id' => $vendeurId]
                );

                if ($vendeur === null) {
                    throw new RuntimeException('Vendeur introuvable.');
                }

                if (!(bool) $vendeur['actif']) {
                    throw new RuntimeException('Ce vendeur est désactivé.');
                }

                $activite = $this->fetchOne(
                    "
                    SELECT id, sac_id
                    FROM activites
                    WHERE journee_id = :journee_id
                      AND vendeur_id = :vendeur_id
                      AND statut = 'en_cours'
                    LIMIT 1
                    FOR UPDATE
                    ",
                    [
                        'journee_id' => $journeeId,
                        'vendeur_id' => $vendeurId,
                    ]
                );

                $activiteCreee = false;

                if ($activite === null) {
                    if ($sacId === null) {
                        throw new InvalidArgumentException(
                            'Le sac est obligatoire pour le premier '
                            . 'ravitaillement du vendeur dans la journée.'
                        );
                    }

                    $periodeActivite = $periode ?? $vendeur['periode_habituelle'];

                    if ($periodeActivite === null) {
                        throw new InvalidArgumentException(
                            'La période est obligatoire : ce vendeur '
                            . 'n’a pas de période habituelle.'
                        );
                    }

                    $activiteId = $this->ouvrirActivite(
                        $journeeId,
                        $vendeurId,
                        $sacId,
                        (string) $periodeActivite,
                        $userId
                    );

                    $activiteCreee = true;
                } else {
                    $activiteId = (int) $activite['id'];

                    if ($sacId !== null && (int) $activite['sac_id'] !== $sacId) {
                        throw new RuntimeException(
                            'Un seul sac est actif pour ce vendeur à un '
                            . 'instant donné : il utilise déjà le sac '
                            . $activite['sac_id'] . '.'
                        );
                    }
                }

                $ravitaillementId = $this->ajouterRavitaillement(
                    $activiteId,
                    $userId,
                    $lignes,
                    $note
                );

                return [
                    'activite_id' => $activiteId,
                    'ravitaillement_id' => $ravitaillementId,
                    'activite_creee' => $activiteCreee,
                ];
            }
        );
    }

    /**
     * @return array<int, array{produit_id: int, quantite: int}>
     */
    private function preparerLignes(array $lignes): array
    {
        if ($lignes === []) {
            throw new InvalidArgumentException(
                'Un ravitaillement doit contenir au moins un produit.'
            );
        }

        return $this->normaliserLignes($lignes, false);
    }

    /**
     * Ajoute un ravitaillement à une activité.
     * À appeler DANS une transaction (l'utilisateur est déjà vérifié).
     */
    private function ajouterRavitaillement(
        int $activiteId,
        int $userId,
        array $lignes,
        ?string $note
    ): int {
        // Verrouille l'activité : pas de ravitaillement
        // pendant une clôture simultanée.
        $activite = $this->fetchOne(
            "
            SELECT
                a.id,
                a.statut,
                j.site_id,
                j.date_jour,
                j.statut AS journee_statut,
                v.categorie_transport_id
            FROM activites a
            INNER JOIN journees j
                ON j.id = a.journee_id
            INNER JOIN vendeurs v
                ON v.id = a.vendeur_id
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
                . 'impossible de la ravitailler.'
            );
        }

        if ($activite['journee_statut'] !== 'ouverte') {
            throw new RuntimeException(
                'La journée est clôturée : '
                . 'aucun ravitaillement possible.'
            );
        }

        $date = (string) $activite['date_jour'];

        $transport = $this->fetchOne(
            "
            SELECT montant_par_ravitaillement
            FROM transport_prix
            WHERE categorie_transport_id = :categorie_id
              AND date_debut <= :date_debut
              AND (date_fin IS NULL OR date_fin >= :date_fin)
            LIMIT 1
            ",
            [
                'categorie_id' => $activite['categorie_transport_id'],
                'date_debut' => $date,
                'date_fin' => $date,
            ]
        );

        if ($transport === null) {
            throw new RuntimeException(
                "Aucun tarif de transport valide au {$date} "
                . "pour la catégorie de ce vendeur."
            );
        }

        // Résolution des produits, prix et coûts.
        $resolues = [];

        foreach ($lignes as $ligne) {
            $produit = $this->fetchOne(
                "
                SELECT id, code, nom, actif
                FROM produits
                WHERE id = :id
                ",
                ['id' => $ligne['produit_id']]
            );

            if ($produit === null) {
                throw new RuntimeException(
                    "Produit introuvable : {$ligne['produit_id']}."
                );
            }

            if (!(bool) $produit['actif']) {
                throw new RuntimeException(
                    "Le produit {$produit['nom']} est désactivé."
                );
            }

            $prix = $this->fetchOne(
                "
                SELECT prix
                FROM produit_prix
                WHERE produit_id = :produit_id
                  AND date_debut <= :date_debut
                  AND (date_fin IS NULL OR date_fin >= :date_fin)
                LIMIT 1
                ",
                [
                    'produit_id' => $produit['id'],
                    'date_debut' => $date,
                    'date_fin' => $date,
                ]
            );

            if ($prix === null) {
                throw new RuntimeException(
                    "Aucun prix de vente valide au {$date} "
                    . "pour {$produit['nom']}."
                );
            }

            $cout = $this->fetchOne(
                "
                SELECT cout_unitaire
                FROM produit_couts
                WHERE produit_id = :produit_id
                  AND date_debut <= :date_debut
                  AND (date_fin IS NULL OR date_fin >= :date_fin)
                LIMIT 1
                ",
                [
                    'produit_id' => $produit['id'],
                    'date_debut' => $date,
                    'date_fin' => $date,
                ]
            );

            if ($cout === null) {
                throw new RuntimeException(
                    "Aucun coût de consommables valide au {$date} "
                    . "pour {$produit['nom']}."
                );
            }

            $resolues[] = [
                'produit_id' => (int) $produit['id'],
                'quantite' => $ligne['quantite'],
                'prix_unitaire' => $prix['prix'],
                'cout_unitaire' => $cout['cout_unitaire'],
            ];
        }

        $stmt = $this->db->prepare(
            "
            INSERT INTO ravitaillements (
                activite_id,
                effectue_par,
                transport_par_ravitaillement,
                note
            )
            VALUES (
                :activite_id,
                :effectue_par,
                :transport,
                :note
            )
            RETURNING id
            "
        );

        $stmt->execute([
            'activite_id' => $activiteId,
            'effectue_par' => $userId,
            'transport' => $transport['montant_par_ravitaillement'],
            'note' => $note,
        ]);

        $ravitaillementId = (int) $stmt->fetchColumn();

        $stmtLigne = $this->db->prepare(
            "
            INSERT INTO ravitaillement_lignes (
                ravitaillement_id,
                produit_id,
                quantite,
                prix_unitaire,
                cout_unitaire
            )
            VALUES (
                :ravitaillement_id,
                :produit_id,
                :quantite,
                :prix_unitaire,
                :cout_unitaire
            )
            "
        );

        foreach ($resolues as $ligne) {
            $stmtLigne->execute([
                'ravitaillement_id' => $ravitaillementId,
                'produit_id' => $ligne['produit_id'],
                'quantite' => $ligne['quantite'],
                'prix_unitaire' => $ligne['prix_unitaire'],
                'cout_unitaire' => $ligne['cout_unitaire'],
            ]);
        }

        AuditService::enregistrer(
            $this->db,
            $userId,
            'ravitaillement_cree',
            'ravitaillements',
            $ravitaillementId,
            (int) $activite['site_id'],
            null,
            [
                'activite_id' => $activiteId,
                'transport' => $transport['montant_par_ravitaillement'],
                'lignes' => $resolues,
            ]
        );

        return $ravitaillementId;
    }
}
