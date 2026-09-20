<?php

declare(strict_types=1);

require_once __DIR__ . '/ServiceBase.php';
require_once __DIR__ . '/AuditService.php';

/**
 * Création des sacs et changement de leur disponibilité.
 *
 * Identité d'un sac : site + numéro. Le statut « en_utilisation » n'est
 * pas géré ici : il est posé et retiré par le démarrage et la clôture
 * d'une activité.
 *
 * Règle confirmée : un sac indisponible doit avoir un motif.
 */
class SacService extends ServiceBase
{
    private const CATEGORIES = ['standard', 'russell'];

    /**
     * @return int Identifiant du sac créé (statut « libre »).
     */
    public function creer(
        int $siteId,
        string $numero,
        string $categorie,
        int $userId
    ): int {
        $numero = trim($numero);

        if ($numero === '') {
            throw new InvalidArgumentException('Le numéro du sac est obligatoire.');
        }

        if (mb_strlen($numero) > 50) {
            throw new InvalidArgumentException('Le numéro du sac ne doit pas dépasser 50 caractères.');
        }

        if (!in_array($categorie, self::CATEGORIES, true)) {
            throw new InvalidArgumentException(
                "Catégorie invalide : {$categorie} (valeurs possibles : standard, russell)."
            );
        }

        return $this->transaction(
            function () use ($siteId, $numero, $categorie, $userId): int {
                $this->verifierUtilisateur($userId);

                $site = $this->fetchOne(
                    "SELECT id, nom FROM sites WHERE id = :id FOR UPDATE",
                    ['id' => $siteId]
                );

                if ($site === null) {
                    throw new RuntimeException('Site introuvable.');
                }

                $existant = $this->fetchOne(
                    "SELECT id FROM sacs WHERE site_id = :site_id AND numero = :numero",
                    ['site_id' => $siteId, 'numero' => $numero]
                );

                if ($existant !== null) {
                    throw new RuntimeException(
                        "Le site {$site['nom']} possède déjà un sac n° {$numero}."
                    );
                }

                $stmt = $this->db->prepare(
                    "
                    INSERT INTO sacs (site_id, numero, categorie)
                    VALUES (:site_id, :numero, :categorie)
                    RETURNING id
                    "
                );

                $stmt->execute([
                    'site_id' => $siteId,
                    'numero' => $numero,
                    'categorie' => $categorie,
                ]);

                $sacId = (int) $stmt->fetchColumn();

                AuditService::enregistrer(
                    $this->db,
                    $userId,
                    'sac_cree',
                    'sacs',
                    $sacId,
                    $siteId,
                    null,
                    ['numero' => $numero, 'categorie' => $categorie]
                );

                return $sacId;
            }
        );
    }

    /**
     * Rend un sac indisponible. Le motif est obligatoire.
     */
    public function marquerIndisponible(int $sacId, string $motif, int $userId): void
    {
        $motif = trim($motif);

        if ($motif === '') {
            throw new InvalidArgumentException(
                'Un motif est obligatoire pour rendre un sac indisponible.'
            );
        }

        $this->transaction(
            function () use ($sacId, $motif, $userId): void {
                $this->verifierUtilisateur($userId);

                $sac = $this->verrouillerSac($sacId);

                if ($sac['statut'] === 'indisponible') {
                    throw new RuntimeException(
                        "Le sac {$sac['numero']} est déjà indisponible."
                    );
                }

                $this->verifierPasEnActivite($sac);

                $stmt = $this->db->prepare(
                    "
                    UPDATE sacs
                    SET statut = 'indisponible',
                        motif_indisponibilite = :motif,
                        updated_at = NOW()
                    WHERE id = :id
                    "
                );

                $stmt->execute(['motif' => $motif, 'id' => $sacId]);

                AuditService::enregistrer(
                    $this->db,
                    $userId,
                    'sac_indisponible',
                    'sacs',
                    $sacId,
                    (int) $sac['site_id'],
                    ['statut' => $sac['statut'], 'motif_indisponibilite' => null],
                    ['statut' => 'indisponible', 'motif_indisponibilite' => $motif],
                    $motif
                );
            }
        );
    }

    /**
     * Remet un sac indisponible à « libre ». Le motif précédent reste
     * consultable dans l'audit.
     */
    public function rendreDisponible(int $sacId, int $userId, ?string $note = null): void
    {
        $note = $note !== null ? trim($note) : null;

        if ($note === '') {
            $note = null;
        }

        $this->transaction(
            function () use ($sacId, $userId, $note): void {
                $this->verifierUtilisateur($userId);

                $sac = $this->verrouillerSac($sacId);

                if ($sac['statut'] !== 'indisponible') {
                    throw new RuntimeException(
                        "Le sac {$sac['numero']} n’est pas indisponible "
                        . "(statut : {$sac['statut']})."
                    );
                }

                // La contrainte de la table impose un motif vide
                // pour un sac qui n'est plus indisponible.
                $stmt = $this->db->prepare(
                    "
                    UPDATE sacs
                    SET statut = 'libre',
                        motif_indisponibilite = NULL,
                        updated_at = NOW()
                    WHERE id = :id
                    "
                );

                $stmt->execute(['id' => $sacId]);

                AuditService::enregistrer(
                    $this->db,
                    $userId,
                    'sac_disponible',
                    'sacs',
                    $sacId,
                    (int) $sac['site_id'],
                    [
                        'statut' => 'indisponible',
                        'motif_indisponibilite' => $sac['motif_indisponibilite'],
                    ],
                    ['statut' => 'libre', 'motif_indisponibilite' => null],
                    $note
                );
            }
        );
    }

    private function verrouillerSac(int $sacId): array
    {
        $sac = $this->fetchOne(
            "
            SELECT id, site_id, numero, statut, motif_indisponibilite
            FROM sacs
            WHERE id = :id
            FOR UPDATE
            ",
            ['id' => $sacId]
        );

        if ($sac === null) {
            throw new RuntimeException('Sac introuvable.');
        }

        return $sac;
    }

    private function verifierPasEnActivite(array $sac): void
    {
        $activite = $this->fetchOne(
            "
            SELECT id
            FROM activites
            WHERE sac_id = :sac_id
              AND statut = 'en_cours'
            LIMIT 1
            ",
            ['sac_id' => $sac['id']]
        );

        if ($sac['statut'] === 'en_utilisation' || $activite !== null) {
            throw new RuntimeException(
                "Le sac {$sac['numero']} est en cours d’utilisation : "
                . 'attends la fin de l’activité.'
            );
        }
    }
}
