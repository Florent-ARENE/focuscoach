# 📅 Focus Coach — Système de Réservation

**Version:** 2.6.8
**Date:** 16 juin 2026
**Auteur:** Développé avec Claude AI
**Stack:** PHP 7.4+ / MySQL / JavaScript vanilla / **PWA installable**
**Hébergement cible:** OVH mutualisé

---

## 📑 Table des matières

1. [Présentation](#-présentation)
2. [Changelog](#-changelog)
3. [Arborescence des fichiers](#-arborescence-des-fichiers)
4. [Relations entre fichiers](#-relations-entre-fichiers)
5. [Carte des fonctions](#-carte-des-fonctions)
6. [Installation](#-installation)
7. [Configuration](#-configuration)
8. [Tutoriel Google Service Account](#-tutoriel-google-service-account)
9. [Utilisation](#-utilisation)
10. [API Reference](#-api-reference)
11. [Dépannage](#-dépannage)

---

## 🎯 Présentation

Système de réservation de rendez-vous complet comprenant :

- **Site vitrine** : Page d'accueil avec services, KPIs, témoignages
- **Module de réservation** : Interface visiteur pour prendre RDV
- **Espace client** : Gestion autonome du RDV (déplacer, annuler)
- **Administration** : Gestion des réservations, créneaux, paramètres
- **Notifications email** : Confirmations automatiques
- **Sync Google Calendar** : Miroir unidirectionnel (sans librairie externe)
- **PWA installable** : manifest + service worker (network-first pour le HTML
  dynamique, cache-first pour les assets statiques). Cible Lighthouse PWA ≥ 90.

### Fonctionnalités clés

| Fonctionnalité | Description |
|----------------|-------------|
| 📅 Calendrier interactif | Sélection de date avec disponibilités |
| ⏰ Créneaux dynamiques | Générés selon les règles de disponibilité |
| 📧 Emails automatiques | Confirmation, validation, annulation, déplacement |
| 🔗 Espace client | Lien unique pour gérer son RDV |
| 🔄 Sync Google Calendar | Push automatique des RDV |
| 🔒 Admin sécurisé | Interface protégée par mot de passe |
| ⚙️ Paramètres en BDD | Configuration modifiable sans toucher au code |
| 📊 Stats publiques | Indicateurs de fréquentation côté visiteur |
| 🔀 Déplacement RDV | Admin et client peuvent modifier |

---

## 📝 Changelog

### Version 2.3.0 (13/02/2026)

**🔒 Conformité RGPD**
- **Politique de confidentialité** : Page dédiée `confidentialite.php` — 12 mentions art. 13
- **Mentions légales** : Page dédiée `mentions-legales.php` — Obligatoire LCEN
- **Mention d'information** : Texte RGPD sous le formulaire de réservation
- **Droit à l'effacement** : Bouton "Supprimer mes données" dans l'espace client (`manage.php`)
- **Purge automatique** : Script CRON `cron/purge-rgpd.php` — 3 niveaux (troncature IP 90j, suppression IP/UA 180j, suppression RDV 365j)
- **Traçabilité RGPD** : Table `rgpd_deletion_log` pour l'accountability (art. 24)
- **Documentation** : Registre des traitements (art. 30), test d'intérêt légitime (art. 6.1.f)
- **Footer** : Liens vers les pages légales (remplace les `#`)

**⚙️ Centralisation des paramètres**
- **6 nouveaux champs admin** : prénom, nom, téléphone, adresse, SIRET, statut juridique
- **Propagation automatique** : Toutes les pages (logos, footers, emails, pages légales) lisent la BDD via `siteConfig()`
- **Zéro hardcodé** : Plus aucun placeholder `[NOM]` ni constante dans les pages publiques
- **Placeholders visuels** : Les champs non remplis affichent `[À compléter]` en rouge sur les pages légales
- **`Mailer.php`** : Nouvelle méthode `getAdminName()` — signature emails dynamique

**📁 Fichiers ajoutés**
- `confidentialite.php` — Politique de confidentialité
- `mentions-legales.php` — Mentions légales LCEN
- `api/rgpd-delete-data.php` — Logique d'effacement RGPD (inclus par manage.php)
- `cron/purge-rgpd.php` — Script de purge automatique
- `sql/migration-rgpd.sql` — Tables de conformité
- `sql/migration-2.3.0.sql` — Nouvelles clés settings (INSERT IGNORE)
- `docs/registre-des-traitements.md` — Registre obligatoire art. 30
- `docs/test-interet-legitime.md` — Justification collecte IP/UA

**📁 Fichiers modifiés**
- `includes/init.php` — Fonctions `siteConfig()` et `cfgField()` (chargement centralisé)
- `index.html` — Footer : liens vers pages légales
- `booking/index.php` — Mention RGPD + logo dynamique
- `booking/manage.php` — Bouton suppression + logo dynamique
- `admin/index.php` — 6 nouveaux champs (prénom, nom, tél, adresse, SIRET, statut) + logos dynamiques
- `assets/js/admin.js` — saveSettings() avec les 10 clés
- `assets/js/manage.js` — Événements suppression RGPD
- `api/admin.php` — 6 nouvelles clés dans allowedKeys
- `api/manage.php` — Action `delete_data` via require
- `api/rgpd-delete-data.php` — Utilise `siteConfig()` au lieu des constantes
- `classes/Mailer.php` — `getAdminName()` + signatures dynamiques
- `sql/database.sql` — INSERT par défaut des nouvelles clés

### Version 2.2.1 (13/02/2026)

**🔧 Corrections critiques**
- **Email admin** : Utilise maintenant la valeur configurée en BDD (au lieu de la constante `config.php`)
- **Encodage HTML** : `sanitize()` ne fait plus d'encodage HTML, seulement `trim()`. L'encodage se fait à l'affichage avec `escape()`
- **Récapitulatifs harmonisés** : Même icône (✓) et espacement partout

**📁 Nouveaux fichiers**
- `api/test-email.php` - Diagnostic envoi emails admin
- `sql/fix-html-encoding.sql` - Script de correction des données encodées en BDD

**📁 Fichiers modifiés**
- `classes/Mailer.php` - Nouvelle méthode `getAdminEmail()` qui lit la BDD
- `classes/Helpers.php` - `sanitize()` = trim, nouvelle méthode `escape()` pour l'affichage
- `booking/manage.php` - Icône et espacement harmonisés

### Version 2.2.0 (12/02/2026)

**🆕 Espace Client (Self-Service)**
- **Token unique** par réservation pour accès sécurisé
- **Page de gestion** `/booking/manage.php?token=xxx`
- Actions disponibles pour le client :
  - Voir les détails de son rendez-vous
  - Déplacer vers un nouveau créneau (soumis à validation)
  - Annuler son rendez-vous
- **Lien dans tous les emails** vers l'espace client
- Emails de notification à l'admin lors d'actions client

**📁 Nouveaux fichiers**
- `booking/manage.php` - Page espace client
- `api/manage.php` - API gestion client
- `assets/css/manage.css` - Styles espace client
- `assets/js/calendar-module.js` - Module calendrier partagé (réutilisable)
- `assets/js/manage.js` - JavaScript espace client
- `sql/migration-2.2.0.sql` - Migration BDD (ajout manage_token)

**📁 Fichiers modifiés**
- `classes/Booking.php` - Nouvelles méthodes : `getByToken()`, `clientReschedule()`, `clientCancel()`, `generateManageToken()`
- `classes/Mailer.php` - Nouveaux emails : `notifyClientRescheduleRequest()`, `notifyClientCancellation()` + liens de gestion
- `api/booking.php` - Inclut le token dans les emails
- `config/config.php` - Fix calcul BASE_URL (toujours racine du projet)
- `assets/js/booking.js` - Refactorisé pour utiliser CalendarModule
- `sql/database.sql` - Colonne `manage_token` ajoutée

**🔧 Refactoring**
- Calendrier extrait en module partagé (`CalendarModule`)
- Plus de duplication de code entre booking.js et manage.js
- Toutes les fonctionnalités disponibles partout (stats, indicateurs)

### Version 2.1.1 (12/02/2026)

**🔧 Corrections**
- Fix "undefined - undefined" dans les créneaux admin (propriétés `time_start`/`time_end` au lieu de `start`/`end`)
- Fix CSS créneaux : le "09:00 - 09:30" ne passe plus sur 2 lignes (`white-space: nowrap`)
- Stats affichées **directement sur les cases du calendrier** au lieu d'au-dessus
  - Pastille verte avec nombre = RDV confirmés ce jour
  - Pastille orange avec nombre = RDV en attente ce jour
- Ajout d'une légende explicative sous le titre

**📁 Fichiers modifiés**
- `assets/js/admin.js` - Correction noms de propriétés créneaux
- `assets/js/booking.js` - Chargement par mois avec stats par date
- `assets/css/booking.css` - Styles indicateurs + fix créneaux
- `booking/index.php` - Légende au lieu des stats globales
- `api/slots.php` - Retourne `booking_stats` par date
- `classes/Booking.php` - Nouvelle méthode `getBookingsByDateRange()`

### Version 2.1.0 (12/02/2026)

**🆕 Nouveautés côté visiteur**
- Indicateurs de fréquentation sur la page de réservation :
  - "X rendez-vous confirmés cette semaine"
  - "X demandes en cours de traitement"
- Endpoint API `GET /api/slots.php?stats=1` pour les statistiques publiques

**🆕 Nouveautés côté admin**
- **Déplacement de réservation** : Modifier date/heure d'un RDV existant
  - Sélection de date avec calendrier
  - Liste des créneaux disponibles uniquement
  - Vérification anti-chevauchement automatique
  - Mise à jour Google Calendar
  - Email de notification au visiteur
- **Suppression** : Possible même pour les RDV confirmés
- Boutons d'action améliorés dans la modale de détail

**📁 Fichiers modifiés**
- `classes/Booking.php` - Nouvelles méthodes `reschedule()`, `isSlotAvailable()`, `getPublicStats()`
- `classes/Mailer.php` - Nouvelle méthode `notifyReschedule()`
- `api/admin.php` - Nouvelle action `reschedule`
- `api/slots.php` - Nouvelle action `stats`
- `assets/js/admin.js` - Interface de déplacement
- `assets/js/booking.js` - Affichage des stats
- `assets/css/booking.css` - Styles des indicateurs
- `booking/index.php` - Conteneur stats

### Version 2.0.0 (12/02/2026)

**🆕 Nouveautés**
- Synchronisation Google Calendar **sans librairie externe** (API REST directe)
- Fichier de test diagnostic `/api/test-gcal.php`
- Méthode `testConnection()` pour vérifier l'accès au calendrier
- Gestion des couleurs d'événements selon le statut

**🔧 Corrections**
- Fix erreur 500 sur sauvegarde paramètres (warnings Deprecated ignorés)
- Fix type nullable dans `Slot::getAvailableDates()`
- Fix vérification `isEnabled()` au lieu de constante `GOOGLE_SYNC_ENABLED`
- Création automatique de l'événement Google si absent lors de la validation
- Amélioration des messages d'erreur debug (fichier + ligne)

**📁 Fichiers modifiés**
- `classes/GoogleCalendarSync.php` - Réécrit sans dépendance
- `classes/Slot.php` - Fix type nullable
- `classes/Booking.php` - Utilise Settings pour calendar ID
- `api/admin.php` - Fix gestion erreurs + sync Google
- `api/booking.php` - Vérifie isEnabled() au lieu de constante
- `api/test-gcal.php` - Nouveau fichier diagnostic

### Version 1.5.0 (12/02/2026)

**🆕 Nouveautés**
- Architecture modulaire avec namespace `App\`
- Autoloader PSR-4
- Classe Settings pour paramètres en BDD
- Interface admin avec onglet Paramètres
- Configuration Google Calendar depuis l'admin

**🔧 Corrections**
- Fix encodage UTF-8 dans config.php
- Fix sauvegarde paramètres (méthode `set()` corrigée)

### Version 1.0.0 (11/02/2026)

- Version initiale
- Site vitrine avec animations
- Module de réservation
- Interface admin basique
- Notifications email

---

## 📁 Arborescence des fichiers

> ⚠️ **Section obsolète depuis 2.6.0 — régénérée au §10 du chantier Booking v3.**
>
> Cette section décrivait l'état pré-purge (tunnel v2, `booking/`,
> `api/slots.php`, `api/booking.php`, `assets/css/booking.css`,
> `assets/js/booking.js`, `assets/js/calendar-module.js`, table
> `available_slots`, ENUM `service_type`, méthodes legacy
> `Slot::getAvailableSlots`/`getAvailableDates`, helper JS
> `CalendarModule`…). Tous ces éléments ont été retirés en 2.6.0.
>
> Le chantier Booking v3 continue : §6 ajoutera
> `api/stripe-webhook.php` + `cron/expire-holds.php`, §7
> `modules/booking/pack.php`, §9 `health.php`. Reconstruire le
> détail maintenant serait jeté avant la livraison v3.0.0.
>
> **Sources de vérité d'ici là** : le code réel,
> `CHANGELOG.md` (historique daté des ajouts/suppressions depuis
> la 2.4.x) et `docs/booking-v3-spec.md` (modèle, modules ajoutés,
> état d'avancement).

---

## 🔗 Relations entre fichiers

> ⚠️ **Section obsolète depuis 2.6.0 — régénérée au §10 du chantier Booking v3.**
>
> Cette section décrivait l'état pré-purge (tunnel v2, `booking/`,
> `api/slots.php`, `api/booking.php`, `assets/css/booking.css`,
> `assets/js/booking.js`, `assets/js/calendar-module.js`, table
> `available_slots`, ENUM `service_type`, méthodes legacy
> `Slot::getAvailableSlots`/`getAvailableDates`, helper JS
> `CalendarModule`…). Tous ces éléments ont été retirés en 2.6.0.
>
> Le chantier Booking v3 continue : §6 ajoutera
> `api/stripe-webhook.php` + `cron/expire-holds.php`, §7
> `modules/booking/pack.php`, §9 `health.php`. Reconstruire le
> détail maintenant serait jeté avant la livraison v3.0.0.
>
> **Sources de vérité d'ici là** : le code réel,
> `CHANGELOG.md` (historique daté des ajouts/suppressions depuis
> la 2.4.x) et `docs/booking-v3-spec.md` (modèle, modules ajoutés,
> état d'avancement).

---

## 🗺️ Carte des fonctions

> ⚠️ **Section obsolète depuis 2.6.0 — régénérée au §10 du chantier Booking v3.**
>
> Cette section décrivait l'état pré-purge (tunnel v2, `booking/`,
> `api/slots.php`, `api/booking.php`, `assets/css/booking.css`,
> `assets/js/booking.js`, `assets/js/calendar-module.js`, table
> `available_slots`, ENUM `service_type`, méthodes legacy
> `Slot::getAvailableSlots`/`getAvailableDates`, helper JS
> `CalendarModule`…). Tous ces éléments ont été retirés en 2.6.0.
>
> Le chantier Booking v3 continue : §6 ajoutera
> `api/stripe-webhook.php` + `cron/expire-holds.php`, §7
> `modules/booking/pack.php`, §9 `health.php`. Reconstruire le
> détail maintenant serait jeté avant la livraison v3.0.0.
>
> **Sources de vérité d'ici là** : le code réel,
> `CHANGELOG.md` (historique daté des ajouts/suppressions depuis
> la 2.4.x) et `docs/booking-v3-spec.md` (modèle, modules ajoutés,
> état d'avancement).

---

## 🚀 Installation

### Prérequis

- PHP 7.4 ou supérieur
- MySQL 5.7 ou supérieur
- Extensions PHP : `pdo_mysql`, `openssl`, `curl`, `json`, `mbstring`

### Étapes

1. **Uploader les fichiers** sur votre hébergement

2. **Créer la base de données** via phpMyAdmin ou équivalent

3. **Importer le schéma SQL**
   ```sql
   -- Dans phpMyAdmin, importer le fichier sql/database.sql
   ```

4. **Configurer les identifiants** dans `config/config.local.php`
   ```php
   define('DB_HOST', 'votre-serveur.mysql.db');
   define('DB_NAME', 'votre_base');
   define('DB_USER', 'votre_user');
   define('DB_PASS', 'votre_mot_de_passe');
   define('ADMIN_PASSWORD', 'mot_de_passe_admin');
   ```

5. **Tester l'installation**
   - Site vitrine : `https://votre-site.com/`
   - Réservation : `https://votre-site.com/booking/`
   - Admin : `https://votre-site.com/admin/`

---

## ⚙️ Configuration

### Fichier config/config.php

| Constante | Description | Valeur par défaut |
|-----------|-------------|-------------------|
| `BASE_URL` | URL canonique du site (lue depuis `config.local.php`) | À fixer par environnement |
| `TIMEZONE` | Fuseau horaire | `Europe/Paris` |
| `APP_DEBUG` | Mode debug (dépend de `APP_ENV`) | `true` en `development` |
| `EMAIL_ENABLED` | Activer emails | `true` |
| `ADMIN_EMAIL` | Email admin (fallback `siteConfig()`) | `admin@example.com` |
| `BOOKING_STEP` | Pas de calcul des créneaux (min) | `15` |
| `MIN_NOTICE_MIN` | Délai minimum avant un créneau sur J (min) | `120` |
| `MAX_HORIZON_DAYS` | Horizon de réservation (jours) | `60` |

> Les constantes legacy `SLOT_DURATION`, `BOOKING_ADVANCE_MIN_HOURS`,
> `BOOKING_ADVANCE_MAX_DAYS` et `SERVICE_TYPES` ont été retirées en
> 2.6.0 (purge avant §6). Les durées et tarifs vivent désormais en
> BDD dans la table `services` (cf. `docs/booking-v3-spec.md`).

### Fichier config/config.local.php

Ce fichier n'est **jamais écrasé** lors des mises à jour. Il contient vos identifiants sensibles.

```php
<?php
// Identifiants base de données
define('DB_HOST', 'localhost');
define('DB_NAME', 'ma_base');
define('DB_USER', 'mon_user');
define('DB_PASS', 'mon_password');
define('DB_CHARSET', 'utf8mb4');
define('DB_PORT', 3306);

// Mot de passe admin
define('ADMIN_PASSWORD', 'mon_mot_de_passe_admin');
```

### Paramètres en base de données

Via l'interface admin (`/admin/?page=settings`) :

| Clé | Description | Propagation |
|-----|-------------|-------------|
| `site_name` | Nom du site | Titres `<title>`, footers, emails |
| `admin_name` | Prénom | Logos, signature emails, pages légales |
| `admin_lastname` | Nom de famille | Pages légales (responsable traitement) |
| `admin_email` | Email de notification | Emails, pages légales (contact RGPD) |
| `admin_phone` | Téléphone | Mentions légales |
| `admin_address` | Adresse professionnelle | Mentions légales, politique confidentialité |
| `admin_siret` | Numéro SIRET | Mentions légales, politique confidentialité |
| `legal_status` | Statut juridique | Mentions légales (EI, SASU, etc.) |
| `google_calendar_enabled` | Activer sync Google (0/1) | Sync API |
| `google_calendar_id` | ID du calendrier Google | Sync API |

> **💡 Toutes ces valeurs se propagent automatiquement** dans les pages légales, les emails, les logos et les footers. Aucun fichier à modifier manuellement.

---

## 🔐 Tutoriel Google Service Account

### Pourquoi un Service Account ?

Un Service Account permet à votre application d'accéder à l'API Google Calendar **sans intervention humaine** (pas de popup de connexion). C'est idéal pour un serveur.

### Étape 1 : Créer un projet Google Cloud

1. Allez sur [Google Cloud Console](https://console.cloud.google.com/)
2. Connectez-vous avec votre compte Google
3. Cliquez sur le sélecteur de projet en haut → **"Nouveau projet"**
4. Donnez un nom (ex: `renaud-booking`)
5. Cliquez **"Créer"** et attendez la création

### Étape 2 : Activer l'API Google Calendar

1. Dans le menu ☰ → **"API et services"** → **"Bibliothèque"**
2. Recherchez **"Google Calendar API"**
3. Cliquez dessus puis **"Activer"**

### Étape 3 : Créer le Service Account

1. Menu ☰ → **"API et services"** → **"Identifiants"**
2. Cliquez **"+ Créer des identifiants"** → **"Compte de service"**
3. Remplissez :
   - **Nom** : `booking-sync`
   - **Description** : `Synchronisation calendrier réservations`
4. Cliquez **"Créer et continuer"**
5. Passez l'étape des rôles (pas nécessaire) → **"Continuer"**
6. Cliquez **"OK"**

### Étape 4 : Générer la clé JSON

1. Dans la liste des comptes de service, cliquez sur `booking-sync`
2. Onglet **"Clés"**
3. Cliquez **"Ajouter une clé"** → **"Créer une clé"**
4. Sélectionnez **"JSON"**
5. Cliquez **"Créer"**
6. **Le fichier se télécharge automatiquement** (gardez-le précieusement !)

### Étape 5 : Installer le fichier sur votre serveur

1. Renommez le fichier téléchargé en **`google-credentials.json`**
2. Uploadez-le dans le dossier **`config/`**
3. Vérifiez que le chemin est : `config/google-credentials.json`

⚠️ **Sécurité** : Ce fichier contient une clé privée. Ne le commitez jamais sur Git public !

### Étape 6 : Partager votre calendrier avec le Service Account

1. Ouvrez [Google Calendar](https://calendar.google.com/)
2. À gauche, survolez votre calendrier → cliquez sur ⋮ → **"Paramètres et partage"**
3. Section **"Partager avec des personnes ou des groupes"**
4. Cliquez **"+ Ajouter des personnes ou des groupes"**
5. Collez l'email du Service Account :
   ```
   booking-sync@votre-projet.iam.gserviceaccount.com
   ```
   (Vous trouvez cet email dans le fichier JSON, champ `client_email`)
6. Permissions : **"Apporter des modifications aux événements"**
7. Cliquez **"Envoyer"**

### Étape 7 : Récupérer l'ID du calendrier

1. Toujours dans les paramètres du calendrier
2. Section **"Intégrer le calendrier"**
3. Copiez l'**"ID de l'agenda"** (format : `xxx@group.calendar.google.com`)

### Étape 8 : Configurer dans l'administration

1. Allez sur `https://votre-site.com/admin/?page=settings`
2. Collez l'ID du calendrier dans le champ prévu
3. Activez le toggle "Synchronisation Google Calendar"
4. Cliquez **"Enregistrer"**

### Étape 9 : Tester la configuration

1. Allez sur `https://votre-site.com/api/test-gcal.php`
2. Vérifiez que tous les tests sont ✅
3. Cliquez sur "Créer un événement de test"
4. Vérifiez dans votre Google Calendar

### Résumé des fichiers nécessaires

| Fichier | Emplacement | Contenu |
|---------|-------------|---------|
| `google-credentials.json` | `config/` | Clé du Service Account |
| En BDD | Table `settings` | `google_calendar_id` + `google_calendar_enabled` |

### Fonctionnement de la synchronisation

```
┌─────────────────┐     ┌──────────────────┐     ┌─────────────────┐
│ Nouvelle        │────►│ Site de          │────►│ Google          │
│ réservation     │     │ réservation      │     │ Calendar        │
└─────────────────┘     └──────────────────┘     └─────────────────┘
                               │
                               │ 1. Génère JWT signé
                               │ 2. Échange contre Access Token
                               │ 3. Appelle API Calendar
                               │ 4. Crée/modifie événement
                               ▼
                        ┌──────────────────┐
                        │ Événement créé   │
                        │ avec couleur     │
                        │ selon statut     │
                        └──────────────────┘
```

| Statut | Couleur | Préfixe titre |
|--------|---------|---------------|
| `pending` | 🟠 Orange (Mandarine) | [ATTENTE] |
| `confirmed` | 🟢 Vert (Basilic) | [OK] |
| `cancelled` | ⚫ Gris (Graphite) | [ANNULÉ] |
| `completed` | 🔵 Bleu (Myrtille) | [TERMINÉ] |

---

## 📖 Utilisation

> ⚠️ **Section obsolète depuis 2.6.0 — régénérée au §10 du chantier Booking v3.**
>
> Cette section décrivait l'état pré-purge (tunnel v2, `booking/`,
> `api/slots.php`, `api/booking.php`, `assets/css/booking.css`,
> `assets/js/booking.js`, `assets/js/calendar-module.js`, table
> `available_slots`, ENUM `service_type`, méthodes legacy
> `Slot::getAvailableSlots`/`getAvailableDates`, helper JS
> `CalendarModule`…). Tous ces éléments ont été retirés en 2.6.0.
>
> Le chantier Booking v3 continue : §6 ajoutera
> `api/stripe-webhook.php` + `cron/expire-holds.php`, §7
> `modules/booking/pack.php`, §9 `health.php`. Reconstruire le
> détail maintenant serait jeté avant la livraison v3.0.0.
>
> **Sources de vérité d'ici là** : le code réel,
> `CHANGELOG.md` (historique daté des ajouts/suppressions depuis
> la 2.4.x) et `docs/booking-v3-spec.md` (modèle, modules ajoutés,
> état d'avancement).

---

## 📡 API Reference

> ⚠️ **Section obsolète depuis 2.6.0 — régénérée au §10 du chantier Booking v3.**
>
> Cette section décrivait l'état pré-purge (tunnel v2, `booking/`,
> `api/slots.php`, `api/booking.php`, `assets/css/booking.css`,
> `assets/js/booking.js`, `assets/js/calendar-module.js`, table
> `available_slots`, ENUM `service_type`, méthodes legacy
> `Slot::getAvailableSlots`/`getAvailableDates`, helper JS
> `CalendarModule`…). Tous ces éléments ont été retirés en 2.6.0.
>
> Le chantier Booking v3 continue : §6 ajoutera
> `api/stripe-webhook.php` + `cron/expire-holds.php`, §7
> `modules/booking/pack.php`, §9 `health.php`. Reconstruire le
> détail maintenant serait jeté avant la livraison v3.0.0.
>
> **Sources de vérité d'ici là** : le code réel,
> `CHANGELOG.md` (historique daté des ajouts/suppressions depuis
> la 2.4.x) et `docs/booking-v3-spec.md` (modèle, modules ajoutés,
> état d'avancement).

---

## 🔧 Dépannage

### Erreur 500 sur l'API admin

1. Vérifiez les logs PHP de votre hébergeur
2. Allez sur `/api/test-admin.php` pour diagnostiquer
3. Vérifiez que toutes les tables existent en BDD

### Les emails ne partent pas

1. Vérifiez que `EMAIL_ENABLED` est `true`
2. OVH mutualisé utilise la fonction `mail()` native
3. Vérifiez les logs email de votre hébergeur

### Google Calendar ne se synchronise pas

1. Allez sur `/api/test-gcal.php`
2. Vérifiez chaque étape du diagnostic
3. Causes fréquentes :
   - Fichier `google-credentials.json` absent ou invalide
   - Calendrier non partagé avec le Service Account
   - ID calendrier incorrect dans les paramètres

### Le calendrier n'affiche pas les créneaux

1. Vérifier les fenêtres d'ouverture dans la table `availability` (planning hebdo récurrent depuis 2.6.0)
2. Vérifier que la date n'est pas dans `blocked_dates` ni écrasée par `availability_exceptions`
3. Tester avec `/api/booking-v3-slots.php?service=<id>&date=YYYY-MM-DD`

### Problème d'encodage (accents)

1. Vérifiez que votre éditeur enregistre en UTF-8
2. Le fichier `.htaccess` force UTF-8
3. Les tables MySQL utilisent `utf8mb4`

---

## 📞 Support

Pour toute question ou problème :
- Consultez d'abord ce README
- Utilisez les fichiers de test (`/api/test-*.php`)
- Vérifiez les logs PHP de votre hébergeur

---

**Développé avec ❤️ par Claude AI pour Renaud - Performance Collective**
