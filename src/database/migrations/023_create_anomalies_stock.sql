CREATE TYPE anomalie_stock_statut AS ENUM (
    'a_traiter',
    'en_cours',
    'resolue'
);

CREATE TABLE anomalies_stock (
    id BIGSERIAL PRIMARY KEY,

    activite_id BIGINT NOT NULL,
    produit_id BIGINT NOT NULL,

    quantite_attendue INTEGER NOT NULL,
    quantite_constatee INTEGER NOT NULL,
    ecart INTEGER NOT NULL,

    motif TEXT,

    statut anomalie_stock_statut NOT NULL DEFAULT 'a_traiter',

    signalee_par BIGINT NOT NULL,
    resolue_par BIGINT,

    date_signalement TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    date_resolution TIMESTAMPTZ,

    note_resolution TEXT,

    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT fk_anomalies_stock_activite
        FOREIGN KEY (activite_id)
        REFERENCES activites(id),

    CONSTRAINT fk_anomalies_stock_produit
        FOREIGN KEY (produit_id)
        REFERENCES produits(id),

    CONSTRAINT fk_anomalies_stock_signalee_par
        FOREIGN KEY (signalee_par)
        REFERENCES users(id),

    CONSTRAINT fk_anomalies_stock_resolue_par
        FOREIGN KEY (resolue_par)
        REFERENCES users(id),

    CONSTRAINT chk_anomalies_stock_quantite_attendue
        CHECK (quantite_attendue >= 0),

    CONSTRAINT chk_anomalies_stock_quantite_constatee
        CHECK (quantite_constatee >= 0),

    CONSTRAINT chk_anomalies_stock_ecart
        CHECK (ecart = quantite_constatee - quantite_attendue),

    CONSTRAINT chk_anomalies_stock_resolution
        CHECK (
            statut <> 'resolue'
            OR (
                resolue_par IS NOT NULL
                AND date_resolution IS NOT NULL
            )
        )
);