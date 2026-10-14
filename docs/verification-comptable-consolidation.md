# Scénario de recette comptable — consolidation multi-sites

Ce scénario utilise uniquement des chiffres fictifs. Il ne se connecte pas à Neon et ne modifie aucune donnée.

## Règles à vérifier

- Commission vendeur : 25 % du chiffre d'affaires, y compris sur un sac de catégorie Russell.
- Les consommables sont comptés sur les quantités reçues, même lorsqu'une partie est retournée.
- Le bénéfice d'une activité = CA réellement vendu − transport − commission − consommables.
- Le bénéfice des sacs Russell reste séparé du bénéfice entreprise.
- Le net entreprise consolidé = bénéfice entreprise − dépenses de tous les sites − dépenses générales.

## Données fictives après correction d'un retour

Prix et coût unitaire : petite tasse vendue 500 FC ; consommables 200 FC par tasse reçue.

| Activité | Catégorie du sac | Reçues | Retournées | Vendues | CA | Consommables | Transport | Commission 25 % | Bénéfice |
|---|---|---:|---:|---:|---:|---:|---:|---:|---:|
| Site A | Entreprise | 100 | 20 | 80 | 40 000 FC | 20 000 FC | 4 000 FC | 10 000 FC | 6 000 FC |
| Site B | Entreprise | 50 | 10 | 40 | 20 000 FC | 10 000 FC | 2 000 FC | 5 000 FC | 3 000 FC |
| Site A | Russell (ex. sac 19) | 80 | 20 | 60 | 30 000 FC | 16 000 FC | 2 000 FC | 7 500 FC | 4 500 FC |

Les retours sont déjà inclus dans les quantités ci-dessus. Le CA et la commission utilisent les quantités vendues ; les consommables utilisent les quantités reçues.

## Résultats attendus

- CA total : 90 000 FC.
- Commissions vendeurs : 22 500 FC, y compris 7 500 FC sur le sac Russell.
- Bénéfice entreprise avant dépenses : 9 000 FC.
- Bénéfice Russell : 4 500 FC.
- Dépenses site A : 5 000 FC.
- Dépenses site B : 3 000 FC.
- Dépenses générales (sans site) : 2 000 FC.
- Total des dépenses déduites du bénéfice entreprise : 10 000 FC.
- **Bénéfice net entreprise consolidé attendu : −1 000 FC.**
- **Bénéfice Russell attendu : 4 500 FC, inchangé par les dépenses consolidées de l'entreprise.**

## Contrôles de recette

1. À la clôture, vérifier les CA, commissions et bénéfices de chaque activité.
2. Après réouverture de la journée et correction d'un retour, vérifier que CA, commission et écart financier sont recalculés.
3. Vérifier que le rapport attribue 9 000 FC à l'entreprise et 4 500 FC à Russell.
4. Vérifier que le tableau de bord soustrait les 8 000 FC de dépenses des sites et les 2 000 FC de dépenses générales du bénéfice entreprise seulement.
5. Vérifier qu'une dépense de site sans journée associée reste incluse dans le total des dépenses du site pour la date choisie.
6. Vérifier qu'aucune donnée réelle de production n'est utilisée dans cette recette.

## Limite

Ce document est un scénario de recette chiffré, pas un test automatisé exécuté contre les services PHP. Les résultats attendus sont calculés à partir des règles comptables décrites ; une exécution applicative nécessite un environnement de test isolé avec les migrations et une base de test.
