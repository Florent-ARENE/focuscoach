# CLAUDE.md — Focus Coach

> **Socle commun** : `./cadrage/CADRAGE_UNIVERSEL.md` (règles AD-1→AD-10) + `./cadrage/INSTRUCTIONS_DEMARRAGE_SESSION_UNIVERSEL.md`. **Source unique** — ne pas éditer de copie locale, lire la source.
> **Version** : source unique = `./VERSION` (lue par `version.php` → `appVersion()`). Aucun numéro de version codé en dur ailleurs (AD-1) ; alignement vérifié par `.git/hooks/pre-commit` (AD-8).

Point d'entrée Claude Code — committé à la racine. Décrit **(1)** l'état courant du projet, **(2)** la mission en cours et **(3)** les règles d'or immuables qui s'appliquent à toutes les itérations.

> **Avant toute modification** : lire `README_TECHNIQUE.md` (arborescence, carte des fonctions, design system) puis `grep` pour vérifier qu'on ne duplique pas. **À la fin de chaque itération** : mettre à jour `README_TECHNIQUE.md` (changelog daté, arborescence, carte des fonctions, section CSS si nouveaux tokens) et `README.md` si l'impact utilisateur est visible.

---

## 📦 État courant — v2.6.6

Projet stable, livré. Repère rapide pour reprendre le contexte sans relire tout le changelog.

- **Marque** : rebrand « Focus Coach » appliqué de façon cohérente sur toutes les pages (accueil, booking, admin, manage, légales).
- **CSS** : zéro hex en dur dans les modules — tokens sémantiques de statut (`--status-*`, `--info-*`, `--success`, `--red-*`) centralisés dans `main.css :root`. Refacto **mobile-first** intégrale (toutes les media queries en `min-width`).
- **Identité** : tout passe par `siteConfig()` / `Settings::get()` (nom, contact, légal, SIRET). Wordmark bicolore via `brandWordmark()` (split par milieu de caractères, snap espace ±2 → `.brand-half-a/b`).
- **Icônes** : zéro emoji décoratif. SVG Lucide inline via `App\Icons::svg($name, $size, $class)` (PHP) et `Icons.svg(name, size, class)` (JS). ~32 icônes embarquées, `stroke="currentColor"` (couleur héritée). Classes CSS `.lucide`, `.icon-inline`, `.icon-cta`.
- **Header mobile** : seul le CTA « Prendre RDV » reste visible (`.nav-cta-item`), les autres liens réapparaissent à 768px+.
- **Légales** : `mentions-legales.php` + `confidentialite.php` en pages PHP dédiées, grille de cartes responsive.
- **PWA** : `manifest.json` + `sw.js` (network-first HTML/PHP, cache-first assets, exclusion `/api/`) + icônes 192/512 maskables + `theme-color`. Helpers `pwaHead()` / `pwaRegister()` dans `init.php`.
- **DB** : source unique = `sql/schema.sql` (structure) + `sql/seed.sql` (défauts, idempotent). Migrations incrémentales jusqu'à `migration-2.4.8.sql` (à appliquer une par une sur bases déjà déployées). Anciennement `database.sql` (supprimé en v2.4.7).

---

## 🎯 Mission en cours

> _v2.4.2 livrée. Renseigner ci-dessous la prochaine mission avant de lancer une itération._

### Chantier — _(titre)_

**Contexte :** _(quel résidu / besoin / bug, fichiers et lignes concernés)_

**À faire :**
1. …
2. …

### Livrables attendus
- Fichiers modifiés/créés.
- Migration SQL `sql/migration-vX.Y.Z.sql` **uniquement** si nouvelles clés `settings` ou schéma modifié.
- `README_TECHNIQUE.md` : changelog daté + sections impactées (CSS, arborescence, carte des fonctions).
- `README.md` si impact fonctionnel visible.

