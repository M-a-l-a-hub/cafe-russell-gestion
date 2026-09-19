-- =========================================================
-- 004_create_categories_transport.sql
-- Catégories de distance pour le transport
-- =========================================================

CREATE TABLE categories_transport (

    id BIGSERIAL PRIMARY KEY,

    code VARCHAR(50) NOT NULL UNIQUE,

    nom VARCHAR(150) NOT NULL,

    description TEXT,

    actif BOOLEAN NOT NULL DEFAULT TRUE,

    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()

);