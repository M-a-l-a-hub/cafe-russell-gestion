-- =========================================================
-- 018_create_retraits.sql
-- Retraits effectués sur le solde des vendeurs
-- =========================================================

CREATE TABLE retraits (
    id BIGSERIAL PRIMARY KEY,

    vendeur_id BIGINT NOT NULL,

    montant NUMERIC(12, 2) NOT NULL,

    effectue_par BIGINT NOT NULL,

    date TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    motif TEXT,

    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT fk_retraits_vendeur
        FOREIGN KEY (vendeur_id)
        REFERENCES vendeurs(id),

    CONSTRAINT fk_retraits_effectue_par
        FOREIGN KEY (effectue_par)
        REFERENCES users(id),

    CONSTRAINT chk_retraits_montant
        CHECK (montant > 0)
);