<?php

declare(strict_types=1);

require_once __DIR__ . '/ServiceBase.php';
require_once __DIR__ . '/AuditService.php';
require_once __DIR__ . '/SoldeVendeurService.php';

class PaiementSoldeService extends ServiceBase
{
    public function enregistrer(
        int $vendeurId,
        int $siteId,
        int $userId,
        int|float|string $montant,
        ?string $note = null
    ): int {
        $montantTexte = $this->normaliserMontant($montant);

        return $this->transaction(function () use (
            $vendeurId,
            $siteId,
            $userId,
            $montantTexte,
            $note
        ): int {
            $this->verifierUtilisateur($userId);
            $this->verifierAccesSite($userId, $siteId);

            $stmt = $this->db->prepare('SELECT id FROM vendeurs WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $vendeurId]);

            if ($stmt->fetchColumn() === false) {
                throw new RuntimeException('Vendeur introuvable.');
            }

            $stmt = $this->db->prepare(
                "
                SELECT 1
                FROM activites a
                INNER JOIN journees j ON j.id = a.journee_id
                WHERE a.vendeur_id = :vendeur_id
                  AND j.site_id = :site_id
                LIMIT 1
                "
            );
            $stmt->execute(['vendeur_id' => $vendeurId, 'site_id' => $siteId]);

            if ($stmt->fetchColumn() === false) {
                throw new RuntimeException('Le vendeur n’a aucune activité sur ce site.');
            }

            (new SoldeVendeurService())->verifierDisponible(
                $vendeurId,
                $siteId,
                (float) $montantTexte
            );

            $stmt = $this->db->prepare(
                "
                INSERT INTO paiements_soldes (
                    vendeur_id, site_id, montant, effectue_par, note
                )
                VALUES (:vendeur_id, :site_id, :montant, :effectue_par, :note)
                RETURNING id
                "
            );
            $stmt->execute([
                'vendeur_id' => $vendeurId,
                'site_id' => $siteId,
                'montant' => $montantTexte,
                'effectue_par' => $userId,
                'note' => $note,
            ]);

            $id = (int) $stmt->fetchColumn();
            AuditService::enregistrer(
                $this->db,
                $userId,
                'paiement_solde_cree',
                'paiements_soldes',
                $id,
                $siteId,
                null,
                ['vendeur_id' => $vendeurId, 'site_id' => $siteId, 'montant' => $montantTexte],
                $note
            );

            return $id;
        });
    }

    private function normaliserMontant(int|float|string $valeur): string
    {
        $texte = str_replace([' ', ',', "\u{00A0}"], ['', '.', ''], (string) $valeur);

        if (!preg_match('/^\d+(\.\d{1,2})?$/', $texte)) {
            throw new InvalidArgumentException("Montant invalide : {$valeur}");
        }

        $centimes = (int) round((float) $texte * 100);

        if ($centimes <= 0) {
            throw new InvalidArgumentException('Le montant doit être strictement positif.');
        }

        return sprintf('%d.%02d', intdiv($centimes, 100), $centimes % 100);
    }
}