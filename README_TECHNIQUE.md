# 📘 README TECHNIQUE - Système de Réservation

> **Document orienté développeur / IA**  
> Mémoire vivante du projet - Mis à jour à chaque itération

**Version:** 2.7.0  
**Dernière mise à jour:** 25 juin 2026 — v2.7.0 (module diagnostic AD-11 : hub + 5 pages, lecture seule)

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
| 25/06/2026 | 2.7.0 | 🩺 Module diagnostic | **`modules/diagnostic/` (AD-11) — hub + 5 pages, lecture seule, auth admin, secrets masqués.** `health` (PHP/extensions/BDD/tables/config/fuseaux/BASE_URL, résilient si BDD down via `diag_try_pdo` qui ne `die()` pas), `config-check` (constantes par sévérité, secrets jamais affichés), `alignment` (VERSION↔stamps, enum `bookings.status`⇄`active_key`⇄`BOOKING_STATUS`, catalogue seed⇄migration), `api-console` (fetch **client** d'un endpoint GET — évite le loopback serveur non fiable ici), `smoke` (corpus en `<iframe>`). CSS `diagnostic.css` 100 % tokens + un seul bloc `DIAG-FALLBACK` (garde-fou hex étendu). +4 icônes Lucide dans les 2 miroirs. **Fix [O1]** : `diag_head()` n'utilise plus `siteConfig()` (qui touche la BDD et `die()`) mais la constante `SITE_NAME` → header résilient. Tests 2 sens prouvés : BASE_URL absent → 500 ; BDD KO → health 200 « Inaccessible » ; stamp altéré → dérive ; statut hors enum → signalé ; hex hors fallback → pre-commit bloque ; lecture seule (0 écriture), 0 fuite de secret. |
| 25/06/2026 | 2.6.8 | 🔒 §6 BASE_URL | **`BASE_URL` stricte, jamais dérivée du `Host`.** `config/config.php` ne dérive plus l'URL de `$_SERVER['HTTP_HOST']`/`SCRIPT_NAME` (bloc retiré) ; `BASE_URL` est lue exclusivement depuis `config.local.php` (anti XSS Host-header, valide en webhook/cron). Sanction au démarrage via `\App\configProblems()` (C1, source unique) : config requise manquante/mal formée → **HTTP 500 + message clair** (refus net). Le warning « Constant BASE_URL already defined » disparaît par construction (plus de 2ᵉ `define`) — smoke CLI 0 warning, 30/30. ⚠️ Migration : `config.local.php` **doit** définir `BASE_URL` sinon 500. Doc : template + `docs/booking-v3-spec.md` (§ BASE_URL livrée + checklist). |
| 25/06/2026 | 2.6.7 | 🧱 Fondation | **Import SQL robuste au charset + hook fail-closed.** `SET NAMES utf8mb4;` en 1ʳᵉ ligne de `sql/schema.sql` et `sql/seed.sql` : un import via client défaut latin1/cp850 (mysql.exe Windows) corrompait les chaînes accentuées à chaque clone (invisible sur `COUNT`, visible sur le contenu). Prouvé `[O1]` par re-import **sans flag** → `HEX` propre (`é`=C3A9, `—`=E28094) sur `services.name`, `settings.legal_status` et les `COMMENT` du schéma. Hook : garde-fou AD-5 **fail-closed** (self-check emoji 😀 en tête ; si le grep ne compile pas le motif hors-BMP → blocage avec message clair, plus de fail-open silencieux via `\|\| true`). |
| 25/06/2026 | 2.6.6 | 🛠️ Outillage | **Fix faux positif emoji du `pre-commit`.** Le garde-fou AD-5 faisait `grep -nP '[\xF0-\xF4]'` : en locale UTF-8 (Git Bash Windows), PCRE interprète `\xF4` comme le codepoint U+00F4 = `ô` → faux positifs sur « Rôle », « contrôle », « côté »… bloquant chaque commit français. Remplacé par `[\x{10000}-\x{10FFFF}]` (hors-BMP = 4 octets UTF-8 = emoji modernes), robuste à la locale. Source + copie installée alignés. |
| 25/06/2026 | 2.6.6 | 🛠️ Outillage | **`tests/smoke.php` lisible en navigateur.** En HTTP, sortie enveloppée `<!doctype html> … <pre>` + `Content-Type: text/html; charset=utf-8` → les retours à la ligne du corpus sont préservés (avant : pavé sur une seule ligne, le HTML écrasant les `\n`). **Gardé par `PHP_SAPI`** : en CLI, sortie strictement inchangée (mêmes octets, exit codes 0/1) → aucun impact CI/perf. **Zéro CSS ajouté** (pas de `<style>`, pas de `main.css`) — on ne tire pas le design system de l'app dans un outil de test (§2.2 / AD-4). Le `Warning: Constant BASE_URL already defined` reste le signal pré-§6 connu (garde manquante `config/config.php:60`). Smoke 24/24 vert. |
| 17/06/2026 | 2.6.5 | 📄 AD-7 / AD-10 | **Trou laissé par 2.6.4 corrigé** — l'audit indépendant a remonté à juste titre que `README_TECHNIQUE.md` gardait des sections **non neutralisées** qui décrivaient du code mort comme vivant, hors des 2 encarts posés en 2.6.4. Mon grep post-2.6.4 était scopé `README.md` seulement, j'aurais dû le refaire sur les deux fichiers. **3 zones traitées ici** : (1) **§ Relations entre fichiers** (le diagramme ASCII `booking/index.php → api/booking.php → calendar-module.js → api/slots.php` était précisément l'artefact le plus trompeur pour une session §6) → **3ᵉ encart** d'obsolescence cohérent avec les 2 autres ; (2) **§ Chargement CSS par page** → corrigée directement (table petite et stable) — `booking/index.php` retiré, `booking/manage.php` → `modules/booking/manage.php`, `booking.css` → `booking-v3.css`, ajout des 5 nouvelles pages v3 (`index.php`/`date.php`/`slot.php`/`confirm.php`/`success.php`) ; (3) **exemple `brandWordmark()`** ligne 518 — `<span class="accent">` (classe supprimée en 2.4.2) → `.brand-half-a` / `.brand-half-b` avec explication du split par milieu de caractères. |
| 17/06/2026 | 2.6.5 | ✅ AD-9 | Smoke 24/24 vert (logique pure inchangée). Vérif post-patch des **deux** fichiers de doc cette fois : `grep -nE 'getAvailableSlots\|/api/slots\.\|booking/index\|calendar-module\|class="accent"\|...'` sur README.md → **0 hit** hors changelog + encarts ; sur README_TECHNIQUE.md → 11 hits restants, tous **dans des encarts d'obsolescence qui CITENT volontairement les éléments supprimés** ou dans la nouvelle table CSS qui liste les **vrais** chemins `modules/booking/`. Plus aucun mensonge structurel ouvert dans la doc avant §6. |
| 17/06/2026 | 2.6.4 | 📄 AD-7 / AD-10 | **Neutralisation des sections doc obsolètes** (proposition de l'audit indépendant, validation utilisateur). Les sections « Arborescence complète » et « Carte des fonctions » de **`README_TECHNIQUE.md`** + les 5 sections legacy de **`README.md`** racine (« Arborescence des fichiers », « Relations entre fichiers », « Carte des fonctions », « Utilisation », « API Reference ») sont remplacées par un encart standardisé qui pointe vers la source de vérité (code réel + `CHANGELOG.md` + `docs/booking-v3-spec.md`) — la régénération complète attendra le §10 (quand les §6-§9 auront stabilisé l'arborescence et les méthodes). Les tables de **changelog** historiques restent intactes (les entrées datées qui citent `booking/`, `api/slots.php`, `getAvailableSlots`, etc. sont factuelles dans leur contexte historique — AD-7). |
| 17/06/2026 | 2.6.4 | 📄 Fix doc précis | **Table des constantes `config.php`** (`README.md` §Configuration) : `BOOKING_DURATION` (jamais existé dans le code, faux depuis l'origine), `BOOKING_ADVANCE_MIN_HOURS` (supprimée 2.6.0), `BOOKING_ADVANCE_MAX_DAYS` (supprimée 2.6.0) → remplacées par les vraies constantes v3 (`BOOKING_STEP`, `MIN_NOTICE_MIN`, `MAX_HORIZON_DAYS`). Note explicite sous la table sur les constantes retirées. Section Dépannage : exemple `api/slots.php?date=…` (endpoint supprimé) → `api/booking-v3-slots.php?service=<id>&date=YYYY-MM-DD`. Mention `WORKING_HOURS` (constante inexistante) → mention de la table `availability` (planning v3 réel). |
| 17/06/2026 | 2.6.4 | ✅ AD-9 | Smoke 24/24 vert (logique pure inchangée). Vérif post-patch : `grep -E 'getAvailableSlots\|/api/slots\.\|booking/index\|booking/manage\|calendar-module\|SERVICE_TYPES\|BOOKING_ADVANCE\|available_slots\|CalendarModule\|BOOKING_DURATION\|SLOT_DURATION\|WORKING_HOURS' README.md` hors changelog historique et hors encarts → **0 résultat**. README.md passé de 922 à 612 lignes (~310 lignes de mensonge retirées). |
| 17/06/2026 | 2.6.3 | 🎨 AD-4 | **`style="margin-top:...; font-size:...;"` décoratif retiré de `modules/booking/manage.php:155`** (paragraphe d'aide sous le bouton Annuler). Classe utilitaire `.bv3-help-note` ajoutée à `assets/css/booking-v3.css`. Régression introduite au §5b (le `style=` venait du `manage.php` legacy v2 et a survécu à la copie vers v3) — remontée par l'audit indépendant [O1] et confirmée localement par `grep 'style="' modules/booking/*.php | grep -v display:none` → 0 résiduel post-patch. Les 3 `style="display:none;"` restants sur les modales (`#delete-data-modal`, `#cancel-modal`, `#success-card`) sont l'exception fonctionnelle pilotée par JS tolérée par CLAUDE.md §1.2. |
| 17/06/2026 | 2.6.3 | 📄 Doc | **Checklist pré-bascule prod ajoutée** comme nouvelle section `§7.bis` de `docs/booking-v3-spec.md`. L'audit a remonté à juste titre qu'on ne peut pas confier `APP_ENV='development'` + clé cron RGPD en placeholder au seul `health.php` futur (qui n'arrive qu'au §9) : d'ici là, le filet est humain. La section liste les invariants Code & config (APP_ENV, ADMIN_PASSWORD_HASH, clés Stripe live, BASE_URL canonique, hash-password.php supprimé du serveur, google-credentials sécurisé), BDD (`mysqldump`, migration appliquée, `stripe_price_id` complets, crons RGPD et expire-holds planifiés) et Serveur (HTTPS forcé, cookies session durcis, webhook testé en sandbox, sw.js bumpé). Verdict : ne pas basculer tant qu'une case est rouge. |
| 17/06/2026 | 2.6.2 | 🔧 Fix | **Liens email — régression purge 2.6.0 corrigée**. `classes/Mailer.php::getManageLink()` pointait encore vers `BASE_URL . "booking/manage.php?token=..."` (page droppée). Cassait tous les emails contenant le bloc « Gérer votre rendez-vous » (visiteur nouvelle demande, confirmation, déplacement admin, déplacement client). Idem pour les 2 liens « nouveau RDV » dans `notifyCancellation()` et `notifyClientCancellation()` qui pointaient vers `booking/`. Les 3 chemins basculent sur `modules/booking/...`. Remontée par un audit indépendant [O1, RESUME_focuscoach.md]. |
| 17/06/2026 | 2.6.2 | 🎨 Icons | **`arrow-left` ajoutée aux deux miroirs**. `classes/Icons.php` (LIBRARY) et `assets/js/icons.js` (LIBRARY) — le nom était utilisé 6 fois dans le tunnel v3 (`modules/booking/{index,date,slot,confirm,success,manage}.php` pour les liens « Retour ») mais absent des deux miroirs → `Icons::svg('arrow-left')` retournait une chaîne vide (fallback du validateur de nom), d'où les rendus vides observés. Path SVG Lucide officiel symétrique d'`arrow-right` : `<path d="M19 12H5"/><path d="m12 5-7 7 7 7"/>`. Validation [O1] : `Icons::svg('arrow-left', 20, 'icon-test')` produit 269 bytes de SVG bien formé (DOMDocument::loadXML accepte). Remontée par un audit indépendant. |
| 17/06/2026 | 2.6.2 | 🧹 Doc inline | **Commentaires obsolètes corrigés**. `index.php:313` HTML comment « (vers le module /booking/) » → `(vers /modules/booking/)`. `api/booking-v3-slots.php:14` docblock qui présentait `api/slots.php` et `booking/index.php` comme cohabitants (alors qu'ils sont supprimés depuis 2.6.0) → reformulé comme leur successeur unique. Conservés tels quels (factuels) : `config/config.php:55` (décrit le regex `(api|admin|booking)` du calcul `BASE_URL` legacy, à dégager au §6) et `classes/Slot.php:91` (mémo historique « avant la purge 2.6.0… »). |
| 17/06/2026 | 2.6.2 | ✅ AD-9 | Smoke 24/24 vert (logique pure inchangée). `php -l` propre sur les 4 fichiers PHP touchés. Validation runtime d'`Icons::svg('arrow-left')` ajoutée à la main : retour non vide + XML valide via DOMDocument. Garde-fous AD-1/AD-4/AD-5/Lot 4 VERTS au commit. |
| 17/06/2026 | 2.6.2 | 📋 Audit | **3 autres écarts remontés non patchés ici**, suivant l'arbitrage avec le user : (1) `APP_ENV='development'` et clé cron RGPD en placeholder → seront traités au checkpoint pré-prod (à grouper avec §9 `health.php`) ; (2) sections « Arborescence » et « Carte des fonctions » du README_TECHNIQUE et du README.md racine encore pré-2.6.0 → dette confirmée pour le passage v3.0.0 (déjà tracée au 2.6.0). 7 autres points du `RESUME_focuscoach.md` non actionnables ou non remontés au tri (dépendent du contenu live de `config.local.php` / `settings`). |
| 17/06/2026 | 2.6.1 | 🛠️ Outil | **`sql/reset-dev.sql` créé** — script de remise à zéro complète, **DESTRUCTIF, dev uniquement**. Drop toutes les tables manipulées par le projet (v3 + historiques) dans l'ordre inverse des FK (`bookings` → `package_purchases` → `packages` → `services`, puis les indépendantes). Workflow de reset dev : `mysql < reset-dev.sql && mysql < schema.sql && mysql < seed.sql` → base propre, données seed (settings, availability, services, packages). `schema.sql` reste non destructif (`CREATE TABLE IF NOT EXISTS`, AD-5 cadrage) — c'est `reset-dev.sql` qui porte exclusivement la responsabilité de nettoyer. À NE JAMAIS appliquer en prod tant qu'un utilisateur réel a posé un booking. |
| 17/06/2026 | 2.6.1 | 🧹 AD-2 | **`config/config.local.php.template` purgé** — la grille tarifaire mémo (« Sport Flash 80 €… ») est retirée. Source unique = `services.price_cents` en BDD (seed). Le template ne porte plus que : `DB_*`, `ADMIN_PASSWORD_HASH`, `STRIPE_SECRET_KEY` / `STRIPE_PUBLISHABLE_KEY` / `STRIPE_WEBHOOK_SECRET`, `BASE_URL`, `GOOGLE_CREDENTIALS_PATH` (optionnel). Le commentaire de la section Stripe pointe vers la BDD comme source de vérité, sans répéter les valeurs. |
| 17/06/2026 | 2.6.1 | 🔌 Config §6 | **`BASE_URL` ajoutée au template** — URL canonique absolue (avec slash final) de la racine du site. Sert à Stripe Checkout (`success_url` / `cancel_url`) et à tout endpoint produisant une URL absolue. **Jamais dérivée du header `Host`** (XSS Host-header). Fixe par environnement (prod / staging / dev) — exemples fournis en commentaire. Le déplacement effectif de `BASE_URL` depuis `config/config.php` (où elle est encore calculée à partir de `$_SERVER['HTTP_HOST']`) vers une lecture stricte de `config.local.php` fera partie du périmètre §6 (paiement Stripe). |
| 17/06/2026 | 2.6.1 | 📄 Dettes | **Dettes confirmées (pas d'action)** : reschedule client désactivé (tracé en 2.6.0, rebrancher après §6/§7 sur `api/booking-v3-slots.php`) ; `README.md` racine à refondre au passage v3.0.0 (références aux constantes/fichiers legacy supprimés). Les deux restent au changelog comme dette technique connue. |
| 17/06/2026 | 2.6.0 | ✂️ Purge | **Purge legacy avant §6 — tunnel v2 retiré**. Le user a confirmé phase de dev sans utilisateurs : on dégage la dette legacy plutôt que d'enchaîner Stripe sur une base à deux algos. **Fichiers supprimés** : `booking/index.php`, `booking/manage.php`, `booking/` (dossier vide), `api/slots.php`, `api/booking.php`, `assets/css/booking.css`, `assets/js/booking.js`, `assets/js/calendar-module.js`. `booking/manage.php` est remplacé par `modules/booking/manage.php` (version v3 minimale : récap + cancel + RGPD, reschedule client temporairement désactivé — `Booking::reschedule()` reste utilisable côté admin). |
| 17/06/2026 | 2.6.0 | 🧱 Modèle | **`classes/Slot.php`** : suppression de toutes les méthodes legacy (`getAvailableForDate`, `getAvailableDates`, `getAvailabilityForMonth`, `getSlotsByDay`, `toggleSlot`). Le fichier ne contient plus que la logique v3 (`computeCandidates` statique pure, `resolveDayWindows`, `getActiveBookingsForDate`, `computeSlotsForService`, `getServiceAvailableDates`, `getServiceAvailabilityForMonth`) + `blocked_dates` (utile). Nouvelle méthode `getAvailabilityByDay()` qui remplace `getSlotsByDay()` pour le back-office, en lisant la table `availability`. |
| 17/06/2026 | 2.6.0 | 🧱 Modèle | **`classes/Booking.php`** : `create()` accepte uniquement le mode v3 (refus si `service_id` manquant) — la branche legacy `service_type` est supprimée. `getByToken()`, `getById()` et `getAll()` font désormais un **`LEFT JOIN services s ON s.id = b.service_id`** qui expose `service_name` + `service_slug` sur chaque booking. `enrichBooking()` retire l'attribut `service_label` (qui faisait le lookup dans `SERVICE_TYPES`) — `service_name` (issu du JOIN) est la nouvelle voie unique. |
| 17/06/2026 | 2.6.0 | 🧱 Modèle | **`classes/Mailer.php`** et **`classes/GoogleCalendarSync.php`** : le lookup `SERVICE_TYPES[$booking['service_type']]` est remplacé par `$booking['service_name'] ?? 'Prestation'` (utilise la valeur posée par le JOIN). Plus de dépendance à l'ENUM legacy. |
| 17/06/2026 | 2.6.0 | ⚙️ Config | **`config/config.php`** : suppression de `SLOT_DURATION`, `BOOKING_ADVANCE_MIN_HOURS`, `BOOKING_ADVANCE_MAX_DAYS`, `SERVICE_TYPES`, et du champ `BOOKING_STATUS['icon']` (emoji jamais consommé par une vue depuis la bascule SVG Lucide — flag déjà posé dans `CLAUDE.md` §10). Restent : `TIMEZONE`, `BOOKING_STEP`, `MIN_NOTICE_MIN`, `MAX_HORIZON_DAYS`, `BOOKING_STATUS` (sans `icon`). |
| 17/06/2026 | 2.6.0 | 🔌 API | **`api/admin.php`** : le case `slots` (qui exposait `Slot::getSlotsByDay()` lisant `available_slots`) devient `availability` et expose `Slot::getAvailabilityByDay()` (fenêtres du planning hebdo récurrent v3). Le CRUD complet (édition / ajout / suppression) viendra avec §8. |
| 17/06/2026 | 2.6.0 | 🗄️ SQL | **`sql/migration-3.0.0.sql`** : ajout d'une étape 11 destructive — `DROP TABLE IF EXISTS available_slots` + `ALTER TABLE bookings DROP COLUMN service_type`. La migration est donc destructive sur cette dernière étape ; un commentaire explicite avertit qu'il faut retirer ces lignes si on l'applique sur une prod historique qui contiendrait des bookings de l'offre conseil. |
| 17/06/2026 | 2.6.0 | 🗄️ SQL | **`sql/schema.sql`** : le `CREATE TABLE available_slots` (était conservé en cible pour réversibilité) est retiré ; la déclaration de `bookings` retire la colonne `service_type` (ENUM offre conseil). Le commentaire en tête explique que le nom de la prestation s'obtient désormais via `JOIN services.name`. |
| 17/06/2026 | 2.6.0 | 🎨 Frontal | **CTA basculé sur `/modules/booking/`** : `index.php` racine (4 liens) et `admin/index.php` (lien sidebar "Page réservation") pointent désormais vers le tunnel v3. Le dossier `/booking/` (et son `index.php` qui était la cible historique) n'existe plus. |
| 17/06/2026 | 2.6.0 | 🛡️ Hook | **Pre-commit + miroir** : `hex_files` retire `assets/css/booking.css` ; `emoji_targets` et `escape_targets` retirent `booking/index.php`, `booking/manage.php`, `assets/js/booking.js`, et ajoutent `modules/booking/manage.php`. **`scripts/git-hooks/pre-commit` resynchronisé** avec le hook local — Renaud refait `cp scripts/git-hooks/pre-commit .git/hooks/pre-commit && chmod +x .git/hooks/pre-commit` pour propager. |
| 17/06/2026 | 2.6.0 | 📄 Doc | **`docs/booking-v3-spec.md`** : ligne `§5b — purge legacy` ajoutée à l'état d'avancement (marquée livrée). La section « Co-existence v2 ↔ v3 » devient obsolète et sera nettoyée au passage v3.0.0. **Dette restante** : `README.md` racine contient encore plusieurs références à l'arborescence et aux constantes legacy (`SERVICE_TYPES`, `BOOKING_ADVANCE_*`, `assets/js/calendar-module.js`, `Slot::getAvailableDates`) — à refondre quand on stabilisera v3.0.0 (pas critique pour le pipeline). |
| 17/06/2026 | 2.6.0 | ✅ AD-9 | Smoke 24/24 vert sans modification (les cas testent la logique pure de `computeCandidates`, indépendante de la suppression du tunnel legacy). `php -l` propre sur les 17 fichiers PHP touchés. Vérification d'intégrité visuelle requise côté Renaud (le tunnel v3 reste à tester en navigateur sur les 3 tailles). |
| 17/06/2026 | 2.5.3 | 🧹 AD-2 | **Cleanup mono-source — grille tarifaire retirée du template**. La constante `STRIPE_PRICES` de `config/config.local.php.template` (jamais consommée par le code, vérifié `grep -rn STRIPE_PRICES *.php` → 0 hit hors `config/`) est remplacée par un commentaire qui pointe vers la source unique : `services.stripe_price_id` + `packages.stripe_price_id` en BDD, gérés via /admin. Évite la divergence inévitable entre la grille en BDD et celle dans le template à mesure que Renaud édite ses tarifs en admin. La constante reste tolérée dans un `config.local.php` existant (pas de code qui la lit, pas de breakage). |
| 17/06/2026 | 2.5.2 | 🗂️ Module | **Booking v3 — checkpoint §5 : tunnel multi-pages PHP** (`modules/booking/`, AD-6 autonome). 6 pages PHP server-rendered, état dans `$_SESSION['booking_draft']` : `index.php` (étape 1, cards prestations par segment), `date.php` (étape 2, liste des dates disponibles pour le service choisi via `Slot::getServiceAvailableDates`), `slot.php` (étape 3, créneaux du jour via `Slot::computeSlotsForService`), `confirm.php` (étape 4, formulaire identité + récap), `process.php` (création du booking via `Booking::create()` mode v3, CSRF vérifié), `success.php` (confirmation). Pas de SPA — cohérent avec la stack vanilla et `booking/manage.php`. Le tunnel legacy `/booking/` reste en place (transition). |
| 17/06/2026 | 2.5.2 | 🔌 API | **`api/booking-v3-slots.php`** créé — endpoint service-aware (`?service=<id>` requis) avec trois modes : `&date=YYYY-MM-DD` → créneaux du jour ; `&month=YYYY-MM` → vue calendrier mensuelle ; sans paramètre → toutes les dates disponibles sur l'horizon. L'ancien `api/slots.php` reste en place (consommé par le tunnel legacy v2). |
| 17/06/2026 | 2.5.2 | 🧱 Modèle | **`Booking::create()` accepte le mode v3** : si `$data['service_id'] > 0`, insère `service_id` + `duration_min` + `buffer_after_min` (figés à la création) + `payment_status = 'none'` ; sinon mode legacy (service_type ENUM). Bascule détectée à l'usage, pas par signature → pas de duplication. La race UNIQUE `active_key` continue de fermer le double-booking — l'invariant transactionnel pour les chevauchements de durées variables sera ajouté au §6 (POST de paiement). |
| 17/06/2026 | 2.5.2 | 🎨 CSS | **`assets/css/booking-v3.css`** créé — préfixe `.bv3-` (pas de collision avec `.booking-` v2). Mobile-first (breakpoints 768 / 1024), cards 1→2→3 colonnes, steps bar (1→4) horizontale dès 768 px. Zéro hex en dur (`var(--navy)`, `--orange`, `--cream`, `--text-dark`, `--success`, `--shadow-card`, `--radius-md`, `--white`). |
| 17/06/2026 | 2.5.2 | 🛡️ Hook | **Pre-commit étendu** : `hex_files` ajoute `assets/css/booking-v3.css` ; `emoji_targets` et `escape_targets` ajoutent les 6 pages `modules/booking/*.php`. Le hook étant local (`.git/hooks/pre-commit`), un miroir versionné est ajouté en **`scripts/git-hooks/pre-commit`** — installation : `cp scripts/git-hooks/pre-commit .git/hooks/pre-commit && chmod +x .git/hooks/pre-commit`. À refaire côté Renaud avant tout commit qui touche les nouveaux fichiers. |
| 17/06/2026 | 2.5.2 | ⚠️ Limites | **§5 ne fait PAS de paiement** — booking créé en `status = 'pending'` / `payment_status = 'none'` (équivalent legacy v2, validation admin). §6 réécrira `process.php` pour insérer Stripe Checkout (et `success.php` pour traiter le retour). Hold `pending_payment` + invariant transactionnel arrivent avec §6. |
| 17/06/2026 | 2.5.2 | ⚠️ Limites | **Vérification visuelle requise** côté Renaud : un container éphémère ne permet pas de tester le tunnel dans un navigateur. À valider en local sur les 3 tailles (375 / 768 / 1280) selon la checklist CLAUDE.md, et à vérifier le flow complet prestation → date → créneau → confirm → success. Bugs UX éventuels traités en patch (2.5.3). |
| 17/06/2026 | 2.5.1 | 🧮 Algo | **Booking v3 — checkpoint §4 : algorithme de calcul des créneaux**. `classes/Slot.php` étendu — méthode statique pure `computeCandidates()` (testable sans BDD, cœur de l'algo : pour chaque fenêtre, parcours par pas `BOOKING_STEP`, candidat `t` valide si `t + duration ≤ W_end`, `t ≥ earliestStart` et `[t, t + duration + buffer)` ne chevauche aucun intervalle occupé — convention `[start, end)` semi-ouvert) + méthodes BDD `resolveDayWindows()` (priorité `blocked_dates` > `availability_exceptions[date]` > `availability[day_of_week]`), `getActiveBookingsForDate()` (filtre les holds expirés via lazy-expiry), `computeSlotsForService()` (orchestrateur applique `MIN_NOTICE_MIN`/`MAX_HORIZON_DAYS`), `getServiceAvailableDates()`, `getServiceAvailabilityForMonth()`. **L'algo v2 (`getAvailableForDate`, `getAvailableDates`, `getAvailabilityForMonth`) est conservé en parallèle** — encore consommé par `api/slots.php` jusqu'à la bascule §5. |
| 17/06/2026 | 2.5.1 | ⚙️ Config | **`config/config.php`** : 3 constantes ajoutées pour l'algo v3 — `BOOKING_STEP` (15 min), `MIN_NOTICE_MIN` (120 min), `MAX_HORIZON_DAYS` (60 j). Les constantes legacy `BOOKING_ADVANCE_MIN_HOURS` / `BOOKING_ADVANCE_MAX_DAYS` restent en place (consommées par le Slot.php v2). |
| 17/06/2026 | 2.5.1 | ✅ AD-9 | **`tests/smoke.php`** étend de 7 cas en Lot 5 — algo créneaux (logique pure, sans BDD) : fenêtre 09-12 + service 60+15 step 15 → 9 candidats ; buffer respecté (booking 11-12 + service 60+15 → 4 candidats 09:00…09:45) ; booking + buffer qui coupe la fenêtre en deux moitiés trop courtes → 0 candidat ; plusieurs fenêtres (matin + après-midi) concaténées dans l'ordre ; `earliestStart` filtre les candidats du matin (cas MIN_NOTICE sur J) ; `duration ≤ 0` / `step ≤ 0` → 0 candidat (défense en profondeur) ; intervalles juxtaposés sans chevauchement (`[09:00, 10:00)` + `[10:00, 11:00)` co-existent — vérifie la convention semi-ouverte). 24/24 cas verts. |
| 17/06/2026 | 2.5.1 | 📄 Doc | **`docs/booking-v3-spec.md`** §3 réécrite : pseudo-code définitif, tableau des constantes tunables, convention d'intervalle semi-ouvert documentée, **co-existence v2 ↔ v3 explicitée** (Slot.php héberge les deux algos en parallèle pendant la transition — bascule programmée §5). |
| 17/06/2026 | 2.5.0 | 🗄️ SQL | **Booking v3 — checkpoint §3 : modèle de données**. Nouvelles tables : `services` (catalogue prestations, segment ∈ sportif/dirigeant/particulier, `duration_min`, `buffer_after_min`, `price_cents`, `stripe_price_id`), `availability` (planning récurrent hebdo en fenêtres `[window_start, window_end]` — remplace `available_slots`), `availability_exceptions` (overrides par date), `packages` (forfaits, FK vers `services`), `package_purchases` (achats clients, `manage_token` unique, `credits_total`/`credits_used`, `expires_at`), `stripe_events_processed` (idempotence webhook). Colonnes ajoutées à `bookings` : `service_id` (FK NULL), `package_purchase_id` (FK NULL), `duration_min`, `buffer_after_min` (figés à la création), `payment_status` ∈ {none, pending, paid, refunded}, `stripe_session_id`, `amount_paid_cents`, `payment_expires_at`, `confirmation_email_sent_at`. ENUM `status` étendu de `pending_payment` et `expired`. `active_key` redéfinie pour inclure `pending_payment` (sinon double-hold non arbitré → double paiement possible). |
| 17/06/2026 | 2.5.0 | 🗄️ SQL | **`sql/migration-3.0.0.sql`** créée — non destructive sur les données live. Ordre : nouvelles tables → DROP `active_key` + index → MODIFY enum `status` → ADD colonnes nouvelles → ADD `active_key` étendue + UNIQUE → ADD FKs sur `bookings` → migration `available_slots` → `availability` (regroupement des créneaux consécutifs en SQL pur avec `LAG()` + `SUM() OVER`) → seed `services` + `packages` **DUPLIQUÉ** (les bases déjà déployées ne rejouent pas `seed.sql`, sinon catalogue vide → tunnel cassé). Sauvegarde `mysqldump` obligatoire avant exécution (OVH mutualisé = pas d'atomicité). |
| 17/06/2026 | 2.5.0 | 🗄️ SQL | **`sql/schema.sql`** mis à jour avec les 6 nouvelles tables et la nouvelle structure `bookings`. `available_slots` conservée en cible (vide) pour réversibilité d'un rejeu de la migration sur install neuve. `service_type` (ancienne ENUM offre conseil) gardée pour les bookings archivés — plus utilisée par le code v3. |
| 17/06/2026 | 2.5.0 | 🗄️ SQL | **`sql/seed.sql`** remplace le seed `available_slots` (60 lignes de créneaux 30 min) par 10 fenêtres `availability` (Lu-Ve, 09:00-12:00 + 14:00-17:00). Ajout du catalogue `services` (10 prestations selon §11 du prompt v3) et 3 forfaits. `price_cents` aligné sur la grille tarifaire Focus Coach déjà versionnée dans `config/config.local.php.template` (80 € / 100 € / 200 € / 250 € / 280 € / 350 € / 70 € / 100 € × 2 / forfaits 420 € × 2 et 1 500 €) ; séance Découverte = 0 (gratuite). `stripe_price_id` restent NULL — `health.php` (§9) signalera ceux à compléter en admin, le tunnel (§6) refusera de démarrer tant que pas de price_id valide. |
| 17/06/2026 | 2.5.0 | 📄 Doc | **`docs/booking-v3-spec.md`** créé — mémoire technique du chantier : modèle de données, algorithme de regroupement `available_slots` → `availability` (résultat attendu sur défauts : 10 fenêtres), algorithme de calcul des créneaux (esquisse §4), invariants de course (`active_key` étendue + vérification transactionnelle de chevauchement + retry sur collision hold expiré), machines à états `bookings` + `package_purchases`, pipeline Stripe (résilience clés absentes, court-circuit prix 0, webhook idempotent, effets de bord rendus idempotents séparément), procédure de rollback (dump avant migration). |
| 17/06/2026 | 2.5.0 | 📘 Cadrage | **Cadrage universel à v1.2** posé en amont (deux commits dédiés `91ab9b3` + `a5f6a29`). `CADRAGE_UNIVERSEL.md` v1.2 étend AD-8 au pendant runtime (`health.php` — liveness rapide / check profond séparés, **aucun appel API tiers synchrone** sinon faux ROUGE) et AD-9 aux tests d'API/endpoints. `INSTRUCTIONS_DEMARRAGE_SESSION_UNIVERSEL.md` v1.2 recentre les sources d'autorité sur l'état réel du repo et explicite le pipeline humain → instance Claude (architecture + prompts) → Claude Code (implémentation) → allers-retours → verdict humain. Pré-requis §12 du prompt v3 levé. |
| 16/06/2026 | 2.4.8 | 🗄️ SQL | **`available_slots` idempotent**. Ajout `UNIQUE KEY uq_slot (day_of_week, time_start, time_end)` dans `schema.sql`. `seed.sql` passe d'`INSERT` sec à `INSERT IGNORE` — rejouable sans risque de doublons (Renaud avait re-seedé 3 fois → 180 lignes au lieu de 60, dédoublonnage manuel à 120 supprimés). Migration `sql/migration-2.4.8.sql` : DELETE auto-join de dédoublonnage (garde le plus petit id par groupe) + ALTER ADD UNIQUE. Safe sur base déjà propre. |
| 16/06/2026 | 2.4.7 | 🗄️ SQL | **Source unique du schéma**. `sql/database.sql` (qui mélangeait DDL + seed + `CREATE DATABASE` + `DROP TABLE`) supprimé. Remplacé par : `sql/schema.sql` (structure pure, `CREATE TABLE IF NOT EXISTS`, idempotent, zéro données, prérequis MySQL ≥ 5.7.6 ou MariaDB ≥ 10.2 pour `active_key STORED`) + `sql/seed.sql` (paramètres `settings` + créneaux Lu-Ve 9-17 via `INSERT IGNORE` — non destructif, ne réécrit pas les valeurs déjà personnalisées). Installation fraîche : `mysql < schema.sql && mysql < seed.sql`. Les `sql/migration-vX.Y.Z.sql` restent l'historique incrémental pour bases déjà déployées. |
| 16/06/2026 | 2.4.7 | 🧰 Outil/Web | `scripts/hash-password.php` mode WEB ajouté (en plus du CLI existant) : formulaire HTML pour générer `ADMIN_PASSWORD_HASH` quand SSH indisponible (VM de test, OVH mutualisé). Headers `Cache-Control: no-store` + `X-Robots-Tag: noindex`. Mot de passe effacé côté serveur dès le hash retourné. Bandeau « À SUPPRIMER après usage » bien visible. |
| 16/06/2026 | 2.4.7 | 📘 Règle | CLAUDE.md §8.3 réécrite : SQL = source unique (`schema.sql` + `seed.sql`) à tenir à jour dans le même commit qu'une modif de schéma ou un ajout de clé `settings`. Checklist anti-régression : nouvelle ligne « SQL aligné ». |
| 16/06/2026 | 2.4.6 | 🔒 Sécurité | **Lot 4 — Échappement systématique**. 5 `<title>` (admin/booking/manage/légales) : `$pageTitle` + `siteConfig()['site_name']` passent par `Helpers::escape()`. Tous les `<?= $var ?>` bruts (h1, footer site_name, option SERVICE_TYPES, status badge, info-value, error, token data-attr) : `Helpers::escape()`. `htmlspecialchars(...)` → `Helpers::escape(...)` (cohérence — un seul helper). `<a href="tel:<?= preg_replace(...) ?>">` enveloppé dans `escape()` (defense-in-depth). |
| 16/06/2026 | 2.4.6 | 🛡️ AD-8 | **Garde-fou pre-commit Lot 4** : tout `<?= ... ?>` doit débuter par une fonction allowlistée — `Helpers::escape`, `brandWordmark`, `Icons::svg`, `cfgField`, `pwaHead`, `pwaRegister`, `appVersion`, `cacheVersion`, `Helpers::csrfMeta`, `Helpers::csrfField`, `date(`. Hors allowlist = rouge bloquant. Cible : pages templates publiques + admin (Mailer / GoogleCalendarSync hors scope — pas de `<?=`). |
| 16/06/2026 | 2.4.6 | 🧹 AD-5 | Emojis 3-bytes résiduels (`❌`, `⏳`, `✓`) remplacés par `Icons::svg('circle-x'\|'hourglass'\|'check')` dans `booking/manage.php` + `booking/index.php`. Le hook AD-5 ne flagait que les 4-bytes (`[\xF0-\xF4]`) — gap couvert au passage côté code (le hook reste, mais le DOM est déjà propre). |
| 16/06/2026 | 2.4.5 | 🧱 Intégrité | **Lot 3 — race double-booking fermée**. Colonne générée `bookings.active_key = CONCAT(slot_date,'_',slot_time_start)` quand status ∈ (`pending`,`confirmed`), NULL sinon, + `UNIQUE KEY uq_active_slot`. L'arbitrage passe côté SQL : deux requêtes concurrentes peuvent franchir `isSlotTaken()`, l'INSERT loser tombe sur 23000. `Booking::create()` trap le 23000 et renvoie « Ce créneau vient d'être réservé ». Migration `sql/migration-2.4.5.sql` + intégration `sql/database.sql`. |
| 16/06/2026 | 2.4.5 | 🧱 Intégrité | **Idempotence reschedule**. `Booking::reschedule()` + `Booking::clientReschedule()` : early return `unchanged:true` si `slot_date` ET `slot_time_start[0..5]` ET `slot_time_end[0..5]` identiques à la valeur en base. `api/admin.php` + `api/manage.php` skippent alors la sync Google Calendar et `Mailer::notifyReschedule()`/`notifyClientRescheduleRequest()` — fin du double mail sur double POST. |
| 16/06/2026 | 2.4.5 | 🧱 Intégrité | **Timeouts cURL Google bornés** (`GoogleCalendarSync`). Constantes `CURL_CONNECT_TIMEOUT=5s` + `CURL_TOTAL_TIMEOUT=15s` appliquées au token endpoint et à chaque `apiRequest()`. Au-delà : log + retour `null`, on rend la main avant les 60 s du budget Apache. La BDD locale reste cohérente, la sync repasse au prochain événement. |
| 16/06/2026 | 2.4.5 | 🧱 Intégrité | **`SET time_zone` MySQL aligné sur PHP**. `Database::getInstance()` exécute `SET time_zone = '<date(\'P\')>'` après `new PDO()` — offset numérique (`+01:00` / `+02:00` avec DST). Élimine les décalages d'1 ou 2 h entre `NOW()` SQL et `DateTime` PHP (visibles dans `created_at`, `confirmed_at`, comparaisons de créneaux). |
| 16/06/2026 | 2.4.5 | ✅ AD-9 | `tests/smoke.php` étend de 5 cas Lot 3 (format `date('P')`, longueur `active_key` ≤ 20, équivalence HH:MM ignorant les secondes, minute différente = change). Race UNIQUE + timeouts cURL = tests d'intégration BDD/réseau, hors smoke unitaire. |
| 16/06/2026 | 2.4.4 | 🔒 Sécurité | **Lot 2 — Auth admin durcie**. `admin/index.php` bascule sur `password_verify(ADMIN_PASSWORD_HASH)` strict ; `define('ADMIN_PASSWORD', 'renaud2026')` supprimé de `config/config.php`. Rate limit 5 essais / 15 min par IP (table `admin_login_attempts`). Cookies session : `HttpOnly`/`SameSite=Lax`/`Secure` conditionnel HTTPS. `session_regenerate_id(true)` après login (anti-fixation). Logout efface session + cookie. |
| 16/06/2026 | 2.4.4 | 🧰 Helper | `Helpers::clientIp()`, `Helpers::isLoginLocked($ip)`, `Helpers::recordLoginAttempt($ip, $success)` + constantes `LOGIN_MAX_ATTEMPTS=5` / `LOGIN_WINDOW_MIN=15`. Succès purge les échecs antérieurs (relâche le verrou). Fail-open BDD pour ne pas lock-out sur panne. |
| 16/06/2026 | 2.4.4 | 🗄️ Migration | `sql/migration-2.4.4.sql` + `sql/database.sql` : nouvelle table `admin_login_attempts (ip_address, success, attempted_at)` avec index composite `(ip_address, attempted_at)`. |
| 16/06/2026 | 2.4.4 | ⚙️ Config | `config/config.php` exige désormais `config.local.php` (plus de fallback clair). `config/config.local.php.template` documente `ADMIN_PASSWORD_HASH`. `includes/init.php` durcit les cookies de session via `session_set_cookie_params()` avant `session_start()`. |
| 16/06/2026 | 2.4.4 | ✅ AD-9 | `tests/smoke.php` étend de 5 cas auth bcrypt (hash valide OK/KO, hash vide rejeté, hash malformé rejeté, format `$2y$`). Rate limit testé en intégration BDD, hors smoke unitaire. |
| 16/06/2026 | 2.4.4 | 🧹 Doc | `README_TECHNIQUE.md` pied de page « Dernière mise à jour … v2.4.0 » supprimé — un seul stamp de version en en-tête (AD-2 appliqué au stamp lui-même). |
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

> ⚠️ **Section obsolète depuis 2.6.0 — régénérée au §10 du chantier Booking v3.**
>
> Cette arborescence décrivait l'état pré-purge (tunnel v2, `booking/`,
> `api/slots.php`, `api/booking.php`, `assets/css/booking.css`,
> `assets/js/booking.js`, `assets/js/calendar-module.js`, table
> `available_slots`, ENUM `service_type`…). Tous ces éléments ont été
> retirés en 2.6.0. À l'inverse, elle ignore `modules/booking/`,
> `api/booking-v3-slots.php`, `assets/css/booking-v3.css`,
> `sql/reset-dev.sql`, `scripts/git-hooks/`, `cadrage/`, `docs/booking-v3-spec.md`,
> et — depuis 2.7.0 — **`modules/diagnostic/`** (hub + pages health /
> config-check / alignment / api-console / smoke, AD-11) et
> **`assets/css/diagnostic.css`**.
>
> Et le chantier continue : §6 ajoutera `api/stripe-webhook.php` et
> `cron/expire-holds.php`, §7 ajoutera `modules/booking/pack.php`.
> *(La santé runtime promise « §9 » est livrée en 2.7.0 comme page du
> module diagnostic — `modules/diagnostic/health.php` —, pas un
> `health.php` racine.)* Reconstruire le détail maintenant serait
> jeté avant la livraison v3.0.0.
>
> **Sources de vérité d'ici là** :
> - Code réel — un `tree -L 3` sur le repo dit l'état exact ;
> - `CHANGELOG.md` — historique daté de tous les ajouts/suppressions
>   depuis la 2.4.x ;
> - `docs/booking-v3-spec.md` — modèle de données, modules ajoutés,
>   état d'avancement du chantier.

---

## 🔗 Relations entre fichiers

> ⚠️ **Section obsolète depuis 2.6.0 — régénérée au §10 du chantier Booking v3.**
>
> Le diagramme de flux décrivait le pipeline pré-purge
> `booking/index.php → api/booking.php → calendar-module.js →
> api/slots.php` — **tous ces fichiers ont été supprimés en
> 2.6.0**. Le vrai flux v3 est `modules/booking/{index,date,slot,
> confirm}.php → api/booking-v3-slots.php` (lecture) et
> `modules/booking/process.php → Booking::create() v3` (écriture),
> avec retour Stripe Checkout à venir au §6 (`api/stripe-webhook.php`
> + `cron/expire-holds.php`).
>
> La table de dépendances mentionnait aussi `api/slots.php`,
> `api/booking.php`, `calendar-module.js` (supprimés) et ignorait
> les nouvelles relations (`Slot.php` → `api/booking-v3-slots.php`,
> JOIN `services` dans `Booking::getByToken`/`getById`/`getAll`,
> `modules/booking/manage.php` → `api/manage.php`).
>
> **Sources de vérité d'ici la régénération v3.0.0** : le code
> réel (les `require_once` en tête de chaque fichier donnent
> l'arborescence des dépendances directement), `CHANGELOG.md`
> (historique des renommages depuis 2.4.x), et
> `docs/booking-v3-spec.md` (modèle de données + pipeline §6).

---

## 🗺️ Carte des fonctions

> ⚠️ **Section obsolète depuis 2.6.0 — régénérée au §10 du chantier Booking v3.**
>
> Cette table décrivait des méthodes qui n'existent plus
> (`Slot::getAvailableSlots`, `Slot::getAvailableDates` — supprimées
> en 2.6.0 avec la purge ; `CalendarModule (JS)` — supprimé idem) et
> des signatures Booking pré-v3 (`create()` mode legacy
> `service_type`, `service_label` via `SERVICE_TYPES` — supprimés).
>
> À l'inverse, elle ignore tout ce qui est arrivé en 2.5.x / 2.6.x :
> - `Slot` : `computeCandidates()` (statique pure),
>   `resolveDayWindows()`, `getActiveBookingsForDate()`,
>   `computeSlotsForService()`, `getServiceAvailableDates()`,
>   `getServiceAvailabilityForMonth()`, `getAvailabilityByDay()` ;
> - `Booking` : `create()` mode v3 (refus si `service_id` absent),
>   `getByToken`/`getById`/`getAll` avec `LEFT JOIN services` qui
>   expose `service_name` ;
> - les méthodes Stripe (§6) et pack (§7) à venir.
>
> **Sources de vérité d'ici la régénération v3.0.0** :
> - Code réel — chaque classe a son fichier dans `classes/`, lisible
>   directement ;
> - `docs/booking-v3-spec.md` — pseudo-code et invariants des
>   méthodes ajoutées en 2.5.x ;
> - `CHANGELOG.md` — chaque entrée 2.5.x / 2.6.x liste les méthodes
>   ajoutées ou supprimées.

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
- `brandWordmark()` → wordmark deux tons à partir de `site_name`. Le mot est split par **milieu de caractères** (snap sur espace ±2) et les deux moitiés sont rendues dans `<span class="brand-half-a">` (orange par défaut) et `<span class="brand-half-b">` (navy par défaut). Ex : « Focus Coach » → `<span class="brand-half-a">Focus </span><span class="brand-half-b">Coach</span>`. Refacto 2.4.2 — remplace l'ancien `.accent` (supprimé).

### Chargement CSS par page

| Page | Feuilles `<link>` |
|------|-------------------|
| `index.php` (accueil) | `main.css` + `home.css` |
| `mentions-legales.php` / `confidentialite.php` | `main.css` + `home.css` (classes `.legal-*`) |
| `modules/booking/index.php` (étape 1 prestation) | `main.css` + `booking-v3.css` |
| `modules/booking/date.php` (étape 2 date) | `main.css` + `booking-v3.css` |
| `modules/booking/slot.php` (étape 3 créneau) | `main.css` + `booking-v3.css` |
| `modules/booking/confirm.php` (étape 4 formulaire) | `main.css` + `booking-v3.css` |
| `modules/booking/success.php` (confirmation) | `main.css` + `booking-v3.css` |
| `modules/booking/manage.php` (espace client par token) | `main.css` + `booking-v3.css` + `manage.css` |
| `admin/index.php` | `main.css` + `admin.css` |

> Re-skin global : les pages booking/admin/manage consomment les tokens `:root`
> de `main.css` via `var(...)`. Remapper les VALEURS (sans renommer) suffit à
> changer toute la charte — c'est ainsi qu'a été appliquée la refonte 2.4.0.
