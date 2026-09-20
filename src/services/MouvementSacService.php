<?php

declare(strict_types=1);

require_once __DIR__ . '/ServiceBase.php';
require_once __DIR__ . '/AuditService.php';

class MouvementSacService extends ServiceBase
{
    /**
     * Transfère un sac vers un autre site.
     *
     * Le rattachement du sac change (sacs.site_id) et le mouvement est
     * conservé dans mouvements_sacs. Le site de départ est toujours lu
     * dans la base, jamais fourni par l'appelant.
     *
     * Contrôles techniques (pas des règles métier supplémentaires) :
     * - le numéro ne doit pas déjà exister sur le site d'arrivée
     *   (identification = site + numéro) ;
     * - un sac en cours d'utilisation ne peut pas être déplacé.
     *
     * @return int Identifiant du mouvement créé.
     */
    public function deplacer(
        int $sacId,
        int $siteArriveeId,
        int $userId,
        string $motif,
        ?string $note = null
    ): int {
        $motif = trim($motif);

        if ($motif === '') {
            throw new InvalidArgumentException(
                'Un motif est obligatoire pour déplacer un sac.'
            );
        }

        if (mb_strlen($motif) > 255) {
            throw new InvalidArgumentException(
                'Le motif ne doit pas dépasser 255 caractères '
                . '(utilise la note pour le détail).'
            );
        }

        return $this->transaction(
            function () use ($sacId, $siteArriveeId, $userId, $motif, $note): int {
                $this->verifierUtilisateur($userId);

                $sac = $this->fetchOne(
                    "
                    SELECT id, site_id, numero, categorie, statut
                    FROM sacs
                    WHERE id = :id
                    FOR UPDATE
                    ",
                    ['id' => $sacId]
                );

                if ($sac === null) {
                    throw new RuntimeException('Sac introuvable.');
                }

                $siteDepartId = (int) $sac['site_id'];

                if ($siteDepartId === $siteArriveeId) {
                    throw new RuntimeException(
                        'Ce sac est déjà rattaché à ce site.'
                    );
                }

                $activiteEnCours = $this->fetchOne(
                    "
                    SELECT id
                    FROM activites
                    WHERE sac_id = :sac_id
                      AND statut = 'en_cours'
                    LIMIT 1
                    ",
                    ['sac_id' => $sacId]
                );

                if (
                    $sac['statut'] === 'en_utilisation'
                    || $activiteEnCours !== null
                ) {
                    throw new RuntimeException(
                        "Le sac {$sac['numero']} est en cours d’utilisation : "
                        . 'impossible de le déplacer.'
                    );
                }

                $siteArrivee = $this->fetchOne(
                    "
                    SELECT id, code, nom, actif
                    FROM sites
                    WHERE id = :id
                    ",
                    ['id' => $siteArriveeId]
                );

                if ($siteArrivee === null) {
                    throw new RuntimeException('Site d’arrivée introuvable.');
                }

                if (!(bool) $siteArrivee['actif']) {
                    throw new RuntimeException(
                        "Le site {$siteArrivee['nom']} est désactivé."
                    );
                }

                $doublon = $this->fetchOne(
                    "
                    SELECT id
                    FROM sacs
                    WHERE site_id = :site_id
                      AND numero = :numero
                    LIMIT 1
                    ",
                    [
                        'site_id' => $siteArriveeId,
                        'numero' => $sac['numero'],
                    ]
                );

                if ($doublon !== null) {
                    throw new RuntimeException(
                        "Le site {$siteArrivee['nom']} possède déjà "
                        . "un sac n° {$sac['numero']}."
                    );
                }

                $stmt = $this->db->prepare(
                    "
                    UPDATE sacs
                    SET site_id = :site_id,
                        updated_at = NOW()
                    WHERE id = :id
                    "
                );

                $stmt->execute([
                    'site_id' => $siteArriveeId,
                    'id' => $sacId,
                ]);

                $stmt = $this->db->prepare(
                    "
                    INSERT INTO mouvements_sacs (
                        sac_id,
                        site_depart_id,
                        site_arrivee_id,
                        effectue_par,
                        motif,
                        note
                    )
                    VALUES (
                        :sac_id,
                        :site_depart_id,
                        :site_arrivee_id,
                        :effectue_par,
                        :motif,
                        :note
                    )
                    RETURNING id
                    "
                );

                $stmt->execute([
                    'sac_id' => $sacId,
                    'site_depart_id' => $siteDepartId,
                    'site_arrivee_id' => $siteArriveeId,
                    'effectue_par' => $userId,
                    'motif' => $motif,
                    'note' => $note,
                ]);

                $mouvementId = (int) $stmt->fetchColumn();

                AuditService::enregistrer(
                    $this->db,
                    $userId,
                    'sac_deplace',
                    'mouvements_sacs',
                    $mouvementId,
                    $siteArriveeId,
                    ['site_id' => $siteDepartId],
                    [
                        'sac_id' => $sacId,
                        'numero' => $sac['numero'],
                        'site_id' => $siteArriveeId,
                    ],
                    $motif
                );

                return $mouvementId;
            }
        );
    }
}
