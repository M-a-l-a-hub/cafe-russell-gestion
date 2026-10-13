-- =========================================================
-- 032_create_sessions.sql
-- Sessions de connexion stockées en base.
--
-- Sur Render le disque est éphémère : avec les sessions PHP par
-- fichiers, chaque redéploiement déconnectait tout le monde.
-- Les données de session (identifiant, jeton CSRF, dernière activité)
-- sont encodées en base64 pour éviter tout problème d'octets nuls.
-- =========================================================

CREATE TABLE IF NOT EXISTS sessions (
    id VARCHAR(128) PRIMARY KEY,

    donnees TEXT NOT NULL DEFAULT '',

    derniere_activite TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_sessions_derniere_activite
    ON sessions (derniere_activite);
