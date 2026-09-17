-- =========================================================
-- 006_create_vendeurs.sql
-- Création des vendeurs
-- =========================================================

CREATE TABLE vendeurs (
    id BIGSERIAL PRIMARY KEY,

    code VARCHAR(50) NOT NULL UNIQUE,

    nom VARCHAR(100) NOT NULL,

    prenom VARCHAR(100),

    telephone VARCHAR(30),

    photo TEXT,

    periode_habituelle VARCHAR(20),

    categorie_transport_id BIGINT NOT NULL,

    actif BOOLEAN NOT NULL DEFAULT TRUE,

    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT fk_vendeurs_categorie_transport
        FOREIGN KEY (categorie_transport_id)
        REFERENCES categories_transport(id),

    CONSTRAINT chk_vendeur_periode
        CHECK (
            periode_habituelle IS NULL
            OR periode_habituelle IN ('matin', 'soir', 'journee')
        )
);