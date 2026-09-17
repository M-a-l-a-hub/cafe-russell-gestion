-- ============================================================
-- CAFÉ RUSSELL
-- SEED 003 : JOURNÉE DE DÉMONSTRATION COMPLÈTE
-- DATE : 01/09/2026
-- ============================================================

BEGIN;


-- ============================================================
-- 1. CRÉATION DE LA JOURNÉE
-- ============================================================

INSERT INTO journees (
    site_id,
    date_jour,
    statut,
    ouverte_par,
    date_ouverture,
    note_ouverture
)
SELECT
    s.id,
    DATE '2026-09-01',
    'ouverte',
    u.id,
    TIMESTAMPTZ '2026-09-01 07:00:00+01',
    'Journée de démonstration'
FROM sites s
CROSS JOIN users u
WHERE s.code = 'SITE_DEMO'
  AND u.telephone = '+243000000002'
  AND NOT EXISTS (
      SELECT 1
      FROM journees j
      WHERE j.site_id = s.id
        AND j.date_jour = DATE '2026-09-01'
  );


-- ============================================================
-- 2. ACTIVITÉ DE DAVID
-- ============================================================

INSERT INTO activites (
    journee_id,
    vendeur_id,
    sac_id,
    periode,
    statut,
    date_debut,
    date_fin
)
SELECT
    j.id,
    v.id,
    s.id,
    'matin',
    'terminee',
    TIMESTAMPTZ '2026-09-01 07:30:00+01',
    TIMESTAMPTZ '2026-09-01 12:30:00+01'
FROM journees j
JOIN vendeurs v
    ON v.code = 'VENDEUR_DEMO_01'
JOIN sacs s
    ON s.site_id = j.site_id
   AND s.numero = 'DEMO-01'
WHERE j.date_jour = DATE '2026-09-01'
  AND j.site_id = (
      SELECT id
      FROM sites
      WHERE code = 'SITE_DEMO'
  );


-- ============================================================
-- 3. ACTIVITÉ DE PATRICK
-- ============================================================

INSERT INTO activites (
    journee_id,
    vendeur_id,
    sac_id,
    periode,
    statut,
    date_debut,
    date_fin
)
SELECT
    j.id,
    v.id,
    s.id,
    'soir',
    'terminee',
    TIMESTAMPTZ '2026-09-01 14:00:00+01',
    TIMESTAMPTZ '2026-09-01 20:00:00+01'
FROM journees j
JOIN vendeurs v
    ON v.code = 'VENDEUR_DEMO_02'
JOIN sacs s
    ON s.site_id = j.site_id
   AND s.numero = '19'
WHERE j.date_jour = DATE '2026-09-01'
  AND j.site_id = (
      SELECT id
      FROM sites
      WHERE code = 'SITE_DEMO'
  );


-- ============================================================
-- 4. RAVITAILLEMENT DE DAVID
-- 70 petites + 40 grandes
-- Transport récupéré automatiquement depuis le tarif LONGUE
-- ============================================================

INSERT INTO ravitaillements (
    activite_id,
    effectue_par,
    date_heure,
    transport_par_ravitaillement,
    note
)
SELECT
    a.id,
    u.id,
    TIMESTAMPTZ '2026-09-01 07:30:00+01',
    tp.montant_par_ravitaillement,
    'Premier ravitaillement de David'
FROM activites a
JOIN vendeurs v
    ON v.id = a.vendeur_id
JOIN categories_transport ct
    ON ct.id = v.categorie_transport_id
JOIN transport_prix tp
    ON tp.categorie_transport_id = ct.id
   AND tp.date_debut <= DATE '2026-09-01'
   AND (
       tp.date_fin IS NULL
       OR tp.date_fin >= DATE '2026-09-01'
   )
JOIN users u
    ON u.telephone = '+243000000002'
WHERE v.code = 'VENDEUR_DEMO_01'
  AND a.journee_id = (
      SELECT j.id
      FROM journees j
      WHERE j.site_id = (
          SELECT id FROM sites WHERE code = 'SITE_DEMO'
      )
      AND j.date_jour = DATE '2026-09-01'
  );


-- ============================================================
-- 5. LIGNES DU RAVITAILLEMENT DE DAVID
-- ============================================================

