-- Rejeu sûr des saisies envoyées depuis la file d'attente hors ligne :
-- une même clé (en-tête Idempotency-Key) ne s'exécute qu'une fois.
CREATE TABLE requetes_idempotentes (
    cle VARCHAR(64) PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id),
    methode VARCHAR(10) NOT NULL,
    chemin VARCHAR(255) NOT NULL,
    statut_http SMALLINT NOT NULL,
    reponse TEXT NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_requetes_idempotentes_created_at
    ON requetes_idempotentes (created_at);
