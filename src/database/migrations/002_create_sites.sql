-- =========================================================
-- 002_create_sites.sql
-- Table des sites de Café Russell
-- =========================================================

CREATE TABLE sites (
    id BIGSERIAL PRIMARY KEY,

    code VARCHAR(50) NOT NULL UNIQUE,
    nom VARCHAR(150) NOT NULL,

    adresse TEXT,
    description TEXT,

    actif BOOLEAN NOT NULL DEFAULT TRUE,

    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);