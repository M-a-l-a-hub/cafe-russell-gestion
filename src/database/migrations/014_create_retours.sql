-- =========================================================
-- 014_create_retours.sql
-- Retours de stock à la fin d'une activité
-- =========================================================

CREATE TABLE retours (
    id BIGSERIAL PRIMARY KEY,

    activite_id BIGINT NOT NULL UNIQUE,

    effectue_par BIGINT NOT NULL,

    date_heure TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    note TEXT,

    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT fk_retours_activite
        FOREIGN KEY (activite_id)
        REFERENCES activites(id),

    CONSTRAINT fk_retours_effectue_par
        FOREIGN KEY (effectue_par)
        REFERENCES users(id)
);