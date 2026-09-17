CREATE EXTENSION IF NOT EXISTS btree_gist;

CREATE TABLE transport_prix (
    id BIGSERIAL PRIMARY KEY,

    categorie_transport_id BIGINT NOT NULL,

    montant_par_ravitaillement NUMERIC(12, 2) NOT NULL,

    date_debut DATE NOT NULL,
    date_fin DATE,

    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT fk_transport_prix_categorie
        FOREIGN KEY (categorie_transport_id)
        REFERENCES categories_transport(id),

    CONSTRAINT chk_transport_prix_montant
        CHECK (montant_par_ravitaillement >= 0),

    CONSTRAINT chk_transport_prix_dates
        CHECK (
            date_fin IS NULL
            OR date_fin >= date_debut
        )
);

ALTER TABLE transport_prix
ADD CONSTRAINT excl_transport_prix_periodes
EXCLUDE USING GIST (
    categorie_transport_id WITH =,
    daterange(
        date_debut,
        COALESCE(date_fin + 1, 'infinity'::date),
        '[)'
    ) WITH &&
);

CREATE UNIQUE INDEX uq_transport_prix_actuel
ON transport_prix (categorie_transport_id)
WHERE date_fin IS NULL;