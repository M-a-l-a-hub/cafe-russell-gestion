-- =========================================================
-- 003_create_site_responsables.sql
-- Affectation des responsables aux sites
-- =========================================================

CREATE TYPE responsable_type AS ENUM (
    'principal',
    'adjoint'
);

CREATE TABLE site_responsables (
    id BIGSERIAL PRIMARY KEY,

    site_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,

    type responsable_type NOT NULL,

    date_debut DATE NOT NULL,
    date_fin DATE,

    actif BOOLEAN NOT NULL DEFAULT TRUE,

    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT fk_site_responsables_site
        FOREIGN KEY (site_id)
        REFERENCES sites(id),

    CONSTRAINT fk_site_responsables_user
        FOREIGN KEY (user_id)
        REFERENCES users(id),

    CONSTRAINT chk_site_responsables_dates
        CHECK (
            date_fin IS NULL
            OR date_fin >= date_debut
        )
);

-- Un seul responsable principal actif par site
CREATE UNIQUE INDEX uq_site_responsable_principal_actif
ON site_responsables (site_id)
WHERE type = 'principal'
  AND actif = TRUE;

-- Évite de créer exactement deux fois la même affectation active
CREATE UNIQUE INDEX uq_site_responsable_user_site_actif
ON site_responsables (site_id, user_id)
WHERE actif = TRUE;