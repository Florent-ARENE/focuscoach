# 📘 README TECHNIQUE - Système de Réservation

> **Document orienté développeur / IA**  
> Mémoire vivante du projet - Mis à jour à chaque itération

**Version:** 2.4.1  
**Dernière mise à jour:** 28 mai 2026

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
| 28/05/2026 | 2.4.1 | 🧹 Marque | `admin/index.php` — logos login + sidebar passent à `brandWordmark()` (suppression du `<span>Performance</span>` codé en dur, dernier résidu « Renaud Performance ») |
| 28/05/2026 | 2.4.1 | ⚙️ Settings | Nouveau champ paramétrable `admin_activity` (Activité / Profession) → remplace l'activité codée en dur dans `confidentialite.php` |
| 28/05/2026 | 2.4.1 | 🎨 CSS | Refacto anti-inline : extraction de **tout le CSS décoratif** vers les fichiers (`manage.css`, `booking.css`, `admin.css`, `main.css`). Plus aucun `style=` décoratif ni `<style>` inline ; seuls subsistent les `display:none` fonctionnels pilotés par JS |
| 28/05/2026 | 2.4.1 | 🎨 CSS | `cfgField()` → `<span class="cfg-missing">` ; `admin.js`/`manage.js` → classes au lieu de styles injectés ; hex en dur `#fef3c7/#059669/#f59e0b` → tokens `var(--warning-light/--green/--warning)` |
| 28/05/2026 | 2.4.1 | 📄 Nouveau | `sql/migration-2.4.1.sql` — clé `admin_activity` (bases existantes) ; seed `admin_activity` ajouté à `sql/database.sql` (réimport propre) |
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

### 🔒 Règle d'or CSS (NE PAS DÉROGER)

> **Tout le style passe par des fichiers `.css` et des classes.** Aucun
> `style="…"` inline décoratif, aucun bloc `<style>` dans le HTML/PHP,
> aucune couleur hexadécimale codée en dur (toujours un token `var(--…)`).
>
> **Source unique des tokens :** le bloc `:root` de `main.css`. Les autres
> feuilles (`home.css`, `booking.css`, `admin.css`, `manage.css`) ne font
> que **consommer** ces variables — pas de second `:root`.
>
> **Seules exceptions tolérées :**
> 1. Les icônes Lucide en `<svg>` inline (`stroke="currentColor"`, `width`/`height`, `viewBox`) ;
> 2. Les `style="display:none"` **fonctionnels** que JS bascule via `element.style.display` (panneaux, modales). Tout le reste de leur style est dans une classe.

### Variables CSS (`:root` dans `main.css`) — charte « Focus Coach »

```css
/* Couleurs de marque */
--navy-900: #2E2A5E;   --navy-800: #4A4580;   --navy-700: #6B66B0;
--gold: #F0A500;       --gold-light: #ffb92e; --copper: #c96d22;
--cream: #F7F5F0;      --cream-dark: #ECEAE3; --white: #ffffff;

/* Alias sémantiques (home.css) : --navy, --blue, --orange, --sand… */

/* États fonctionnels */
--green: #10b981;  --green-light: #d1fae5;
--red: #ef4444;    --red-light: #fee2e2;
--warning: #f59e0b; --warning-light: #fef3c7;

/* Gris : --gray-100/200/400/500/600/700 */
/* Typo : --font-serif (Playfair), --font-sans (DM Sans) */
/* Rayons : --radius-sm/md/lg/pill · Ombres : --shadow-sm/md/lg/card */
```

### Classes ajoutées en v2.4.1 (extraction de l'inline)

| Classe | Fichier | Rôle |
|--------|---------|------|
| `.cfg-missing` | main.css | Placeholder rouge des champs légaux non renseignés (`cfgField()`) |
| `.settings-grid-2` | admin.css | Grille 2 colonnes (prénom/nom) des paramètres |
| `.reschedule-current` / `.reschedule-warning` | admin.css | Bloc rappel + avertissement du formulaire de déplacement (injecté par `admin.js`) |
| `.rgpd-notice` / `.rgpd-notice__text` / `.rgpd-notice__link` | booking.css | Mention RGPD sous le formulaire de réservation |
| `.rgpd-section` `.rgpd-details` `.rgpd-toggle` `.rgpd-shield` `.rgpd-chevron` `.rgpd-panel(-text)` `.rgpd-delete-btn` | manage.css | Bloc « Protection des données » (accordéon + chevron) |
| `.rgpd-modal` + `.rgpd-modal__box/title/text/list/note/field/label/input/actions` | manage.css | Modale de confirmation de suppression RGPD |
| `.rgpd-success` + `.rgpd-success__icon/title/text` | manage.css | Écran « Données supprimées » (injecté par `manage.js`) |

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
| Activité / Profession | `admin_activity` | /admin → Paramètres |

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

**Dernière mise à jour : 28/05/2026 - v2.4.1**
