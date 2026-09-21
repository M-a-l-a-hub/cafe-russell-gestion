<?php

declare(strict_types=1);

/**
 * Table des routes de l'API. Retourne le Routeur.
 *
 * Toutes les routes exigent une connexion, sauf celles marquées « public ».
 * Toute requête qui modifie des données (POST) exige l'en-tête
 * X-CSRF-Token reçu à la connexion.
 */

$r = new Routeur();

$auth = new AuthControleur();
$lectures = new LecturesControleur();
$journees = new JourneesControleur();
$activites = new ActivitesControleur();
$finances = new FinancesControleur();
$sacs = new SacsControleur();

$russell = ['role' => 'super_admin'];

// --- Session -----------------------------------------------------------
$r->get('/api/sante', [$auth, 'sante'], ['public' => true, 'sans_session' => true]);
$r->post('/api/login', [$auth, 'connexion'], ['public' => true]);
$r->post('/api/logout', [$auth, 'deconnexion']);
$r->get('/api/moi', [$auth, 'moi']);

// --- Lectures ----------------------------------------------------------
$r->get('/api/sites', [$lectures, 'sites']);
$r->get('/api/produits', [$lectures, 'produits']);
$r->get('/api/vendeurs', [$lectures, 'vendeurs']);
$r->get('/api/categories-depenses', [$lectures, 'categoriesDepenses']);
$r->get('/api/sites/{id}/sacs', [$lectures, 'sacsSite']);
$r->get('/api/sites/{id}/journees', [$lectures, 'journeesSite']);
$r->get('/api/sites/{id}/depenses', [$lectures, 'depensesSite']);
$r->get('/api/activites/{id}', [$lectures, 'activite']);
$r->get('/api/vendeurs/{id}/solde', [$lectures, 'soldeVendeur']);
$r->get('/api/ecarts', [$lectures, 'ecarts']);
$r->get('/api/anomalies', [$lectures, 'anomalies']);

// --- Journées ----------------------------------------------------------
$r->post('/api/journees', [$journees, 'ouvrir']);
$r->get('/api/journees/{id}/rapport', [$journees, 'rapport']);
$r->post('/api/journees/{id}/cloture', [$journees, 'cloturer']);
$r->post('/api/journees/{id}/reouverture', [$journees, 'rouvrir'], $russell);
$r->post('/api/journees/{id}/ravitaillements', [$journees, 'ravitaillerVendeur']);
$r->post('/api/journees/{id}/activites', [$journees, 'demarrerActivite']);

// --- Activités ---------------------------------------------------------
$r->post('/api/activites/{id}/ravitaillements', [$activites, 'ravitailler']);
$r->post('/api/activites/{id}/retours', [$activites, 'retourner']);
$r->post('/api/activites/{id}/cloture', [$activites, 'cloturer']);
$r->post('/api/activites/{id}/annulation', [$activites, 'annuler']);
$r->post('/api/activites/{id}/anomalies', [$activites, 'signalerAnomalie']);
$r->post('/api/anomalies/{id}/traitement', [$activites, 'demarrerTraitementAnomalie']);
$r->post('/api/anomalies/{id}/resolution', [$activites, 'resoudreAnomalie']);

// --- Argent ------------------------------------------------------------
$r->post('/api/retraits', [$finances, 'retrait']);
$r->post('/api/paiements-soldes', [$finances, 'paiementSolde']);
$r->post('/api/depenses', [$finances, 'depense']);
$r->post('/api/ecarts/{id}/mise-en-resolution', [$finances, 'mettreEnResolution']);
$r->post('/api/ecarts/{id}/resolution', [$finances, 'resoudreEcart'], $russell);
$r->post('/api/tarifs', [$finances, 'changerTarif'], $russell);

// --- Sacs --------------------------------------------------------------
$r->post('/api/sacs', [$sacs, 'creer']);
$r->post('/api/sacs/{id}/indisponibilite', [$sacs, 'rendreIndisponible']);
$r->post('/api/sacs/{id}/disponibilite', [$sacs, 'rendreDisponible']);
$r->post('/api/sacs/{id}/deplacement', [$sacs, 'deplacer']);

return $r;
