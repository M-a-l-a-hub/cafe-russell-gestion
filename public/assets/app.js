'use strict';

/* =====================================================================
 * Café Russell — application (connexion, journée, ravitaillement,
 * retour, clôture, écarts). Vanilla JS, sans dépendance externe.
 * ===================================================================== */

const etat = {
  utilisateur: null,
  csrf: null,
  sites: [],
  siteId: null,
  produits: [],
  vendeurs: [],
  sacs: [],
  categoriesTransport: [],
  journee: null,
  rapport: null,
};

/* ---------------------------------------------------------------------
 * Appels API
 * ------------------------------------------------------------------- */

async function api(chemin, options = {}) {
  const config = {
    method: options.method || 'GET',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json' },
  };

  if (options.method && options.method !== 'GET' && etat.csrf) {
    config.headers['X-CSRF-Token'] = etat.csrf;
  }

  if (options.corps) {
    config.body = JSON.stringify(options.corps);
  }

  let reponse;

  try {
    reponse = await fetch(chemin, config);
  } catch {
    throw new ErreurApi('reseau', "Impossible de joindre le serveur. Vérifie ta connexion.");
  }

  let donnees;

  try {
    donnees = await reponse.json();
  } catch {
    throw new ErreurApi('reponse_invalide', 'Réponse inattendue du serveur.');
  }

  if (!donnees.ok) {
    throw new ErreurApi(donnees.erreur.code, donnees.erreur.message, reponse.status);
  }

  return donnees.donnees;
}

class ErreurApi extends Error {
  constructor(code, message, statut) {
    super(message);
    this.code = code;
    this.statut = statut;
  }
}

/* ---------------------------------------------------------------------
 * Formatage
 * ------------------------------------------------------------------- */

function fc(montant) {
  return Math.round(Number(montant)).toLocaleString('fr-FR') + ' FC';
}

function dateLisible(iso) {
  return new Date(iso).toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' });
}

function aujourdhui() {
  // Date locale au format AAAA-MM-JJ, sans décalage de fuseau.
  const d = new Date();
  const decalage = d.getTimezoneOffset() * 60000;
  return new Date(d - decalage).toISOString().slice(0, 10);
}

/* ---------------------------------------------------------------------
 * Connexion
 * ------------------------------------------------------------------- */

const elConnexion = document.getElementById('ecran-connexion');
const elApp = document.getElementById('app');
const formConnexion = document.getElementById('form-connexion');
const erreurConnexion = document.getElementById('erreur-connexion');

formConnexion.addEventListener('submit', async (ev) => {
  ev.preventDefault();
  erreurConnexion.hidden = true;

  const bouton = formConnexion.querySelector('button');
  bouton.disabled = true;
  bouton.textContent = 'Connexion…';

  try {
    const { utilisateur, csrf } = await api('/api/login', {
      method: 'POST',
      corps: {
        telephone: formConnexion.telephone.value.trim(),
        mot_de_passe: formConnexion.mot_de_passe.value,
      },
    });

    etat.utilisateur = utilisateur;
    etat.csrf = csrf;
    formConnexion.mot_de_passe.value = '';
    await demarrerApp();
  } catch (e) {
    erreurConnexion.textContent = e instanceof ErreurApi
      ? e.message
      : 'Connexion impossible. Réessaie.';
    erreurConnexion.hidden = false;
  } finally {
    bouton.disabled = false;
    bouton.textContent = 'Se connecter';
  }
});

document.getElementById('bouton-deconnexion').addEventListener('click', async () => {
  try { await api('/api/logout', { method: 'POST' }); } catch { /* on quitte quand même */ }
  window.location.reload();
});

async function tenterSessionExistante() {
  try {
    const { utilisateur, csrf } = await api('/api/moi');
    if (utilisateur) {
      etat.utilisateur = utilisateur;
      etat.csrf = csrf;
      await demarrerApp();
      return;
    }
  } catch { /* pas connecté : on affiche l'écran de connexion */ }

  elConnexion.hidden = false;
}

/* ---------------------------------------------------------------------
 * Démarrage de l'application (après connexion)
 * ------------------------------------------------------------------- */

