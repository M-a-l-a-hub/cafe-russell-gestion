-- =========================================================
-- 009_create_produit_prix.sql
-- Historique des prix de vente des produits
-- =========================================================

CREATE TABLE produit_prix (
    id BIGSERIAL PRIMARY KEY,

    produit_id BIGINT NOT NULL,

    prix NUMERIC(12, 2) NOT NULL,

    date_debut DATE NOT NULL,

    date_fin DATE,

    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT fk_produit_prix_produit
        FOREIGN KEY (produit_id)
        REFERENCES produits(id),

    CONSTRAINT chk_produit_prix_montant
        CHECK (prix > 0),

    CONSTRAINT chk_produit_prix_dates
        CHECK (
            date_fin IS NULL
            OR date_fin >= date_debut
        )
);

-- Extension nécessaire pour la contrainte d'exclusion
CREATE EXTENSION IF NOT EXISTS btree_gist;

-- Empêche deux périodes de prix de se chevaucher
ALTER TABLE produit_prix
ADD CONSTRAINT excl_produit_prix_periodes
EXCLUDE USING GIST (
    produit_id WITH =,
    daterange(
        date_debut,
        COALESCE(date_fin + 1, 'infinity'::date),
        '[)'
    ) WITH &&
);

-- Un seul prix actuellement actif par produit
CREATE UNIQUE INDEX uq_produit_prix_actuel
ON produit_prix (produit_id)
WHERE date_fin IS NULL;