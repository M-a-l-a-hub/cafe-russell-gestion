-- =========================================================
-- 012_create_ravitaillements.sql
-- Ravitaillements des vendeurs
-- =========================================================

CREATE TABLE ravitaillements (
    id BIGSERIAL PRIMARY KEY,

    activite_id BIGINT NOT NULL,

    effectue_par BIGINT NOT NULL,

    date_heure TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    -- Montant du transport applicable
    -- à ce ravitaillement au moment où il est effectué.
    transport_par_ravitaillement NUMERIC(12, 2) NOT NULL,

    note TEXT,

    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT fk_ravitaillements_activite
        FOREIGN KEY (activite_id)
        REFERENCES activites(id),

    CONSTRAINT fk_ravitaillements_effectue_par
        FOREIGN KEY (effectue_par)
        REFERENCES users(id),

    CONSTRAINT chk_ravitaillement_transport
        CHECK (transport_par_ravitaillement >= 0)
);