-- =========================================================
-- 026_activites_historique_sac_et_vendeur.sql
--
-- 1. Fige la catégorie du sac au moment de l'activité :
--    une ancienne activité ne change plus si la catégorie
--    du sac est modifiée plus tard.
-- 2. Un seul sac actif par vendeur à un instant donné,
--    tous sites confondus.
-- =========================================================
BEGIN;

ALTER TABLE activites
    ADD COLUMN sac_categorie sac_categorie;

UPDATE activites a
SET sac_categorie = s.categorie
FROM sacs s
WHERE s.id = a.sac_id;

ALTER TABLE activites
    ALTER COLUMN sac_categorie SET NOT NULL;

CREATE FUNCTION activites_figer_categorie_sac()
RETURNS TRIGGER AS $$
BEGIN
    IF TG_OP = 'INSERT' THEN
        IF NEW.sac_categorie IS NULL THEN
            SELECT categorie
            INTO NEW.sac_categorie
            FROM sacs
            WHERE id = NEW.sac_id;
        END IF;

        RETURN NEW;
    END IF;

    IF NEW.sac_categorie IS DISTINCT FROM OLD.sac_categorie THEN
        RAISE EXCEPTION
            'La catégorie de sac d''une activité ne peut pas être modifiée (activité %).',
            OLD.id;
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_activites_figer_categorie_sac
BEFORE INSERT OR UPDATE ON activites
FOR EACH ROW
EXECUTE FUNCTION activites_figer_categorie_sac();

-- Un vendeur ne peut avoir qu'une seule activité en cours,
-- même si elle est sur un autre site.
CREATE UNIQUE INDEX uq_activite_vendeur_en_cours
ON activites (vendeur_id)
WHERE statut = 'en_cours';

COMMIT;
