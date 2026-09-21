-- =========================================================
-- 029_create_tentatives_connexion.sql
-- Journal des tentatives de connexion (limitation anti force brute)
-- =========================================================

CREATE TABLE tentatives_connexion (
    id BIGSERIAL PRIMARY KEY,
    telephone VARCHAR(30) NOT NULL,
    adresse_ip VARCHAR(45) NOT NULL DEFAULT '',
    reussie BOOLEAN NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_tentatives_connexion_telephone
ON tentatives_connexion (telephone, created_at);