async function demarrerApp() {
  // On charge d'abord toutes les données : si une requête échoue, l'écran
  // de connexion reste affiché et peut montrer l'erreur.
  const [sites, produits, vendeurs, categoriesTransport] = await Promise.all([
    api('/api/sites'),
    api('/api/produits'),
    api('/api/vendeurs'),
    api('/api/categories-transport'),
  ]);

  etat.sites = sites;
  etat.produits = produits;
  etat.vendeurs = vendeurs;
  etat.categoriesTransport = categoriesTransport;

  elConnexion.hidden = true;
  elApp.classList.add('visible');

  document.getElementById('nom-utilisateur').textContent =
    `${etat.utilisateur.prenom} ${etat.utilisateur.nom}`;

  const selecteur = document.getElementById('selecteur-site');
  selecteur.innerHTML = sites.map((s) => `<option value="${s.id}">${s.nom}</option>`).join('');
  selecteur.hidden = sites.length <= 1 && etat.utilisateur.role !== 'super_admin';

  if (sites.length === 0) {
    afficherVue('accueil', '<p class="liste-vide">Aucun site accessible. Contacte Russell.</p>');
    return;
  }

  etat.siteId = sites[0].id;
  selecteur.value = String(etat.siteId);
  selecteur.addEventListener('change', () => {
    etat.siteId = Number(selecteur.value);
    const vueActive = document.querySelector('nav.bas button[data-vue].actif');
    allerA(vueActive ? vueActive.dataset.vue : 'accueil');
  });

  if (etat.utilisateur.role === 'super_admin') {
    const boutonExistant = document.querySelector('[data-role-site-creator]');
    if (boutonExistant) boutonExistant.remove();
    const gestionExistante = document.querySelector('[data-role-site-manager]');
    if (gestionExistante) gestionExistante.remove();
    const responsableExistant = document.querySelector('[data-role-responsable-creator]');
    if (responsableExistant) responsableExistant.remove();

    const actionSite = document.createElement('button');
    actionSite.type = 'button';
    actionSite.className = 'secondaire';
    actionSite.dataset.roleSiteCreator = 'true';
    actionSite.style.marginLeft = '12px';
    actionSite.textContent = '+ Site';
    actionSite.addEventListener('click', ouvrirModaleNouveauSite);
    selecteur.insertAdjacentElement('afterend', actionSite);

    const gestionSites = document.createElement('button');
    gestionSites.type = 'button';
    gestionSites.className = 'secondaire';
    gestionSites.dataset.roleSiteManager = 'true';
    gestionSites.style.marginLeft = '8px';
    gestionSites.textContent = 'Gérer les sites';
    gestionSites.addEventListener('click', ouvrirGestionSites);
    actionSite.insertAdjacentElement('afterend', gestionSites);

    const actionResponsable = document.createElement('button');
    actionResponsable.type = 'button';
    actionResponsable.className = 'secondaire';
    actionResponsable.dataset.roleResponsableCreator = 'true';
    actionResponsable.style.marginLeft = '8px';
    actionResponsable.textContent = '+ Responsable';
    actionResponsable.addEventListener('click', ouvrirModaleNouveauResponsable);
    gestionSites.insertAdjacentElement('afterend', actionResponsable);
  }

  document.querySelectorAll('nav.bas button[data-vue]').forEach((bouton) => {
    bouton.addEventListener('click', () => allerA(bouton.dataset.vue));
  });

  if (etat.utilisateur.role !== 'super_admin') {
    document.querySelectorAll('[data-russell-seulement]').forEach((el) => el.remove());
  }

  await allerA('accueil');
}

async function allerA(vue) {
  document.querySelectorAll('nav.bas button[data-vue]').forEach((b) => {
    b.classList.toggle('actif', b.dataset.vue === vue);
  });

  if (vue === 'accueil') return chargerAccueil();
  if (vue === 'ecarts') return chargerEcarts();
  if (vue === 'anomalies') return chargerAnomalies();
  if (vue === 'vendeurs') return chargerVendeurs();
  if (vue === 'sacs') return chargerSacs();
}

function afficherVue(id, html) {
  document.querySelectorAll('main.contenu > section').forEach((s) => s.hidden = true);
  const section = document.getElementById(`vue-${id}`);
  section.hidden = false;
  section.innerHTML = html;
}

/* ---------------------------------------------------------------------
 * Accueil : journée du jour du site sélectionné
 * ------------------------------------------------------------------- */

async function chargerAccueil() {
  afficherVue('accueil', '<p class="chargement">Chargement…</p>');

  const journees = await api(`/api/sites/${etat.siteId}/journees?limite=30`)
    .catch(() => []);

  const journeeOuverte = journees.find((j) => j.statut === 'ouverte') || null;

  if (journeeOuverte) {
    etat.journee = journeeOuverte;
    await afficherJournee();
    return;
  }

  const dateAujourdhui = aujourdhui();
  const journeeExistante = journees.find((j) => j.date_jour === dateAujourdhui) || null;

  if (journeeExistante) {
    etat.journee = journeeExistante;

    const estJourneeFermee = journeeExistante.statut === 'cloturee';

    afficherVue('accueil', `
      <div class="carte">
        <h2>${estJourneeFermee ? 'Journée déjà clôturée' : 'Journée existante'}</h2>
        <p>${dateLisible(journeeExistante.date_jour + 'T00:00:00')} a déjà une journée enregistrée.</p>
        <button class="principal" id="btn-voir-rapport">Voir le rapport</button>
      </div>
    `);

    document.getElementById('btn-voir-rapport').addEventListener('click', () => afficherJournee());
    return;
  }

  afficherVue('accueil', `
    <div class="carte">
      <h2>Aucune journée ouverte</h2>
      <p>Ouvre la journée du ${dateLisible(dateAujourdhui + 'T00:00:00')} pour commencer à enregistrer les ventes.</p>
      <button class="principal" id="btn-ouvrir-journee">Ouvrir la journée</button>
    </div>
  `);
  document.getElementById('btn-ouvrir-journee').addEventListener('click', ouvrirJourneeAujourdhui);
}

async function ouvrirJourneeAujourdhui() {
  try {
    const { journee_id } = await api('/api/journees', {
      method: 'POST',
      corps: { site_id: etat.siteId, date: aujourdhui() },
    });
    etat.journee = { id: journee_id, statut: 'ouverte' };
    await afficherJournee();
  } catch (e) {
    alert(e.message);
  }
}

