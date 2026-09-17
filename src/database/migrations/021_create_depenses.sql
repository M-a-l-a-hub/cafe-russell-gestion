-- =========================================================
-- 021_create_depenses.sql
-- Dépenses de Café Russell
-- =========================================================

CREATE TABLE depenses (
    id BIGSERIAL PRIMARY KEY,

    site_id BIGINT,

    categorie_id BIGINT NOT NULL,

    libelle VARCHAR(255) NOT NULL,

    montant NUMERIC(12, 2) NOT NULL,

    date_depense DATE NOT NULL DEFAULT CURRENT_DATE,

    effectue_par BIGINT NOT NULL,

    justificatif TEXT,

    note TEXT,

    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT fk_depenses_site
        FOREIGN KEY (site_id)
        REFERENCES sites(id),

    CONSTRAINT fk_depenses_categorie
        FOREIGN KEY (categorie_id)
        REFERENCES categories_depenses(id),

    CONSTRAINT fk_depenses_effectue_par
        FOREIGN KEY (effectue_par)
        REFERENCES users(id),

    CONSTRAINT chk_depenses_montant
        CHECK (montant > 0)
);