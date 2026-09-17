-- ============================================================
-- CAFÉ RUSSELL
-- SEED 001 : DONNÉES INITIALES
-- ============================================================


-- ============================================================
-- 1. PRODUITS
-- ============================================================

INSERT INTO produits (
    code,
    nom,
    description,
    unite
)
VALUES
(
    'PETITE_TASSE',
    'Petite tasse',
    'Petite tasse de café',
    'piece'
),
(
    'GRANDE_TASSE',
    'Grande tasse',
    'Grande tasse de café',
    'piece'
)
ON CONFLICT (code) DO NOTHING;


-- ============================================================
-- 2. PRIX DE VENTE INITIAUX
-- ============================================================

INSERT INTO produit_prix (
    produit_id,
    prix,
    date_debut
)
SELECT
    id,
    CASE code
        WHEN 'PETITE_TASSE' THEN 500
        WHEN 'GRANDE_TASSE' THEN 1000
    END,
    DATE '2026-09-01'
FROM produits
WHERE code IN (
    'PETITE_TASSE',
    'GRANDE_TASSE'
)
AND NOT EXISTS (
    SELECT 1
    FROM produit_prix pp
    WHERE pp.produit_id = produits.id
      AND pp.date_fin IS NULL
);


-- ============================================================
-- 3. COÛTS CONSOMMABLES INITIAUX
-- ============================================================

INSERT INTO produit_couts (
    produit_id,
    cout_unitaire,
    date_debut
)
SELECT
    id,
    CASE code
        WHEN 'PETITE_TASSE' THEN 150
        WHEN 'GRANDE_TASSE' THEN 250
    END,
    DATE '2026-09-01'
FROM produits
WHERE code IN (
    'PETITE_TASSE',
    'GRANDE_TASSE'
)
AND NOT EXISTS (
    SELECT 1
    FROM produit_couts pc
    WHERE pc.produit_id = produits.id
      AND pc.date_fin IS NULL
);


-- ============================================================
-- 4. CATÉGORIES DE TRANSPORT
-- ============================================================

INSERT INTO categories_transport (
    code,
    nom,
    description
)
VALUES
(
    'COURTE',
    'Courte distance',
    'Transport prévu pour les vendeurs proches du site'
),
(
    'MOYENNE',
    'Distance moyenne',
    'Transport prévu pour les vendeurs à distance moyenne'
),
(
    'LONGUE',
    'Longue distance',
    'Transport prévu pour les vendeurs éloignés du site'
)
ON CONFLICT (code) DO NOTHING;


-- ============================================================
-- 5. TARIFS DE TRANSPORT INITIAUX
-- ============================================================

INSERT INTO transport_prix (
    categorie_transport_id,
    montant_par_ravitaillement,
    date_debut
)
SELECT
    id,
    CASE code
        WHEN 'COURTE' THEN 1500
        WHEN 'MOYENNE' THEN 2000
        WHEN 'LONGUE' THEN 3000
    END,
    DATE '2026-09-01'
FROM categories_transport
WHERE code IN (
    'COURTE',
    'MOYENNE',
    'LONGUE'
)
AND NOT EXISTS (
    SELECT 1
    FROM transport_prix tp
    WHERE tp.categorie_transport_id = categories_transport.id
      AND tp.date_fin IS NULL
);


-- ============================================================
-- 6. CATÉGORIES DE DÉPENSES
-- ============================================================

INSERT INTO categories_depenses (
    code,
    nom,
    description
)
VALUES
(
    'LAIT',
    'Lait',
    'Achats de lait utilisés pour la préparation du café'
),
(
    'SUCRE',
    'Sucre',
    'Achats de sucre'
),
(
    'CAFE',
    'Café',
    'Achats de café et matières premières liées au café'
),
(
    'AUTRES_CONSOMMABLES',
    'Autres consommables',
    'Autres consommables utilisés dans l''activité'
),
(
    'LOYER',
    'Loyer',
    'Loyer d''un site ou d''un espace de travail'
),
(
    'ENTRETIEN',
    'Entretien',
    'Entretien et maintenance'
),
(
    'MATERIEL',
    'Matériel',
    'Achat ou remplacement de matériel'
),
(
    'COMMUNICATION',
    'Communication',
    'Publicité, communication et promotion'
),
(
    'AUTRE',
    'Autre',
    'Dépense ne correspondant à aucune catégorie existante'
)
ON CONFLICT (code) DO NOTHING;