async function afficherJournee() {
  afficherVue('accueil', '<p class="chargement">Chargement du rapport…</p>');

  const rapport = await api(`/api/journees/${etat.journee.id}/rapport`);
  etat.rapport = rapport;

  const enCours = rapport.activites.filter((a) => a.activite.statut !== 'terminee' && a.activite.statut !== 'annulee');
  const terminees = rapport.activites.filter((a) => a.activite.statut === 'terminee');

  const carteActivite = (a) => `
    <div class="carte">
      <div class="carte-titre">
        <div>
          <strong>${a.activite.vendeur_prenom} ${a.activite.vendeur_nom}</strong><br>
          <span class="etiquette">${a.activite.periode}</span>
          <span class="etiquette">sac ${a.activite.sac_numero}</span>
        </div>
        <span class="etiquette ${a.activite.statut === 'terminee' ? 'cloturee' : 'ouverte'}">
          ${a.activite.statut === 'terminee' ? 'clôturée' : 'en cours'}
        </span>
      </div>
      <div class="ligne"><span>Ventes</span><span class="montant">${fc(a.ca)}</span></div>
      <div class="ligne"><span>Bénéfice (${a.beneficiaire_benefice === 'russell' ? 'Russell' : 'entreprise'})</span><span class="montant">${fc(a.benefice)}</span></div>
      ${a.activite.statut !== 'terminee' ? `
        <div class="barre-actions">
          <button class="secondaire" data-ravitailler="${a.activite.id}">Ravitailler</button>
          <button class="principal" data-cloturer="${a.activite.id}">Retour et clôture</button>
        </div>
      ` : ''}
    </div>
  `;

  const html = `
    <div class="carte">
      <h2>${dateLisible(rapport.journee.date_jour + 'T00:00:00')}</h2>
      <div class="ligne"><span>Ventes</span><span class="montant">${fc(rapport.ventes)}</span></div>
      <div class="ligne"><span>Bénéfice entreprise</span><span class="montant">${fc(rapport.benefice_entreprise)}</span></div>
      <div class="ligne"><span>Bénéfice Russell</span><span class="montant">${fc(rapport.benefice_russell)}</span></div>
      ${rapport.journee.statut === 'ouverte' ? `<button class="principal" id="btn-nouveau-ravitaillement" style="margin-top:12px">+ Nouveau ravitaillement</button>` : ''}
    </div>
    ${enCours.length ? '<h2>En cours</h2>' + enCours.map(carteActivite).join('') : ''}
    ${terminees.length ? '<h2>Clôturées</h2>' + terminees.map(carteActivite).join('') : ''}
    ${rapport.activites.length === 0 ? '<p class="liste-vide">Aucune activité aujourd\'hui pour l\'instant.</p>' : ''}
  `;

  afficherVue('accueil', html);

  const btnNouveau = document.getElementById('btn-nouveau-ravitaillement');
  if (btnNouveau) btnNouveau.addEventListener('click', () => ouvrirModaleRavitaillement());

  document.querySelectorAll('[data-ravitailler]').forEach((b) => {
    b.addEventListener('click', () => ouvrirModaleRavitaillement(Number(b.dataset.ravitailler)));
  });
  document.querySelectorAll('[data-cloturer]').forEach((b) => {
    b.addEventListener('click', () => ouvrirModaleCloture(Number(b.dataset.cloturer)));
  });
}

/* ---------------------------------------------------------------------
 * Modale générique
 * ------------------------------------------------------------------- */

const elModale = document.getElementById('modale');

function ouvrirModale(titre, html) {
  elModale.innerHTML = `
    <div class="fond-modale">
      <div class="carte-modale">
        <div class="entete-modale">
          <h2>${titre}</h2>
          <button class="fermer-modale" aria-label="Fermer">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M5 5l14 14M19 5 5 19"/></svg>
          </button>
        </div>
        ${html}
      </div>
    </div>
  `;
  elModale.hidden = false;
  elModale.querySelector('.fermer-modale').addEventListener('click', fermerModale);
  elModale.querySelector('.fond-modale').addEventListener('click', (ev) => {
    if (ev.target.classList.contains('fond-modale')) fermerModale();
  });
}

function fermerModale() {
  elModale.hidden = true;
  elModale.innerHTML = '';
}

/* ---------------------------------------------------------------------
 * Ravitaillement (nouveau vendeur ou vendeur déjà en activité)
 * ------------------------------------------------------------------- */

