<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

/**
 * Stockage des sessions PHP dans PostgreSQL (table « sessions »).
 *
 * Remplace les fichiers de session : ils disparaissent à chaque
 * redéploiement sur Render. Compatible avec session.use_strict_mode
 * (validateId) et ne réécrit pas la ligne quand rien n'a changé
 * (updateTimestamp).
 */
final class SessionBaseDeDonnees implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface
{
    /** @param int $inactiviteMax Secondes d'inactivité avant expiration. */
    public function __construct(private int $inactiviteMax)
    {
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $stmt = getDatabaseConnection()->prepare(
            "
            SELECT donnees
            FROM sessions
            WHERE id = :id
              AND derniere_activite > NOW() - make_interval(secs => :duree)
            "
        );
        $stmt->execute(['id' => $id, 'duree' => $this->inactiviteMax]);

        $donnees = $stmt->fetchColumn();

        if (!is_string($donnees) || $donnees === '') {
            return '';
        }

        $decode = base64_decode($donnees, true);

        return $decode === false ? '' : $decode;
    }

    public function write(string $id, string $data): bool
    {
        $stmt = getDatabaseConnection()->prepare(
            "
            INSERT INTO sessions (id, donnees, derniere_activite)
            VALUES (:id, :donnees, NOW())
            ON CONFLICT (id) DO UPDATE
            SET donnees = EXCLUDED.donnees,
                derniere_activite = NOW()
            "
        );

        return $stmt->execute([
            'id' => $id,
            'donnees' => base64_encode($data),
        ]);
    }

    public function destroy(string $id): bool
    {
        $stmt = getDatabaseConnection()->prepare('DELETE FROM sessions WHERE id = :id');

        return $stmt->execute(['id' => $id]);
    }

    public function gc(int $max_lifetime): int|false
    {
        $stmt = getDatabaseConnection()->prepare(
            "
            DELETE FROM sessions
            WHERE derniere_activite < NOW() - make_interval(secs => :duree)
            "
        );
        $stmt->execute(['duree' => $this->inactiviteMax]);

        return $stmt->rowCount();
    }

    /** Mode strict : un identifiant inconnu ou expiré est refusé et remplacé. */
    public function validateId(string $id): bool
    {
        $stmt = getDatabaseConnection()->prepare(
            "
            SELECT 1
            FROM sessions
            WHERE id = :id
              AND derniere_activite > NOW() - make_interval(secs => :duree)
            "
        );
        $stmt->execute(['id' => $id, 'duree' => $this->inactiviteMax]);

        return $stmt->fetchColumn() !== false;
    }

    /** Session inchangée : on ne rafraîchit que l'horodatage. */
    public function updateTimestamp(string $id, string $data): bool
    {
        $stmt = getDatabaseConnection()->prepare(
            'UPDATE sessions SET derniere_activite = NOW() WHERE id = :id'
        );

        return $stmt->execute(['id' => $id]);
    }
}
