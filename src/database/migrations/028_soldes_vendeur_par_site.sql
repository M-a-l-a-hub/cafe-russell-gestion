-- =========================================================
-- 028_soldes_vendeur_par_site.sql
-- Rattachement explicite des retraits et paiements a un site
-- =========================================================

BEGIN;

ALTER TABLE retraits
    ADD COLUMN site_id BIGINT NOT NULL;

ALTER TABLE retraits
    ADD CONSTRAINT fk_retraits_site
    FOREIGN KEY (site_id)
    REFERENCES sites(id);

ALTER TABLE paiements_soldes
    ADD COLUMN site_id BIGINT NOT NULL;

ALTER TABLE paiements_soldes
    ADD CONSTRAINT fk_paiements_soldes_site
    FOREIGN KEY (site_id)
    REFERENCES sites(id);

COMMIT;
