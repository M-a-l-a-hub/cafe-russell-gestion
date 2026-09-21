-- Les sacs 19 et 66 sont Russell ; tous les autres sont standards.
-- NOT VALID permet de migrer une base existante sans réécrire son historique.
ALTER TABLE sacs
ADD CONSTRAINT chk_sacs_numero_categorie_russell
CHECK (
    (numero IN ('19', '66') AND categorie = 'russell')
    OR
    (numero NOT IN ('19', '66') AND categorie = 'standard')
) NOT VALID;