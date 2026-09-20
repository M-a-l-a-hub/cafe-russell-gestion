<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

/**
 * Socle commun des services d'écriture.
 *
 * Fournit la connexion, les transactions et quelques
 * vérifications réutilisées par tous les services.
 */
abstract class ServiceBase
{
    protected PDO $db;

    public function __construct()
    {
        $this->db = getDatabaseConnection();
    }

    /**
     * Exécute une requête et retourne la première ligne (ou null).
     */
    protected function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Exécute une requête et retourne toutes les lignes.
     */
    protected function fetchAllRows(string $sql, array $params = []): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Exécute $callback dans une transaction.
     * Commit si tout va bien, rollback et relance l'exception sinon.
     */
    protected function transaction(callable $callback): mixed
    {
        $this->db->beginTransaction();

        try {
            $resultat = $callback();

            $this->db->commit();

            return $resultat;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }

    /**
     * Vérifie que l'utilisateur existe et est actif.
     */
    protected function verifierUtilisateur(int $userId): array
    {
        $user = $this->fetchOne(
            "
            SELECT id, nom, prenom, role, actif
            FROM users
            WHERE id = :id
            ",
            ['id' => $userId]
        );

        if ($user === null) {
            throw new RuntimeException('Utilisateur introuvable.');
        }

        if (!(bool) $user['actif']) {
            throw new RuntimeException('Cet utilisateur est désactivé.');
        }

        return $user;
    }

    /**
     * Valide et normalise des lignes [produit_id, quantite].
     *
     * @return array<int, array{produit_id: int, quantite: int}>
     */
    protected function normaliserLignes(
        array $lignes,
        bool $autoriserZero
    ): array {
        $vus = [];
        $resultat = [];

        foreach (array_values($lignes) as $index => $ligne) {
            $numero = $index + 1;

            if (
                !is_array($ligne)
                || !isset($ligne['produit_id'], $ligne['quantite'])
            ) {
                throw new InvalidArgumentException(
                    "Ligne {$numero} invalide : "
                    . "produit_id et quantite sont obligatoires."
                );
            }

            $produitId = filter_var($ligne['produit_id'], FILTER_VALIDATE_INT);
            $quantite = filter_var($ligne['quantite'], FILTER_VALIDATE_INT);

            if ($produitId === false || $produitId <= 0) {
                throw new InvalidArgumentException(
                    "Ligne {$numero} : produit_id invalide."
                );
            }

            $minimum = $autoriserZero ? 0 : 1;

            if ($quantite === false || $quantite < $minimum) {
                throw new InvalidArgumentException(
                    "Ligne {$numero} : la quantité doit être un entier "
                    . ">= {$minimum}."
                );
            }

            if (isset($vus[$produitId])) {
                throw new InvalidArgumentException(
                    "Le produit {$produitId} apparaît plusieurs fois."
                );
            }

            $vus[$produitId] = true;

            $resultat[] = [
                'produit_id' => $produitId,
                'quantite' => $quantite,
            ];
        }

        return $resultat;
    }
}