INSERT INTO ravitaillement_lignes (
    ravitaillement_id,
    produit_id,
    quantite,
    prix_unitaire,
    cout_unitaire
)
SELECT
    r.id,
    p.id,
    70,
    pp.prix,
    pc.cout_unitaire
FROM ravitaillements r
JOIN produits p
    ON p.code = 'PETITE_TASSE'
JOIN produit_prix pp
    ON pp.produit_id = p.id
   AND pp.date_debut <= DATE '2026-09-01'
   AND (
       pp.date_fin IS NULL
       OR pp.date_fin >= DATE '2026-09-01'
   )
JOIN produit_couts pc
    ON pc.produit_id = p.id
   AND pc.date_debut <= DATE '2026-09-01'
   AND (
       pc.date_fin IS NULL
       OR pc.date_fin >= DATE '2026-09-01'
   )
WHERE r.activite_id = (
    SELECT a.id
    FROM activites a
    JOIN vendeurs v ON v.id = a.vendeur_id
    WHERE v.code = 'VENDEUR_DEMO_01'
      AND a.journee_id = (
          SELECT id
          FROM journees
          WHERE date_jour = DATE '2026-09-01'
            AND site_id = (
                SELECT id FROM sites WHERE code = 'SITE_DEMO'
            )
      )
);


INSERT INTO ravitaillement_lignes (
    ravitaillement_id,
    produit_id,
    quantite,
    prix_unitaire,
    cout_unitaire
)
SELECT
    r.id,
    p.id,
    40,
    pp.prix,
    pc.cout_unitaire
FROM ravitaillements r
JOIN produits p
    ON p.code = 'GRANDE_TASSE'
JOIN produit_prix pp
    ON pp.produit_id = p.id
   AND pp.date_debut <= DATE '2026-09-01'
   AND (
       pp.date_fin IS NULL
       OR pp.date_fin >= DATE '2026-09-01'
   )
JOIN produit_couts pc
    ON pc.produit_id = p.id
   AND pc.date_debut <= DATE '2026-09-01'
   AND (
       pc.date_fin IS NULL
       OR pc.date_fin >= DATE '2026-09-01'
   )
WHERE r.activite_id = (
    SELECT a.id
    FROM activites a
    JOIN vendeurs v ON v.id = a.vendeur_id
    WHERE v.code = 'VENDEUR_DEMO_01'
      AND a.journee_id = (
          SELECT id
          FROM journees
          WHERE date_jour = DATE '2026-09-01'
            AND site_id = (
                SELECT id FROM sites WHERE code = 'SITE_DEMO'
            )
      )
);


-- ============================================================
-- 6. PREMIER RAVITAILLEMENT DE PATRICK
-- 50 petites + 20 grandes
-- ============================================================

INSERT INTO ravitaillements (
    activite_id,
    effectue_par,
    date_heure,
    transport_par_ravitaillement,
    note
)
SELECT
    a.id,
    u.id,
    TIMESTAMPTZ '2026-09-01 14:00:00+01',
    tp.montant_par_ravitaillement,
    'Premier ravitaillement de Patrick'
FROM activites a
JOIN vendeurs v
    ON v.id = a.vendeur_id
JOIN categories_transport ct
    ON ct.id = v.categorie_transport_id
JOIN transport_prix tp
    ON tp.categorie_transport_id = ct.id
   AND tp.date_debut <= DATE '2026-09-01'
   AND (
       tp.date_fin IS NULL
       OR tp.date_fin >= DATE '2026-09-01'
   )
JOIN users u
    ON u.telephone = '+243000000002'
WHERE v.code = 'VENDEUR_DEMO_02'
  AND a.journee_id = (
      SELECT id
      FROM journees
      WHERE date_jour = DATE '2026-09-01'
        AND site_id = (
            SELECT id FROM sites WHERE code = 'SITE_DEMO'
        )
  );


-- ============================================================
-- 7. LIGNES DU PREMIER RAVITAILLEMENT DE PATRICK
-- ============================================================

INSERT INTO ravitaillement_lignes (
    ravitaillement_id,
    produit_id,
    quantite,
    prix_unitaire,
    cout_unitaire
)
SELECT
    r.id,
    p.id,
    50,
    pp.prix,
    pc.cout_unitaire
