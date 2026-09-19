-- =========================================================
-- 011_create_activites.sql
-- Activités des vendeurs pendant une journée
-- =========================================================
CREATE TYPE activite_periode AS ENUM (
    'matin',
    'soir',
    'journee'
);

CREATE TYPE activite_statut AS ENUM (
    'en_cours',
    'terminee',
    'annulee',
    'anomalie'
);

CREATE TABLE activites (

    id BIGSERIAL PRIMARY KEY,

    journee_id BIGINT NOT NULL,

    vendeur_id BIGINT NOT NULL,

    sac_id BIGINT NOT NULL,

    periode activite_periode NOT NULL,

    statut activite_statut NOT NULL DEFAULT 'en_cours',

    date_debut TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    date_fin TIMESTAMPTZ,

    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT fk_activites_journee
        FOREIGN KEY (journee_id)
        REFERENCES journees(id),

    CONSTRAINT fk_activites_vendeur
        FOREIGN KEY (vendeur_id)
        REFERENCES vendeurs(id),

    CONSTRAINT fk_activites_sac
        FOREIGN KEY (sac_id)
        REFERENCES sacs(id),

    CONSTRAINT chk_activite_dates
        CHECK (
            date_fin IS NULL
            OR date_fin >= date_debut
        ),

    CONSTRAINT chk_activite_statut_dates
        CHECK (
            (
                statut = 'en_cours'
                AND date_fin IS NULL
            )
            OR
            (
                statut IN ('terminee', 'annulee')
                AND date_fin IS NOT NULL
            )
            OR
            (
                statut = 'anomalie'
            )
        )
);

ALTER TABLE activites
    ADD CONSTRAINT uq_activite_vendeur
    UNIQUE (id, vendeur_id);


-- Un vendeur ne peut avoir qu'une activité
-- en cours pendant une même journée.
CREATE UNIQUE INDEX uq_activite_vendeur_active
ON activites (journee_id, vendeur_id)
WHERE statut = 'en_cours';

-- Un sac ne peut être utilisé que par une seule
-- activité en cours à la fois.
CREATE UNIQUE INDEX uq_activite_sac_active
ON activites (sac_id)
WHERE statut = 'en_cours';