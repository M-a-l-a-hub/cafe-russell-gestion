'use strict';

/* ---------------------------------------------------------------------
 * Mode hors ligne
 *  - lectures (GET) : dernière réponse gardée dans IndexedDB et servie si
 *    le réseau est coupé ;
 *  - écritures autorisées (dépenses, ravitaillements, retours) : mises en
 *    file d'attente, puis envoyées dans l'ordre au retour du réseau, avec
 *    une clé d'idempotence (le serveur n'exécute jamais deux fois la
 *    même saisie).
 * Les clôtures, retraits et paiements restent volontairement en ligne.
 * ------------------------------------------------------------------- */

const HorsLigne = (() => {
  const NOM_BASE = 'cafe-russell';
  const ENREGISTRABLES = [
    /^\/api\/depenses$/,
    /^\/api\/activites\/\d+\/ravitaillements$/,
    /^\/api\/activites\/\d+\/retours$/,
    /^\/api\/journees\/\d+\/ravitaillements$/,
  ];

  let basePromesse = null;
  let enCours = false;
  let minuteur = null;
  let derniereErreurSession = false;

  function ouvrirBase() {
    if (basePromesse) return basePromesse;

    basePromesse = new Promise((resolve, reject) => {
      const demande = indexedDB.open(NOM_BASE, 1);

      demande.onupgradeneeded = () => {
        const base = demande.result;
        base.createObjectStore('cache', { keyPath: 'chemin' });
        base.createObjectStore('file', { keyPath: 'cle' });
      };
      demande.onsuccess = () => resolve(demande.result);
      demande.onerror = () => reject(demande.error);
    });

    return basePromesse;
  }

  async function transaction(store, mode, action) {
    const base = await ouvrirBase();

    return new Promise((resolve, reject) => {
      const tx = base.transaction(store, mode);
      const resultat = action(tx.objectStore(store));

      tx.oncomplete = () => resolve(resultat && 'result' in resultat ? resultat.result : undefined);
      tx.onerror = () => reject(tx.error);
      tx.onabort = () => reject(tx.error);
    });
  }

  function nouvelleCle() {
    if (crypto.randomUUID) return crypto.randomUUID();

    return 'k' + Date.now().toString(36) + Math.random().toString(36).slice(2, 12);
  }

  function estEnregistrable(methode, chemin) {
    return methode === 'POST' && ENREGISTRABLES.some((re) => re.test(chemin));
  }

  /* ---- Cache de lecture ---- */

  async function mettreEnCache(chemin, donnees) {
    try {
      await transaction('cache', 'readwrite', (s) => s.put({ chemin, donnees, le: Date.now() }));
    } catch { /* le cache est facultatif */ }
  }

  async function lireCache(chemin) {
    try {
      return await transaction('cache', 'readonly', (s) => s.get(chemin));
    } catch {
      return undefined;
    }
  }

  async function viderCache() {
    try {
      await transaction('cache', 'readwrite', (s) => s.clear());
    } catch { /* rien à faire */ }
  }

  /* ---- File d'attente ---- */

  function decrire(chemin, corps) {
    if (/depenses$/.test(chemin)) {
      return `Dépense : ${corps?.libelle ?? ''} (${Number(corps?.montant ?? 0).toLocaleString('fr-FR')} FC)`;
    }
    if (/retours$/.test(chemin)) return 'Retour des quantités non vendues';
    return 'Ravitaillement';
  }

  async function ajouter({ cle, chemin, corps }) {
    await transaction('file', 'readwrite', (s) => s.put({
      cle,
      chemin,
      corps: corps ?? null,
      description: decrire(chemin, corps),
      utilisateur_id: etat.utilisateur ? etat.utilisateur.id : null,
      statut: 'attente',
      message: null,
      le: Date.now(),
    }));
    await rafraichirBadge();
  }

  async function lister() {
    const tout = await transaction('file', 'readonly', (s) => s.getAll());
    const mien = (tout || []).filter((i) => !etat.utilisateur || i.utilisateur_id === etat.utilisateur.id);

    return mien.sort((a, b) => a.le - b.le);
  }

  async function enregistrerItem(item) {
    await transaction('file', 'readwrite', (s) => s.put(item));
  }

  async function supprimer(cle) {
    await transaction('file', 'readwrite', (s) => s.delete(cle));
    await rafraichirBadge();
  }

  /* ---- Synchronisation ---- */

  async function synchroniser() {
    if (enCours || !navigator.onLine || !etat.utilisateur) return;
    enCours = true;
    let envoyes = 0;

    try {
      const items = (await lister()).filter((i) => i.statut === 'attente');
      if (items.length === 0) return;

      // Jeton CSRF à jour (et session encore valide ?).
      let moi;
      try {
        const r = await fetch('/api/moi', { credentials: 'same-origin' });
        moi = (await r.json()).donnees;
      } catch {
        return;
      }

      if (!moi || !moi.utilisateur || moi.utilisateur.id !== etat.utilisateur.id) {
        derniereErreurSession = true;
        return;
      }
      etat.csrf = moi.csrf;
      derniereErreurSession = false;

      for (const item of items) {
        let reponse;

        try {
          reponse = await fetch(item.chemin, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-Token': etat.csrf,
              'Idempotency-Key': item.cle,
            },
            body: item.corps ? JSON.stringify(item.corps) : undefined,
          });
        } catch {
          break; // réseau coupé : on reprendra plus tard
        }

        if (reponse.status === 401) {
          derniereErreurSession = true;
          break;
        }

        let json = null;
        try { json = await reponse.json(); } catch { /* corps illisible */ }

        if (reponse.ok && json && json.ok) {
          await supprimer(item.cle);
          envoyes += 1;
        } else if (reponse.status >= 500) {
          break; // problème serveur : on garde et on réessaie
        } else {
          item.statut = 'rejete';
          item.message = json && json.erreur ? json.erreur.message : 'Saisie refusée par le serveur.';
          await enregistrerItem(item);
        }
      }
    } finally {
      enCours = false;
      await rafraichirBadge();
    }

    if (envoyes > 0) {
      notifier(envoyes === 1 ? '1 saisie synchronisée.' : `${envoyes} saisies synchronisées.`);
      document.dispatchEvent(new CustomEvent('horsligne:synchronise'));
    }
  }

  /* ---- Interface ---- */

  function injecterStyle() {
    if (document.getElementById('style-hors-ligne')) return;

    const style = document.createElement('style');
    style.id = 'style-hors-ligne';
    style.textContent = `
      .badge-sync { display:inline-flex; align-items:center; gap:6px; border:1px solid var(--creme-2);
        background: var(--creme); color: var(--brun-900); border-radius: 999px; padding: 4px 10px;
        font-size: .78rem; cursor: pointer; white-space: nowrap; max-width: 52vw;
        overflow: hidden; text-overflow: ellipsis; }
      .badge-sync.hors-ligne { background: var(--brun-900); color: var(--creme); border-color: var(--brun-900); }
      .badge-sync.attention { border-color: var(--rouge); color: var(--rouge); }
      .toast-sync { position: fixed; left: 50%; bottom: 84px; transform: translateX(-50%); z-index: 40;
        background: var(--brun-900); color: var(--creme); padding: 10px 16px; border-radius: 10px;
        font-size: .9rem; box-shadow: var(--ombre); max-width: 90vw; }
      .item-file { border-bottom: 1px solid var(--creme-2); padding: 10px 0; }
      .item-file .texte-secondaire { font-size: .82rem; color: var(--brun-500); margin: 2px 0 0; }
      .item-file .rejet { color: var(--rouge); }
    `;
    document.head.appendChild(style);
  }

  function notifier(message) {
    const ancien = document.querySelector('.toast-sync');
    if (ancien) ancien.remove();

    const el = document.createElement('div');
    el.className = 'toast-sync';
    el.setAttribute('role', 'status');
    el.textContent = message;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 4000);
  }

  function badge() {
    let el = document.getElementById('badge-sync');
    if (el) return el;

    const cible = document.getElementById('entete-actions');
    if (!cible) return null;

    el = document.createElement('button');
    el.type = 'button';
    el.id = 'badge-sync';
    el.className = 'badge-sync';
    el.hidden = true;
    el.addEventListener('click', ouvrirFile);
    cible.insertAdjacentElement('afterbegin', el);

    return el;
  }

  const ICONE = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 12a8 8 0 0 1-13.7 5.6M4 12a8 8 0 0 1 13.7-5.6"/><path d="M18 3v4h-4M6 21v-4h4"/></svg>';

  async function rafraichirBadge() {
    const el = badge();
    if (!el) return;

    let items = [];
    try { items = await lister(); } catch { /* base indisponible */ }

    const attente = items.filter((i) => i.statut === 'attente').length;
    const rejetes = items.filter((i) => i.statut === 'rejete').length;
    const horsLigne = !navigator.onLine;

    el.classList.toggle('hors-ligne', horsLigne);
    el.classList.toggle('attention', rejetes > 0 || derniereErreurSession);

    const morceaux = [];
    if (horsLigne) morceaux.push('Hors ligne');
    if (attente > 0) morceaux.push(`${attente} en attente`);
    if (rejetes > 0) morceaux.push(`${rejetes} refusée${rejetes > 1 ? 's' : ''}`);
    if (derniereErreurSession && attente > 0) morceaux.push('reconnexion requise');

    el.hidden = morceaux.length === 0;
    el.innerHTML = `${ICONE}<span>${esc(morceaux.join(' · '))}</span>`;
  }

  async function ouvrirFile() {
    const items = await lister();

    ouvrirModale('Saisies à synchroniser', `
      ${derniereErreurSession ? '<p class="message-erreur">Ta session a expiré : déconnecte-toi puis reconnecte-toi pour envoyer ces saisies.</p>' : ''}
      ${items.map((i) => `
        <div class="item-file">
          <strong>${esc(i.description)}</strong>
          <p class="texte-secondaire">${esc(new Date(i.le).toLocaleString('fr-FR'))} — ${i.statut === 'rejete' ? 'refusée' : 'en attente'}</p>
          ${i.statut === 'rejete' ? `<p class="texte-secondaire rejet">${esc(i.message || '')}</p>` : ''}
          <button type="button" class="secondaire" data-retirer-file="${esc(i.cle)}">${i.statut === 'rejete' ? 'Supprimer' : 'Annuler cette saisie'}</button>
        </div>
      `).join('') || '<p class="liste-vide">Rien à synchroniser.</p>'}
      <button type="button" class="principal" id="bouton-sync-maintenant" style="margin-top:14px">Synchroniser maintenant</button>
    `);

    document.querySelectorAll('[data-retirer-file]').forEach((b) => {
      b.addEventListener('click', async () => {
        if (!confirm('Supprimer cette saisie ? Elle ne sera jamais envoyée au serveur.')) return;
        await supprimer(b.dataset.retirerFile);
        await ouvrirFile();
      });
    });

    document.getElementById('bouton-sync-maintenant').addEventListener('click', async () => {
      await synchroniser();
      await ouvrirFile();
    });
  }

  function demarrer() {
    injecterStyle();
    rafraichirBadge();
    synchroniser();

    if (minuteur === null) {
      window.addEventListener('online', () => { rafraichirBadge(); synchroniser(); });
      window.addEventListener('offline', rafraichirBadge);
      document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') synchroniser();
      });
      minuteur = setInterval(synchroniser, 30000);
    }
  }

  return {
    estEnregistrable, nouvelleCle, mettreEnCache, lireCache, viderCache,
    ajouter, synchroniser, demarrer, notifier, rafraichirBadge,
  };
})();
