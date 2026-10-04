<?php

declare(strict_types=1);

require_once __DIR__ . '/ServiceBase.php';

final class ResponsableService extends ServiceBase
{
    public function creer(
        string $nom,
        ?string $prenom,
        string $telephone,
        ?string $email,
        string $motDePasse,
        int $adminId
    ): int {
        $admin = $this->verifierUtilisateur($adminId);
        if (($admin['role'] ?? '') !== 'super_admin') {
            throw new RuntimeException('Seul Russell peut créer un responsable.');
        }

        $nom = trim($nom);
        $prenom = $prenom !== null ? trim($prenom) : null;
        $telephone = (string) preg_replace('/[\s.\-()]/', '', trim($telephone));
        $email = $email !== null ? trim($email) : null;

        if ($nom === '' || mb_strlen($nom) > 100) {
            throw new InvalidArgumentException('Le nom est obligatoire et limité à 100 caractères.');
        }
        if ($telephone === '' || mb_strlen($telephone) > 30) {
            throw new InvalidArgumentException('Le téléphone est obligatoire et limité à 30 caractères.');
        }
        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('L’adresse e-mail est invalide.');
        }
        if (strlen($motDePasse) < 10 || strlen($motDePasse) > 200) {
            throw new InvalidArgumentException('Le mot de passe doit contenir au moins 10 caractères.');
        }

        if ($this->fetchOne('SELECT id FROM users WHERE telephone = :telephone', ['telephone' => $telephone]) !== null) {
            throw new RuntimeException('Un utilisateur possède déjà ce numéro de téléphone.');
        }

        return $this->transaction(function () use ($nom, $prenom, $telephone, $email, $motDePasse, $adminId): int {
            $stmt = $this->db->prepare(
                "INSERT INTO users (nom, prenom, telephone, email, password_hash, role) VALUES (:nom, :prenom, :telephone, :email, :password_hash, 'responsable') RETURNING id"
            );
            $stmt->execute([
                'nom' => $nom,
                'prenom' => $prenom,
                'telephone' => $telephone,
                'email' => $email,
                'password_hash' => password_hash($motDePasse, PASSWORD_DEFAULT),
            ]);

            $id = (int) $stmt->fetchColumn();
            AuditService::enregistrer($this->db, $adminId, 'responsable_cree', 'users', $id, null, null, ['nom' => $nom, 'prenom' => $prenom, 'telephone' => $telephone]);
            return $id;
        });
    }
}