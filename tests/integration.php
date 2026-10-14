<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/http/HttpException.php';
require_once __DIR__ . '/../src/services/SiteService.php';
require_once __DIR__ . '/../src/services/AuthService.php';
require_once __DIR__ . '/../src/services/AutorisationService.php';

function check(bool $ok, string $message): void {
    if (!$ok) { throw new RuntimeException("ÉCHEC: {$message}"); }
    echo "OK: {$message}\n";
}
function expectException(callable $fn, string $class, string $message): void {
    try { $fn(); } catch (Throwable $e) {
        check($e instanceof $class, $message . ' (exception ' . get_class($e) . ')');
        return;
    }
    throw new RuntimeException("ÉCHEC: exception attendue — {$message}");
}

$db = getDatabaseConnection();

/*
 * Schéma isolé créé dans le PostgreSQL éphémère de GitHub Actions.
 * Aucune connexion à la base Neon ou à la production n'est utilisée.
 */
$db->exec(<<<'SQL'
CREATE TYPE user_role AS ENUM ('super_admin', 'responsable');
CREATE TYPE responsable_type AS ENUM ('principal', 'adjoint');
CREATE TABLE users (
 id BIGSERIAL PRIMARY KEY, nom VARCHAR(100) NOT NULL, prenom VARCHAR(100),
 telephone VARCHAR(30) NOT NULL UNIQUE, email VARCHAR(255) UNIQUE,
 password_hash TEXT NOT NULL, role user_role NOT NULL, photo TEXT,
 actif BOOLEAN NOT NULL DEFAULT TRUE, last_login_at TIMESTAMPTZ,
 created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(), updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE TABLE sites (
 id BIGSERIAL PRIMARY KEY, code VARCHAR(50) NOT NULL UNIQUE, nom VARCHAR(150) NOT NULL,
 adresse TEXT, description TEXT, actif BOOLEAN NOT NULL DEFAULT TRUE,
 created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(), updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE TABLE site_responsables (
 id BIGSERIAL PRIMARY KEY, site_id BIGINT NOT NULL REFERENCES sites(id),
 user_id BIGINT NOT NULL REFERENCES users(id), type responsable_type NOT NULL,
 date_debut DATE NOT NULL, date_fin DATE, actif BOOLEAN NOT NULL DEFAULT TRUE,
 created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(), updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
 CHECK (date_fin IS NULL OR date_fin >= date_debut)
);
CREATE UNIQUE INDEX uq_site_responsable_principal_actif ON site_responsables(site_id)
 WHERE type='principal' AND actif=TRUE;
CREATE UNIQUE INDEX uq_site_responsable_user_site_actif ON site_responsables(site_id,user_id)
 WHERE actif=TRUE;
CREATE TABLE tentatives_connexion (
 id BIGSERIAL PRIMARY KEY, telephone VARCHAR(30) NOT NULL, adresse_ip VARCHAR(45) NOT NULL,
 reussie BOOLEAN NOT NULL DEFAULT FALSE, created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE TABLE audit_logs (
 id BIGSERIAL PRIMARY KEY, user_id BIGINT NOT NULL REFERENCES users(id),
 site_id BIGINT, action VARCHAR(100) NOT NULL, table_cible VARCHAR(100) NOT NULL,
 enregistrement_id BIGINT NOT NULL, anciennes_valeurs JSONB, nouvelles_valeurs JSONB,
 raison TEXT, created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
SQL);

$hash = password_hash('Test-Pass-2026!', PASSWORD_DEFAULT);
$insert = $db->prepare("INSERT INTO users(nom,prenom,telephone,email,password_hash,role,actif) VALUES(:n,:p,:t,:e,:h,CAST(:r AS user_role),:a) RETURNING id");
$makeUser = function(string $n,string $p,string $t,string $role,bool $active=true) use($insert,$hash): int {
 $insert->execute(['n'=>$n,'p'=>$p,'t'=>$t,'e'=>$t.'@example.invalid','h'=>$hash,'r'=>$role,'a'=>$active?'true':'false']);
 return (int)$insert->fetchColumn();
};
$admin = $makeUser('Admin','Test','+999100001','super_admin');
$alpha = $makeUser('Responsable','Alpha','+999100002','responsable');
$beta = $makeUser('Responsable','Beta','+999100003','responsable');
$inactive = $makeUser('Responsable','Inactif','+999100004','responsable',false);
$otherAdmin = $makeUser('Utilisateur','NonAdmin','+999100005','responsable');
$db->exec("INSERT INTO sites(code,nom) VALUES ('TEST-A','Site de test A'),('TEST-B','Site de test B')");
$siteA = (int)$db->query("SELECT id FROM sites WHERE code='TEST-A'")->fetchColumn();
$siteB = (int)$db->query("SELECT id FROM sites WHERE code='TEST-B'")->fetchColumn();

$siteService = new SiteService();
$auth = new AuthService();
$access = new AutorisationService($db);

// Authentification réelle via AuthService, mot de passe stocké avec password_hash().
$logged = $auth->connecter('+999100002', 'Test-Pass-2026!', '127.0.0.1');
check((int)$logged['id'] === $alpha && !array_key_exists('password_hash', $logged), 'connexion responsable active et hash absent de la réponse');
expectException(fn()=>$auth->connecter('+999100002','mauvais-mot-de-passe','127.0.0.1'), HttpException::class, 'mauvais mot de passe refusé');
expectException(fn()=>$auth->connecter('+999100004','Test-Pass-2026!','127.0.0.2'), HttpException::class, 'compte inactif refusé');

// Affectation principale, puis promotion d'un adjoint en principal sans doublon.
$assignmentA = $siteService->affecterResponsable($siteA, $alpha, 'principal', $admin);
$assignmentB = $siteService->affecterResponsable($siteA, $beta, 'adjoint', $admin);
$promotedId = $siteService->affecterResponsable($siteA, $beta, 'principal', $admin);
check($promotedId === $assignmentB, 'promotion adjoint -> principal réutilise la même affectation');
check((int)$db->query("SELECT COUNT(*) FROM site_responsables WHERE site_id={$siteA} AND type='principal' AND actif")->fetchColumn() === 1, 'un seul principal actif par site');
check((int)$db->query("SELECT COUNT(*) FROM audit_logs WHERE action='responsable_affectation_modifiee'")->fetchColumn() >= 1, 'promotion inscrite dans le journal d’audit');

// Compte/site inactif et non-superadmin rejetés par le vrai service PHP.
expectException(fn()=>$siteService->affecterResponsable($siteA, $inactive, 'adjoint', $admin), RuntimeException::class, 'affectation d’un responsable inactif refusée');
expectException(fn()=>$siteService->affecterResponsable(999999, $alpha, 'adjoint', $admin), RuntimeException::class, 'affectation à un site inexistant refusée');
expectException(fn()=>$siteService->affecterResponsable($siteA, $alpha, 'adjoint', $otherAdmin), RuntimeException::class, 'affectation par un non-superadmin refusée');

$db->exec("UPDATE sites SET actif=FALSE WHERE id={$siteB}");
expectException(fn()=>$siteService->affecterResponsable($siteB, $alpha, 'principal', $admin), RuntimeException::class, 'affectation à un site inactif refusée');

// Autorisations site : responsable affecté autorisé, autre site refusé.
$access->verifierSite($beta, $siteA);
check(true, 'responsable affecté autorisé sur son site');
expectException(fn()=>$access->verifierSite($alpha, $siteA), AccesRefuseException::class, 'responsable non affecté refusé sur le site');
expectException(fn()=>$access->verifierSuperAdministrateur($alpha), AccesRefuseException::class, 'responsable non autorisé aux opérations superadmin');

// Désactivation site clôture les affectations via SiteService.
$db->exec("UPDATE sites SET actif=TRUE WHERE id={$siteB}");
$siteService->affecterResponsable($siteB, $alpha, 'principal', $admin);
$siteService->desactiver($siteB, $admin);
check((int)$db->query("SELECT COUNT(*) FROM site_responsables WHERE site_id={$siteB} AND actif")->fetchColumn() === 0, 'désactivation site clôture ses affectations');
check((int)$db->query("SELECT COUNT(*) FROM audit_logs WHERE action='site_desactive'")->fetchColumn() === 1, 'désactivation du site inscrite dans le journal d’audit');

echo "\nSUCCÈS: tous les tests de services PHP et PostgreSQL ont réussi.\n";
