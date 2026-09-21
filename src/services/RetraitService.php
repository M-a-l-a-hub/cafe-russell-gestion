<?php

declare(strict_types=1);

require_once __DIR__ . '/ServiceBase.php';
require_once __DIR__ . '/AuditService.php';
require_once __DIR__ . '/SoldeVendeurService.php';

class RetraitService extends ServiceBase
{
    public function enregistrer(
        int $vendeurId,
        int $siteId,
        int $userId,
        int|float|string $montant,
        ?string $motif = null
    ): int {
        $montantTexte = $this->normaliserMontant($montant);

        return $this->transaction(function () use (
            $vendeurId,
            $siteId,
            $userId,
            $montantTexte,
            $motif
        ): int {
            $this->verifierUtilisateur($userId);
            $this->verifierAccesSite($userId, $siteId);
            $this->verrouillerVendeur($vendeurId);
            $this->verifierVendeurSurSite($vendeurId, $siteId);

            $solde = new SoldeVendeurService();
            $solde->verifierDisponible(
                $vendeurId,
                $siteId,
                (float) $montantTexte
            );

            $stmt = $this->db->prepare(
                "
                INSERT INTO retraits (
                    vendeur_id, site_id, montant, effectue_par, motif
                )
                VALUES (:vendeur_id, :site_id, :montant, :effectue_par, :motif)
                RETURNING id
                "
            );
            $stmt->execute([
                'vendeur_id' => $vendeurId,
                'site_id' => $siteId,
                'montant' => $montantTexte,
                'effectue_par' => $userId,
                'motif' => $motif,
            ]);

            $id = (int) $stmt->fetchColumn();
            AuditService::enregistrer(
                $this->db,
                $userId,
                'retrait_cree',
                'retraits',
                $id,
                $siteId,
                null,
                ['vendeur_id' => $vendeurId, 'site_id' => $siteId, 'montant' => $montantTexte],
                $motif
            );

            return $id;
        });
    }

    private function verrouillerVendeur(int $vendeurId): void
    {
        $stmt = $this->db->prepare('SELECT id FROM vendeurs WHERE id = :id FOR UPDATE');
        $stmt->execute(['id' => $vendeurId]);

        if ($stmt->fetchColumn() === false) {
            throw new RuntimeException('Vendeur introuvable.');
        }
    }

    private function verifierVendeurSurSite(int $vendeurId, int $siteId): void
    {
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
            throw new RuntimeException('Le vendeur n\'a aucune activite sur ce site.');
        }
    }

    private function normaliserMontant(int|float|string $valeur): string
    {
        $texte = str_replace([' ', ',', "\u{00A0}"], ['', '.', ''], (string) $valeur);

        if (!preg_match('/^\d+(\.\d{1,2})?$/', $texte)) {
            throw new InvalidArgumentException("Montant invalide : {$valeur}");
        }

        $centimes = (int) round((float) $texte * 100);

        if ($centimes <= 0) {
            throw new InvalidArgumentException('Le montant doit etre strictement positif.');
        }

        return sprintf('%d.%02d', intdiv($centimes, 100), $centimes % 100);
    }
}
