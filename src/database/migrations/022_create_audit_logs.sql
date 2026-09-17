-- =========================================================
-- 022_create_audit_logs.sql
-- Journal d'audit des actions importantes
-- =========================================================

CREATE TABLE audit_logs (
    id BIGSERIAL PRIMARY KEY,

    user_id BIGINT NOT NULL,

    site_id BIGINT,

    action VARCHAR(50) NOT NULL,

    table_cible VARCHAR(100),

    enregistrement_id BIGINT,

    anciennes_valeurs JSONB,

    nouvelles_valeurs JSONB,

    raison TEXT,

    date_heure TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT fk_audit_logs_user
        FOREIGN KEY (user_id)
        REFERENCES users(id),

    CONSTRAINT fk_audit_logs_site
        FOREIGN KEY (site_id)
        REFERENCES sites(id)
);