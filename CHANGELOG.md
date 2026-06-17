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

## [2.5.0] — 2026-06-17 — Booking v3 §3 : modèle de données

Premier checkpoint du chantier **Module de réservation v3** (cible
v3.0.0). Cette release ne change rien à l'expérience utilisateur —
elle pose le modèle de données qui sera consommé par §4-§10.

Les pré-requis cadrage (`CADRAGE_UNIVERSEL.md` + `INSTRUCTIONS_…` à
**v1.2**) ont été posés en amont (deux commits dédiés, cf. branche
`claude/dazzling-goodall-VLGur`).

### 🗄️ SQL — nouvelles tables

- **`services`** — catalogue prestations (CRUD admin). Remplace
  l'ENUM figé `service_type` de l'offre conseil. Porte `slug`
  (unique), `segment` ∈ {sportif, dirigeant, particulier}, `name`,
  `description`, `duration_min`, `buffer_after_min` (temps de
  compte rendu, défaut 15 min), `price_cents` (0 → séance gratuite
  / court-circuit Stripe), `stripe_price_id` (renseigné en admin
  après création du Price), `is_active`, `sort_order`.
- **`availability`** — planning récurrent hebdomadaire en fenêtres
  `[window_start, window_end]` au lieu de créneaux pré-découpés.
  Les créneaux sont calculés au runtime à partir de (fenêtre,
  service.duration, service.buffer, `BOOKING_STEP`). Unique sur
  `(day_of_week, window_start, window_end)`.
- **`availability_exceptions`** — overrides par date.
  `is_available = 0` → journée fermée ; `is_available = 1` + une
  fenêtre → cette fenêtre remplace le récurrent pour ce jour.
- **`packages`** — forfaits à jetons. Réfère 1 service inclus
  (`fk_packages_service`). `sessions_count`, `validity_days`,
  `price_cents`, `stripe_price_id`.
- **`package_purchases`** — achats clients. Porte les crédits
  (`credits_total`, `credits_used`), le `manage_token` unique
  d'accès à l'espace pack, le statut ∈ {pending_payment, active,
  expired, exhausted}. Un seul jeton par client — les séances
  issues du pack n'ont pas leur propre `manage_token` (cf.
  invariant § 4 de `docs/booking-v3-spec.md`).
- **`stripe_events_processed`** — idempotence du webhook Stripe.
  PK = `event_id`. En tête de `api/stripe-webhook.php` (à créer
  §6) : `INSERT IGNORE` ; `rowCount() == 0` → event déjà traité →
  200 no-op.

### 🗄️ SQL — table `bookings` étendue

Colonnes ajoutées (toutes nullable / avec default, donc non
destructives sur les enregistrements existants) :

