-- =========================================================
-- 031_sacs_categorie_modifiable.sql
-- Retire la règle qui figeait la catégorie des sacs 19 et 66 à
-- « russell » (et tous les autres à « standard »). Russell peut
-- désormais choisir librement la catégorie de chaque sac.
--
-- Rien à changer côté historique : depuis la migration 026, chaque
-- activité garde sa propre catégorie figée au moment où elle a eu
-- lieu (colonne activites.sac_categorie, verrouillée par trigger).
-- Modifier un sac aujourd'hui ne touche donc jamais le passé.
-- =========================================================

ALTER TABLE sacs
DROP CONSTRAINT IF EXISTS chk_sacs_numero_categorie_russell;
