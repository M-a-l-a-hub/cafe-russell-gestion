-- =========================================================
-- 016_create_controles_financiers.sql
-- Contrôle financier d'une activité
-- =========================================================

CREATE TYPE controle_financier_statut AS ENUM (
    'aucun_ecart',
    'ecart_a_expliquer',
    'ecart_en_cours_de_resolution',
    'ecart_resolu'
);

CREATE TABLE controles_financiers (
    id BIGSERIAL PRIMARY KEY,

    activite_id BIGINT NOT NULL UNIQUE,

    ca_theorique NUMERIC(12, 2) NOT NULL,

    montant_remis NUMERIC(12, 2) NOT NULL,

    ecart NUMERIC(12, 2) NOT NULL,

    statut controle_financier_statut NOT NULL DEFAULT 'aucun_ecart',

    motif_ecart TEXT,

    controle_par BIGINT NOT NULL,

    date_heure TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    note TEXT,

    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT fk_controles_financiers_activite
        FOREIGN KEY (activite_id)
        REFERENCES activites(id),

    CONSTRAINT fk_controles_financiers_controle_par
        FOREIGN KEY (controle_par)
        REFERENCES users(id),

    CONSTRAINT chk_controles_financiers_ca
        CHECK (ca_theorique >= 0),

    CONSTRAINT chk_controles_financiers_montant_remis
        CHECK (montant_remis >= 0),

    CONSTRAINT chk_controles_financiers_ecart
        CHECK (ecart = montant_remis - ca_theorique),

    CONSTRAINT chk_controles_financiers_statut
        CHECK (
            (
                ecart = 0
                AND statut = 'aucun_ecart'
                AND motif_ecart IS NULL
            )
            OR
            (
                ecart <> 0
                AND statut IN (
                    'ecart_a_expliquer',
                    'ecart_en_cours_de_resolution',
                    'ecart_resolu'
                )
                AND motif_ecart IS NOT NULL
            )
        )
);