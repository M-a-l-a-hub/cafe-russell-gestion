-- =========================================================
-- 008_create_produits.sql
-- Création des produits
-- =========================================================

CREATE TABLE produits (
    id BIGSERIAL PRIMARY KEY,

    code VARCHAR(50) NOT NULL UNIQUE,

    nom VARCHAR(150) NOT NULL,

    description TEXT,

    unite VARCHAR(50) NOT NULL DEFAULT 'piece',

    actif BOOLEAN NOT NULL DEFAULT TRUE,

    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);