async function ouvrirModaleRavitaillement(activiteId = null) {
  const [sacs] = await Promise.all([
    activiteId ? Promise.resolve([]) : api(`/api/sites/${etat.siteId}/sacs`),
  ]);

  const optionsVendeurs = !activiteId
    ? `<div class="champ">
        <label>Vendeur</label>
        <select id="rav-vendeur">${etat.vendeurs.map((v) => `<option value="${v.id}">${v.prenom} ${v.nom}</option>`).join('')}</select>
      </div>
      <div class="champ">
        <label>Sac</label>
        <select id="rav-sac">${sacs.filter((s) => s.statut === 'libre').map((s) => `<option value="${s.id}">${s.numero} (${s.categorie})</option>`).join('')}</select>
      </div>
      <div class="champ">
        <label>Période</label>
        <select id="rav-periode">
          <option value="matin">Matin</option>
          <option value="soir">Soir</option>
          <option value="journee">Journée</option>
        </select>
      </div>`
    : '';

  ouvrirModale('Ravitaillement', `
    <form id="form-ravitaillement">
      ${optionsVendeurs}
      <div class="grille-produits">
        ${etat.produits.map((p) => `
          <div class="ligne-produit">
            <span>${p.nom}</span>
            <input type="number" min="0" step="1" value="0" data-produit="${p.id}">
          </div>
        `).join('')}
      </div>
      <p class="message-erreur" id="erreur-rav" hidden></p>
      <button class="principal" type="submit">Enregistrer</button>
    </form>
  `);

  document.getElementById('form-ravitaillement').addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const lignes = [...document.querySelectorAll('[data-produit]')]
      .map((el) => ({ produit_id: Number(el.dataset.produit), quantite: Number(el.value) }))
      .filter((l) => l.quantite > 0);

    if (lignes.length === 0) {
      document.getElementById('erreur-rav').textContent = 'Indique au moins une quantité.';
      document.getElementById('erreur-rav').hidden = false;
      return;
    }

    try {
      if (activiteId) {
        await api(`/api/activites/${activiteId}/ravitaillements`, { method: 'POST', corps: { lignes } });
      } else {
        await api(`/api/journees/${etat.journee.id}/ravitaillements`, {
          method: 'POST',
          corps: {
            vendeur_id: Number(document.getElementById('rav-vendeur').value),
            sac_id: Number(document.getElementById('rav-sac').value),
            periode: document.getElementById('rav-periode').value,
            lignes,
          },
        });
      }
      fermerModale();
      await afficherJournee();
    } catch (e) {
      document.getElementById('erreur-rav').textContent = e.message;
      document.getElementById('erreur-rav').hidden = false;
    }
  });
}

/* ---------------------------------------------------------------------
 * Retour puis clôture d'une activité
 * ------------------------------------------------------------------- */

async function ouvrirModaleCloture(activiteId) {
  ouvrirModale('Retour et clôture', `
    <form id="form-retour">
      <p style="color:var(--brun-500);font-size:.9rem">Quantités non vendues, rapportées par le vendeur.</p>
      <div class="grille-produits">
        ${etat.produits.map((p) => `
          <div class="ligne-produit">
            <span>${p.nom}</span>
            <input type="number" min="0" step="1" value="0" data-retour="${p.id}">
          </div>
        `).join('')}
      </div>
      <p class="message-erreur" id="erreur-retour" hidden></p>
      <button class="principal" type="submit">Continuer</button>
    </form>
  `);

  document.getElementById('form-retour').addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const lignes = [...document.querySelectorAll('[data-retour]')]
      .map((el) => ({ produit_id: Number(el.dataset.retour), quantite: Number(el.value) }));

    try {
      await api(`/api/activites/${activiteId}/retours`, { method: 'POST', corps: { lignes } });
      await ouvrirModaleMontantRemis(activiteId);
    } catch (e) {
      document.getElementById('erreur-retour').textContent = e.message;
      document.getElementById('erreur-retour').hidden = false;
    }
  });
}

async function ouvrirModaleMontantRemis(activiteId) {
  ouvrirModale('Clôture', `
    <form id="form-cloture">
      <div class="champ">
        <label>Montant remis par le vendeur (FC)</label>
        <input type="number" min="0" step="1" id="cloture-montant" required>
      </div>
      <div class="champ" id="champ-motif" hidden>
        <label>Motif de l'écart (obligatoire)</label>
        <input type="text" id="cloture-motif" maxlength="255">
      </div>
      <p class="message-erreur" id="erreur-cloture" hidden></p>
      <button class="principal" type="submit">Clôturer l'activité</button>
    </form>
  `);

  document.getElementById('form-cloture').addEventListener('submit', async (ev) => {
    ev.preventDefault();
    try {
      await api(`/api/activites/${activiteId}/cloture`, {
        method: 'POST',
        corps: {
          montant_remis: Number(document.getElementById('cloture-montant').value),
          motif_ecart: document.getElementById('cloture-motif').value || null,
        },
      });
      fermerModale();
      await afficherJournee();
    } catch (e) {
      if (e.code === 'donnees_invalides') {
        document.getElementById('champ-motif').hidden = false;
      }
      document.getElementById('erreur-cloture').textContent = e.message;
      document.getElementById('erreur-cloture').hidden = false;
    }
  });
}

/* ---------------------------------------------------------------------
 * Écarts
 * ------------------------------------------------------------------- */