### Checklist anti-régression avant commit
- [ ] `php -l` propre sur tous les `.php`.
- [ ] `grep -nE '#[0-9a-fA-F]{3,6}\b' assets/css/{booking,manage,admin,home}.css` → vide.
- [ ] `grep -rnP '[\xF0-\xF4]' --include='*.php' --include='*.js' index.php admin/ booking/ confidentialite.php mentions-legales.php assets/js/{admin,booking,manage,summary-module,home,icons}.js` → vide (aucun emoji dans le DOM).
- [ ] `<script src=".../icons.js">` chargé **avant** tout autre module JS appelant `Icons.svg()`.
- [ ] `grep -rn 'style="' --include='*.php' --include='*.html' .` → uniquement `display:none` fonctionnels.
- [ ] `grep -rln '<style>' --include='*.php' --include='*.html' .` → vide.
- [ ] Wordmark identique sur accueil / booking / admin / mentions / confidentialité.
- [ ] Lighthouse mobile : PWA ≥ 90, accessibilité ≥ 90.
- [ ] Test visuel des pages clés en 3 tailles (375 / 768 / 1280).
- [ ] `siteConfig()` partout : aucun littéral identité en dur.
- [ ] `config.local.php` non touché.
- [ ] **SQL aligné** : si schéma touché → `sql/schema.sql` modifié dans le commit. Si nouvelle clé `settings` utilisée par le code → `sql/seed.sql` modifié dans le commit.
- [ ] `README_TECHNIQUE.md` et `README.md` à jour.

---

## 🚨 RÈGLES D'OR — Immuables

Communes à tous les projets, instanciées ici pour Focus Coach.

### 1. Architecture & code
1. **Zéro duplication** PHP/JS. Avant d'écrire une fonction : `grep` + carte des fonctions du `README_TECHNIQUE.md`. Si un équivalent existe → réutiliser ou étendre, jamais recréer.
2. **Helpers partagés mutualisés** — `includes/init.php`, `classes/Helpers.php`, `classes/Icons.php`, `assets/js/api.js`, `assets/js/icons.js`. Toute logique commune (formatage, API, modales, identité, icônes) y est centralisée.
3. **Fonctions courtes**, responsabilité unique (~40 lignes max).
4. **Code en anglais, commentaires + UI en français**, de façon uniforme.
5. **API JSON uniforme** : `{ success: bool, data: …, error: … }`. Point d'entrée centralisé par domaine, raccourcis dans `api.js` (un seul fichier à toucher si une URL change).
6. **Architecture modulaire** — chaque fonctionnalité autonome, activable/désactivable sans casser l'ensemble.

### 2. CSS & design
1. **Zéro hex en dur dans les modules** — couleurs et tokens uniquement dans `main.css :root`. Statuts = tokens sémantiques (`--status-*`, `--info-*`, `--success`, `--red-*`).
2. **Zéro `style=` décoratif, zéro `<style>` inline** — seuls les `display:none` fonctionnels pilotés par JS sont tolérés. Tout style inline récurrent (3+) devient une classe utilitaire.
3. **Un seul design system** — `main.css` point d'entrée unique. Modules CSS chargés par page (`main.css` + module spécifique de la page).
4. **Cohérence absolue** — avant de créer une classe, vérifier qu'aucune existante ne couvre déjà le besoin (section CSS du `README_TECHNIQUE`).
5. **Mobile-first** — règles de base = mobile, `min-width` pour enrichir. Jamais de `max-width`.
6. **Breakpoints uniques** — 768px (tablette+), 1024px (desktop+), documentés en commentaire au-dessus du `:root` de `main.css`. Mêmes seuils partout.
7. **Cohérence PC ↔ mobile** — un seul design system, jamais deux UI parallèles.
8. **Media queries centralisées** — globales en fin de `main.css`, spécifiques en fin de module. Jamais de doublon (ex : `.grid-2/.grid-3` ont déjà leur collapse → ne pas le refaire).
9. **Wordmark / identité visuelle** — toujours via `brandWordmark()`. Aucun mot de marque en dur.
10. **Modales** — tailles standardisées (`.modal-sm/md/lg/xl`), un seul système dans tout le projet, pas de `max-width` inline.
11. **Zéro emoji décoratif dans le DOM utilisateur** — toute icône passe par `App\Icons::svg()` (PHP) ou `Icons.svg()` (JS), qui rendent du SVG Lucide inline. Ajout d'une nouvelle icône : copier le path Lucide officiel dans `classes/Icons.php` **et** dans `assets/js/icons.js` (deux miroirs synchronisés). Exceptions tolérées : contenu des emails (`classes/Mailer.php`) et descriptions Google Calendar (`classes/GoogleCalendarSync.php`) — hors DOM.

