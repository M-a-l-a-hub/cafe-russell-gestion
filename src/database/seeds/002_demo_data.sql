-- ============================================================
-- CAFÉ RUSSELL
-- SEED 002 : DONNÉES DE DÉMONSTRATION
-- ============================================================


-- ============================================================
-- 1. UTILISATEUR SUPER ADMINISTRATEUR
-- ============================================================

INSERT INTO users (
    nom,
    prenom,
    telephone,
    email,
    password_hash,
    role,
    actif
)
VALUES (
    'RUSSELL',
    'Russell',
    '+243000000001',
    'russell.demo@example.com',

    -- Hash fictif uniquement pour la démonstration.
    -- À remplacer par un vrai hash généré avec password_hash()
    -- avant toute utilisation réelle.
    '$2y$10$DEMO_HASH_SUPER_ADMINISTRATEUR',

    'super_admin',
    TRUE
)
ON CONFLICT (telephone) DO NOTHING;


-- ============================================================
-- 2. RESPONSABLE DE DÉMONSTRATION
-- ============================================================

INSERT INTO users (
    nom,
    prenom,
    telephone,
    email,
    password_hash,
    role,
    actif
)
VALUES (
    'RESPONSABLE',
    'Demo',
    '+243000000002',
    'responsable.demo@example.com',

    -- Hash fictif uniquement pour la démonstration.
    '$2y$10$DEMO_HASH_RESPONSABLE',

    'responsable',
    TRUE
)
ON CONFLICT (telephone) DO NOTHING;


-- ============================================================
-- 3. SITE DE DÉMONSTRATION
-- ============================================================

INSERT INTO sites (
    code,
    nom,
    adresse,
    description,
    actif
)
VALUES (
    'SITE_DEMO',
    'Site de démonstration',
    'Kinshasa',
    'Site utilisé pour tester l''application Café Russell',
    TRUE
)
ON CONFLICT (code) DO NOTHING;


-- ============================================================
-- 4. AFFECTATION DU RESPONSABLE AU SITE
-- ============================================================

INSERT INTO site_responsables (
    site_id,
    user_id,
    type,
    date_debut,
    actif
)
SELECT
    s.id,
    u.id,
    'principal',
    DATE '2026-09-01',
    TRUE
FROM sites s
CROSS JOIN users u
WHERE s.code = 'SITE_DEMO'
  AND u.telephone = '+243000000002'
  AND NOT EXISTS (
      SELECT 1
      FROM site_responsables sr
      WHERE sr.site_id = s.id
        AND sr.user_id = u.id
        AND sr.actif = TRUE
  );


-- ============================================================
-- 5. VENDEUR : DAVID
-- ============================================================

INSERT INTO vendeurs (
    code,
    nom,
    prenom,
    telephone,
    periode_habituelle,
    categorie_transport_id,
    actif
)
SELECT
    'VENDEUR_DEMO_01',
    'KABILA',
    'David',
    '+243000000101',
    'matin',
    ct.id,
    TRUE
FROM categories_transport ct
WHERE ct.code = 'LONGUE'
  AND NOT EXISTS (
      SELECT 1
      FROM vendeurs v
      WHERE v.code = 'VENDEUR_DEMO_01'
  );


-- ============================================================
-- 6. VENDEUR : PATRICK
-- ============================================================

INSERT INTO vendeurs (
    code,
    nom,
    prenom,
    telephone,
    periode_habituelle,
    categorie_transport_id,
    actif
)
SELECT
    'VENDEUR_DEMO_02',
    'MUKENDI',
    'Patrick',
    '+243000000102',
    'soir',
    ct.id,
    TRUE
FROM categories_transport ct
WHERE ct.code = 'LONGUE'
  AND NOT EXISTS (
      SELECT 1
      FROM vendeurs v
      WHERE v.code = 'VENDEUR_DEMO_02'
  );


-- ============================================================
-- 7. VENDEUR : GRÂCE
-- ============================================================

INSERT INTO vendeurs (
    code,
    nom,
    prenom,
    telephone,
    periode_habituelle,
    categorie_transport_id,
    actif
)
SELECT
    'VENDEUR_DEMO_03',
    'KASONGO',
    'Grâce',
    '+243000000103',
    'journee',
    ct.id,
    TRUE
FROM categories_transport ct
WHERE ct.code = 'LONGUE'
  AND NOT EXISTS (
      SELECT 1
      FROM vendeurs v
      WHERE v.code = 'VENDEUR_DEMO_03'
  );


-- ============================================================
-- 8. SAC STANDARD 01
-- ============================================================

INSERT INTO sacs (
    site_id,
    numero,
    categorie,
    statut
)
SELECT
    s.id,
    'DEMO-01',
    'standard',
    'libre'
FROM sites s
WHERE s.code = 'SITE_DEMO'
  AND NOT EXISTS (
      SELECT 1
      FROM sacs sa
      WHERE sa.site_id = s.id
        AND sa.numero = 'DEMO-01'
  );


-- ============================================================
-- 9. SAC STANDARD 02
-- ============================================================

INSERT INTO sacs (
    site_id,
    numero,
    categorie,
    statut
)
SELECT
    s.id,
    'DEMO-02',
    'standard',
    'libre'
FROM sites s
WHERE s.code = 'SITE_DEMO'
  AND NOT EXISTS (
      SELECT 1
      FROM sacs sa
      WHERE sa.site_id = s.id
        AND sa.numero = 'DEMO-02'
  );


-- ============================================================
-- 10. SAC RUSSELL 19
-- ============================================================

INSERT INTO sacs (
    site_id,
    numero,
    categorie,
    statut
)
SELECT
    s.id,
    '19',
    'russell',
    'libre'
FROM sites s
WHERE s.code = 'SITE_DEMO'
  AND NOT EXISTS (
      SELECT 1
      FROM sacs sa
      WHERE sa.site_id = s.id
        AND sa.numero = '19'
  );


-- ============================================================
-- 11. SAC RUSSELL 66
-- ============================================================

INSERT INTO sacs (
    site_id,
    numero,
    categorie,
    statut
)
SELECT
    s.id,
    '66',
    'russell',
    'libre'
FROM sites s
WHERE s.code = 'SITE_DEMO'
  AND NOT EXISTS (
      SELECT 1
      FROM sacs sa
      WHERE sa.site_id = s.id
        AND sa.numero = '66'
  );