async function chargerEcarts() {
  afficherVue('ecarts', '<p class="chargement">Chargement…</p>');
  const ecarts = await api(`/api/ecarts?site_id=${etat.siteId}`);

  if (ecarts.length === 0) {
    afficherVue('ecarts', '<p class="liste-vide">Aucun écart en attente.</p>');
    return;
  }

  const estRussell = etat.utilisateur.role === 'super_admin';

  afficherVue('ecarts', ecarts.map((e) => `
    <div class="carte">
      <div class="carte-titre">
        <strong>${e.vendeur_prenom} ${e.vendeur_nom}</strong>
        <span class="etiquette ecart">${fc(e.ecart)}</span>
      </div>
      <div class="ligne"><span>Date</span><span>${dateLisible(e.date_jour + 'T00:00:00')}</span></div>
      <div class="ligne"><span>Statut</span><span>${e.statut.replace(/_/g, ' ')}</span></div>
      ${e.statut === 'ecart_a_expliquer' ? `<button class="secondaire" style="margin-top:10px" data-mise-en-resolution="${e.id}">Mettre en résolution</button>` : ''}
      ${e.statut === 'ecart_en_cours_de_resolution' && estRussell ? `<button class="principal" style="margin-top:10px" data-resoudre="${e.id}">Résoudre (Russell)</button>` : ''}
    </div>
  `).join(''));

  document.querySelectorAll('[data-mise-en-resolution]').forEach((b) => {
    b.addEventListener('click', () => agirSurEcart(Number(b.dataset.miseEnResolution), 'mise-en-resolution'));
  });
  document.querySelectorAll('[data-resoudre]').forEach((b) => {
    b.addEventListener('click', () => agirSurEcart(Number(b.dataset.resoudre), 'resolution'));
  });
}

async function agirSurEcart(id, action) {
  const motif = prompt('Motif :');
  if (!motif) return;

  try {
    await api(`/api/ecarts/${id}/${action}`, { method: 'POST', corps: { motif } });
    await chargerEcarts();
  } catch (e) {
    alert(e.message);
  }
}

/* ---------------------------------------------------------------------
 * Anomalies de stock
 * ------------------------------------------------------------------- */

async function chargerAnomalies() {
  afficherVue('anomalies', '<p class="chargement">Chargement…</p>');
  const anomalies = await api(`/api/anomalies?site_id=${etat.siteId}`);

  if (anomalies.length === 0) {
    afficherVue('anomalies', '<p class="liste-vide">Aucune anomalie en attente.</p>');
    return;
  }

  afficherVue('anomalies', anomalies.map((a) => `
    <div class="carte">
      <div class="carte-titre">
        <strong>${a.vendeur_nom} — ${a.produit_nom}</strong>
        <span class="etiquette ecart">${a.ecart}</span>
      </div>
      <div class="ligne"><span>Attendu / constaté</span><span>${a.quantite_attendue} / ${a.quantite_constatee}</span></div>
      <div class="ligne"><span>Statut</span><span>${a.statut.replace(/_/g, ' ')}</span></div>
    </div>
  `).join(''));
}

/* ---------------------------------------------------------------------
 * Vendeurs : liste, création, catégorie de transport
 * ------------------------------------------------------------------- */

function libelleCategorie(cat) {
  return cat.tarif_actuel === null
    ? cat.nom
    : `${cat.nom} — ${fc(cat.tarif_actuel)} par ravitaillement`;
}

async function chargerVendeurs() {
  afficherVue('vendeurs', '<p class="chargement">Chargement…</p>');

  const vendeurs = await api('/api/vendeurs');
  etat.vendeurs = vendeurs;

  const html = `
    <button class="principal" id="btn-nouveau-vendeur" style="margin-bottom:14px">+ Nouveau vendeur</button>
    ${vendeurs.map((v) => `
      <div class="carte">
        <div class="carte-titre">
          <strong>${v.prenom ? v.prenom + ' ' + v.nom : v.nom}</strong>
        </div>
        <div class="champ" style="margin-bottom:8px">
          <label>Catégorie de transport</label>
          <select data-vendeur="${v.id}">
            ${etat.categoriesTransport.map((c) => `
              <option value="${c.id}" ${c.id === v.categorie_transport_id ? 'selected' : ''}>${libelleCategorie(c)}</option>
            `).join('')}
          </select>
        </div>
        <button class="secondaire" data-desactiver="${v.id}">Désactiver</button>
      </div>
    `).join('') || '<p class="liste-vide">Aucun vendeur pour l\'instant.</p>'}
  `;

  afficherVue('vendeurs', html);

  document.getElementById('btn-nouveau-vendeur').addEventListener('click', ouvrirModaleNouveauVendeur);

  document.querySelectorAll('[data-vendeur]').forEach((select) => {
    select.addEventListener('change', async () => {
      const vendeurId = Number(select.dataset.vendeur);
      const categorieId = Number(select.value);

      try {
        await api(`/api/vendeurs/${vendeurId}/categorie-transport`, {
          method: 'POST',
          corps: { categorie_transport_id: categorieId },
        });
      } catch (e) {
        alert(e.message);
        await chargerVendeurs();
      }
    });
  });

  document.querySelectorAll('[data-desactiver]').forEach((b) => {
    b.addEventListener('click', async () => {
      const motif = prompt('Motif de la désactivation :');
      if (!motif) return;
      try {
        await api(`/api/vendeurs/${b.dataset.desactiver}/desactivation`, { method: 'POST', corps: { motif } });
        await chargerVendeurs();
      } catch (e) {
        alert(e.message);
      }
    });
  });
}

