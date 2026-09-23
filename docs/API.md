# API Café Russell

Toutes les réponses sont du JSON :

- succès : `{"ok": true, "donnees": ...}`
- erreur : `{"ok": false, "erreur": {"code": "...", "message": "..."}}`

| Statut | Signification |
|---|---|
| 401 | pas connecté (ou session expirée après 12 h d'inactivité) |
| 403 | connecté mais pas le droit (autre site, action réservée à Russell, jeton CSRF invalide) |
| 404 | ressource ou route introuvable |
| 409 | règle métier refusée (message en français, à afficher tel quel) |
| 422 | donnée manquante ou invalide |
| 429 | trop d'échecs de connexion |
| 500 | erreur interne (détail dans `storage/logs/app.log`) |

## Connexion

1. `POST /api/login` avec `{"telephone": "...", "mot_de_passe": "..."}`
   → renvoie l'utilisateur et un **jeton `csrf`**. Le cookie de session est posé automatiquement.
2. Toutes les requêtes `POST` suivantes envoient l'en-tête `X-CSRF-Token: <jeton>` et `Content-Type: application/json`.
3. Au rechargement de l'application : `GET /api/moi` (renvoie l'utilisateur et le jeton, ou 401).
4. `POST /api/logout`.

## Routes

Lectures (GET)

| Route | Rôle |
|---|---|
| `/api/sites` | sites accessibles |
| `/api/produits` | produits + prix du jour (coût visible par Russell seulement) |
| `/api/vendeurs` | vendeurs actifs |
| `/api/categories-transport` | catégories de transport avec leur tarif actuel |
| `/api/categories-depenses` | catégories de dépenses |
| `/api/sites/{id}/sacs` | sacs du site |
| `/api/sites/{id}/journees?statut=ouverte&limite=30` | journées du site |
| `/api/sites/{id}/depenses?date=AAAA-MM-JJ` | dépenses du site (aujourd'hui par défaut) |
| `/api/journees/{id}/rapport` | rapport de la journée |
| `/api/activites/{id}` | détail et calculs d'une activité |
| `/api/vendeurs/{id}/solde?site_id=` | solde d'un vendeur sur un site |
| `/api/ecarts?site_id=` | écarts financiers non résolus |
| `/api/anomalies?site_id=` | anomalies de stock non résolues |

Actions (POST)

| Route | Corps |
|---|---|
| `/api/journees` | `site_id, date, note?` |
| `/api/journees/{id}/cloture` | `note?` |
| `/api/journees/{id}/reouverture` | `raison` (Russell) |
| `/api/journees/{id}/ravitaillements` | `vendeur_id, lignes[{produit_id, quantite}], sac_id?, periode?, note?` |
| `/api/journees/{id}/activites` | `vendeur_id, sac_id, periode` |
| `/api/activites/{id}/ravitaillements` | `lignes, note?` |
| `/api/activites/{id}/retours` | `lignes, note?` |
| `/api/activites/{id}/cloture` | `montant_remis, motif_ecart?, note?` |
| `/api/activites/{id}/annulation` | `motif` |
| `/api/activites/{id}/anomalies` | `produit_id, quantite_attendue, quantite_constatee, motif?` |
| `/api/anomalies/{id}/traitement` | `note?` |
| `/api/anomalies/{id}/resolution` | `note_resolution` |
| `/api/retraits` | `vendeur_id, site_id, montant, motif?` |
| `/api/paiements-soldes` | `vendeur_id, site_id, montant, note?` |
| `/api/depenses` | `categorie_id, libelle, montant, site_id, date?, justificatif?, note?` |
| `/api/ecarts/{id}/mise-en-resolution` | `motif` |
| `/api/ecarts/{id}/resolution` | `motif` (Russell) |
| `/api/tarifs` | `type (prix\|cout\|transport), entite_id, valeur, date_debut, motif?` (Russell) |
| `/api/vendeurs` | `nom, prenom?, telephone?, categorie_transport_id, periode_habituelle?` (code généré automatiquement) |
| `/api/vendeurs/{id}/categorie-transport` | `categorie_transport_id, motif?` |
| `/api/vendeurs/{id}/desactivation` | `motif` |
| `/api/sacs` | `site_id, numero, categorie` |
| `/api/sacs/{id}/indisponibilite` | `motif` |
| `/api/sacs/{id}/disponibilite` | `note?` |
| `/api/sacs/{id}/deplacement` | `site_arrivee_id, motif, note?` |

## Essai rapide (curl)

```bash
php bin/definir-mot-de-passe.php +243000000001
php -S 127.0.0.1:8000 -t public public/index.php

curl -s localhost:8000/api/sante

curl -s -c cookies.txt -H 'Content-Type: application/json' \
  -d '{"telephone":"+243000000001","mot_de_passe":"TON_MOT_DE_PASSE"}' \
  localhost:8000/api/login
# → note la valeur de "csrf"

curl -s -b cookies.txt localhost:8000/api/sites

curl -s -b cookies.txt -H 'Content-Type: application/json' \
  -H 'X-CSRF-Token: LE_JETON' \
  -d '{"site_id":1,"date":"2026-09-22"}' \
  localhost:8000/api/journees
```