FROM ravitaillements r
JOIN produits p ON p.code = 'PETITE_TASSE'
JOIN produit_prix pp
    ON pp.produit_id = p.id
   AND pp.date_debut <= DATE '2026-09-01'
   AND (pp.date_fin IS NULL OR pp.date_fin >= DATE '2026-09-01')
JOIN produit_couts pc
    ON pc.produit_id = p.id
   AND pc.date_debut <= DATE '2026-09-01'
   AND (pc.date_fin IS NULL OR pc.date_fin >= DATE '2026-09-01')
WHERE r.activite_id = (
    SELECT a.id
    FROM activites a
    JOIN vendeurs v ON v.id = a.vendeur_id
    WHERE v.code = 'VENDEUR_DEMO_02'
      AND a.journee_id = (
          SELECT id
          FROM journees
          WHERE date_jour = DATE '2026-09-01'
            AND site_id = (
                SELECT id FROM sites WHERE code = 'SITE_DEMO'
            )
      )
);


INSERT INTO ravitaillement_lignes (
    ravitaillement_id,
    produit_id,
    quantite,
    prix_unitaire,
    cout_unitaire
)
SELECT
    r.id,
    p.id,
    20,
    pp.prix,
    pc.cout_unitaire
FROM ravitaillements r
JOIN produits p ON p.code = 'GRANDE_TASSE'
JOIN produit_prix pp
    ON pp.produit_id = p.id
   AND pp.date_debut <= DATE '2026-09-01'
   AND (pp.date_fin IS NULL OR pp.date_fin >= DATE '2026-09-01')
JOIN produit_couts pc
    ON pc.produit_id = p.id
   AND pc.date_debut <= DATE '2026-09-01'
   AND (pc.date_fin IS NULL OR pc.date_fin >= DATE '2026-09-01')
WHERE r.activite_id = (
    SELECT a.id
    FROM activites a
    JOIN vendeurs v ON v.id = a.vendeur_id
    WHERE v.code = 'VENDEUR_DEMO_02'
      AND a.journee_id = (
          SELECT id
          FROM journees
          WHERE date_jour = DATE '2026-09-01'
            AND site_id = (
                SELECT id FROM sites WHERE code = 'SITE_DEMO'
            )
      )
);


-- ============================================================
-- 8. DEUXIÈME RAVITAILLEMENT DE PATRICK
-- 10 petites
-- ============================================================

INSERT INTO ravitaillements (
    activite_id,
    effectue_par,
    date_heure,
    transport_par_ravitaillement,
    note
)
SELECT
    a.id,
    u.id,
    TIMESTAMPTZ '2026-09-01 17:00:00+01',
    tp.montant_par_ravitaillement,
    'Deuxième ravitaillement de Patrick'
FROM activites a
JOIN vendeurs v
    ON v.id = a.vendeur_id
JOIN categories_transport ct
    ON ct.id = v.categorie_transport_id
JOIN transport_prix tp
    ON tp.categorie_transport_id = ct.id
   AND tp.date_debut <= DATE '2026-09-01'
   AND (
       tp.date_fin IS NULL
       OR tp.date_fin >= DATE '2026-09-01'
   )
JOIN users u
    ON u.telephone = '+243000000002'
WHERE v.code = 'VENDEUR_DEMO_02'
  AND a.journee_id = (
      SELECT id
      FROM journees
      WHERE date_jour = DATE '2026-09-01'
        AND site_id = (
            SELECT id FROM sites WHERE code = 'SITE_DEMO'
        )
  );


INSERT INTO ravitaillement_lignes (
    ravitaillement_id,
    produit_id,
    quantite,
    prix_unitaire,
    cout_unitaire
)
SELECT
    r.id,
    p.id,
    10,
    pp.prix,
    pc.cout_unitaire
FROM ravitaillements r
JOIN produits p ON p.code = 'PETITE_TASSE'
JOIN produit_prix pp
    ON pp.produit_id = p.id
   AND pp.date_debut <= DATE '2026-09-01'
   AND (pp.date_fin IS NULL OR pp.date_fin >= DATE '2026-09-01')