function ouvrirModaleNouveauSite() {
  ouvrirModale('Nouveau site', `
    <form id="form-nouveau-site">
      <div class="champ">
        <label>Code</label>
        <input type="text" id="ns-code" maxlength="50" required>
      </div>
      <div class="champ">
        <label>Nom</label>
        <input type="text" id="ns-nom" maxlength="150" required>
      </div>
      <div class="champ">
        <label>Adresse</label>
        <input type="text" id="ns-adresse" maxlength="500">
      </div>
      <div class="champ">
        <label>Description</label>
        <textarea id="ns-description" rows="3" maxlength="1000"></textarea>
      </div>
      <p class="message-erreur" id="erreur-ns" hidden></p>
      <button class="principal" type="submit">Créer le site</button>
    </form>
  `);

  document.getElementById('form-nouveau-site').addEventListener('submit', async (ev) => {
    ev.preventDefault();

    try {
      const reponse = await api('/api/sites', {
        method: 'POST',
        corps: {
          code: document.getElementById('ns-code').value.trim(),
          nom: document.getElementById('ns-nom').value.trim(),
          adresse: document.getElementById('ns-adresse').value.trim() || null,
          description: document.getElementById('ns-description').value.trim() || null,
        },
      });

      fermerModale();
      await demarrerApp();

      const selecteur = document.getElementById('selecteur-site');
      if (selecteur) {
        selecteur.value = String(reponse.site_id);
        etat.siteId = Number(reponse.site_id);
      }

      await allerA('accueil');
    } catch (e) {
      document.getElementById('erreur-ns').textContent = e.message;
      document.getElementById('erreur-ns').hidden = false;
    }
  });
}

function ouvrirModaleNouveauResponsable() {
  ouvrirModale('Nouveau responsable', `
    <form id="form-nouveau-responsable">
      <div class="champ"><label>Nom</label><input id="nr-nom" maxlength="100" required></div>
      <div class="champ"><label>Prénom</label><input id="nr-prenom" maxlength="100"></div>
      <div class="champ"><label>Téléphone</label><input id="nr-telephone" maxlength="30" required></div>
      <div class="champ"><label>E-mail</label><input id="nr-email" type="email" maxlength="255"></div>
      <div class="champ"><label>Mot de passe initial</label><input id="nr-mot-de-passe" type="password" minlength="8" required></div>
      <p class="message-erreur" id="erreur-nr" hidden></p>
      <button class="principal" type="submit">Créer le responsable</button>
    </form>
  `);

  document.getElementById('form-nouveau-responsable').addEventListener('submit', async (ev) => {
    ev.preventDefault();
    try {
      await api('/api/responsables', {
        method: 'POST',
        corps: {
          nom: document.getElementById('nr-nom').value.trim(),
          prenom: document.getElementById('nr-prenom').value.trim() || null,
          telephone: document.getElementById('nr-telephone').value.trim(),
          email: document.getElementById('nr-email').value.trim() || null,
          mot_de_passe: document.getElementById('nr-mot-de-passe').value,
        },
      });
      fermerModale();
      alert('Responsable créé. Il peut maintenant se connecter avec son téléphone et ce mot de passe.');
    } catch (e) {
      document.getElementById('erreur-nr').textContent = e.message;
      document.getElementById('erreur-nr').hidden = false;
    }
  });
}

async function ouvrirGestionSites() {
  const [sites, responsables] = await Promise.all([api('/api/sites'), api('/api/responsables')]);

  ouvrirModale('Gestion des sites', `
    <div class="liste-sites-admin">
      ${sites.map((site) => `
        <article class="item-liste site-admin" data-site-admin="${site.id}">
          <div class="ligne-principale">
            <strong>${site.nom}</strong>
            <span>${site.code}</span>
          </div>
          <p class="texte-secondaire">Responsable principal : ${site.responsable_principal || 'Non affecté'}</p>
          <div class="actions-ligne">
            <button type="button" class="secondaire" data-modifier-site="${site.id}">Modifier</button>
            <button type="button" class="secondaire" data-affecter-site="${site.id}">Affecter un responsable</button>
            <button type="button" class="danger" data-desactiver-site="${site.id}">Désactiver</button>
          </div>
        </article>
      `).join('') || '<p class="liste-vide">Aucun site actif.</p>'}
    </div>
  `);

  document.querySelectorAll('[data-modifier-site]').forEach((bouton) => {
    bouton.addEventListener('click', () => ouvrirModaleModifierSite(Number(bouton.dataset.modifierSite), sites));
  });
  document.querySelectorAll('[data-affecter-site]').forEach((bouton) => {
    bouton.addEventListener('click', () => ouvrirModaleAffecterResponsable(Number(bouton.dataset.affecterSite), responsables));
  });
  document.querySelectorAll('[data-desactiver-site]').forEach((bouton) => {
    bouton.addEventListener('click', async () => {
      const site = sites.find((ligne) => ligne.id === Number(bouton.dataset.desactiverSite));
      if (!site || !confirm(`Désactiver le site « ${site.nom} » ?`)) return;
      try {
        await api(`/api/sites/${site.id}/desactivation`, { method: 'POST' });
        fermerModale();
        await demarrerApp();
      } catch (e) {
        alert(e.message);
      }
    });
  });
}

