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

## [2.4.4] — 2026-06-16 — Lot 2 : Auth admin durcie

### 🔒 Sécurité
- **`password_verify(ADMIN_PASSWORD_HASH)` strict** sur le login admin
  (`admin/index.php`). Le `define('ADMIN_PASSWORD', 'renaud2026')` qui
  vivait en clair dans `config/config.php` est supprimé. La valeur
  `renaud2026` ayant fuité dans le repo et les fils de conversation,
  elle est considérée comme compromise — le nouveau mot de passe est
  posé en hash bcrypt dans `config.local.php` (non versionné). Aucun
  fallback transitoire : sans `ADMIN_PASSWORD_HASH` valide, le login
  refuse plutôt que de retomber sur le clair.
- **Rate limit login admin** : 5 essais ratés / 15 min par IP, puis
  refus avec message générique (pas de fuite du compteur restant).
  Implémenté côté SQL (table `admin_login_attempts`) — cohérent avec
  le reste du projet, audit possible. Succès = purge des échecs (le
  verrou se relâche immédiatement après une bonne saisie).
- **Cookies de session durcis** (`includes/init.php`,
  `session_set_cookie_params` avant `session_start`) : `HttpOnly`
  (anti-XSS read), `SameSite=Lax` (anti-CSRF flow web), `Secure`
  conditionné HTTPS (préserve le dev local en `php -S`).
- **`session_regenerate_id(true)` après login admin réussi** : anti
  session-fixation. Logout : on rase `$_SESSION`, on efface le cookie,
  on `session_destroy()` — pas juste un `unset` de clé.
- **`config/config.php` exige `config.local.php`** : die clair si
  absent (plus de chemin de fallback avec mot de passe en dur). Le
  template `config.local.php.template` documente toutes les clés
  attendues (DB, `ADMIN_PASSWORD_HASH`, Stripe).

### 🧰 Helpers
- `Helpers::clientIp()` — IP serveur observée (pas de `X-Forwarded-For`,
  pas de proxy de confiance sur OVH mutualisé).
- `Helpers::isLoginLocked($ip)` — true si ≥ 5 échecs sur la fenêtre.
  Fail-open en cas d'erreur BDD : on ne lock pas si on ne peut pas lire
  (la dernière ligne de défense reste `password_verify`).
- `Helpers::recordLoginAttempt($ip, $success)` — log + purge des
  échecs antérieurs si succès. Constantes `LOGIN_MAX_ATTEMPTS=5` et
  `LOGIN_WINDOW_MIN=15`.

### 🗄️ Migration
- **`sql/migration-2.4.4.sql`** — `CREATE TABLE admin_login_attempts`
  avec index `(ip_address, attempted_at)` pour la fenêtre glissante.
  Intégrée à `sql/database.sql` pour les installations fresh.

### ✅ Non-régression (AD-9)
- `tests/smoke.php` : +5 cas auth bcrypt (hash valide accepte / refuse,
  hash vide refusé, hash malformé refusé sans exception qui leak,
  format `$2y$` attendu). Le rate limit demande la BDD → testé en
  intégration, hors smoke unitaire.

### 🧹 Doc
- **Pied de page redondant supprimé** dans `README_TECHNIQUE.md`
  (« Dernière mise à jour : 28/05/2026 - v2.4.0 ») : AD-2 appliqué au
  stamp lui-même — une seule source de version dans le fichier
  (l'en-tête). Le pre-commit AD-1 cherchait un match « n'importe où »,
  le pied désynchronisé verdissait à tort. Plus de divergence possible.

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
