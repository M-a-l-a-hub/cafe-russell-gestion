-- =========================================================
-- 015_create_retour_lignes.sql
-- Détail des produits retournés
-- =========================================================

CREATE TABLE retour_lignes (
    id BIGSERIAL PRIMARY KEY,

    retour_id BIGINT NOT NULL,

    produit_id BIGINT NOT NULL,

    quantite INTEGER NOT NULL,

    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT fk_retour_lignes_retour
        FOREIGN KEY (retour_id)
        REFERENCES retours(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_retour_lignes_produit
        FOREIGN KEY (produit_id)
        REFERENCES produits(id),

    CONSTRAINT chk_retour_lignes_quantite
        CHECK (quantite >= 0),

    CONSTRAINT uq_retour_produit
        UNIQUE (retour_id, produit_id)
);