function ouvrirModaleModifierSite(siteId, sites) {
  const site = sites.find((ligne) => ligne.id === siteId);
  if (!site) return;

  ouvrirModale('Modifier le site', `
    <form id="form-modifier-site">
      <div class="champ"><label>Code</label><input id="ms-code" value="${site.code}" maxlength="50" required></div>
      <div class="champ"><label>Nom</label><input id="ms-nom" value="${site.nom}" maxlength="150" required></div>
      <div class="champ"><label>Adresse</label><input id="ms-adresse" value="${site.adresse || ''}" maxlength="500"></div>
      <div class="champ"><label>Description</label><textarea id="ms-description" rows="3" maxlength="1000">${site.description || ''}</textarea></div>
      <p class="message-erreur" id="erreur-ms" hidden></p>
      <button class="principal" type="submit">Enregistrer</button>
    </form>
  `);

  document.getElementById('form-modifier-site').addEventListener('submit', async (ev) => {
    ev.preventDefault();
    try {
      await api(`/api/sites/${siteId}/modification`, {
        method: 'POST',
        corps: {
          code: document.getElementById('ms-code').value.trim(),
          nom: document.getElementById('ms-nom').value.trim(),
          adresse: document.getElementById('ms-adresse').value.trim() || null,
          description: document.getElementById('ms-description').value.trim() || null,
        },
      });
      fermerModale();
      await demarrerApp();
    } catch (e) {
      document.getElementById('erreur-ms').textContent = e.message;
      document.getElementById('erreur-ms').hidden = false;
    }
  });
}

function ouvrirModaleAffecterResponsable(siteId, responsables) {
  ouvrirModale('Affecter un responsable', `
    <form id="form-affecter-responsable">
      <div class="champ"><label>Responsable</label><select id="ar-responsable" required>
        ${responsables.map((r) => `<option value="${r.id}">${r.prenom || ''} ${r.nom} (${r.telephone})</option>`).join('')}
      </select></div>
      <div class="champ"><label>Type</label><select id="ar-type"><option value="principal">Principal</option><option value="adjoint">Adjoint</option></select></div>
      <p class="message-erreur" id="erreur-ar" hidden></p>
      <button class="principal" type="submit">Enregistrer l’affectation</button>
    </form>
  `);

  document.getElementById('form-affecter-responsable').addEventListener('submit', async (ev) => {
    ev.preventDefault();
    try {
      await api(`/api/sites/${siteId}/responsable`, {
        method: 'POST',
        corps: {
          responsable_id: Number(document.getElementById('ar-responsable').value),
          type: document.getElementById('ar-type').value,
        },
      });
      fermerModale();
      await ouvrirGestionSites();
    } catch (e) {
      document.getElementById('erreur-ar').textContent = e.message;
      document.getElementById('erreur-ar').hidden = false;
    }
  });
}

function ouvrirModaleNouveauVendeur() {
  ouvrirModale('Nouveau vendeur', `
    <form id="form-nouveau-vendeur">
      <div class="champ">
        <label>Nom</label>
        <input type="text" id="nv-nom" maxlength="100" required>
      </div>
      <div class="champ">
        <label>Prénom</label>
        <input type="text" id="nv-prenom" maxlength="100">
      </div>
      <div class="champ">
        <label>Téléphone</label>
        <input type="tel" id="nv-telephone" maxlength="30">
      </div>
      <div class="champ">
        <label>Catégorie de transport</label>
        <select id="nv-categorie">
          ${etat.categoriesTransport.map((c) => `<option value="${c.id}">${libelleCategorie(c)}</option>`).join('')}
        </select>
      </div>
      <div class="champ">
        <label>Période habituelle (optionnel)</label>
        <select id="nv-periode">
          <option value="">Non précisée</option>
          <option value="matin">Matin</option>
          <option value="soir">Soir</option>
          <option value="journee">Journée</option>
        </select>
      </div>
      <p class="message-erreur" id="erreur-nv" hidden></p>
      <button class="principal" type="submit">Créer le vendeur</button>
    </form>
  `);

  document.getElementById('form-nouveau-vendeur').addEventListener('submit', async (ev) => {
    ev.preventDefault();

    try {
      await api('/api/vendeurs', {
        method: 'POST',
        corps: {
          nom: document.getElementById('nv-nom').value.trim(),
          prenom: document.getElementById('nv-prenom').value.trim() || null,
          telephone: document.getElementById('nv-telephone').value.trim() || null,
          categorie_transport_id: Number(document.getElementById('nv-categorie').value),
          periode_habituelle: document.getElementById('nv-periode').value || null,
        },
      });
      fermerModale();
      await chargerVendeurs();
    } catch (e) {
      document.getElementById('erreur-nv').textContent = e.message;
      document.getElementById('erreur-nv').hidden = false;
    }
  });
}

/* ---------------------------------------------------------------------
 * Sacs du site sélectionné
 * ------------------------------------------------------------------- */

const LIBELLES_STATUT_SAC = {
  libre: 'libre',
  en_utilisation: 'en utilisation',
  indisponible: 'indisponible',
};