### 3. PWA (toujours)
1. **PWA obligatoire** — `manifest.json` + `sw.js` + icônes 192/512 **maskables** (safe-zone : contenu visible dans un cercle de rayon = `size * 0.4` autour du centre) + `theme-color` + `apple-touch-icon`. Cible Lighthouse PWA ≥ 90 sur mobile.
2. **Service worker minimaliste** — network-first pour HTML/PHP (données fraîches), cache-first pour assets statiques, exclusion des `/api/`. Bumper `CACHE_VERSION` à chaque release CSS/JS.
3. **Inclusion mutualisée** — `pwaHead()` dans le `<head>`, `pwaRegister()` en fin de `<body>`. `.htaccess` : MIME `application/manifest+json` + `Service-Worker-Allowed: /` + `no-cache` sur `sw.js`.

### 4. Paramétrage & contenu
1. **Tout contenu répétitif** (nom, contact, légal, SIRET, emails…) = paramétrable via la table `settings` + `siteConfig()`. **Aucune valeur d'identité en dur** dans les templates.
2. **`cfgField($value, $placeholder)`** — affiche la valeur échappée ou un badge `.cfg-missing` `[À compléter dans Paramètres]` si vide.
3. **`brandWordmark()`** — rendu graphique bicolore du nom de marque. Retourne du **HTML déjà safe** (`<span class="brand-half-*">` + valeur échappée) : à injecter avec `<?= ?>` sans escape supplémentaire.
4. Propagation automatique : un réglage modifié dans `/admin` se répercute partout (logos, footers, emails, pages légales) sans édition manuelle.

### 5. Sécurité & données
1. **`config.local.php`** = secrets (BDD, clés API, tokens). Jamais versionné, jamais écrasé, protégé `.htaccess` (`Require all denied`). `google-credentials.json` et `*.sql` idem.
2. **Validation / sanitisation systématique** côté PHP (`Helpers::sanitize`, `Helpers::escape`) sur toute entrée utilisateur.
3. **Zéros préfixes préservés** — champs code en `VARCHAR`, jamais castés en `INT`/`float` (ni PHP, ni JS, ni SQL). Vrai pour code postal, département, SIRET, etc.
4. **Pas d'exécution PHP** dans `/uploads/` (`php_flag engine off`). `.htaccess` sur tous les dossiers sensibles.

### 6. Import / export (le cas échéant)
1. **Import Excel** — zéros préfixes préservés (traiter les codes comme chaînes), apostrophes Excel strippées, détection auto encodage + séparateur, prévisualisation avant confirmation.
2. **Export CSV (France)** — séparateur `;`, encodage **UTF-8 BOM** (`\xEF\xBB\xBF`), zéros préfixes maintenus en format texte.

### 7. RGPD
- **Base légale du projet : mesures précontractuelles (Art. 6.1.b)**, pas le consentement — ce qui simplifie les obligations. Suppression de données via lien à token. Six champs d'identité configurables côté admin.

