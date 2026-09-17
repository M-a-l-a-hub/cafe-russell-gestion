CREATE TABLE produit_couts (
    id BIGSERIAL PRIMARY KEY,

    produit_id BIGINT NOT NULL,

    cout_unitaire NUMERIC(12, 2) NOT NULL,

    date_debut DATE NOT NULL,
    date_fin DATE,

    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT fk_produit_couts_produit
        FOREIGN KEY (produit_id)
        REFERENCES produits(id),

    CONSTRAINT chk_produit_couts_montant
        CHECK (cout_unitaire > 0),

    CONSTRAINT chk_produit_couts_dates
        CHECK (
            date_fin IS NULL
            OR date_fin >= date_debut
        )
);

ALTER TABLE produit_couts
ADD CONSTRAINT excl_produit_couts_periodes
EXCLUDE USING GIST (
    produit_id WITH =,
    daterange(
        date_debut,
        COALESCE(date_fin + 1, 'infinity'::date),
        '[)'
    ) WITH &&
);

CREATE UNIQUE INDEX uq_produit_cout_actuel
ON produit_couts (produit_id)
WHERE date_fin IS NULL;