<?php

declare(strict_types=1);

require_once __DIR__ . '/ServiceBase.php';
require_once __DIR__ . '/AuditService.php';

/**
 * Annulation d'une activité créée par erreur.
 *
 * Règle confirmée : une activité n'est annulable que si aucun
 * ravitaillement n'a été enregistré ; sinon elle se clôture normalement.
 * Une description (motif) est obligatoire.
 */
class AnnulationActiviteService extends ServiceBase
{
    public function annuler(int $activiteId, int $userId, string $motif): void
    {
        $motif = trim($motif);

        if ($motif === '') {
            throw new InvalidArgumentException(
                'Une description est obligatoire pour annuler une activité.'
            );
        }

        $this->transaction(
            function () use ($activiteId, $userId, $motif): void {
                $this->verifierUtilisateur($userId);

                $activite = $this->fetchOne(
                    "
                    SELECT
                        a.id,
                        a.statut,
                        a.sac_id,
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
                        'Cette activité n’est pas en cours '
                        . "(statut : {$activite['statut']}) : "
                        . 'elle ne peut pas être annulée.'
                    );
                }

                if ($activite['journee_statut'] !== 'ouverte') {
                    throw new RuntimeException(
                        'La journée est clôturée : aucune annulation possible.'
                    );
                }

                $ravitaillements = (int) $this->fetchOne(
                    "SELECT COUNT(*) AS nb FROM ravitaillements WHERE activite_id = :id",
                    ['id' => $activiteId]
                )['nb'];

                if ($ravitaillements > 0) {
                    throw new RuntimeException(
                        'Cette activité a déjà reçu un ravitaillement : '
                        . 'elle doit être clôturée normalement.'
                    );
                }

                $stmt = $this->db->prepare(
                    "
                    UPDATE activites
                    SET statut = 'annulee',
                        date_fin = NOW(),
                        updated_at = NOW()
                    WHERE id = :id
                      AND statut = 'en_cours'
                    "
                );

                $stmt->execute(['id' => $activiteId]);

                if ($stmt->rowCount() !== 1) {
                    throw new RuntimeException(
                        'L’activité n’a pas pu être annulée.'
                    );
                }

                $stmt = $this->db->prepare(
                    "
                    UPDATE sacs
                    SET statut = 'libre',
                        updated_at = NOW()
                    WHERE id = :id
                      AND statut = 'en_utilisation'
                    "
                );

                $stmt->execute(['id' => $activite['sac_id']]);

                AuditService::enregistrer(
                    $this->db,
                    $userId,
                    'activite_annulee',
                    'activites',
                    $activiteId,
                    (int) $activite['site_id'],
                    ['statut' => 'en_cours'],
                    ['statut' => 'annulee'],
                    $motif
                );
            }
        );
    }
}
