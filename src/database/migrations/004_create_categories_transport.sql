-- =========================================================
-- 004_create_categories_transport.sql
-- Catégories de distance et tarifs de transport
-- =========================================================

CREATE TABLE categories_transport (
    id BIGSERIAL PRIMARY KEY,

    code VARCHAR(50) NOT NULL UNIQUE,

    nom VARCHAR(150) NOT NULL,

    description TEXT,

    montant_par_ravitaillement NUMERIC(12, 2) NOT NULL,

    actif BOOLEAN NOT NULL DEFAULT TRUE,

    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT chk_categories_transport_montant
        CHECK (
            montant_par_ravitaillement >= 0
        )
);