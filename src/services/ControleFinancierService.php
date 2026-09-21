<?php

declare(strict_types=1);

require_once __DIR__ . '/ServiceBase.php';
require_once __DIR__ . '/AuditService.php';

class ControleFinancierService extends ServiceBase
{
    public function mettreEnResolution(int $controleId, int $userId, string $motif): void
    {
        $this->changerStatut($controleId, $userId, 'ecart_en_cours_de_resolution', $motif);
    }

    public function resoudre(int $controleId, int $userId, string $motif): void
    {
        $this->changerStatut($controleId, $userId, 'ecart_resolu', $motif);
    }

    private function changerStatut(
        int $controleId,
        int $userId,
        string $statut,
        string $motif
    ): void {
        $motif = trim($motif);

        if ($motif === '') {
            throw new InvalidArgumentException('Un motif est obligatoire pour modifier un écart.');
        }

        $this->transaction(function () use ($controleId, $userId, $statut, $motif): void {
            $this->verifierUtilisateur($userId);
            $controle = $this->fetchOne(
                "
                SELECT cf.id, cf.activite_id, cf.ecart, cf.statut, j.site_id
                FROM controles_financiers cf
                INNER JOIN activites a ON a.id = cf.activite_id
                INNER JOIN journees j ON j.id = a.journee_id
                WHERE cf.id = :id
                FOR UPDATE
                ",
                ['id' => $controleId]
            );

            if ($controle === null) {
                throw new RuntimeException('Contrôle financier introuvable.');
            }

            $this->verifierAccesSite($userId, (int) $controle['site_id']);

            if ((float) $controle['ecart'] == 0.0) {
                throw new RuntimeException('Un contrôle sans écart ne peut pas être mis en résolution.');
            }

            if ($controle['statut'] === 'aucun_ecart') {
                throw new RuntimeException('Le contrôle ne contient aucun écart à traiter.');
            }

            if ($statut === 'ecart_resolu' && $controle['statut'] !== 'ecart_en_cours_de_resolution') {
                throw new RuntimeException('Un écart doit être en cours de résolution avant d’être résolu.');
            }

            $stmt = $this->db->prepare(
                "
                UPDATE controles_financiers
                SET statut = :statut, motif_ecart = :motif, updated_at = NOW()
                WHERE id = :id
                "
            );
            $stmt->execute(['statut' => $statut, 'motif' => $motif, 'id' => $controleId]);

            AuditService::enregistrer(
                $this->db,
                $userId,
                'controle_financier_statut_modifie',
                'controles_financiers',
                $controleId,
                null,
                ['statut' => $controle['statut']],
                ['statut' => $statut, 'motif_ecart' => $motif],
                $motif
            );
        });
    }
}