async function chargerSacs() {
  afficherVue('sacs', '<p class="chargement">Chargement…</p>');

  const sacs = await api(`/api/sites/${etat.siteId}/sacs`);

  const html = `
    <button class="principal" id="btn-nouveau-sac" style="margin-bottom:14px">+ Nouveau sac</button>
    ${sacs.map((s) => `
      <div class="carte">
        <div class="carte-titre">
          <strong>Sac ${s.numero}</strong>
          <span class="etiquette ${s.statut === 'indisponible' ? 'ecart' : (s.statut === 'libre' ? 'ouverte' : 'cloturee')}">
            ${LIBELLES_STATUT_SAC[s.statut] || s.statut}
          </span>
        </div>
        <div class="ligne"><span>Catégorie</span><span>${s.categorie === 'russell' ? 'Russell' : 'standard'}</span></div>
        ${s.motif_indisponibilite ? `<div class="ligne"><span>Motif</span><span>${s.motif_indisponibilite}</span></div>` : ''}
        <div class="barre-actions">
          ${s.statut === 'indisponible'
            ? `<button class="principal" data-disponible="${s.id}">Rendre disponible</button>`
            : `<button class="secondaire" data-indisponible="${s.id}" ${s.statut === 'en_utilisation' ? 'disabled title="Sac en cours d\'utilisation"' : ''}>Rendre indisponible</button>`
          }
          <button class="secondaire" data-deplacer="${s.id}" ${s.statut === 'en_utilisation' ? 'disabled title="Sac en cours d\'utilisation"' : ''}>Déplacer</button>
        </div>
      </div>
    `).join('') || '<p class="liste-vide">Aucun sac pour ce site.</p>'}
  `;

  afficherVue('sacs', html);

  document.getElementById('btn-nouveau-sac').addEventListener('click', ouvrirModaleNouveauSac);

  document.querySelectorAll('[data-indisponible]').forEach((b) => {
    b.addEventListener('click', async () => {
      const motif = prompt('Motif de l\'indisponibilité :');
      if (!motif) return;
      try {
        await api(`/api/sacs/${b.dataset.indisponible}/indisponibilite`, { method: 'POST', corps: { motif } });
        await chargerSacs();
      } catch (e) {
        alert(e.message);
      }
    });
  });

  document.querySelectorAll('[data-disponible]').forEach((b) => {
    b.addEventListener('click', async () => {
      try {
        await api(`/api/sacs/${b.dataset.disponible}/disponibilite`, { method: 'POST', corps: {} });
        await chargerSacs();
      } catch (e) {
        alert(e.message);
      }
    });
  });

  document.querySelectorAll('[data-deplacer]').forEach((b) => {
    b.addEventListener('click', () => ouvrirModaleDeplacerSac(Number(b.dataset.deplacer)));
  });
}

function ouvrirModaleNouveauSac() {
  ouvrirModale('Nouveau sac', `
    <form id="form-nouveau-sac">
      <div class="champ">
        <label>Numéro</label>
        <input type="text" id="ns-numero" maxlength="50" required>
      </div>
      <div class="champ">
        <label>Catégorie</label>
        <select id="ns-categorie">
          <option value="standard">Standard</option>
          <option value="russell">Russell</option>
        </select>
      </div>
      <p class="message-erreur" id="erreur-ns" hidden></p>
      <button class="principal" type="submit">Créer le sac</button>
    </form>
  `);

  document.getElementById('form-nouveau-sac').addEventListener('submit', async (ev) => {
    ev.preventDefault();

    try {
      await api('/api/sacs', {
        method: 'POST',
        corps: {
          site_id: etat.siteId,
          numero: document.getElementById('ns-numero').value.trim(),
          categorie: document.getElementById('ns-categorie').value,
        },
      });
      fermerModale();
      await chargerSacs();
    } catch (e) {
      document.getElementById('erreur-ns').textContent = e.message;
      document.getElementById('erreur-ns').hidden = false;
    }
  });
}

function ouvrirModaleDeplacerSac(sacId) {
  const autresSites = etat.sites.filter((s) => s.id !== etat.siteId);

  if (autresSites.length === 0) {
    alert('Aucun autre site vers lequel déplacer ce sac.');
    return;
  }

  ouvrirModale('Déplacer le sac', `
    <form id="form-deplacer-sac">
      <div class="champ">
        <label>Site d'arrivée</label>
        <select id="ds-site">
          ${autresSites.map((s) => `<option value="${s.id}">${s.nom}</option>`).join('')}
        </select>
      </div>
      <div class="champ">
        <label>Motif</label>
        <input type="text" id="ds-motif" maxlength="255" required>
      </div>
      <p class="message-erreur" id="erreur-ds" hidden></p>
      <button class="principal" type="submit">Déplacer</button>
    </form>
  `);

  document.getElementById('form-deplacer-sac').addEventListener('submit', async (ev) => {
    ev.preventDefault();

    try {
      await api(`/api/sacs/${sacId}/deplacement`, {
        method: 'POST',
        corps: {
          site_arrivee_id: Number(document.getElementById('ds-site').value),
          motif: document.getElementById('ds-motif').value.trim(),
        },
      });
      fermerModale();
      await chargerSacs();
    } catch (e) {
      document.getElementById('erreur-ds').textContent = e.message;
      document.getElementById('erreur-ds').hidden = false;
    }
  });
}

/* ---------------------------------------------------------------------
 * Démarrage
 * ------------------------------------------------------------------- */

tenterSessionExistante();