### 8. Documentation
1. **`README_TECHNIQUE.md`** = mémoire vivante. MAJ à chaque itération : changelog daté, arborescence, relations entre fichiers, carte des fonctions, section CSS si nouveaux tokens.
2. **`README.md`** = orientation utilisateur. MAJ si impact fonctionnel visible.
3. **SQL — source unique** : `sql/schema.sql` (structure, `CREATE TABLE IF NOT EXISTS`, zéro données) + `sql/seed.sql` (paramètres + créneaux par défaut, idempotent). **À tenir à jour dans le même commit qu'une modif de schéma** : ajout d'une table → `schema.sql`, ajout d'une clé `settings` consommée par le code → `seed.sql`. Les `sql/migration-vX.Y.Z.sql` restent l'historique incrémental pour les bases déjà déployées (chaque migration livrée doit aussi se retrouver dans `schema.sql` et, si elle ajoute des défauts, dans `seed.sql`).

### 9. Process
1. Avant toute modif : **(a)** lire `README_TECHNIQUE.md`, **(b)** `grep` anti-duplication, **(c)** identifier les helpers existants.
2. **Pas de fichier orphelin** — pas de JS/CSS mort. Si on remplace, on supprime l'ancien.
3. **Compatible OVH mutualisé** — PHP 7.4+ vanilla, JS natif, MySQL. Pas de Node serveur, pas de SSH.
4. **Test avant push** — `php -l`, ouverture des pages affectées, vérif visuelle desktop + mobile. Lighthouse mobile (PWA + accessibilité ≥ 90) **avant chaque PR taggée release** (pas à chaque commit).

### 10. Pièges connus
- Les `use` (imports de namespace) d'un fichier parent **ne sont pas hérités** par un fichier `require`'d : chaque fichier inclus déclare ses propres `use`.
- Reskins : auditer **tout le repo** avant de supposer qu'une chose est à construire — la paramétrisation admin et de nombreux helpers existent déjà.
- **PWA bouton « Installer »** : Chrome/Edge ne l'exposent qu'en HTTPS (ou `localhost`). En HTTP simple, manifest et SW se chargent correctement mais le bouton n'apparaît jamais. Tester en local avec `php -S` puis tunnel HTTPS (ngrok), ou directement sur l'OVH déployé.
- **`Icons::svg()`** : retourne du HTML brut, à injecter avec `<?= ?>` **sans** `htmlspecialchars` (sinon le SVG s'affiche en texte). Pas de risque XSS : `$name` est validé contre une liste blanche interne (`LIBRARY`), `$class` est échappée. Même logique que `brandWordmark()`.
- **`config/config.php` `BOOKING_STATUS['icon']`** : champ emoji conservé pour compat, plus consommé par aucune vue (à supprimer à la prochaine refacto majeure).
- **`:has()` ? Non** : la nav mobile utilise `.nav-cta-item` plutôt que `li:has(.nav-cta)` pour la compat (Safari 15.4+ seulement). Standard projet : classer explicitement plutôt que recourir à des sélecteurs CSS modernes non polyfillables.

---

## 🧱 Stack & constantes de référence

- **Stack** : PHP 7.4+ vanilla / MySQL / JS natif / OVH mutualisé.
- **Cible PWA Lighthouse** : ≥ 90 sur mobile.
- **Breakpoints** : 768px (tablette+), 1024px (desktop+) — mobile-first.
- **Polices** : DM Sans (body / wordmark uppercase `letter-spacing: 0.08em`) — Playfair Display (display).
- **Palette** : `--navy-deep #2E2A5E`, `--navy #4A4580`, `--orange #F0A500`, `--sand #F7F5F0`.
- **Fichiers clés** : `includes/init.php` (helpers + `siteConfig()` + `pwaHead/Register`), `classes/Helpers.php`, `classes/Icons.php`, `assets/js/api.js`, `assets/js/icons.js`, `assets/css/main.css`, `sql/`, `manifest.json`, `sw.js`, `scripts/generate-pwa-icons.php`.
