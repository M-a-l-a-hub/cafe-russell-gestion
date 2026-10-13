<?php

declare(strict_types=1);

require_once __DIR__ . '/HttpException.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/SessionBaseDeDonnees.php';

/**
 * Session de connexion : cookie HttpOnly + SameSite=Strict, jeton CSRF
 * exigé sur toutes les requêtes qui modifient des données.
 */
final class SessionHttp
{
    private const NOM = 'cafe_russell';

    /** Inactivité maximale avant déconnexion : 12 heures. */
    private const INACTIVITE_MAX = 43200;

    public static function demarrer(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', (string) self::INACTIVITE_MAX);

        // Sessions en base : le disque de Render est éphémère.
        session_set_save_handler(new SessionBaseDeDonnees(self::INACTIVITE_MAX), true);

        session_name(self::NOM);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $https,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);

        session_start();
    }

    /**
     * Ouvre la session d'un utilisateur authentifié.
     *
     * @return string Jeton CSRF à renvoyer au client.
     */
    public static function ouvrir(int $userId): string
    {
        session_regenerate_id(true);

        $jeton = bin2hex(random_bytes(32));

        $_SESSION = [
            'user_id' => $userId,
            'csrf' => $jeton,
            'derniere_activite' => time(),
        ];

        return $jeton;
    }

    /**
     * Utilisateur de la session, relu en base à chaque requête : un
     * compte désactivé ou un rôle modifié s'applique immédiatement.
     */
    public static function utilisateurConnecte(): ?array
    {
        $userId = $_SESSION['user_id'] ?? null;

        if (!is_int($userId)) {
            return null;
        }

        $derniere = (int) ($_SESSION['derniere_activite'] ?? 0);

        if (time() - $derniere > self::INACTIVITE_MAX) {
            self::fermer();

            return null;
        }

        $stmt = getDatabaseConnection()->prepare(
            '
            SELECT id, nom, prenom, telephone, email, role, actif
            FROM users
            WHERE id = :id
            '
        );
        $stmt->execute(['id' => $userId]);

        $user = $stmt->fetch();

        if ($user === false || !(bool) $user['actif']) {
            self::fermer();

            return null;
        }

        $_SESSION['derniere_activite'] = time();
        $user['id'] = (int) $user['id'];

        return $user;
    }

    public static function jetonCsrf(): string
    {
        $jeton = $_SESSION['csrf'] ?? '';

        return is_string($jeton) ? $jeton : '';
    }

    public static function verifierCsrf(?string $recu): void
    {
        $attendu = self::jetonCsrf();

        if ($attendu === '' || $recu === null || !hash_equals($attendu, $recu)) {
            throw new HttpException(
                403,
                'csrf_invalide',
                'Jeton de sécurité manquant ou invalide : recharge l’application.'
            );
        }
    }

    public static function fermer(): void
    {
        $_SESSION = [];

        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $cookie = session_get_cookie_params();

        setcookie(session_name(), '', [
            'expires' => time() - 3600,
            'path' => $cookie['path'],
            'secure' => $cookie['secure'],
            'httponly' => $cookie['httponly'],
            'samesite' => 'Strict',
        ]);

        session_destroy();
    }
}