- `service_id` (FK NULL — anciens bookings conseil restent NULL),
- `package_purchase_id` (FK NULL — séance issue d'un pack),
- `duration_min`, `buffer_after_min` (copiés du service à la
  création — figent la durée du booking, indépendamment d'une
  édition ultérieure du catalogue),
- `payment_status` ∈ {none, pending, paid, refunded},
- `stripe_session_id`, `amount_paid_cents`,
- `payment_expires_at` (hold de 15 min pendant Stripe Checkout),
- `confirmation_email_sent_at` (garde-fou anti double-email sur
  retry webhook Stripe).

ENUM `status` étendu de `pending_payment` (hold pendant Stripe) et
`expired` (hold libéré sans confirmation).

### 🛡️ Invariant — `active_key` redéfinie pour inclure `pending_payment`

```sql
active_key = CASE WHEN status IN ('pending','confirmed','pending_payment')
                  THEN CONCAT(slot_date, '_', slot_time_start)
                  ELSE NULL END
```

Sans cela, deux holds concurrents de 15 min sur le même créneau ne
seraient pas arbitrés par l'UNIQUE → les deux clients arrivent sur
Stripe, les deux paient → double booking. La nouvelle colonne
ferme le cas « même départ ». Le cas « durées variables, départs
différents qui se chevauchent » sera couvert au §5/§6 par une
vérification transactionnelle (`SELECT … FOR UPDATE`). Les deux
gardes coexistent (cf. `docs/booking-v3-spec.md` § 4).

### 🗄️ Migration `sql/migration-3.0.0.sql`

**Non destructive sur les données live.** Ordre :

1. CREATE TABLE des 6 nouvelles tables.
2. DROP UNIQUE `uq_active_slot` + DROP COLUMN `active_key`.
3. MODIFY enum `bookings.status` (ajout `pending_payment` +
   `expired`).
4. ADD COLUMNs nouvelles sur `bookings`.
5. ADD `active_key` étendue + UNIQUE.
6. ADD FKs `fk_bookings_service` + `fk_bookings_package_purchase`
   (ON DELETE SET NULL → un service ou pack supprimé ne supprime
   pas les bookings historiques, il les détache).
7. **Regroupement `available_slots` → `availability`** en SQL pur
   avec `LAG()` + `SUM() OVER` (prérequis MariaDB ≥ 10.2 ou
   MySQL ≥ 8). Trous = coupures de fenêtres. **Résultat attendu
   sur défauts inchangés** (12 × 5 = 60 créneaux 30 min) : 10
   fenêtres (matin + après-midi × 5 jours).
8. Seed `services` (10 prestations selon §11 du prompt v3) +
   `packages` (3 forfaits), **dupliqué** depuis `seed.sql` car les
   bases déjà déployées ne rejouent jamais `seed.sql` — sans cette
   duplication, la prod arriverait en 3.0.0 avec un catalogue vide
   → tunnel cassé.

⚠️ **Sauvegarde obligatoire avant exécution** :

```
mysqldump --single-transaction --routines --triggers \
  -u <user> -p <db> > backup-pre-3.0.0-$(date +%F).sql
```

OVH mutualisé = pas d'atomicité de déploiement. Rollback = restore
du dump + revert du code.

### 🗄️ SQL — `schema.sql` & `seed.sql`

`sql/schema.sql` consolide la cible install neuve : 6 nouvelles
tables + nouvelle structure `bookings`. `available_slots`
conservée en cible (vide) pour réversibilité d'un éventuel rejeu
de la migration sur install neuve. `service_type` (ancienne ENUM
offre conseil) gardée pour les bookings archivés — plus utilisée
par le code v3.

`sql/seed.sql` remplace le seed `available_slots` (60 lignes) par
10 fenêtres `availability` (Lu-Ve, 09:00-12:00 + 14:00-17:00) et
ajoute le catalogue `services` + `packages`. `price_cents` reflète
la grille tarifaire Focus Coach (montants en centimes, alignés sur
les commentaires `STRIPE_PRICES` du template `config.local.php`) —
Sport Flash 80 €, Préparation mentale 100 €, Décision Express
200 €, Manager Miroir 250 €, Coaching Fondateur 280 €, Duo Aligné
350 €, Déclic 30 70 €, Reset Mental & Rebond 100 €, forfaits 5×
420 €, Parcours Dirigeant 6× 1 500 €. La séance Découverte reste
à 0 (gratuite par design → court-circuit Stripe). `stripe_price_id`
restent NULL — Renaud les renseignera en admin après avoir créé
les Price côté Stripe. Tant que c'est le cas, `health.php` (§9)
signalera les services avec `price_cents > 0` mais sans
`stripe_price_id`, et le tunnel (§6) refusera de démarrer pour
ces services.

### 📄 Doc — `docs/booking-v3-spec.md`

Mémoire technique du chantier, créée au §3, à enrichir aux
checkpoints suivants :

- Modèle de données (lien vers schema.sql).
- Algorithme de regroupement `available_slots` → `availability` +
  résultat attendu sur défauts.
- Algorithme de calcul des créneaux (esquisse §4).
- Invariants de course : `active_key` étendue + vérification
  transactionnelle de chevauchement + retry sur collision hold
  expiré.
- Machines à états `bookings.status` et
  `package_purchases.status`.
- Tunnel multi-pages PHP (état dans `$_SESSION['booking_draft']`).
- Stripe : résilience clés absentes, court-circuit `price_cents
  == 0`, webhook idempotent, effets de bord (sync GCal, email)
  rendus idempotents **chacun séparément** plutôt que via le flag
  d'event (sinon un retry après timeout GCal perdrait la sync).
- RGPD : `package_purchases` est un nouveau puits de données
  nominatives, à couvrir par le cron de purge.
- Procédure de rollback (dump avant migration).

### 📘 Cadrage en amont

Cadrage universel passé à **v1.2** (deux commits dédiés `91ab9b3`
+ `a5f6a29`, sur la branche `claude/dazzling-goodall-VLGur`).

- **`CADRAGE_UNIVERSEL.md` v1.2** : AD-8 étendu au pendant
  runtime (`health.php`, liveness rapide / check profond séparés,
  **aucun appel API tiers synchrone** sinon une panne externe
  ferait passer le déploiement en ROUGE à tort). AD-9 étendu aux
  tests d'API/endpoints. §8 checklist : 2 lignes ajoutées.
- **`INSTRUCTIONS_DEMARRAGE_SESSION_UNIVERSEL.md` v1.2** : sources
  d'autorité recentrées sur l'état réel du repo (commits poussés
  ou ZIP de réamorçage en filet). Pipeline humain → instance
  Claude (architecture + rédaction des prompts) → Claude Code
  (implémentation) → allers-retours → verdict humain, explicité.

Pré-requis §12 du prompt Booking v3 levé.

## [2.4.8] — 2026-06-16 — `available_slots` idempotent

### 🗄️ SQL
- **`schema.sql`** — ajout `UNIQUE KEY uq_slot (day_of_week, time_start,
  time_end)` sur `available_slots`. Empêche les doublons silencieux si
  quelqu'un rejoue `seed.sql` par erreur.
- **`seed.sql`** — créneaux passent de `INSERT` sec à `INSERT IGNORE`.
  Désormais idempotent : rejouable sans risque, sans warning.
- **`sql/migration-2.4.8.sql`** — pour les bases déjà déployées :
  1. `DELETE` auto-join de dédoublonnage sur
     `(day_of_week, time_start, time_end)` qui garde l'id le plus
     ancien par groupe. Safe : ne supprime rien si la table était
     déjà propre.
  2. `ALTER TABLE ... ADD UNIQUE KEY uq_slot`. Si cet ALTER échoue
     avec « Duplicate entry », c'est qu'il reste des doublons que
     l'étape 1 n'a pas couverts → la requête de diagnostic est
     fournie en commentaire.

### Contexte
- Renaud avait re-seedé 3 fois → 180 lignes (3 occurrences par
  créneau) au lieu de 60. Dédoublonnage manuel via le DELETE
  auto-join (-120 lignes → 60).
- Le `seed.sql` v2.4.7 documentait l'usage « ne pas rejouer » mais
  ne l'empêchait pas techniquement. v2.4.8 industrialise la garantie.

## [2.4.7] — 2026-06-16 — SQL : schema.sql + seed.sql + outils

### 🗄️ SQL — source unique
- **`sql/database.sql` supprimé**. Mélangeait DDL + données + `CREATE
  DATABASE` + `DROP TABLE` → impossible à ré-exécuter sur une base
  existante sans tout effacer.
- **`sql/schema.sql`** — structure pure (`CREATE TABLE IF NOT EXISTS`),
  idempotent, zéro données, zéro `CREATE DATABASE`. Inclut toutes les
  tables : `bookings` (avec `active_key STORED` du Lot 3), `available_slots`,
  `blocked_dates`, `settings`, `rgpd_deletion_log`, `purge_stats`,
  `admin_login_attempts` (Lot 2).
  Prérequis : MySQL ≥ 5.7.6 ou MariaDB ≥ 10.2 (pour la colonne générée).
- **`sql/seed.sql`** — valeurs par défaut, idempotent.
  - `settings` : 11 clés (`site_name`, `admin_name`/`lastname`/
    `activity`/`email`/`phone`/`address`/`siret`, `legal_status`,
    `google_calendar_enabled`/`id`) en `INSERT IGNORE` — **ne touche
    pas** aux valeurs déjà personnalisées en admin (la clé unique
    `idx_setting_key` arbitre côté MySQL).
  - `available_slots` : Lundi-Vendredi, 9h-12h et 14h-17h, par tranches
    de 30 min, en `INSERT IGNORE`.
  - Note : la version finalement committée (après revue) utilise
    `INSERT IGNORE` partout pour la lisibilité, et non le
    `ON DUPLICATE KEY UPDATE setting_value = setting_value` initial
    (sémantiquement équivalent, mais c'était un no-op explicite
    déguisé). Cf. commit suivant « refactor(sql): adopter la version
    revue par Renaud ».

### 📘 Règle « à tenir à jour »
- **CLAUDE.md §8.3** réécrite : SQL = source unique. À chaque modif
  de schéma → `schema.sql` MAJ dans le même commit. À chaque ajout
  d'une clé `settings` consommée par le code → `seed.sql` MAJ dans
  le même commit. Les `migration-vX.Y.Z.sql` restent l'historique
  incrémental pour les bases déjà déployées (chaque migration livrée
  se retrouve aussi dans `schema.sql`/`seed.sql`).
- **Checklist anti-régression** : nouvelle ligne « SQL aligné ».
- **État courant** : section DB mise à jour.

### 🧰 hash-password.php — mode WEB
- Ajout d'un mode WEB en plus du CLI existant : formulaire HTML
  accessible depuis le navigateur pour générer `ADMIN_PASSWORD_HASH`
  quand SSH n'est pas dispo (VM de test, OVH mutualisé).
- Détection automatique du SAPI : CLI → comportement d'origine,
  Web → formulaire.
- Headers `Cache-Control: no-store, no-cache` + `X-Robots-Tag:
  noindex, nofollow` → le hash n'est ni indexé ni mis en cache.
- Le mot de passe est effacé (`$password = ''; unset($_POST[...])`)
  dès qu'on n'en a plus besoin — pas de log, pas de stockage.
- Bandeau rouge « À SUPPRIMER après usage » en tête de page.

## [2.4.6] — 2026-06-16 — Lot 4 : Échappement systématique

### 🔒 Sécurité
- **5 `<title>` corrigés** (admin/index.php, booking/index.php,
  booking/manage.php, confidentialite.php, mentions-legales.php) :
  `$pageTitle` et `siteConfig()['site_name']` passent désormais par
  `Helpers::escape()`. Pré-Lot 4, un admin qui plaçait `<script>` dans
  le nom du site (champ paramétrable depuis l'admin) le voyait
  s'exécuter dans toutes les pages.
- **Audit complet `<?= ... ?>`** sur les templates publics + admin :
  - `$pageTitle` dans `<h1>` (booking, manage, légales).
  - Sortie de `SERVICE_TYPES` dans `<option value/label>` de booking.
  - `$booking['status']`, `status_info['label']`, `formatted_date`,
    `formatted_time`, `service_label`, `visitor_name`, `subject`,
    `$token`, `$error` dans `booking/manage.php`.
  - Ternaires à littéraux dans `admin/index.php` (classes actives,
    checked, label toggle) → wrappés dans `Helpers::escape()` même
    quand la valeur est contrôlée — cohérence pour le guard.
  - `<a href="tel:<?= preg_replace(...) ?>">` dans `index.php` →
    enveloppé dans `Helpers::escape()` (defense-in-depth contre une
    valeur admin contenant des chars HTML).
- **`htmlspecialchars(...)` → `Helpers::escape(...)`** dans
  `booking/manage.php` (cohérence : un seul helper d'échappement
  dans le projet).

### 🛡️ Garde-fou AD-8 — pre-commit Lot 4
- Nouveau garde-fou bloquant : tout `<?= ... ?>` dans un template doit
  commencer par une fonction de l'allowlist `Helpers::escape`,
  `brandWordmark`, `Icons::svg`, `cfgField`, `pwaHead`, `pwaRegister`,
  `appVersion`, `cacheVersion`, `Helpers::csrfMeta`, `Helpers::csrfField`,
  `date(`. Cibles : `index.php`, `admin/index.php`,
  `booking/index.php`, `booking/manage.php`, `confidentialite.php`,
  `mentions-legales.php`. `Mailer.php` et `GoogleCalendarSync.php`
  hors scope (pas de `<?=`, et leurs emojis sont des exceptions
  tolérées par la règle d'or §2.11 — contenu emails / Google
  Calendar, hors DOM).

### 🧹 AD-5 — emojis 3-bytes résiduels
- `❌` (`circle-x`), `⏳` (`hourglass`), `✓` (`check`) dans
  `booking/manage.php` (4 occurrences) et `booking/index.php`
  (1 occurrence) remplacés par `Icons::svg(...)`. Le hook AD-5 utilise
  le pattern `[\xF0-\xF4]` qui ne matche que les 4-bytes (smileys,
  pictogrammes hors BMP) — il loupait `❌` (E2 9D 8C) et `⏳`
  (E2 8F B3). Pas de changement du hook : le DOM est maintenant propre,
  et toute nouvelle insertion sera attrapée par revue de code (les 5
  fichiers du DOM utilisateur sont peu nombreux et stables).

## [2.4.5] — 2026-06-16 — Lot 3 : Intégrité

### 🧱 Race double-booking
- **Colonne générée `bookings.active_key` + `UNIQUE KEY`** :
  `CASE WHEN status IN ('pending','confirmed') THEN
   CONCAT(slot_date,'_',slot_time_start) ELSE NULL END`. L'unicité
  d'un créneau actif est désormais arbitrée côté SQL — deux requêtes
  qui passent en même temps `isSlotTaken()` ne peuvent plus toutes les
  deux insérer : la loser tombe sur SQLSTATE 23000. NULL pour
  `cancelled`/`completed` (InnoDB autorise plusieurs NULL sur UNIQUE),
  donc un créneau libéré peut être re-réservé.
- **`Booking::create()` trap 23000** : retour « Ce créneau vient
  d'être réservé. Veuillez en choisir un autre. » au lieu d'une
  erreur générique. Le code de race coexiste avec la vérif PHP en
  amont — la vérif filtre 99 % des cas, l'UNIQUE attrape les 1 % de
  concurrence pure.
- Migration `sql/migration-2.4.5.sql` + intégration au schéma de
  référence `sql/database.sql`.

### 🧱 Idempotence reschedule
- **`Booking::reschedule()` + `Booking::clientReschedule()`** :
  early return `unchanged:true` si `slot_date` et `slot_time_start`
  et `slot_time_end` (comparés sur 5 chars `HH:MM`) sont identiques
  à la valeur en BDD. Le drapeau permet à l'API de skipper la sync
  Google Calendar et les notifications email.
- **`api/admin.php` et `api/manage.php`** consomment `unchanged` —
  double POST / re-clic → plus de double mail, plus de PATCH Google
  Calendar inutile.

### 🧱 Timeouts cURL Google bornés
- **`GoogleCalendarSync`** : constantes `CURL_CONNECT_TIMEOUT=5s` et
  `CURL_TOTAL_TIMEOUT=15s` appliquées au endpoint token et à chaque
  `apiRequest()`. Avant : `CURLOPT_TIMEOUT=30s` sans
  `CONNECTTIMEOUT` — une connexion qui rame pouvait monopoliser
  l'essentiel du budget Apache 60 s. Désormais : au-delà du budget,
  on log et on rend la main. La BDD locale reste source de vérité,
  la sync repasse au prochain événement (création/mise à jour
  suivante).

### 🧱 `SET time_zone` MySQL ↔ PHP
- **`Database::getInstance()`** exécute
  `SET time_zone = '<date('P')>'` après `new PDO()`. `date('P')`
  produit l'offset numérique courant (`+01:00` ou `+02:00` avec
  DST), accepté tel quel par MySQL — pas besoin du nom de fuseau ni
  de la table `mysql.time_zone_name` (souvent absente sur OVH
  mutualisé). Aligne `NOW()` SQL et `DateTime` PHP : fin des
  décalages d'1 ou 2 h sur `created_at`, `confirmed_at`, comparaisons
  de créneaux.

### ✅ Non-régression (AD-9)
- `tests/smoke.php` : +5 cas Lot 3 (format `date('P')` `±HH:MM`,
  longueur `active_key` ≤ 20 chars, équivalence `HH:MM` qui ignore
  les secondes, minute différente → change détecté). La race UNIQUE
  et les timeouts cURL sont des tests d'intégration BDD/réseau —
  hors smoke unitaire.

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
