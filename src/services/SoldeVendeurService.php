<?php

declare(strict_types=1);

require_once __DIR__ . '/ServiceBase.php';

class SoldeVendeurService extends ServiceBase
{
    public function obtenir(int $vendeurId, int $siteId): array
    {
        $vendeur = $this->fetchOne(
            'SELECT id, code, nom, prenom FROM vendeurs WHERE id = :id',
            ['id' => $vendeurId]
        );

        if ($vendeur === null) {
            throw new RuntimeException('Vendeur introuvable.');
        }

        $commissions = $this->totalCommissions($vendeurId, $siteId);
        $retraits = $this->totalParVendeurEtSite('retraits', $vendeurId, $siteId);
        $paiements = $this->totalParVendeurEtSite('paiements_soldes', $vendeurId, $siteId);

        return [
            'vendeur' => $vendeur,
            'site_id' => $siteId,
            'commissions' => $commissions,
            'retraits' => $retraits,
            'paiements' => $paiements,
            'solde' => round($commissions - $retraits - $paiements, 2),
        ];
    }

    public function verifierDisponible(int $vendeurId, int $siteId, float $montant): void
    {
        $solde = $this->obtenir($vendeurId, $siteId)['solde'];

        if ($montant > $solde) {
            throw new RuntimeException(
                "Solde insuffisant : disponible {$solde} FC, demandé {$montant} FC."
            );
        }
    }

    private function totalCommissions(int $vendeurId, int $siteId): float
    {
        $stmt = $this->db->prepare(
            "
            SELECT COALESCE(SUM(c.montant), 0)
            FROM commissions c
            INNER JOIN activites a ON a.id = c.activite_id
            INNER JOIN journees j ON j.id = a.journee_id
            WHERE c.vendeur_id = :vendeur_id
              AND j.site_id = :site_id
            "
        );
        $stmt->execute(['vendeur_id' => $vendeurId, 'site_id' => $siteId]);

        return (float) $stmt->fetchColumn();
    }

    private function totalParVendeurEtSite(
        string $table,
        int $vendeurId,
        int $siteId
    ): float {
        $stmt = $this->db->prepare(
            "SELECT COALESCE(SUM(montant), 0)
             FROM {$table}
             WHERE vendeur_id = :vendeur_id
               AND site_id = :site_id"
        );
        $stmt->execute([
            'vendeur_id' => $vendeurId,
            'site_id' => $siteId,
        ]);

        return (float) $stmt->fetchColumn();
    }
}