<?php

declare(strict_types=1);

require_once __DIR__ . '/ServiceBase.php';
require_once __DIR__ . '/../http/HttpException.php';

/**
 * Vérification téléphone + mot de passe, avec limitation des essais :
 *  - 5 échecs en 15 minutes pour un même numéro depuis une même adresse ;
 *  - 20 échecs en 15 minutes pour un même numéro, toutes adresses
 *    confondues.
 * Le message d'échec est identique que le numéro existe ou non.
 */
final class AuthService extends ServiceBase
{
    private const FENETRE_MINUTES = 15;
    private const MAX_ECHECS_PAR_ADRESSE = 5;
    private const MAX_ECHECS_PAR_NUMERO = 20;

    private static ?string $hashFactice = null;

    /**
     * @return array<string, mixed> Utilisateur (sans le hash du mot de passe).
     */
    public function connecter(string $telephone, string $motDePasse, string $ip): array
    {
        $telephone = self::normaliserTelephone($telephone);

        // La connexion est une requête autonome : nettoyer toute transaction
        // laissée ouverte/avortée sur cette connexion PDO persistante.
        if ($this->db->inTransaction()) {
            error_log('[AuthService] transaction résiduelle détectée avant authentification; rollback.');
            $this->db->rollBack();
        }

        $this->verifierQuota($telephone, $ip);

        $user = $this->fetchOne(
            '
            SELECT id, nom, prenom, telephone, email, password_hash, role, actif
            FROM users
            WHERE telephone = :telephone
            ',
            ['telephone' => $telephone]
        );

        // Même coût de calcul si le numéro n'existe pas.
        $hash = $user !== null
            ? (string) $user['password_hash']
            : self::hashFactice();

        $motDePasseValide = password_verify($motDePasse, $hash);

        if ($user === null || !$motDePasseValide || !(bool) $user['actif']) {
            $this->noterTentative($telephone, $ip, false);

            throw new HttpException(
                401,
                'identifiants_invalides',
                'Téléphone ou mot de passe incorrect.'
            );
        }

        $userId = (int) $user['id'];

        $this->transaction(
            function () use ($userId, $telephone, $ip, $motDePasse, $hash): void {
                if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
                    $stmt = $this->db->prepare(
                        '
                        UPDATE users
                        SET password_hash = :hash, updated_at = NOW()
                        WHERE id = :id
                        '
                    );
                    $stmt->execute([
                        'hash' => password_hash($motDePasse, PASSWORD_DEFAULT),
                        'id' => $userId,
                    ]);
                }

                $stmt = $this->db->prepare(
                    'UPDATE users SET last_login_at = NOW() WHERE id = :id'
                );
                $stmt->execute(['id' => $userId]);

                // Les échecs déjà comptés pour ce couple sont effacés.
                $stmt = $this->db->prepare(
                    '
                    DELETE FROM tentatives_connexion
                    WHERE telephone = :telephone
                      AND adresse_ip = :ip
                      AND reussie = FALSE
                    '
                );
                $stmt->execute(['telephone' => $telephone, 'ip' => $ip]);

                $this->noterTentative($telephone, $ip, true);

                $this->db->exec(
                    "
                    DELETE FROM tentatives_connexion
                    WHERE created_at < NOW() - INTERVAL '30 days'
                    "
                );
            }
        );

        unset($user['password_hash']);
        $user['id'] = $userId;

        return $user;
    }

    private static function normaliserTelephone(string $telephone): string
    {
        $telephone = (string) preg_replace('/[\s.\-()]/', '', trim($telephone));

        if ($telephone === '') {
            throw new InvalidArgumentException('Le numéro de téléphone est obligatoire.');
        }

        return $telephone;
    }

    private static function hashFactice(): string
    {
        self::$hashFactice ??= password_hash(
            'mot-de-passe-factice',
            PASSWORD_DEFAULT
        );

        return self::$hashFactice;
    }

    private function verifierQuota(string $telephone, string $ip): void
    {
        $fenetre = self::FENETRE_MINUTES;

        $parAdresse = $this->fetchOne(
            "
            SELECT COUNT(*) AS n
            FROM tentatives_connexion
            WHERE telephone = :telephone
              AND adresse_ip = :ip
              AND reussie = FALSE
              AND created_at > NOW() - INTERVAL '{$fenetre} minutes'
            ",
            ['telephone' => $telephone, 'ip' => $ip]
        );

        $parNumero = $this->fetchOne(
            "
            SELECT COUNT(*) AS n
            FROM tentatives_connexion
            WHERE telephone = :telephone
              AND reussie = FALSE
              AND created_at > NOW() - INTERVAL '{$fenetre} minutes'
            ",
            ['telephone' => $telephone]
        );

        if (
            (int) ($parAdresse['n'] ?? 0) >= self::MAX_ECHECS_PAR_ADRESSE
            || (int) ($parNumero['n'] ?? 0) >= self::MAX_ECHECS_PAR_NUMERO
        ) {
            throw new HttpException(
                429,
                'trop_de_tentatives',
                'Trop de tentatives échouées. Réessaie dans quelques minutes.'
            );
        }
    }

    private function noterTentative(string $telephone, string $ip, bool $reussie): void
    {
        $stmt = $this->db->prepare(
            '
            INSERT INTO tentatives_connexion (telephone, adresse_ip, reussie)
            VALUES (:telephone, :ip, CAST(:reussie AS BOOLEAN))
            '
        );

        $stmt->execute([
            'telephone' => $telephone,
            'ip' => $ip,
            'reussie' => $reussie ? 'true' : 'false',
        ]);
    }
}
