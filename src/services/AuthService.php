<?php

declare(strict_types=1);

require_once __DIR__ . '/ServiceBase.php';
require_once __DIR__ . '/../http/HttpException.php';

/**
 * Vérification téléphone + mot de passe, avec limitation des essais :
 *  - 5 échecs en 15 minutes pour un même numéro depuis une même adresse ;
 *  - 20 échecs en 15 minutes pour un même numéro, toutes adresses
 *    confondues : au-delà, seules les adresses qui se sont déjà connectées
 *    à ce compte (30 derniers jours) peuvent encore essayer.
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

        $bloque = (int) ($parAdresse['n'] ?? 0) >= self::MAX_ECHECS_PAR_ADRESSE;

        // Quota global par numéro : il bloque les adresses inconnues, mais
        // pas celles qui ont déjà réussi à se connecter à ce compte. Sinon
        // quelqu'un qui connaît un numéro pourrait enfermer dehors son
        // propriétaire en multipliant les échecs. Le quota par adresse
        // ci-dessus reste appliqué à tout le monde.
        if (
            !$bloque
            && (int) ($parNumero['n'] ?? 0) >= self::MAX_ECHECS_PAR_NUMERO
            && !$this->adresseDejaConnue($telephone, $ip)
        ) {
            $bloque = true;
        }

        if ($bloque) {
            throw new HttpException(
                429,
                'trop_de_tentatives',
                'Trop de tentatives échouées. Réessaie dans quelques minutes.'
            );
        }
    }

    /** Une connexion réussie à ce compte depuis cette adresse, ces 30 derniers jours. */
    private function adresseDejaConnue(string $telephone, string $ip): bool
    {
        $ligne = $this->fetchOne(
            "
            SELECT 1 AS connue
            FROM tentatives_connexion
            WHERE telephone = :telephone
              AND adresse_ip = :ip
              AND reussie = TRUE
              AND created_at > NOW() - INTERVAL '30 days'
            LIMIT 1
            ",
            ['telephone' => $telephone, 'ip' => $ip]
        );

        return $ligne !== null;
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
