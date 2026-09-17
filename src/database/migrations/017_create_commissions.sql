CREATE TABLE commissions (
    id BIGSERIAL PRIMARY KEY,

    activite_id BIGINT NOT NULL,
    vendeur_id BIGINT NOT NULL,

    ca NUMERIC(12,2) NOT NULL,
    taux NUMERIC(5,2) NOT NULL DEFAULT 25.00,
    montant NUMERIC(12,2) NOT NULL,

    date DATE NOT NULL DEFAULT CURRENT_DATE,

    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT fk_commissions_activite_vendeur
        FOREIGN KEY (activite_id, vendeur_id)
        REFERENCES activites (id, vendeur_id),

    CONSTRAINT chk_commissions_ca
        CHECK (ca >= 0),

    CONSTRAINT chk_commissions_taux
        CHECK (taux >= 0 AND taux <= 100),

    CONSTRAINT chk_commissions_montant
        CHECK (
            montant = ROUND(ca * taux / 100, 2)
        ),

    CONSTRAINT uq_commission_activite
        UNIQUE (activite_id)
);