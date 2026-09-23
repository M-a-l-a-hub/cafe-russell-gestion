<?php

declare(strict_types=1);

require_once __DIR__ . '/ServiceBase.php';
require_once __DIR__ . '/AuditService.php';

final class SiteService extends ServiceBase
{
    public function listerResponsables(int $userId): array
    {
        $this->verifierSuperAdmin($userId);

        return $this->fetchAllRows(
            "SELECT id, nom, prenom, telephone, email FROM users WHERE role = 'responsable' AND actif = TRUE ORDER BY nom, prenom"
        );
    }

    public function modifier(int $siteId, string $code, string $nom, ?string $adresse, ?string $description, int $userId): void
    {
        $this->verifierSuperAdmin($userId);
        $this->validerInformations($code, $nom, $adresse, $description);

        if ($this->fetchOne('SELECT id FROM sites WHERE id = :id AND actif = TRUE', ['id' => $siteId]) === null) {
            throw new RuntimeException('Site introuvable ou déjà désactivé.');
        }

        if ($this->fetchOne('SELECT id FROM sites WHERE code = :code AND id <> :id', ['code' => $code, 'id' => $siteId]) !== null) {
            throw new RuntimeException('Un site avec ce code existe déjà.');
        }

        $stmt = $this->db->prepare('UPDATE sites SET code = :code, nom = :nom, adresse = :adresse, description = :description, updated_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $siteId, 'code' => $code, 'nom' => $nom, 'adresse' => $adresse, 'description' => $description]);
        AuditService::enregistrer($this->db, $userId, 'site_modifie', 'sites', $siteId, null, null, ['code' => $code, 'nom' => $nom]);
    }

    public function desactiver(int $siteId, int $userId): void
    {
        $this->verifierSuperAdmin($userId);

        $this->transaction(function () use ($siteId, $userId): void {
            $stmt = $this->db->prepare('UPDATE sites SET actif = FALSE, updated_at = NOW() WHERE id = :id AND actif = TRUE');
            $stmt->execute(['id' => $siteId]);
            if ($stmt->rowCount() === 0) {
                throw new RuntimeException('Site introuvable ou déjà désactivé.');
            }

            $this->db->prepare('UPDATE site_responsables SET actif = FALSE, date_fin = CURRENT_DATE, updated_at = NOW() WHERE site_id = :site_id AND actif = TRUE')->execute(['site_id' => $siteId]);
            AuditService::enregistrer($this->db, $userId, 'site_desactive', 'sites', $siteId);
        });
    }

    public function affecterResponsable(int $siteId, int $responsableId, string $type, int $userId): int
    {
        $this->verifierSuperAdmin($userId);

        if (!in_array($type, ['principal', 'adjoint'], true)) {
            throw new InvalidArgumentException('Le type de responsable est invalide.');
        }
        if ($this->fetchOne('SELECT id FROM sites WHERE id = :id AND actif = TRUE', ['id' => $siteId]) === null) {
            throw new RuntimeException('Site introuvable ou désactivé.');
        }
        if ($this->fetchOne("SELECT id FROM users WHERE id = :id AND role = 'responsable' AND actif = TRUE", ['id' => $responsableId]) === null) {
            throw new RuntimeException('Responsable introuvable ou désactivé.');
        }

        return $this->transaction(function () use ($siteId, $responsableId, $type, $userId): int {
            if ($type === 'principal') {
                $this->db->prepare("UPDATE site_responsables SET actif = FALSE, date_fin = CURRENT_DATE, updated_at = NOW() WHERE site_id = :site_id AND type = 'principal' AND actif = TRUE")->execute(['site_id' => $siteId]);
            }

            $stmt = $this->db->prepare("INSERT INTO site_responsables (site_id, user_id, type, date_debut) VALUES (:site_id, :user_id, :type, CURRENT_DATE) RETURNING id");
            $stmt->execute(['site_id' => $siteId, 'user_id' => $responsableId, 'type' => $type]);
            $affectationId = (int) $stmt->fetchColumn();
            AuditService::enregistrer($this->db, $userId, 'responsable_affecte', 'site_responsables', $affectationId, null, null, ['site_id' => $siteId, 'responsable_id' => $responsableId, 'type' => $type]);
            return $affectationId;
        });
    }

    private function verifierSuperAdmin(int $userId): void
    {
        $user = $this->verifierUtilisateur($userId);
        if (($user['role'] ?? '') !== 'super_admin') {
            throw new RuntimeException('Seul Russell peut administrer les sites.');
        }
    }

    private function validerInformations(string &$code, string &$nom, ?string &$adresse, ?string &$description): void
    {
        $code = trim($code);
        $nom = trim($nom);
        $adresse = $adresse !== null ? trim($adresse) : null;
        $description = $description !== null ? trim($description) : null;

        if ($code === '' || mb_strlen($code) > 50) {
            throw new InvalidArgumentException('Le code du site est obligatoire et limité à 50 caractères.');
        }
        if ($nom === '' || mb_strlen($nom) > 150) {
            throw new InvalidArgumentException('Le nom du site est obligatoire et limité à 150 caractères.');
        }
        if ($adresse !== null && mb_strlen($adresse) > 500) {
            throw new InvalidArgumentException('L’adresse du site ne doit pas dépasser 500 caractères.');
        }
        if ($description !== null && mb_strlen($description) > 1000) {
            throw new InvalidArgumentException('La description du site ne doit pas dépasser 1000 caractères.');
        }
    }

    public function creer(
        string $code,
        string $nom,
        ?string $adresse,
        ?string $description,
        int $userId
    ): int {
        $this->verifierSuperAdmin($userId);
        $this->validerInformations($code, $nom, $adresse, $description);

        return $this->transaction(function () use ($code, $nom, $adresse, $description, $userId): int {
            $existant = $this->fetchOne(
                'SELECT id FROM sites WHERE code = :code',
                ['code' => $code]
            );

            if ($existant !== null) {
                throw new RuntimeException('Un site avec ce code existe déjà.');
            }

            $stmt = $this->db->prepare(
                'INSERT INTO sites (code, nom, adresse, description) VALUES (:code, :nom, :adresse, :description) RETURNING id'
            );
            $stmt->execute([
                'code' => $code,
                'nom' => $nom,
                'adresse' => $adresse,
                'description' => $description,
            ]);

            $siteId = (int) $stmt->fetchColumn();
            AuditService::enregistrer($this->db, $userId, 'site_cree', 'sites', $siteId, null, null, ['code' => $code, 'nom' => $nom, 'adresse' => $adresse]);

            return $siteId;
        });
    }
}
