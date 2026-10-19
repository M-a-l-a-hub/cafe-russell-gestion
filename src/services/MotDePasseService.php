<?php

declare(strict_types=1);

require_once __DIR__ . '/ServiceBase.php';
require_once __DIR__ . '/../http/HttpException.php';

/**
 * Modification du mot de passe de son propre compte.
 * Le mot de passe actuel est exigé et aucun secret n'est journalisé.
 */
final class MotDePasseService extends ServiceBase
{
    public function changer(int $userId, string $actuel, string $nouveau, string $confirmation): void
    {
        if (!hash_equals($nouveau, $confirmation)) {
            throw new HttpException(422, 'confirmation_invalide', 'La confirmation du nouveau mot de passe ne correspond pas.');
        }

        if (strlen($nouveau) < 12 || strlen($nouveau) > 200) {
            throw new HttpException(422, 'mot_de_passe_trop_court', 'Le nouveau mot de passe doit contenir au moins 12 caractères.');
        }

        if (hash_equals($actuel, $nouveau)) {
            throw new HttpException(422, 'mot_de_passe_identique', 'Choisis un mot de passe différent de l’ancien.');
        }

        $this->transaction(function () use ($userId, $actuel, $nouveau): void {
            $user = $this->fetchOne(
                'SELECT password_hash, actif FROM users WHERE id = :id FOR UPDATE',
                ['id' => $userId]
            );

            if ($user === null || !(bool) $user['actif']) {
                throw new HttpException(401, 'compte_indisponible', 'Ce compte n’est plus disponible.');
            }

            if (!password_verify($actuel, (string) $user['password_hash'])) {
                throw new HttpException(403, 'mot_de_passe_actuel_incorrect', 'Le mot de passe actuel est incorrect.');
            }

            $stmt = $this->db->prepare(
                'UPDATE users SET password_hash = :hash, updated_at = NOW() WHERE id = :id'
            );
            $stmt->execute([
                'hash' => password_hash($nouveau, PASSWORD_DEFAULT),
                'id' => $userId,
            ]);

            // Ne jamais enregistrer l'ancien ou le nouveau mot de passe dans l'audit.
            AuditService::enregistrer(
                $this->db,
                $userId,
                'mot_de_passe_modifie',
                'users',
                $userId,
                null,
                null,
                ['mot_de_passe_modifie' => true],
                'Modification personnelle du mot de passe'
            );
        });
    }
}
