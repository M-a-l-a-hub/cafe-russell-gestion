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

$_ENV['DATABASE_URL'] = getenv('DATABASE_URL') ?: '';
if ($_ENV['DATABASE_URL'] === '') { throw new RuntimeException('DATABASE_URL absente de l’environnement CI.'); }

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
CREATE TABLE sessions (
 id VARCHAR(128) PRIMARY KEY, donnees TEXT NOT NULL,
 derniere_activite TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
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

CREATE TABLE vendeurs (
 id BIGSERIAL PRIMARY KEY, code VARCHAR(50) NOT NULL, nom VARCHAR(100) NOT NULL,
 prenom VARCHAR(100), actif BOOLEAN NOT NULL DEFAULT TRUE
);
CREATE TABLE sacs (
 id BIGSERIAL PRIMARY KEY, site_id BIGINT NOT NULL REFERENCES sites(id),
 numero INTEGER NOT NULL, statut VARCHAR(30) NOT NULL DEFAULT 'libre',
 categorie VARCHAR(30) NOT NULL DEFAULT 'entreprise'
);
CREATE TABLE journees (
 id BIGSERIAL PRIMARY KEY, site_id BIGINT NOT NULL REFERENCES sites(id),
 date_jour DATE NOT NULL, statut VARCHAR(30) NOT NULL DEFAULT 'ouverte'
);
CREATE TABLE activites (
 id BIGSERIAL PRIMARY KEY, journee_id BIGINT NOT NULL REFERENCES journees(id),
 vendeur_id BIGINT NOT NULL REFERENCES vendeurs(id), sac_id BIGINT NOT NULL REFERENCES sacs(id),
 periode VARCHAR(20) NOT NULL, statut VARCHAR(30) NOT NULL DEFAULT 'terminee',
 date_debut TIMESTAMPTZ NOT NULL DEFAULT NOW(), date_fin TIMESTAMPTZ,
 sac_categorie VARCHAR(30) NOT NULL, created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
 updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE TABLE produits (
 id BIGSERIAL PRIMARY KEY, code VARCHAR(30) NOT NULL, nom VARCHAR(100) NOT NULL,
 unite VARCHAR(30) NOT NULL DEFAULT 'tasse'
);
CREATE TABLE ravitaillements (
 id BIGSERIAL PRIMARY KEY, activite_id BIGINT NOT NULL REFERENCES activites(id),
 effectue_par BIGINT NOT NULL REFERENCES users(id), date_heure TIMESTAMPTZ NOT NULL DEFAULT NOW(),
 transport_par_ravitaillement NUMERIC(12,2) NOT NULL DEFAULT 0,
 note TEXT, created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE TABLE ravitaillement_lignes (
 id BIGSERIAL PRIMARY KEY, ravitaillement_id BIGINT NOT NULL REFERENCES ravitaillements(id),
 produit_id BIGINT NOT NULL REFERENCES produits(id), quantite INTEGER NOT NULL,
 prix_unitaire NUMERIC(12,2) NOT NULL, cout_unitaire NUMERIC(12,2) NOT NULL,
 created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE TABLE retours (
 id BIGSERIAL PRIMARY KEY, activite_id BIGINT NOT NULL REFERENCES activites(id),
 effectue_par BIGINT NOT NULL REFERENCES users(id), date_heure TIMESTAMPTZ NOT NULL DEFAULT NOW(),
 note TEXT, created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(), updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE TABLE retour_lignes (
 id BIGSERIAL PRIMARY KEY, retour_id BIGINT NOT NULL REFERENCES retours(id),
 produit_id BIGINT NOT NULL REFERENCES produits(id), quantite INTEGER NOT NULL,
 created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE TABLE depenses (
 id BIGSERIAL PRIMARY KEY, site_id BIGINT REFERENCES sites(id), date_depense DATE NOT NULL,
 montant NUMERIC(12,2) NOT NULL
);
CREATE TYPE controle_financier_statut AS ENUM ('aucun_ecart','ecart_a_expliquer','ecart_en_cours_de_resolution','resolu');
CREATE TABLE anomalies_stock (id BIGSERIAL PRIMARY KEY, activite_id BIGINT NOT NULL REFERENCES activites(id), statut VARCHAR(30) NOT NULL DEFAULT 'en_attente');
CREATE TABLE controles_financiers (
 id BIGSERIAL PRIMARY KEY, activite_id BIGINT NOT NULL REFERENCES activites(id),
 ecart NUMERIC(12,2) NOT NULL DEFAULT 0, statut controle_financier_statut NOT NULL DEFAULT 'aucun_ecart'
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
$db->exec("INSERT INTO sites(code,nom,actif) VALUES ('TEST-A','Site de test A',TRUE),('TEST-B','Site de test B',TRUE),('TEST-C','Site inactif sans journée',FALSE)");
$siteA = (int)$db->query("SELECT id FROM sites WHERE code='TEST-A'")->fetchColumn();
$siteB = (int)$db->query("SELECT id FROM sites WHERE code='TEST-B'")->fetchColumn();
$siteC = (int)$db->query("SELECT id FROM sites WHERE code='TEST-C'")->fetchColumn();

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


/* Tests comptables sur les vrais ActiviteService et RapportService. */
require_once __DIR__ . '/../src/services/RapportService.php';
$db->exec("INSERT INTO vendeurs(code,nom,prenom) VALUES ('V-TEST-1','Vendeur','Alpha'),('V-TEST-2','Vendeur','Beta')");
$db->exec("INSERT INTO sacs(site_id,numero,categorie) VALUES ({$siteA},1,'entreprise'),({$siteA},19,'russell'),({$siteB},2,'entreprise')");
$db->exec("INSERT INTO journees(site_id,date_jour,statut) VALUES ({$siteA},'2026-10-14','terminee'),({$siteB},'2026-10-14','terminee')");
$dayA = (int)$db->query("SELECT id FROM journees WHERE site_id={$siteA}")->fetchColumn();
$dayB = (int)$db->query("SELECT id FROM journees WHERE site_id={$siteB}")->fetchColumn();
$db->exec("INSERT INTO produits(code,nom,unite) VALUES ('PETITE','Petite tasse','tasse')");
$product = (int)$db->query("SELECT id FROM produits WHERE code='PETITE'")->fetchColumn();
$db->exec("INSERT INTO activites(journee_id,vendeur_id,sac_id,periode,statut,sac_categorie) VALUES ({$dayA},1,1,'matin','terminee','entreprise'),({$dayA},2,2,'matin','terminee','russell'),({$dayB},1,3,'matin','terminee','entreprise')");
$actCompany = (int)$db->query("SELECT id FROM activites WHERE sac_categorie='entreprise' AND journee_id={$dayA}")->fetchColumn();
$actRussell = (int)$db->query("SELECT id FROM activites WHERE sac_categorie='russell'")->fetchColumn();
$actSiteB = (int)$db->query("SELECT id FROM activites WHERE journee_id={$dayB}")->fetchColumn();
$insertSupply = $db->prepare("INSERT INTO ravitaillements(activite_id,effectue_par,transport_par_ravitaillement) VALUES(:a,:u,:t) RETURNING id");
$insertLine = $db->prepare("INSERT INTO ravitaillement_lignes(ravitaillement_id,produit_id,quantite,prix_unitaire,cout_unitaire) VALUES(:r,:p,:q,500,200)");
$insertReturn = $db->prepare("INSERT INTO retours(activite_id,effectue_par) VALUES(:a,:u) RETURNING id");
$insertReturnLine = $db->prepare("INSERT INTO retour_lignes(retour_id,produit_id,quantite) VALUES(:r,:p,:q)");
foreach ([[$actCompany,100,4000,20],[$actRussell,80,2000,20],[$actSiteB,50,2000,10]] as [$aid,$received,$transport,$returned]) {
 $insertSupply->execute(['a'=>$aid,'u'=>$admin,'t'=>$transport]); $rid=(int)$insertSupply->fetchColumn();
 $insertLine->execute(['r'=>$rid,'p'=>$product,'q'=>$received]);
 $insertReturn->execute(['a'=>$aid,'u'=>$admin]); $retid=(int)$insertReturn->fetchColumn();
 $insertReturnLine->execute(['r'=>$retid,'p'=>$product,'q'=>$returned]);
}
$db->exec("INSERT INTO depenses(site_id,date_depense,montant) VALUES ({$siteA},'2026-10-14',5000),({$siteB},'2026-10-14',3000),({$siteC},'2026-10-14',700),(NULL,'2026-10-14',2000)");
$accounting = new ActiviteService();
$companyCalc = $accounting->calculer($actCompany);
$russellCalc = $accounting->calculer($actRussell);
check((float)$companyCalc['ca']===40000.0 && (float)$companyCalc['commission']===10000.0, 'activité entreprise : CA sur quantités vendues et commission 25 %');
check((float)$companyCalc['consommables']===20000.0 && (float)$companyCalc['benefice']===6000.0, 'consommables calculés sur quantités reçues et bénéfice activité correct');
check((float)$russellCalc['ca']===30000.0 && (float)$russellCalc['commission']===7500.0, 'sac Russell : commission vendeur maintenue à 25 %');
check($russellCalc['beneficiaire_benefice']==='russell' && (float)$russellCalc['benefice']===4500.0, 'bénéfice du sac Russell attribué séparément à Russell');
$report = (new RapportService())->pourJournee($dayA,$admin);
check((float)$report['benefice_entreprise']===6000.0 && (float)$report['benefice_russell']===4500.0, 'RapportService sépare bénéfice entreprise et bénéfice Russell');
check((float)$report['depenses_site']===5000.0 && (float)$report['benefice_apres_depenses_site']===1000.0, 'dépenses du site réduisent le bénéfice entreprise uniquement');

// Le rapport vendeur agrège les activités de plusieurs sites sans oublier
// la commission fixe de 25 % ni les coûts calculés sur les quantités reçues.
$sellerReport = (new RapportService())->pourVendeur(1, $admin);
check((float)$sellerReport['ventes']===60000.0, 'rapport vendeur : CA cumulé sur les deux sites');
check((float)$sellerReport['commissions']===15000.0, 'rapport vendeur : commission de 25 % cumulée');
check((float)$sellerReport['transport']===6000.0 && (float)$sellerReport['consommables']===30000.0, 'rapport vendeur : transport et consommables cumulés');
check((float)$sellerReport['benefice_activites']===9000.0, 'rapport vendeur : bénéfice cumulé cohérent');

// Test d'intégration du vrai contrôleur de tableau de bord multi-sites.
require_once __DIR__ . '/../src/http/Requete.php';
require_once __DIR__ . '/../src/http/controleurs/TableauDeBordControleur.php';
$dashboardRequest = new Requete('GET', '/api/tableau-de-bord', ['date' => '2026-10-14'], '', [], '127.0.0.1');
$dashboardRequest->utilisateur = ['id' => $admin, 'role' => 'super_admin'];
$dashboard = (new TableauDeBordControleur())->afficher($dashboardRequest);
$dashboardTotals = $dashboard['totaux'];
check((float)$dashboardTotals['ventes'] === 70000.0, 'tableau de bord consolide les ventes des sites actifs et inactifs');
check((float)$dashboardTotals['benefice_entreprise'] === 9000.0, 'consolidation inclut le bénéfice entreprise du site inactif');
check((float)$dashboardTotals['benefice_russell'] === 4500.0, 'consolidation garde le bénéfice Russell séparé');
check((float)$dashboardTotals['depenses_site'] === 8700.0, 'consolidation inclut les dépenses de tous les sites, même sans journée');
check((float)$dashboardTotals['depenses_sites_non_journee'] === 700.0, 'dépense d’un site sans journée identifiée séparément');
check((float)$dashboardTotals['depenses_generales'] === 2000.0, 'dépenses générales séparées des dépenses des sites');
check((float)$dashboardTotals['benefice_entreprise_net_consolide'] === -1700.0, 'net entreprise = 9000 - 8700 - 2000, bénéfice Russell exclu');
check(count($dashboard['sites']) === 1, 'site inactif exclu des cartes opérationnelles mais inclus dans la consolidation');

// Garde-fou : une quantité retournée supérieure à la quantité reçue doit
// être rejetée, et non produire un chiffre d'affaires négatif.
$invalidReturn = (int)$db->query("SELECT id FROM retours WHERE activite_id={$actCompany}")->fetchColumn();
$insertReturnLine->execute(['r'=>$invalidReturn,'p'=>$product,'q'=>101]);
expectException(fn()=>$accounting->calculer($actCompany), RuntimeException::class, 'retour supérieur au ravitaillement rejeté');

echo "\nSUCCÈS: tous les tests de services PHP, comptabilité et PostgreSQL ont réussi.\n";
