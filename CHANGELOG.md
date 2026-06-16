# Changelog

Toutes les modifications notables du projet. Format inspiré de
[Keep a Changelog](https://keepachangelog.com/fr/1.1.0/), versionnage
[SemVer](https://semver.org/lang/fr/).

Source unique de version : fichier [`VERSION`](./VERSION) racine
(AD-1, cf. `cadrage/CADRAGE_UNIVERSEL.md`). Aucun numéro de version
codé en dur ailleurs ; l'alignement des stamps `.md` et `sw.js` est
vérifié par `.git/hooks/pre-commit` (AD-8).

---

## [Unreleased]

## [2.4.3] — 2026-06-16 — Lot 1 : CSRF obligatoire

### 🔒 Sécurité
- **CSRF obligatoire sur tous les POST d'état admin** (`api/admin.php`
  actions `update`, `delete`, `reschedule`, `blocked_dates`, `settings`).
  Token lu via header `X-CSRF-Token` (priorité) ou body `csrf_token`
  (fallback). Échec → réponse 403 immédiate, avant toute logique métier.
- **CSRF rendu obligatoire côté création de réservation** (`api/booking.php`).
  L'ancien `if (!empty($input['csrf_token']) && !verify...)` était un
  faux semblant : omettre le champ revenait à passer. Vérif désormais
  inconditionnelle.
- **Helper `Helpers::verifyCsrfFromRequest($body)`** : lit le token
  selon l'ordre header > body > `$_POST`. Compatible avec les flows
  fetch JSON (header) et form classique multipart (body).
- **Helper `Helpers::csrfFailure()`** : réponse JSON 403 normalisée
  `{success:false, error:..., code:'CSRF_INVALID'}`.
- **Helper `Helpers::csrfMeta()`** : injecte
  `<meta name="csrf-token" content="...">` dans le `<head>` des pages
  qui font des POST.
- **`Helpers::getJsonInput()` cache statique** : `php://input` n'est lu
  qu'une fois — la vérif CSRF en début de requête puis la lecture des
  données métier dans le switch ne se mangent plus.

### 🎨 Côté client
- **`assets/js/admin.js`** : helper `csrfHeaders()` qui lit la balise
  meta et le fusionne dans tous les `fetch` POST (reschedule, delete,
  update, settings).
- **`assets/js/booking.js`** : même helper. Le token reste aussi dans
  le body (champ hidden `Helpers::csrfField()`), double protection.
- **`admin/index.php`, `booking/index.php`** : `<?= Helpers::csrfMeta() ?>`
  dans le `<head>`.

### ✅ Non-régression (AD-9)
- **`tests/smoke.php`** créé : 7 cas couvrant le helper CSRF.
  - Token valide via header → accepté.
  - Token valide via body JSON → accepté.
  - Token valide via `$_POST` (form classique) → accepté.
  - Token absent (header + body + POST vides) → rejeté.
  - Token invalide → rejeté.
  - Header bidon + body valide → rejeté (politique : header gagne).
  - Chaîne vide explicite → rejeté.
- Le pre-commit exécute désormais `php tests/smoke.php` avant tout
  commit — un cas rouge bloque l'historique.

### 📝 Notes hors scope (à venir dans Lots 2-4)
- `api/manage.php` (espace client) : non concerné — l'auth se fait via
  `manage_token` 64-hex en URL (token-based auth), ce qui sert de
  protection CSRF de facto. Pas de double mécanisme.
- Login admin (`admin/index.php`) : pas de CSRF nécessaire sur le form
  de login lui-même (pas d'état pré-existant à protéger). Rate limit et
  `password_hash` traités au Lot 2.

---

## [2.4.2] — 2026-06-16

- Bibliothèque Lucide SVG inline (`classes/Icons.php` + `assets/js/icons.js`)
  remplaçant tous les emojis user-visibles.
- Header mobile : seul le CTA « Prendre RDV » reste visible (`.nav-cta-item`).
- Refonte mobile-first intégrale (toutes les media queries en `min-width`).
- PWA installable : `manifest.json` + `sw.js` (network-first HTML/PHP,
  cache-first assets) + icônes 192/512 maskables.
- Tokens CSS sémantiques `--status-*`, `--info-*`, `--success`, `--red-*`.
- Wordmark bicolore via `brandWordmark()` (split par milieu).
- Suppression complète des `style="..."` décoratifs.

## [2.4.1] — 2026-05-28

- Anti-inline CSS : extraction de tous les styles décoratifs vers les
  fichiers `.css`.
- Nouveau champ paramétrable `admin_activity`.
- `sql/migration-2.4.1.sql`.

## [2.4.0] — 2026-05-28

- Refonte « Focus Coach » : charte navy/orange + Playfair Display.
- Page d'accueil dynamique `index.php` (remplace `index.html`).
- Identité 100 % paramétrable via `siteConfig()` / `brandWordmark()`.

## [2.3.0] — 2026-02-13

- Conformité RGPD : pages légales, droit à l'effacement, purge auto 3 niveaux.
- 6 nouveaux champs admin (prénom, nom, tél, adresse, SIRET, statut juridique).

## [2.2.0] — 2026-02-12

- Espace client self-service (`booking/manage.php`) avec token unique.
- Module calendrier partagé `CalendarModule`.

---

*Versions antérieures à 2.2.0 : voir l'historique git.*
