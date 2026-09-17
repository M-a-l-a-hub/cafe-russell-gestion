-- =========================================================
-- 019_create_paiements_soldes.sql
-- Paiements des soldes des vendeurs
-- =========================================================

CREATE TABLE paiements_soldes (
    id BIGSERIAL PRIMARY KEY,

    vendeur_id BIGINT NOT NULL,

    montant NUMERIC(12, 2) NOT NULL,

    effectue_par BIGINT NOT NULL,

    date TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    note TEXT,

    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT fk_paiements_soldes_vendeur
        FOREIGN KEY (vendeur_id)
        REFERENCES vendeurs(id),

    CONSTRAINT fk_paiements_soldes_effectue_par
        FOREIGN KEY (effectue_par)
        REFERENCES users(id),

    CONSTRAINT chk_paiements_soldes_montant
        CHECK (montant > 0)
);