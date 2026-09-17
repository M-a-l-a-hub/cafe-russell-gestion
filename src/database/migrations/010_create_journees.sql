-- =========================================================
-- 010_create_journees.sql
-- Gestion des journées par site
-- =========================================================

CREATE TYPE journee_statut AS ENUM (
    'ouverte',
    'cloturee'
);

CREATE TABLE journees (
    id BIGSERIAL PRIMARY KEY,

    site_id BIGINT NOT NULL,

    date_jour DATE NOT NULL,

    statut journee_statut NOT NULL DEFAULT 'ouverte',

    ouverte_par BIGINT NOT NULL,

    date_ouverture TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    cloturee_par BIGINT,

    date_cloture TIMESTAMPTZ,

    note_ouverture TEXT,

    note_cloture TEXT,

    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT fk_journees_site
        FOREIGN KEY (site_id)
        REFERENCES sites(id),

    CONSTRAINT fk_journees_ouverte_par
        FOREIGN KEY (ouverte_par)
        REFERENCES users(id),

    CONSTRAINT fk_journees_cloturee_par
        FOREIGN KEY (cloturee_par)
        REFERENCES users(id),

    CONSTRAINT uq_journee_site_date
        UNIQUE (site_id, date_jour),

    CONSTRAINT chk_journee_cloture
        CHECK (
            (
                statut = 'ouverte'
                AND cloturee_par IS NULL
                AND date_cloture IS NULL
            )
            OR
            (
                statut = 'cloturee'
                AND cloturee_par IS NOT NULL
                AND date_cloture IS NOT NULL
            )
        )
);

-- Un seul jour ouvert à la fois pour un même site
CREATE UNIQUE INDEX uq_journee_ouverte_par_site
ON journees (site_id)
WHERE statut = 'ouverte';