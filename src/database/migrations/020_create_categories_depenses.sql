-- =========================================================
-- 020_create_categories_depenses.sql
-- Catégories des dépenses
-- =========================================================

CREATE TABLE categories_depenses (
    id BIGSERIAL PRIMARY KEY,

    code VARCHAR(50) NOT NULL UNIQUE,

    nom VARCHAR(150) NOT NULL,

    description TEXT,

    actif BOOLEAN NOT NULL DEFAULT TRUE,

    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);