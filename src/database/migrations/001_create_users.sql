-- =========================================================
-- 001_create_users.sql
-- Table des utilisateurs de Café Russell
-- =========================================================

CREATE TYPE user_role AS ENUM (
    'super_admin',
    'responsable'
);

CREATE TABLE users (
    id BIGSERIAL PRIMARY KEY,

    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100),

    telephone VARCHAR(30) NOT NULL UNIQUE,
    email VARCHAR(255) UNIQUE,

    password_hash TEXT NOT NULL,

    role user_role NOT NULL,

    photo TEXT,

    actif BOOLEAN NOT NULL DEFAULT TRUE,

    last_login_at TIMESTAMPTZ,

    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);