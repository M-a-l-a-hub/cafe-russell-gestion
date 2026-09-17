CREATE TABLE ravitaillement_lignes (
    id BIGSERIAL PRIMARY KEY,

    ravitaillement_id BIGINT NOT NULL,
    produit_id BIGINT NOT NULL,

    quantite INTEGER NOT NULL,

    prix_unitaire NUMERIC(12,2) NOT NULL,
    cout_unitaire NUMERIC(12,2) NOT NULL,

    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT fk_ravitaillement_lignes_ravitaillement
        FOREIGN KEY (ravitaillement_id)
        REFERENCES ravitaillements(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_ravitaillement_lignes_produit
        FOREIGN KEY (produit_id)
        REFERENCES produits(id),

    CONSTRAINT chk_ravitaillement_lignes_quantite
        CHECK (quantite > 0),

    CONSTRAINT chk_ravitaillement_lignes_prix
        CHECK (prix_unitaire > 0),

    CONSTRAINT chk_ravitaillement_lignes_cout
        CHECK (cout_unitaire > 0),

    CONSTRAINT uq_ravitaillement_produit
        UNIQUE (ravitaillement_id, produit_id)
);