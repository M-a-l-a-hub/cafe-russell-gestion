-- =========================================================
-- 007_create_sacs.sql
-- Création des sacs
-- =========================================================

CREATE TYPE sac_categorie AS ENUM (
    'standard',
    'russell'
);

CREATE TYPE sac_statut AS ENUM (
    'libre',
    'en_utilisation',
    'indisponible'
);

CREATE TABLE sacs (
    id BIGSERIAL PRIMARY KEY,

    site_id BIGINT NOT NULL,

    numero VARCHAR(50) NOT NULL,

    categorie sac_categorie NOT NULL DEFAULT 'standard',

    statut sac_statut NOT NULL DEFAULT 'libre',

    motif_indisponibilite TEXT,

    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT fk_sacs_site
        FOREIGN KEY (site_id)
        REFERENCES sites(id),

    CONSTRAINT uq_sac_site_numero
        UNIQUE (site_id, numero),

    CONSTRAINT chk_sac_motif_indisponibilite
        CHECK (
            statut = 'indisponible'
            OR motif_indisponibilite IS NULL
        )
);