JOIN produit_couts pc
    ON pc.produit_id = p.id
   AND pc.date_debut <= DATE '2026-09-01'
   AND (pc.date_fin IS NULL OR pc.date_fin >= DATE '2026-09-01')
WHERE r.activite_id = (
    SELECT a.id
    FROM activites a
    JOIN vendeurs v ON v.id = a.vendeur_id
    WHERE v.code = 'VENDEUR_DEMO_02'
      AND a.journee_id = (
          SELECT id
          FROM journees
          WHERE date_jour = DATE '2026-09-01'
            AND site_id = (
                SELECT id FROM sites WHERE code = 'SITE_DEMO'
            )
      )
)
ORDER BY r.date_heure DESC
LIMIT 1;


-- ============================================================
-- 9. RETOUR DE DAVID
-- 20 petites + 10 grandes
-- ============================================================

INSERT INTO retours (
    activite_id,
    effectue_par,
    date_heure,
    note
)
SELECT
    a.id,
    u.id,
    TIMESTAMPTZ '2026-09-01 12:30:00+01',
    'Retour de fin d''activité de David'
FROM activites a
JOIN vendeurs v ON v.id = a.vendeur_id
JOIN users u ON u.telephone = '+243000000002'
WHERE v.code = 'VENDEUR_DEMO_01'
  AND a.journee_id = (
      SELECT id
      FROM journees
      WHERE date_jour = DATE '2026-09-01'
        AND site_id = (
            SELECT id FROM sites WHERE code = 'SITE_DEMO'
        )
  );


INSERT INTO retour_lignes (
    retour_id,
    produit_id,
    quantite
)
SELECT
    r.id,
    p.id,
    20
FROM retours r
JOIN produits p ON p.code = 'PETITE_TASSE'
WHERE r.activite_id = (
    SELECT a.id
    FROM activites a
    JOIN vendeurs v ON v.id = a.vendeur_id
    WHERE v.code = 'VENDEUR_DEMO_01'
      AND a.journee_id = (
          SELECT id
          FROM journees
          WHERE date_jour = DATE '2026-09-01'
            AND site_id = (
                SELECT id FROM sites WHERE code = 'SITE_DEMO'
            )
      )
);


INSERT INTO retour_lignes (
    retour_id,
    produit_id,
    quantite
)
SELECT
    r.id,
    p.id,
    10
FROM retours r
JOIN produits p ON p.code = 'GRANDE_TASSE'
WHERE r.activite_id = (
    SELECT a.id
    FROM activites a
    JOIN vendeurs v ON v.id = a.vendeur_id
    WHERE v.code = 'VENDEUR_DEMO_01'
      AND a.journee_id = (
          SELECT id
          FROM journees
          WHERE date_jour = DATE '2026-09-01'
            AND site_id = (
                SELECT id FROM sites WHERE code = 'SITE_DEMO'
            )
      )
);


-- ============================================================
-- 10. RETOUR DE PATRICK
-- 15 petites + 5 grandes
-- ============================================================

INSERT INTO retours (
    activite_id,
    effectue_par,
    date_heure,
    note
)
SELECT
    a.id,
    u.id,
    TIMESTAMPTZ '2026-09-01 20:00:00+01',
    'Retour de fin d''activité de Patrick'
FROM activites a
JOIN vendeurs v ON v.id = a.vendeur_id
JOIN users u ON u.telephone = '+243000000002'
WHERE v.code = 'VENDEUR_DEMO_02'
  AND a.journee_id = (
      SELECT id
      FROM journees
      WHERE date_jour = DATE '2026-09-01'
        AND site_id = (
            SELECT id FROM sites WHERE code = 'SITE_DEMO'
        )
  );


INSERT INTO retour_lignes (
    retour_id,
    produit_id,
    quantite
)
SELECT
    r.id,
    p.id,
    15
FROM retours r
JOIN produits p ON p.code = 'PETITE_TASSE'
WHERE r.activite_id = (
    SELECT a.id
    FROM activites a
    JOIN vendeurs v ON v.id = a.vendeur_id
    WHERE v.code = 'VENDEUR_DEMO_02'
      AND a.journee_id = (
          SELECT id
          FROM journees
          WHERE date_jour = DATE '2026-09-01'
            AND site_id = (
                SELECT id FROM sites WHERE code = 'SITE_DEMO'
            )
      )
);


