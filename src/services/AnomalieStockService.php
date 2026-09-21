<?php

declare(strict_types=1);

require_once __DIR__ . '/ServiceBase.php';
require_once __DIR__ . '/AuditService.php';

class AnomalieStockService extends ServiceBase
{
    public function signaler(
        int $activiteId,
        int $produitId,
        int $quantiteAttendue,
        int $quantiteConstatee,
        int $userId,
        ?string $motif = null
    ): int {
        if ($quantiteAttendue < 0 || $quantiteConstatee < 0) {
            throw new InvalidArgumentException('Les quantités doivent être positives ou nulles.');
        }

        return $this->transaction(function () use (
            $activiteId,
            $produitId,
            $quantiteAttendue,
            $quantiteConstatee,
            $userId,
            $motif
        ): int {
            $this->verifierUtilisateur($userId);

            $activite = $this->fetchOne(
                "
                SELECT a.id, j.site_id
                FROM activites a
                INNER JOIN journees j ON j.id = a.journee_id
                WHERE a.id = :id
                ",
                ['id' => $activiteId]
            );

            if ($activite === null) {
                throw new RuntimeException('Activité introuvable.');
            }

            $this->verifierAccesSite($userId, (int) $activite['site_id']);

            if ($this->fetchOne('SELECT id FROM produits WHERE id = :id', ['id' => $produitId]) === null) {
                throw new RuntimeException('Produit introuvable.');
            }

            $stmt = $this->db->prepare(
                "
                INSERT INTO anomalies_stock (
                    activite_id,
                    produit_id,
                    quantite_attendue,
                    quantite_constatee,
                    ecart,
                    motif,
                    signalee_par
                )
                VALUES (:activite_id, :produit_id, :attendue, :constatee,
                        :ecart, :motif, :signalee_par)
                RETURNING id
                "
            );
            $stmt->execute([
                'activite_id' => $activiteId,
                'produit_id' => $produitId,
                'attendue' => $quantiteAttendue,
                'constatee' => $quantiteConstatee,
                'ecart' => $quantiteConstatee - $quantiteAttendue,
                'motif' => $motif,
                'signalee_par' => $userId,
            ]);

            $id = (int) $stmt->fetchColumn();
            AuditService::enregistrer(
                $this->db,
                $userId,
                'anomalie_stock_signalee',
                'anomalies_stock',
                $id,
                (int) $activite['site_id'],
                null,
                [
                    'activite_id' => $activiteId,
                    'produit_id' => $produitId,
                    'quantite_attendue' => $quantiteAttendue,
                    'quantite_constatee' => $quantiteConstatee,
                    'ecart' => $quantiteConstatee - $quantiteAttendue,
                ],
                $motif
            );

            return $id;
        });
    }

    public function demarrerTraitement(int $anomalieId, int $userId, ?string $note = null): void
    {
        $this->changerStatut($anomalieId, $userId, 'en_cours', $note);
    }

    public function resoudre(int $anomalieId, int $userId, string $noteResolution): void
    {
        $noteResolution = trim($noteResolution);

        if ($noteResolution === '') {
            throw new InvalidArgumentException('Une note de résolution est obligatoire.');
        }

        $this->transaction(function () use ($anomalieId, $userId, $noteResolution): void {
            $this->verifierUtilisateur($userId);
            $anomalie = $this->fetchOne(
                "
                SELECT an.id, an.statut, j.site_id
                FROM anomalies_stock an
                INNER JOIN activites a ON a.id = an.activite_id
                INNER JOIN journees j ON j.id = a.journee_id
                WHERE an.id = :id
                FOR UPDATE
                ",
                ['id' => $anomalieId]
            );

            if ($anomalie === null) {
                throw new RuntimeException('Anomalie de stock introuvable.');
            }

            $this->verifierAccesSite($userId, (int) $anomalie['site_id']);

            if ($anomalie['statut'] === 'resolue') {
                throw new RuntimeException('Cette anomalie est déjà résolue.');
            }

            $stmt = $this->db->prepare(
                "
                UPDATE anomalies_stock
                SET statut = 'resolue', resolue_par = :user_id,
                    date_resolution = NOW(), note_resolution = :note,
                    updated_at = NOW()
                WHERE id = :id
                "
            );
            $stmt->execute([
                'user_id' => $userId,
                'note' => $noteResolution,
                'id' => $anomalieId,
            ]);

            AuditService::enregistrer(
                $this->db,
                $userId,
                'anomalie_stock_resolue',
                'anomalies_stock',
                $anomalieId,
                null,
                ['statut' => $anomalie['statut']],
                ['statut' => 'resolue', 'note_resolution' => $noteResolution],
                $noteResolution
            );
        });
    }

    private function changerStatut(int $id, int $userId, string $statut, ?string $note): void
    {
        $this->transaction(function () use ($id, $userId, $statut, $note): void {
            $this->verifierUtilisateur($userId);
            $anomalie = $this->fetchOne(
                "
                SELECT an.id, an.statut, j.site_id
                FROM anomalies_stock an
                INNER JOIN activites a ON a.id = an.activite_id
                INNER JOIN journees j ON j.id = a.journee_id
                WHERE an.id = :id
                FOR UPDATE
                ",
                ['id' => $id]
            );

            if ($anomalie === null) {
                throw new RuntimeException('Anomalie de stock introuvable.');
            }

            $this->verifierAccesSite($userId, (int) $anomalie['site_id']);

            if ($anomalie['statut'] === 'resolue') {
                throw new RuntimeException('Une anomalie résolue ne peut plus être reprise.');
            }

            $stmt = $this->db->prepare(
                "UPDATE anomalies_stock SET statut = :statut, updated_at = NOW() WHERE id = :id"
            );
            $stmt->execute(['statut' => $statut, 'id' => $id]);

            AuditService::enregistrer(
                $this->db,
                $userId,
                'anomalie_stock_statut_modifie',
                'anomalies_stock',
                $id,
                null,
                ['statut' => $anomalie['statut']],
                ['statut' => $statut],
                $note
            );
        });
    }
}