<?php

declare(strict_types=1);

require_once __DIR__ . '/ServiceBase.php';
require_once __DIR__ . '/AuditService.php';

class DemarrageActiviteService extends ServiceBase
{
    private const PERIODES = ['matin', 'soir', 'journee'];

    /**
     * Démarre l'activité d'un vendeur avec un sac, dans une journée ouverte.
     *
     * @return int Identifiant de l'activité créée.
     */
    public function demarrer(
        int $journeeId,
        int $vendeurId,
        int $sacId,
        string $periode,
        int $userId
    ): int {
        if (!in_array($periode, self::PERIODES, true)) {
            throw new InvalidArgumentException(
                "Période invalide : {$periode} "
                . "(valeurs possibles : matin, soir, journee)."
            );
        }

        return $this->transaction(
            function () use ($journeeId, $vendeurId, $sacId, $periode, $userId): int {
                $this->verifierUtilisateur($userId);

                // Verrouille la journée pendant la création.
                $journee = $this->fetchOne(
                    "
                    SELECT id, site_id, statut
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
                        'Cette journée est clôturée : '
                        . 'impossible d’y démarrer une activité.'
                    );
                }

                $vendeur = $this->fetchOne(
                    "
                    SELECT id, code, nom, prenom, actif
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

                // Verrouille le sac pour éviter une double attribution.
                $sac = $this->fetchOne(
                    "
                    SELECT id, site_id, numero, statut
                    FROM sacs
                    WHERE id = :id
                    FOR UPDATE
                    ",
                    ['id' => $sacId]
                );

                if ($sac === null) {
                    throw new RuntimeException('Sac introuvable.');
                }

                if ((int) $sac['site_id'] !== (int) $journee['site_id']) {
                    throw new RuntimeException(
                        'Ce sac n’appartient pas au site de la journée.'
                    );
                }

                if ($sac['statut'] !== 'libre') {
                    throw new RuntimeException(
                        "Le sac {$sac['numero']} n’est pas disponible "
                        . "(statut : {$sac['statut']})."
                    );
                }

                $activiteVendeur = $this->fetchOne(
                    "
                    SELECT id
                    FROM activites
                    WHERE journee_id = :journee_id
                      AND vendeur_id = :vendeur_id
                      AND statut = 'en_cours'
                    LIMIT 1
                    ",
                    [
                        'journee_id' => $journeeId,
                        'vendeur_id' => $vendeurId,
                    ]
                );

                if ($activiteVendeur !== null) {
                    throw new RuntimeException(
                        'Ce vendeur a déjà une activité en cours '
                        . 'dans cette journée.'
                    );
                }

                $stmt = $this->db->prepare(
                    "
                    INSERT INTO activites (
                        journee_id,
                        vendeur_id,
                        sac_id,
                        periode
                    )
                    VALUES (
                        :journee_id,
                        :vendeur_id,
                        :sac_id,
                        :periode
                    )
                    RETURNING id
                    "
                );

                $stmt->execute([
                    'journee_id' => $journeeId,
                    'vendeur_id' => $vendeurId,
                    'sac_id' => $sacId,
                    'periode' => $periode,
                ]);

                $activiteId = (int) $stmt->fetchColumn();

                $stmt = $this->db->prepare(
                    "
                    UPDATE sacs
                    SET statut = 'en_utilisation',
                        updated_at = NOW()
                    WHERE id = :id
                    "
                );

                $stmt->execute(['id' => $sacId]);

                AuditService::enregistrer(
                    $this->db,
                    $userId,
                    'activite_demarree',
                    'activites',
                    $activiteId,
                    (int) $journee['site_id'],
                    null,
                    [
                        'journee_id' => $journeeId,
                        'vendeur_id' => $vendeurId,
                        'sac_id' => $sacId,
                        'periode' => $periode,
                    ]
                );

                return $activiteId;
            }
        );
    }
}
