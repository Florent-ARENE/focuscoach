# CLAUDE.md — Focus Coach

Point d'entrée Claude Code — committé à la racine. Décrit (1) la mission en cours et (2) les règles d'or immuables qui s'appliquent à toutes les itérations.

> **Avant toute modification** : lire `README_TECHNIQUE.md` (arborescence, carte des fonctions, design system) et faire un `grep` pour vérifier qu'on ne duplique pas. Mettre à jour `README_TECHNIQUE.md` à la fin de chaque itération avec un changelog daté.

---

## 🎯 Mission en cours — v2.4.2

### Chantier 1 — Tokens sémantiques de statut (zéro hex en module)

Les badges `.status-badge.*` (`manage.css`) et `.alert-info` (`admin.css`) utilisent encore des hex en dur. Plus, d'autres résidus :
`booking.css:258/263/504/508` (#059669 / #f59e0b) et `manage.css:146/151/246/371/376` (#fef3c7 / #fee2e2 / #fecaca).

À faire :
1. Ajouter dans `main.css :root` les tokens `--status-{pending,confirmed,cancelled,completed}-{bg,text}` et `--info-{bg,border,text}`.
2. Substituer tous les hex des modules par `var(--token)` (y compris ceux non listés dans la première spec).
3. Vérification : `grep -nE '#[0-9a-fA-F]{3,6}\b' assets/css/{booking,manage,admin,home}.css` doit retourner vide.

### Chantier 2 — Harmoniser le wordmark sur l'admin

`.sidebar-logo` et `.login-logo` (admin.css) sont en Playfair serif sans uppercase → incohérent avec `.nav-brand-text` (home) et `.booking-logo` (booking) qui sont en DM Sans uppercase avec `letter-spacing: 0.08em`.

À faire : aligner sur le même traitement, ajuster la taille pour rester lisible en sidebar.

### Chantier 3 — Helper bicolore `brandWordmark()`

Remplacer le `brandWordmark()` actuel (split par mot, `.accent`) par un split **par milieu de caractères** avec snap sur espace ±2.

Algorithme :
1. `$len = mb_strlen($name)`
2. `$mid = round($len / 2)`
3. Snap : si un espace existe à ±2 chars du milieu, couper là.
4. Émettre `<span class="brand-half-a">…</span><span class="brand-half-b">…</span>`.
5. Fallback si vide ou ≤1 char.

CSS (`home.css`) : `.brand-half-a { color: var(--orange); }`, `.brand-half-b { color: var(--navy); }`. Contextes sombres (footer accueil, headers booking/legal, sidebar admin, login-logo) → `.brand-half-b { color: var(--white); }`.

Nettoyage obligatoire : `.nav-brand-text .accent`, `.brand-accent`, `.footer-bottom .accent`, `.booking-logo span`, `.sidebar-logo span`, `.login-logo span` → supprimer, remplacés par `.brand-half-a/b`.

### Chantier 4 — Nettoyer `api/test-gcal.php`

Supprimer le bloc `<style>` inline et les `style="background:#…"`. Extraire vers `.diag-*` dans `main.css`.

### Chantier 5 — Mobile-first (refacto media queries)

Inverser `max-width` → `min-width`. Breakpoints : 768px (tablette+), 1024px (desktop+). Documenter en commentaire au-dessus du `:root` de `main.css`. Approche : **diff produit en dry-run** avant commit.

### Chantier 6 — PWA

`manifest.json` + `sw.js` (network-first HTML, cache-first assets) + icônes 192/512 maskables + `theme-color` + `apple-touch-icon`. Helper JS `pwaHead()` ou inclusion mutualisée. `.htaccess` : MIME type manifest. Cible : Lighthouse PWA ≥ 90.

### Livrables attendus

- Fichiers modifiés/créés ci-dessus.
- Pas de `sql/migration-2.4.2.sql` (aucune clé settings nouvelle).
- `README_TECHNIQUE.md` : changelog v2.4.2 + section CSS (mobile-first, tokens statut, helper bicolore) + arborescence (`manifest.json`, `sw.js`, icônes).
- `README.md` : mention PWA.

### Checklist anti-régression avant commit

- [ ] `php -l` propre sur tous les `.php`.
- [ ] `grep -nE '#[0-9a-fA-F]{3,6}\b' assets/css/{booking,manage,admin,home}.css` → vide.
- [ ] `grep -rn 'style="' --include='*.php' --include='*.html' .` → uniquement `display:none` fonctionnels.
- [ ] `grep -rln '<style>' --include='*.php' --include='*.html' .` → vide.
- [ ] Wordmark identique visuellement sur accueil / booking / admin / mentions / confidentialité.
- [ ] Score Lighthouse PWA ≥ 90 sur mobile, accessibilité ≥ 90.
- [ ] Test visuel des 6 pages clés en 3 tailles (375 / 768 / 1280).
- [ ] `siteConfig()` utilisé partout : aucun littéral identité en dur.
- [ ] `README_TECHNIQUE.md` et `README.md` à jour.

---

## 🚨 RÈGLES D'OR — Immuables

### Architecture & code
1. **Zéro duplication** PHP/JS. Avant d'écrire une fonction : grep + `README_TECHNIQUE.md` carte des fonctions.
2. **Helpers partagés** — `includes/init.php`, `classes/Helpers.php`, `assets/js/api.js`.
3. **Fonctions courtes**, responsabilité unique (~40 lignes max).
4. **Code en anglais, commentaires/UI en français**.
5. **API JSON uniforme** : `{success: bool, data: …, error: …}`.

### CSS & design
1. **Zéro hex en dur dans les modules** — uniquement dans `main.css :root`. Statuts = tokens (`--status-*`, `--info-*`).
2. **Zéro `style=` décoratif, zéro `<style>` inline** — seuls les `display:none` fonctionnels pilotés par JS sont tolérés.
3. **Un seul design system** — `main.css` point d'entrée. Modules chargés via `<link>` par page.
4. **Cohérence absolue** — avant de créer une classe, vérifier qu'aucune existante ne couvre déjà le besoin.
5. **Mobile-first** — règles de base = mobile, `min-width` pour enrichir.
6. **Breakpoints uniques** — 768px (tablette), 1024px (desktop), documentés au-dessus du `:root` de `main.css`.
7. **Cohérence PC ↔ mobile** — un seul design system, jamais deux UI parallèles.
8. **Media queries centralisées** — globales en fin de `main.css`, spécifiques en fin du module. Pas de doublon.
9. **Wordmark / identité visuelle** — toujours via `brandWordmark()`. Aucun mot de marque en dur.

### PWA (toujours)
1. **PWA obligatoire** — manifest, sw, icônes 192+512 maskables, theme-color. Lighthouse PWA ≥ 90.
2. **Service worker minimaliste** — network-first HTML dynamique, cache-first assets statiques.

### Paramétrage & contenu
1. **Tout contenu répétitif** = paramétrable via `settings` + `siteConfig()`. Aucune valeur identité en dur.
2. **`cfgField($value, $placeholder)`** affiche valeur ou badge `.cfg-missing`.
3. **`brandWordmark()`** pour le rendu graphique du nom.

### Sécurité & données
1. **`config.local.php`** = secrets. Jamais versionné, jamais écrasé. Protégé `.htaccess`.
2. **Validation/sanitisation systématique** côté PHP (`Helpers::sanitize`, `Helpers::escape`).
3. **Zéros préfixes préservés** (VARCHAR pas INT) pour codes (postal, département, etc.).

### Documentation
1. **`README_TECHNIQUE.md`** = mémoire vivante. MAJ à chaque itération : changelog daté, arborescence, carte des fonctions, section CSS si nouveaux tokens.
2. **`README.md`** = orientation utilisateur. MAJ si impact fonctionnel visible.
3. **Migrations SQL** — un fichier par phase `migration-vX.Y.Z.sql`. `database.sql` reste référence complète.

### Process
1. Avant toute modification : (a) lire `README_TECHNIQUE.md`, (b) grep contre duplication, (c) identifier helpers existants.
2. **Pas de fichier inutile** — pas de JS/CSS orphelin. Si on remplace, on supprime l'ancien.
3. **Compatible OVH mutualisé** — PHP 7.4+ vanilla, JS natif, MySQL. Pas de Node, pas de SSH.
4. **Test avant push** — `php -l`, ouverture des pages affectées, vérif visuelle desktop + mobile.

---

## Stack & constantes de référence

- **Stack** : PHP 7.4+ vanilla / MySQL / JS natif / OVH mutualisé.
- **Cible PWA Lighthouse** : ≥ 90 sur mobile.
- **Breakpoints** : 768px (tablette+), 1024px (desktop+) — mobile-first.
- **Polices** : DM Sans (body) / Playfair Display (display).
- **Palette** : `--navy-deep #2E2A5E`, `--navy #4A4580`, `--orange #F0A500`, `--sand #F7F5F0`.
