# 📘 README TECHNIQUE - Système de Réservation

> **Document orienté développeur / IA**  
> Mémoire vivante du projet - Mis à jour à chaque itération

**Version:** 2.4.3  
**Dernière mise à jour:** 16 juin 2026

---

## 📑 Table des matières

1. [ChangeLog](#-changelog)
2. [Arborescence complète](#-arborescence-complète)
3. [Relations entre fichiers](#-relations-entre-fichiers)
4. [Carte des fonctions](#-carte-des-fonctions)
5. [CSS Design System](#-css-design-system)
6. [Conventions de code](#-conventions-de-code)

---

## 📝 ChangeLog

| Date | Version | Type | Description |
|------|---------|------|-------------|
| 16/06/2026 | 2.4.3 | 🔒 Sécurité | **Lot 1 — CSRF obligatoire**. `api/admin.php` (POST update/delete/reschedule/blocked_dates/settings) et `api/booking.php` exigent maintenant un token CSRF valide ; 403 immédiat sinon. Vérif lue via header `X-CSRF-Token` (priorité) ou body `csrf_token` (fallback). |
| 16/06/2026 | 2.4.3 | 🧰 Helper | `Helpers::verifyCsrfFromRequest()`, `Helpers::csrfFailure()`, `Helpers::csrfMeta()` (injecte `<meta name="csrf-token">` dans le `<head>`). `Helpers::getJsonInput()` passe en cache statique (lit `php://input` une seule fois). |
| 16/06/2026 | 2.4.3 | 🎨 Client | `admin.js` + `booking.js` : helper `csrfHeaders()` qui lit le meta et le fusionne dans tous les fetch POST. `admin/index.php` + `booking/index.php` : `<?= Helpers::csrfMeta() ?>` dans le `<head>`. |
| 16/06/2026 | 2.4.3 | ✅ AD-9 | `tests/smoke.php` créé : 7 cas CSRF (header OK, body OK, $_POST OK, absent rejeté, invalide rejeté, header bidon+body valide rejeté, vide rejeté). Câblé au pre-commit — un rouge bloque tout commit. |
| 16/06/2026 | 2.4.3 | 🧰 Outil | `scripts/bump-version.php` propage `VERSION` → stamps `.md` + `sw.js` CACHE_VERSION. `version.php` racine expose `appVersion()` / `cacheVersion()` lus depuis `VERSION` (AD-1). |
| 16/06/2026 | 2.4.3 | 🛡️ AD-8 | `.git/hooks/pre-commit` : 5 garde-fous bloquants (php-l staged, hex modules CSS, emojis DOM, stamps version VERSION ↔ .md/sw.js, smoke tests). |
| 16/06/2026 | 2.4.3 | 📘 Cadrage | `cadrage/CADRAGE_UNIVERSEL.md` + `INSTRUCTIONS_DEMARRAGE_SESSION_UNIVERSEL.md` posés dans le repo en SOURCE (mono-source AD-2, en-tête « ne pas re-copier »). `CHANGELOG.md` initialisé (Keep a Changelog). |
| 16/06/2026 | 2.4.2 | 📘 Doc | `CLAUDE.md` racine — mission v2.4.2 + règles d'or projet (architecture, CSS, PWA, sécurité) |
| 16/06/2026 | 2.4.2 | 🔧 Fix | `admin/index.php` — résidu « Performance » en dur supprimé, remplacé par `brandWordmark()` partout (cohérence avec accueil/booking/légales) |
| 16/06/2026 | 2.4.2 | 🎨 Design | `brandWordmark()` réécrit : split bicolore par milieu de chars (snap sur espace ±2), classes `.brand-half-a/b` — remplace l'ancien `.accent` |
| 16/06/2026 | 2.4.2 | 🎨 Design | `main.css` — tokens sémantiques `--status-{pending\|confirmed\|cancelled\|completed}-{bg\|text}` + `--info-{bg\|border\|text}` + `--success` + `--red-strong` + `--red-soft` |
| 16/06/2026 | 2.4.2 | 🔧 Refacto | `manage.css` — hex `#fef3c7/#92400e/#d1fae5/...` → `var(--status-*)`, `#fee2e2/#991b1b/#fecaca` → `var(--red-*)` |
| 16/06/2026 | 2.4.2 | 🔧 Refacto | `admin.css` — `.alert-info` hex → `var(--info-*)` ; `.sidebar-logo` / `.login-logo` alignés sur DM Sans uppercase letter-spacing 0.08em (cohérence wordmark) |
| 16/06/2026 | 2.4.2 | 🔧 Refacto | `booking.css` — `#059669/#f59e0b` → `var(--success)/var(--warning)` ; `.booking-logo span` retiré (remplacé par `.brand-half-b` contextuel) |
| 16/06/2026 | 2.4.2 | 🔧 Refacto | `home.css` — `.accent`/`.brand-accent`/`.footer-bottom .accent` supprimés, remplacés par `.brand-half-a/b` |
| 16/06/2026 | 2.4.2 | 🧹 Nettoyage | `cfgField()` (init.php) — style inline `color:#ef4444;background:#fee2e2;...` → classe `.cfg-missing` (main.css) |
| 16/06/2026 | 2.4.2 | 🧹 Nettoyage | `api/test-gcal.php` — bloc `<style>` inline + `style="background:#dbeafe;..."` supprimés, remplacés par classes `.diag-*` + `.code-email` dans `main.css` |
| 16/06/2026 | 2.4.2 | 🧹 Nettoyage | `booking/manage.php` — bloc `<style>` chevron RGPD déplacé dans `manage.css` |
| 16/06/2026 | 2.4.2 | ✅ Vérif | `grep -nE '#[0-9a-fA-F]{3,6}\b' assets/css/{booking,manage,admin,home}.css` retourne vide |
| 16/06/2026 | 2.4.2 | 🎨 Design | Refacto **mobile-first** intégral — toutes les `@media max-width` inversées en `@media (min-width: ...)`. Règles de base = mobile, breakpoints uniques 768/1024 |
| 16/06/2026 | 2.4.2 | 🔧 Refacto | `home.css` — bases mobile (hero/contact/grid-2/audience/stats/services/compare/flash-cta/footer-top/timeline-item en `1fr`, nav-links/hero-right/about-photo cachés). Bloc 768px+ enrichit ; bloc 1024px+ passe les grilles à 3 col |
| 16/06/2026 | 2.4.2 | 🔧 Refacto | `admin.css` — sidebar full-width en horizontal (mobile), passe à fixe 260px à partir de 768px |
| 16/06/2026 | 2.4.2 | 🔧 Refacto | `booking.css` — booking-form 1 col mobile, 2 col 768px+ ; steps wrap+label caché mobile, nowrap+visible 768px+ |
| 16/06/2026 | 2.4.2 | 🔧 Refacto | `manage.css` — booking-info-grid 1 col mobile, 2 col 768px+ ; actions empilées mobile, en ligne 768px+ |
| 16/06/2026 | 2.4.2 | 🔧 Refacto | `main.css` — `:root` documenté en mobile-first ; page-title/data-table en valeurs mobile par défaut |
| 16/06/2026 | 2.4.2 | 🧹 Nettoyage | `home.css` — `.legal-logo span { color: var(--orange) }` supprimé (remplacé par `.brand-half-b` contextuel) ; doublons `.legal-header/.legal-main` fusionnés |
| 16/06/2026 | 2.4.2 | 📱 PWA | `manifest.json` racine — nom, icônes 192/512 maskables, theme-color navy-deep, display standalone |
| 16/06/2026 | 2.4.2 | 📱 PWA | `sw.js` racine — service worker cache versionné v2.4.2 ; network-first HTML/PHP, cache-first assets, exclusion des `/api/` |
| 16/06/2026 | 2.4.2 | 📱 PWA | Icônes Lucide `focus` générées via PHP-GD : `assets/img/icon-{192,512}.png` (script `scripts/generate-pwa-icons.php` réutilisable) |
| 16/06/2026 | 2.4.2 | 📱 PWA | `includes/init.php` — helpers `pwaHead()` (manifest + theme-color + apple-touch-icon) et `pwaRegister()` (enregistrement SW) |
| 16/06/2026 | 2.4.2 | 📱 PWA | Inclusion dans `index.php`, `confidentialite.php`, `mentions-legales.php`, `booking/index.php`, `booking/manage.php`. Admin exclu (back-office) |
| 16/06/2026 | 2.4.2 | 📱 PWA | `.htaccess` racine — DirectoryIndex `index.php` ; MIME `application/manifest+json` ; `Cache-Control: no-cache` sur `sw.js` ; `Service-Worker-Allowed: /` ; Deny sur config.local.php / google-credentials.json / *.sql |
| 16/06/2026 | 2.4.2 | 🧹 Nettoyage | Suppression complète des `style="..."` décoratifs : booking/index.php (notice RGPD), booking/manage.php (section RGPD + modale suppression), admin/index.php (settings-grid-2), admin.js (reschedule-warning), manage.js (delete-success) |
| 16/06/2026 | 2.4.2 | 🎨 Design | Nouvelles classes : `.rgpd-notice` (booking.css) ; `.rgpd-section/.rgpd-panel/.rgpd-icon/.btn-rgpd-delete/.delete-modal/.delete-modal__box/.delete-modal__intro/.delete-modal__list/.delete-modal__retain/.delete-modal__field/.delete-modal__label/.delete-modal__input/.delete-modal__actions/.delete-success` (manage.css) ; `.settings-grid-2/.settings-description--lg/.reschedule-warning/.reschedule-current` (admin.css) |
| 16/06/2026 | 2.4.2 | ✅ Vérif | `grep 'style="' --include='*.php' --include='*.js' .` → uniquement 8 `display:none` fonctionnels (dérogation explicite : pilotés par JS) |
| 28/05/2026 | 2.4.0 | 🎨 Design | Refonte « Focus Coach » : charte navy/orange + Playfair Display, appliquée à TOUT le site |
| 28/05/2026 | 2.4.0 | 🎨 Design | Re-skin auto booking/admin/manage via remap des tokens `:root` (aucun markup touché) |
| 28/05/2026 | 2.4.0 | 📄 Nouveau | `index.php` — page d'accueil dynamique (remplace `index.html` statique) |
| 28/05/2026 | 2.4.0 | 📄 Nouveau | `assets/css/home.css` — module accueil + styles pages légales |
| 28/05/2026 | 2.4.0 | 📄 Nouveau | `assets/js/home.js` — formulaire contact (mailto, destinataire = `admin_email`) |
| 28/05/2026 | 2.4.0 | 📄 Nouveau | `assets/img/` — visuels (logo transparent, photo N&B header, photo couleur about) |
| 28/05/2026 | 2.4.0 | 🔧 Refacto | Identité/contact/mentions 100 % paramétrables : `siteConfig()` + helper `brandWordmark()` partout (accueil, booking, manage, footer, pages légales) |
| 28/05/2026 | 2.4.0 | 🔧 Refacto | `mentions-legales.php` / `confidentialite.php` : suppression des `<style>` inline → `home.css` |
| 28/05/2026 | 2.4.0 | 📄 Nouveau | `sql/migration-2.4.0.sql` — pousse l'identité Focus Coach en BDD (éditable via /admin) |
| 28/05/2026 | 2.4.0 | 🔧 Fix | `.htaccess` : `DirectoryIndex` priorise `index.php` |
| 13/02/2026 | 2.3.0 | 🔒 RGPD | `confidentialite.php` — Politique de confidentialité (12 mentions art. 13) |
| 13/02/2026 | 2.3.0 | 🔒 RGPD | `mentions-legales.php` — Mentions légales (LCEN art. 6) |
| 13/02/2026 | 2.3.0 | 🔒 RGPD | `api/rgpd-delete-data.php` — Droit à l'effacement (art. 17) |
| 13/02/2026 | 2.3.0 | 🔒 RGPD | `cron/purge-rgpd.php` — Purge auto 3 niveaux (90j/180j/365j) |
| 13/02/2026 | 2.3.0 | 🔒 RGPD | `sql/migration-rgpd.sql` — Tables rgpd_deletion_log + purge_stats |
| 13/02/2026 | 2.3.0 | 🔒 RGPD | `booking/index.php` — Mention information sous formulaire |
| 13/02/2026 | 2.3.0 | 🔒 RGPD | `booking/manage.php` — Bouton + modale suppression données |
| 13/02/2026 | 2.3.0 | 🔒 RGPD | `manage.js` — Événements suppression RGPD (bindDeleteDataEvents) |
| 13/02/2026 | 2.3.0 | 🔒 RGPD | `api/manage.php` — Action delete_data via require |
| 13/02/2026 | 2.3.0 | 🔒 RGPD | `index.html` — Footer : liens vers pages légales |
| 13/02/2026 | 2.3.0 | ⚙️ Settings | `init.php` — Fonctions `siteConfig()` + `cfgField()` centralisées |
| 13/02/2026 | 2.3.0 | ⚙️ Settings | `admin/index.php` — 6 nouveaux champs (nom, tél, adresse, SIRET, statut) |
| 13/02/2026 | 2.3.0 | ⚙️ Settings | `Mailer.php` — `getAdminName()` (signature emails dynamique) |
| 13/02/2026 | 2.3.0 | ⚙️ Settings | Propagation auto : logos, footers, pages légales, emails |
| 13/02/2026 | 2.3.0 | ⚙️ Settings | `sql/migration-2.3.0.sql` — INSERT IGNORE nouvelles clés |
| 13/02/2026 | 2.3.0 | 📄 Doc | `docs/registre-des-traitements.md` — Registre art. 30 |
| 13/02/2026 | 2.3.0 | 📄 Doc | `docs/test-interet-legitime.md` — LIA collecte IP/UA |
| 13/02/2026 | 2.2.1 | 🔧 Fix | Email admin : utilise valeur BDD via `getAdminEmail()` |
| 13/02/2026 | 2.2.1 | 🔧 Fix | Encodage : `sanitize()` = trim seulement, `escape()` pour affichage HTML |
| 13/02/2026 | 2.2.1 | 🔧 Fix | Récapitulatifs : icône ✓ et espacement harmonisés |
| 13/02/2026 | 2.2.1 | 📄 Nouveau | `api/test-email.php` - Diagnostic emails admin |
| 13/02/2026 | 2.2.1 | 📄 Nouveau | `sql/fix-html-encoding.sql` - Nettoyage données BDD |
| 12/02/2026 | 2.2.0 | 🆕 Feature | Espace client self-service (token unique) |
| 12/02/2026 | 2.2.0 | 🆕 Feature | Page `/booking/manage.php` - gestion autonome RDV |
| 12/02/2026 | 2.2.0 | 🆕 Feature | API `/api/manage.php` - reschedule/cancel client |
| 12/02/2026 | 2.2.0 | 🔧 Refacto | `CalendarModule` - module JS partagé (zéro duplication) |
| 12/02/2026 | 2.2.0 | 🔧 Fix | BASE_URL toujours racine projet (pas /api/) |
| 12/02/2026 | 2.1.1 | 🔧 Fix | Stats sur cases calendrier (indicateurs confirmés/pending) |
| 12/02/2026 | 2.1.1 | 🔧 Fix | Créneaux admin : `time_start`/`time_end` au lieu de `start`/`end` |
| 12/02/2026 | 2.1.0 | 🆕 Feature | Déplacement RDV côté admin |
| 12/02/2026 | 2.1.0 | 🆕 Feature | Stats publiques sur page réservation |
| 12/02/2026 | 2.0.0 | 🆕 Feature | Sync Google Calendar sans librairie externe |
| 12/02/2026 | 1.5.0 | 🆕 Feature | Architecture namespace `App\`, Settings en BDD |
| 11/02/2026 | 1.0.0 | 🚀 Initial | Version initiale |

---

## 📁 Arborescence complète

```
renaud-booking/
│
├── 📄 index.php                     # Site vitrine (accueil dynamique via siteConfig)
├── 📄 mentions-legales.php          # Mentions légales (LCEN) — valeurs depuis /admin
├── 📄 confidentialite.php           # Politique de confidentialité RGPD — valeurs depuis /admin
├── 📄 .htaccess                     # Règles Apache (UTF-8, sécurité)
├── 📄 README.md                     # Documentation utilisateur
├── 📄 README_TECHNIQUE.md           # Ce fichier (documentation technique)
│
├── 📁 config/
│   ├── 📄 config.php                # Config principale (constantes, fonctions)
│   ├── 📄 config.local.php          # ⚠️ Secrets (BDD, mots de passe) - JAMAIS ÉCRASÉ
│   └── 📄 google-credentials.json   # Clé Service Account Google (si sync activée)
│
├── 📁 classes/                      # Classes PHP (namespace App\)
│   ├── 📄 Database.php              # Singleton PDO - connexion BDD
│   ├── 📄 Booking.php               # CRUD réservations + logique métier
│   ├── 📄 Slot.php                  # Génération créneaux disponibles
│   ├── 📄 Settings.php              # Paramètres en BDD (get/set/getAll)
│   ├── 📄 Mailer.php                # Envoi emails (notifications)
│   ├── 📄 Helpers.php               # Fonctions utilitaires (format, CSRF, sanitize)
│   └── 📄 GoogleCalendarSync.php    # Sync Google Calendar (API REST standalone)
│
├── 📁 includes/
│   └── 📄 init.php                  # Bootstrap : autoloader PSR-4, session
│
├── 📁 api/                          # Endpoints REST (JSON)
│   ├── 📄 slots.php                 # GET créneaux/dates/stats
│   ├── 📄 booking.php               # POST nouvelle réservation
│   ├── 📄 admin.php                 # API admin (list/get/update/delete/reschedule)
│   ├── 📄 manage.php                # API client (get/reschedule/cancel/delete_data)
│   ├── 📄 rgpd-delete-data.php      # 🔒 Logique effacement RGPD (inclus par manage.php)
│   ├── 📄 test-email.php            # Diagnostic envoi emails admin
│   ├── 📄 test-settings.php         # Test configuration BDD
│   ├── 📄 test-admin.php            # Test classes admin
│   ├── 📄 test-gcal.php             # Test Google Calendar
│   └── 📄 test-post.php             # Test sauvegarde settings
│
├── 📁 booking/                      # Module réservation visiteur
│   ├── 📄 index.php                 # Interface réservation (+ mention RGPD)
│   └── 📄 manage.php                # Espace client (+ bouton suppression données)
│
├── 📁 admin/
│   └── 📄 index.php                 # Interface admin (login + dashboard)
│
├── 📁 assets/
│   ├── 📁 css/
│   │   ├── 📄 main.css              # Design system global (variables, composants)
│   │   ├── 📄 booking.css           # Styles module réservation
│   │   ├── 📄 manage.css            # Styles espace client
│   │   └── 📄 admin.css             # Styles admin
│   └── 📁 js/
│       ├── 📄 calendar-module.js    # Module calendrier PARTAGÉ (réutilisable)
│       ├── 📄 booking.js            # JS réservation (utilise CalendarModule)
│       ├── 📄 manage.js             # JS espace client (utilise CalendarModule)
│       ├── 📄 admin.js              # JS admin
│       └── 📄 summary-module.js     # Module récapitulatif (non utilisé actuellement)
│
├── 📁 cron/                         # 🔒 Tâches planifiées RGPD
│   ├── 📄 purge-rgpd.php            # Purge auto : troncature IP 90j, suppression 180j/365j
│   └── 📄 .htaccess                 # Protection accès direct
│
├── 📁 sql/
│   ├── 📄 database.sql              # Création tables (référence complète)
│   ├── 📄 migration-2.2.0.sql       # Migration : ajout colonne manage_token
│   ├── 📄 migration-rgpd.sql        # 🔒 Migration : tables rgpd_deletion_log + purge_stats
│   ├── 📄 migration-2.3.0.sql       # ⚙️ Migration : nouvelles clés settings
│   └── 📄 fix-html-encoding.sql     # Correction données encodées HTML
│
└── 📁 docs/                         # 🔒 Documentation RGPD (hors site public)
    ├── 📄 registre-des-traitements.md   # Registre obligatoire art. 30
    └── 📄 test-interet-legitime.md      # Test mise en balance IP/UA (art. 6.1.f)
```

---

## 🔗 Relations entre fichiers

### Flux de données principal

```
┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│   Visiteur  │────►│ booking/    │────►│ api/        │
│   (browser) │     │ index.php   │     │ booking.php │
└─────────────┘     └─────────────┘     └─────────────┘
                           │                   │
                           │                   ▼
                           │            ┌─────────────┐
                           │            │ Booking.php │
                           │            └─────────────┘
                           │                   │
                           ▼                   ▼
                    ┌─────────────┐     ┌─────────────┐
                    │ calendar-   │     │ Mailer.php  │
                    │ module.js   │     └─────────────┘
                    └─────────────┘            │
                           │                   ▼
                           ▼            ┌─────────────┐
                    ┌─────────────┐     │ Google      │
                    │ api/        │     │ Calendar    │
                    │ slots.php   │     └─────────────┘
                    └─────────────┘
```

### Dépendances des fichiers

| Fichier | Dépend de | Utilisé par |
|---------|-----------|-------------|
| `init.php` | `config.php` | Tous les fichiers PHP |
| `Database.php` | - | Toutes les classes |
| `Booking.php` | `Database`, `Helpers` | `api/booking.php`, `api/admin.php`, `api/manage.php` |
| `Slot.php` | `Database`, `Booking` | `api/slots.php` |
| `Settings.php` | `Database` | `api/admin.php`, `Mailer.php`, `GoogleCalendarSync.php` |
| `Mailer.php` | `Helpers`, `Settings` | `api/booking.php`, `api/admin.php`, `api/manage.php` |
| `Helpers.php` | - | Partout |
| `GoogleCalendarSync.php` | `Settings` | `api/booking.php`, `api/admin.php` |
| `calendar-module.js` | - | `booking.js`, `manage.js` |
| `rgpd-delete-data.php` | `Database`, `Booking`, `Helpers`, `Mailer`, `GoogleCalendarSync` | `api/manage.php` (via require) |
| `purge-rgpd.php` | `init.php`, `Database` | CRON OVH (exécution planifiée) |
| `confidentialite.php` | `init.php` | `index.html` (lien footer), `booking/index.php` (lien mention) |
| `mentions-legales.php` | `init.php` | `index.html` (lien footer), `confidentialite.php` (lien footer) |

### Chaîne des includes

```php
// Tout fichier PHP commence par :
define('BOOKING_APP', true);
require_once __DIR__ . '/../includes/init.php';

// init.php charge :
// 1. config/config.php (qui charge config.local.php)
// 2. Autoloader PSR-4 (classes/)
// 3. Session
```

---

## 🗺️ Carte des fonctions

### Database.php

| Fonction | Paramètres | Retour | Description |
|----------|------------|--------|-------------|
| `getInstance()` | - | `PDO` | Singleton connexion BDD |

### Booking.php

| Fonction | Paramètres | Retour | Description |
|----------|------------|--------|-------------|
| `create(array $data)` | données réservation | `['success', 'booking_id', 'manage_token']` | Créer réservation + token |
| `getById(int $id)` | ID | `array\|null` | Récupérer par ID |
| `getByToken(string $token)` | token 64 car. | `array\|null` | Récupérer par token client |
| `getAll(array $filters)` | filtres optionnels | `array` | Liste avec filtres |
| `getBookingsByDateRange(string $start, string $end)` | dates | `array` | Stats par plage de dates |
| `updateStatus(int $id, string $status)` | ID, statut | `bool` | Changer statut |
| `reschedule(int $id, string $date, string $start, string $end)` | ID, nouveau créneau | `bool` | Déplacer (admin) |
| `clientReschedule(string $token, ...)` | token, nouveau créneau | `['success', 'message']` | Déplacer (client) → pending |
| `clientCancel(string $token)` | token | `['success', 'message']` | Annuler (client) |
| `delete(int $id)` | ID | `bool` | Supprimer |
| `isSlotAvailable(string $date, string $start, string $end, ?int $excludeId)` | créneau, ID à exclure | `bool` | Vérifier disponibilité |
| `getPublicStats()` | - | `array` | Stats publiques |
| `generateManageToken()` | - | `string` | Générer token 64 car. |

### Slot.php

| Fonction | Paramètres | Retour | Description |
|----------|------------|--------|-------------|
| `getAvailableSlots(string $date)` | date | `array` | Créneaux disponibles |
| `getAvailableDates(string $month)` | mois YYYY-MM | `array` | Dates avec dispo par mois |
| `isDateBlocked(string $date)` | date | `bool` | Vérifier si date bloquée |

### Settings.php

| Fonction | Paramètres | Retour | Description |
|----------|------------|--------|-------------|
| `get(string $key, $default)` | clé, défaut | `mixed` | Lire un paramètre |
| `set(string $key, $value)` | clé, valeur | `bool` | Écrire un paramètre |
| `getAll()` | - | `array` | Tous les paramètres |

### Mailer.php

| Fonction | Paramètres | Retour | Description |
|----------|------------|--------|-------------|
| `send(string $to, string $subject, string $message)` | destinataire, sujet, corps | `bool` | Envoyer email |
| `getAdminEmail()` | - | `string` | Email admin (BDD puis constante) |
| `notifyNewBooking(array $booking)` | données réservation | `void` | Email nouvelle résa (client + admin) |
| `notifyConfirmation(array $booking)` | données réservation | `void` | Email confirmation |
| `notifyCancellation(array $booking)` | données réservation | `void` | Email annulation |
| `notifyReschedule(array $old, array $new)` | ancien/nouveau | `void` | Email déplacement (admin) |
| `notifyClientRescheduleRequest(array $booking, string $oldDate, string $oldTime)` | données | `void` | Email déplacement (client) |
| `notifyClientCancellation(array $booking)` | données | `void` | Email annulation (client) |

### Helpers.php

| Fonction | Paramètres | Retour | Description |
|----------|------------|--------|-------------|
| `sanitize(string $input)` | entrée | `string` | Nettoyer (trim seulement) |
| `escape(string $input)` | entrée | `string` | Échapper HTML (affichage) |
| `formatDateFr(string $date)` | date ISO | `string` | "Lundi 15 février 2026" |
| `formatTimeSlot(string $start, string $end)` | heures | `string` | "10:00 - 10:30" |
| `generateCsrfToken()` | - | `string` | Générer token CSRF |
| `verifyCsrfToken(string $token)` | token | `bool` | Vérifier token CSRF |
| `jsonResponse(array $data, int $code)` | données, code HTTP | `void` | Réponse JSON + exit |

### GoogleCalendarSync.php

| Fonction | Paramètres | Retour | Description |
|----------|------------|--------|-------------|
| `isEnabled()` | - | `bool` | Sync activée ? |
| `createEvent(array $booking)` | données réservation | `string\|null` | Créer événement → event_id |
| `updateEvent(string $eventId, array $booking)` | ID, données | `bool` | Modifier événement |
| `deleteEvent(string $eventId)` | ID | `bool` | Supprimer événement |
| `testConnection()` | - | `array` | Test connexion API |

### CalendarModule (JS)

| Fonction | Paramètres | Retour | Description |
|----------|------------|--------|-------------|
| `create(options)` | config | `instance` | Créer instance calendrier |
| `init(elements)` | éléments DOM | `void` | Initialiser |
| `loadMonthData()` | - | `void` | Charger données du mois |
| `selectDate(dateStr)` | date ISO | `void` | Sélectionner une date |
| `loadSlots(dateStr)` | date ISO | `void` | Charger créneaux |
| `selectSlot(slot, btn)` | slot, bouton | `void` | Sélectionner créneau |
| `getSelection()` | - | `{date, slot}` | Obtenir sélection actuelle |
| `formatDateFr(dateStr)` | date ISO | `string` | Formater en français |
| `reset()` | - | `void` | Réinitialiser sélection |

---

## 🎨 CSS Design System

> **Règle d'or v2.4.2** : zéro hex en dur dans les modules. Tous les hex
> vivent uniquement dans `:root` de `main.css`. Tout module consomme via
> `var(--token)`. Vérification : `grep -nE '#[0-9a-fA-F]{3,6}\b'
> assets/css/{booking,manage,admin,home}.css` doit retourner vide.

### Variables CSS (`:root` dans main.css)

```css
/* Marque Focus Coach */
--navy-900: #2E2A5E;       /* navy profond */
--navy-800: #4A4580;       /* navy principal */
--navy-700: #6B66B0;       /* bleu/violet clair */
--gold: #F0A500;           /* accent orange */
--gold-light: #ffb92e;
--copper: #c96d22;
--cream: #F7F5F0;          /* fond sable */
--cream-dark: #ECEAE3;
--white: #ffffff;

/* Alias sémantiques (consommés par home.css) */
--navy: var(--navy-800);
--navy-deep: var(--navy-900);
--orange: var(--gold);
--orange-light: #FEF6E4;
--sand: var(--cream);

/* États fonctionnels */
--green / --green-light / --red / --red-light / --red-strong / --red-soft
--warning / --warning-light / --success

/* Statuts de réservation (introduit v2.4.2) */
--status-pending-bg   / --status-pending-text     (jaune)
--status-confirmed-bg / --status-confirmed-text   (vert)
--status-cancelled-bg / --status-cancelled-text   (rouge)
--status-completed-bg / --status-completed-text   (bleu)

/* Bandeau d'information (introduit v2.4.2) */
--info-bg / --info-border / --info-text

/* Typographie */
--font-serif: 'Playfair Display', Georgia, serif;
--font-sans:  'DM Sans', -apple-system, sans-serif;
--font-display: var(--font-serif);
--font-body:    var(--font-sans);

/* Rayons */
--radius / --radius-sm / --radius-md / --radius-lg / --radius-pill

/* Ombres */
--shadow-sm / --shadow-md / --shadow-lg / --shadow-card
```

### Classes globales (main.css)

| Classe | Usage |
|--------|-------|
| `.cfg-missing` | Placeholder rouge `[À compléter]` rendu par `cfgField()` |
| `.diag` (sur `<body>`) | Layout des pages de diagnostic `/api/test-*.php` |
| `.diag .success/.error/.warning/.info` | États visuels du diagnostic |
| `.diag .box` / `.diag .btn` / `.diag .btn-danger` | Composants du diagnostic |
| `.diag .code-email` | Code inline sur fond bleu pâle (email Service Account) |
| `.brand-half-a` / `.brand-half-b` | Wordmark bicolore (cf. `brandWordmark()`) |
| `.hidden` | `display: none !important` (utilitaire) |

### Wordmark bicolore — `brandWordmark()`

Helper PHP (`includes/init.php`) qui rend le `site_name` (paramétrable
via `/admin`) en deux moitiés de caractères, snap sur espace ±2 :

- `"Focus Coach"` → `<span class="brand-half-a">Focus</span><span class="brand-half-b">Coach</span>`
- `"FocusCoach"` → split par milieu → `Focus` + `Coach`
- `"Acme"` (4 chars) → `Ac` + `me`

CSS par défaut (home.css) : `.brand-half-a` orange, `.brand-half-b` navy.
Adaptation par contexte sombre via sélecteur descendant :
`.home-footer .brand-half-b`, `.legal-header .brand-half-b`,
`.sidebar-logo .brand-half-b`, `.booking-header .brand-half-b` → `var(--white)`.

> **Jamais** de mot de marque en dur dans le HTML — toujours `brandWordmark()`.

### Classes utilitaires

| Classe | Effet |
|--------|-------|
| `.mt-1` à `.mt-5` | Margin-top (0.5rem à 3rem) |
| `.mb-1` à `.mb-5` | Margin-bottom |
| `.text-center` | Centrer texte |
| `.text-muted` | Couleur grise |
| `.btn` | Style bouton de base |
| `.btn-primary` | Bouton or |
| `.btn-secondary` | Bouton gris |
| `.btn-danger` | Bouton rouge |
| `.status-badge` | Badge statut |
| `.status-badge.pending` | Badge orange |
| `.status-badge.confirmed` | Badge vert |
| `.status-badge.cancelled` | Badge rouge |

### Breakpoints

```css
/* Mobile first - Breakpoints */
/* mobile: < 768px (défaut) */
/* tablet: 768px - 1024px */
/* desktop: > 1024px */

@media (max-width: 768px) { ... }
@media (max-width: 480px) { ... }
```

---

## 📐 Conventions de code

### Langue

- **Code** : Anglais (noms de fonctions, variables, classes)
- **Commentaires** : Français
- **UI/Messages** : Français

### PHP

```php
// Namespace pour toutes les classes
namespace App;

// Nommage
class MaClasse { }           // PascalCase
public function maMethode() { }  // camelCase
$maVariable = '';            // camelCase
CONSTANTE_GLOBALE            // SCREAMING_SNAKE_CASE

// Documentation
/**
 * Description de la fonction
 * @param string $param Description
 * @return bool Description
 */
```

### JavaScript

```javascript
// Modules en objet
const MonModule = {
    state: {},
    init() { },
    maMethode() { }
};

// Variables
const CONSTANTE = 'valeur';  // const pour les constantes
let maVariable = '';         // let pour les variables
```

### CSS

```css
/* BEM-like pour les composants */
.composant { }
.composant-element { }
.composant.modifier { }

/* Utiliser les variables CSS */
color: var(--primary);  /* ✅ */
color: #1a2744;         /* ❌ Jamais de couleur en dur */
```

### Structure API

```json
// Réponse succès
{
  "success": true,
  "data": { ... },
  "message": "Description"
}

// Réponse erreur
{
  "success": false,
  "error": "Description de l'erreur"
}
```

---

## ⚠️ Points d'attention

### Fichiers à ne jamais écraser
- `config/config.local.php` (identifiants BDD)
- `config/google-credentials.json` (clé Google)

### Encodage
- **Entrée** : `Helpers::sanitize()` = trim seulement
- **Affichage HTML** : `Helpers::escape()` ou `htmlspecialchars()`
- **BDD** : Données brutes (pas d'encodage HTML)
- **Emails/Calendar** : Données brutes

### Email admin
- Priorité à la valeur BDD (`settings.admin_email`)
- Fallback sur constante `ADMIN_EMAIL` si vide

### Longueur des codes (si applicable)
| Champ | Longueur | Format |
|-------|----------|--------|
| `manage_token` | 64 car. | Hexadécimal |

---

## 🔧 Paramétrage du site (contenu répétitif → /admin)

Tout élément **répétitif** (nom du site, nom/coordonnées du créateur, mentions
légales) est centralisé dans la table `settings` et lu via `siteConfig()`
(défini dans `includes/init.php`). **Aucune de ces valeurs ne doit être codée en
dur** dans les pages.

| Donnée | Clé settings | Éditable dans |
|--------|--------------|---------------|
| Nom du site / marque | `site_name` | /admin → Paramètres |
| Prénom / Nom | `admin_name` / `admin_lastname` | /admin → Paramètres |
| Email | `admin_email` | /admin → Paramètres |
| Téléphone | `admin_phone` | /admin → Paramètres |
| Adresse | `admin_address` | /admin → Paramètres |
| SIRET | `admin_siret` | /admin → Paramètres |
| Statut juridique | `legal_status` | /admin → Paramètres |

Helpers associés :
- `siteConfig()` → tableau fusionnant BDD + fallbacks `config.php` (+ champs dérivés `full_name`, `logo_name`).
- `cfgField($valeur, $placeholder)` → affiche la valeur ou un placeholder rouge `[À compléter]` si vide.
- `brandWordmark()` → wordmark deux tons à partir de `site_name` (dernier mot en accent). Ex : « Focus Coach » → `Focus<span class="accent">Coach</span>`.

### Chargement CSS par page

| Page | Feuilles `<link>` |
|------|-------------------|
| `index.php` (accueil) | `main.css` + `home.css` |
| `mentions-legales.php` / `confidentialite.php` | `main.css` + `home.css` (classes `.legal-*`) |
| `booking/index.php` | `main.css` + `booking.css` |
| `booking/manage.php` | `main.css` + `booking.css` + `manage.css` |
| `admin/index.php` | `main.css` + `admin.css` |

> Re-skin global : les pages booking/admin/manage consomment les tokens `:root`
> de `main.css` via `var(...)`. Remapper les VALEURS (sans renommer) suffit à
> changer toute la charte — c'est ainsi qu'a été appliquée la refonte 2.4.0.

---

**Dernière mise à jour : 28/05/2026 - v2.4.0**
