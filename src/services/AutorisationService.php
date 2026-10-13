<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/AccesRefuseException.php';

/** Vérifie les droits d'accès aux sites et aux opérations globales. */
class AutorisationService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? getDatabaseConnection();
    }

    public function verifierSite(int $userId, int $siteId): void
    {
        $user = $this->fetchUser($userId);

        if ($user['role'] === 'super_admin') {
            return;
        }

        $stmt = $this->db->prepare(
            "
            SELECT 1
            FROM site_responsables
            WHERE user_id = :user_id
              AND site_id = :site_id
              AND actif = TRUE
              AND date_debut <= CURRENT_DATE
              AND (date_fin IS NULL OR date_fin >= CURRENT_DATE)
            LIMIT 1
            "
        );
        $stmt->execute(['user_id' => $userId, 'site_id' => $siteId]);

        if ($stmt->fetchColumn() === false) {
            throw new AccesRefuseException(
                'Cet utilisateur n’est pas autorisé à agir sur ce site.'
            );
        }
    }

    public function verifierSuperAdministrateur(int $userId): void
    {
        $user = $this->fetchUser($userId);

        if ($user['role'] !== 'super_admin') {
            throw new AccesRefuseException(
                'Cette opération est réservée au super administrateur.'
            );
        }
    }

    private function fetchUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, role, actif FROM users WHERE id = :id'
        );
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch();

        if ($user === false) {
            throw new RuntimeException('Utilisateur introuvable.');
        }

        if (!(bool) $user['actif']) {
            throw new RuntimeException('Cet utilisateur est désactivé.');
        }

        return $user;
    }
}