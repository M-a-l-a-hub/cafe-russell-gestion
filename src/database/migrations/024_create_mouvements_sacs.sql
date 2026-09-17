CREATE TABLE mouvements_sacs (
    id BIGSERIAL PRIMARY KEY,

    sac_id BIGINT NOT NULL,

    site_depart_id BIGINT NOT NULL,
    site_arrivee_id BIGINT NOT NULL,

    effectue_par BIGINT NOT NULL,

    date_heure TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    motif VARCHAR(255) NOT NULL,
    note TEXT,

    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT fk_mouvements_sacs_sac
        FOREIGN KEY (sac_id)
        REFERENCES sacs(id),

    CONSTRAINT fk_mouvements_sacs_site_depart
        FOREIGN KEY (site_depart_id)
        REFERENCES sites(id),

    CONSTRAINT fk_mouvements_sacs_site_arrivee
        FOREIGN KEY (site_arrivee_id)
        REFERENCES sites(id),

    CONSTRAINT fk_mouvements_sacs_effectue_par
        FOREIGN KEY (effectue_par)
        REFERENCES users(id),

    CONSTRAINT chk_mouvements_sacs_sites_differents
        CHECK (site_depart_id <> site_arrivee_id)
);