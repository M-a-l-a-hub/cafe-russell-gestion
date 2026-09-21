<?php

declare(strict_types=1);

require_once __DIR__ . '/HttpException.php';
require_once __DIR__ . '/Requete.php';
require_once __DIR__ . '/Reponse.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/AccesRefuseException.php';
require_once __DIR__ . '/../services/AutorisationService.php';

/**
 * Socle des contrôleurs : identité de l'appelant et petites requêtes
 * de lecture. Les règles métier restent dans les services.
 */
abstract class ControleurBase
{
    protected function utilisateurId(Requete $req): int
    {
        if ($req->utilisateur === null) {
            throw new HttpException(401, 'non_connecte', 'Connexion requise.');
        }

        return (int) $req->utilisateur['id'];
    }

    protected function estSuperAdmin(Requete $req): bool
    {
        return ($req->utilisateur['role'] ?? '') === 'super_admin';
    }

    protected function verifierSite(Requete $req, int $siteId): void
    {
        (new AutorisationService())->verifierSite(
            $this->utilisateurId($req),
            $siteId
        );
    }

    /**
     * @param array<string, mixed> $params (jamais deux fois le même nom)
     *
     * @return array<int, array<string, mixed>>
     */
    protected function lignes(string $sql, array $params = []): array
    {
        $stmt = getDatabaseConnection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * @param array<string, mixed> $params
     */
    protected function uneLigne(string $sql, array $params = []): ?array
    {
        $stmt = getDatabaseConnection()->prepare($sql);
        $stmt->execute($params);

        $ligne = $stmt->fetch();

        return $ligne === false ? null : $ligne;
    }

    /**
     * Convertit en nombres les montants que PostgreSQL renvoie en texte.
     *
     * @param array<int, array<string, mixed>> $lignes
     * @param string[]                         $cles
     *
     * @return array<int, array<string, mixed>>
     */
    protected function versFlottants(array $lignes, array $cles): array
    {
        foreach ($lignes as &$ligne) {
            foreach ($cles as $cle) {
                if (isset($ligne[$cle])) {
                    $ligne[$cle] = (float) $ligne[$cle];
                }
            }
        }

        unset($ligne);

        return $lignes;
    }
}
