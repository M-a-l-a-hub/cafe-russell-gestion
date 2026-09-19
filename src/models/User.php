<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class User
{
    private PDO $db;

    public function __construct()
    {
        $this->db = getDatabaseConnection();
    }

    public function findById(int $id): ?array
    {
        $sql = "
            SELECT
                id,
                nom,
                prenom,
                telephone,
                email,
                password_hash,
                role,
                photo,
                actif,
                last_login_at,
                created_at,
                updated_at
            FROM users
            WHERE id = :id
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'id' => $id
        ]);

        $user = $stmt->fetch();

        return $user ?: null;
    }

    public function findByTelephone(string $telephone): ?array
    {
        $sql = "
            SELECT
                id,
                nom,
                prenom,
                telephone,
                email,
                password_hash,
                role,
                photo,
                actif,
                last_login_at,
                created_at,
                updated_at
            FROM users
            WHERE telephone = :telephone
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'telephone' => $telephone
        ]);

        $user = $stmt->fetch();

        return $user ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $sql = "
            SELECT
                id,
                nom,
                prenom,
                telephone,
                email,
                password_hash,
                role,
                photo,
                actif,
                last_login_at,
                created_at,
                updated_at
            FROM users
            WHERE email = :email
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'email' => $email
        ]);

        $user = $stmt->fetch();

        return $user ?: null;
    }

    public function getActiveUsers(): array
    {
        $sql = "
            SELECT
                id,
                nom,
                prenom,
                telephone,
                email,
                role,
                photo,
                actif,
                last_login_at,
                created_at,
                updated_at
            FROM users
            WHERE actif = TRUE
            ORDER BY nom ASC, prenom ASC
        ";

        $stmt = $this->db->query($sql);

        return $stmt->fetchAll();
    }

    public function getByRole(string $role): array
    {
        $sql = "
            SELECT
                id,
                nom,
                prenom,
                telephone,
                email,
                role,
                photo,
                actif,
                last_login_at,
                created_at,
                updated_at
            FROM users
            WHERE role = :role
              AND actif = TRUE
            ORDER BY nom ASC, prenom ASC
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'role' => $role
        ]);

        return $stmt->fetchAll();
    }
}
