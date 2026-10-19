-- =========================================================
-- 033_vendeurs_affectations_sites.sql
-- Affectations explicites des vendeurs aux sites.
-- Les anciennes affectations sont déduites des activités historiques.
-- Les vendeurs sans activité historique restent non affectés.
-- =========================================================

BEGIN;

CREATE TABLE site_vendeurs (
    id BIGSERIAL PRIMARY KEY,
    site_id BIGINT NOT NULL REFERENCES sites(id),
    vendeur_id BIGINT NOT NULL REFERENCES vendeurs(id),
    actif BOOLEAN NOT NULL DEFAULT TRUE,
    affecte_par BIGINT REFERENCES users(id),
    date_affectation TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    desaffecte_par BIGINT REFERENCES users(id),
    date_desaffectation TIMESTAMPTZ,
    CONSTRAINT uq_site_vendeur UNIQUE (site_id, vendeur_id),
    CONSTRAINT chk_site_vendeur_statut CHECK (
        (actif = TRUE AND date_desaffectation IS NULL)
        OR (actif = FALSE AND date_desaffectation IS NOT NULL)
    )
);

CREATE INDEX idx_site_vendeurs_site_actif
    ON site_vendeurs(site_id, actif, vendeur_id);

-- Préserver l'historique : affecter chaque vendeur aux seuls sites
-- où il a réellement travaillé selon ses activités enregistrées.
INSERT INTO site_vendeurs (site_id, vendeur_id)
SELECT DISTINCT j.site_id, a.vendeur_id
FROM activites a
INNER JOIN journees j ON j.id = a.journee_id
ON CONFLICT (site_id, vendeur_id) DO NOTHING;

COMMIT;
