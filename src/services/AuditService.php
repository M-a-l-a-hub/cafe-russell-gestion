<?php

declare(strict_types=1);

/**
 * Écriture dans le journal d'audit (table audit_logs).
 *
 * À appeler avec la connexion de la transaction en cours,
 * pour que la ligne d'audit soit validée ou annulée
 * avec l'opération qu'elle décrit.
 */
class AuditService
{
    public static function enregistrer(
        PDO $db,
        int $userId,
        string $action,
        string $tableCible,
        int $enregistrementId,
        ?int $siteId = null,
        ?array $anciennesValeurs = null,
        ?array $nouvellesValeurs = null,
        ?string $raison = null
    ): void {
        $stmt = $db->prepare(
            "
            INSERT INTO audit_logs (
                user_id,
                site_id,
                action,
                table_cible,
                enregistrement_id,
                anciennes_valeurs,
                nouvelles_valeurs,
                raison
            )
            VALUES (
                :user_id,
                :site_id,
                :action,
                :table_cible,
                :enregistrement_id,
                CAST(:anciennes_valeurs AS JSONB),
                CAST(:nouvelles_valeurs AS JSONB),
                :raison
            )
            "
        );

        $stmt->execute([
            'user_id' => $userId,
            'site_id' => $siteId,
            'action' => $action,
            'table_cible' => $tableCible,
            'enregistrement_id' => $enregistrementId,
            'anciennes_valeurs' => self::versJson($anciennesValeurs),
            'nouvelles_valeurs' => self::versJson($nouvellesValeurs),
            'raison' => $raison,
        ]);
    }

    private static function versJson(?array $valeurs): ?string
    {
        if ($valeurs === null) {
            return null;
        }

        return json_encode(
            $valeurs,
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
    }
}