INSERT INTO retour_lignes (
    retour_id,
    produit_id,
    quantite
)
SELECT
    r.id,
    p.id,
    5
FROM retours r
JOIN produits p ON p.code = 'GRANDE_TASSE'
WHERE r.activite_id = (
    SELECT a.id
    FROM activites a
    JOIN vendeurs v ON v.id = a.vendeur_id
    WHERE v.code = 'VENDEUR_DEMO_02'
      AND a.journee_id = (
          SELECT id
          FROM journees
          WHERE date_jour = DATE '2026-09-01'
            AND site_id = (
                SELECT id FROM sites WHERE code = 'SITE_DEMO'
            )
      )
);


-- ============================================================
-- 11. CONTRÔLE FINANCIER DE DAVID
-- CA = 55 000 FC
-- Montant remis = 55 000 FC
-- Écart = 0
-- ============================================================

INSERT INTO controles_financiers (
    activite_id,
    ca_theorique,
    montant_remis,
    ecart,
    statut,
    controle_par,
    date_heure,
    note
)
SELECT
    a.id,
    55000,
    55000,
    0,
    'aucun_ecart',
    u.id,
    TIMESTAMPTZ '2026-09-01 12:35:00+01',
    'Contrôle sans écart'
FROM activites a
JOIN vendeurs v ON v.id = a.vendeur_id
JOIN users u ON u.telephone = '+243000000002'
WHERE v.code = 'VENDEUR_DEMO_01'
  AND a.journee_id = (
      SELECT id
      FROM journees
      WHERE date_jour = DATE '2026-09-01'
        AND site_id = (
            SELECT id FROM sites WHERE code = 'SITE_DEMO'
        )
  );


-- ============================================================
-- 12. CONTRÔLE FINANCIER DE PATRICK
-- CA = 37 500 FC
-- Montant remis = 37 500 FC
-- Écart = 0
-- ============================================================

INSERT INTO controles_financiers (
    activite_id,
    ca_theorique,
    montant_remis,
    ecart,
    statut,
    controle_par,
    date_heure,
    note
)
SELECT
    a.id,
    37500,
    37500,
    0,
    'aucun_ecart',
    u.id,
    TIMESTAMPTZ '2026-09-01 20:05:00+01',
    'Contrôle sans écart'
FROM activites a
JOIN vendeurs v ON v.id = a.vendeur_id
JOIN users u ON u.telephone = '+243000000002'
WHERE v.code = 'VENDEUR_DEMO_02'
  AND a.journee_id = (
      SELECT id
      FROM journees
      WHERE date_jour = DATE '2026-09-01'
        AND site_id = (
            SELECT id FROM sites WHERE code = 'SITE_DEMO'
        )
  );


-- ============================================================
-- 13. COMMISSION DE DAVID
-- 55 000 × 25 % = 13 750 FC
-- ============================================================

INSERT INTO commissions (
    activite_id,
    vendeur_id,
    ca,
    taux,
    montant,
    date
)
SELECT
    a.id,
    v.id,
    55000,
    25.00,
    13750,
    DATE '2026-09-01'
FROM activites a
JOIN vendeurs v ON v.id = a.vendeur_id
WHERE v.code = 'VENDEUR_DEMO_01'
  AND a.journee_id = (
      SELECT id
      FROM journees
      WHERE date_jour = DATE '2026-09-01'
        AND site_id = (
            SELECT id FROM sites WHERE code = 'SITE_DEMO'
        )
  );


-- ============================================================
-- 14. COMMISSION DE PATRICK
-- 37 500 × 25 % = 9 375 FC
-- ============================================================

INSERT INTO commissions (
    activite_id,
    vendeur_id,
    ca,
    taux,
    montant,
    date
)
SELECT
    a.id,
    v.id,
    37500,
    25.00,
    9375,
    DATE '2026-09-01'
FROM activites a
JOIN vendeurs v ON v.id = a.vendeur_id
WHERE v.code = 'VENDEUR_DEMO_02'
  AND a.journee_id = (
      SELECT id
      FROM journees
      WHERE date_jour = DATE '2026-09-01'
        AND site_id = (
            SELECT id FROM sites WHERE code = 'SITE_DEMO'
        )
  );


COMMIT;