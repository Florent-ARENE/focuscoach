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

## [2.7.0] — 2026-06-25 — Module diagnostic standard (AD-11)

Nouveau module `modules/diagnostic/` (MINOR) : outil interne **lecture
seule**, protégé par auth admin, secrets toujours masqués. Concrétise
l'AD-11 ajouté au cadrage en 2.6.8 (C3).

- **Hub** (`index.php`) + **5 pages** :
  - `health.php` — PHP/extensions, BDD joignable, 12 tables, `configProblems()`,
    fuseaux MySQL/PHP, `BASE_URL`. **Résilient** : connexion via `diag_try_pdo()`
    (try/catch local, ne `die()` pas comme `Database::getInstance()`) → si la
    BDD tombe, la page affiche « Inaccessible » au lieu de planter.
  - `config-check.php` — constantes par sévérité (requis / optionnel-défaut /
    optionnel-piloté) ; **secrets jamais affichés** (statut « défini / absent »).
  - `alignment.php` — `VERSION` ↔ stamps (README/CLAUDE/README_TECHNIQUE/sw.js) ;
    enum `bookings.status` ⇄ `active_key` ⇄ `BOOKING_STATUS` ; catalogue
    `seed.sql` ⇄ `migration-3.0.0.sql`.
  - `api-console.php` — interroge un endpoint GET **côté navigateur** (`fetch`),
    affiche le JSON ; un loopback serveur→Apache s'est révélé non fiable en
    imbriqué sur cet hôte (choix documenté).
  - `smoke.php` — corpus `tests/smoke.php` embarqué en `<iframe>` (le navigateur
    le charge directement, pas de loopback).
- **CSS** `assets/css/diagnostic.css` : 100 % tokens, un seul bloc
  `DIAG-FALLBACK-START…END` autorisé aux hex (garde-fou pre-commit étendu, C3).
- **Helpers mutualisés** (`_diagnostic.php`, AD-3) : auth, coquille HTML,
  cartes/lignes/badges d'état, masquage de secret, PDO résilient.
- **Fix `[O1]`** : `diag_head()` n'appelle plus `siteConfig()` (qui touche la
  BDD via `Settings` → `Database::getInstance()` qui `die()`) mais la constante
  `SITE_NAME` — sinon health/alignment n'étaient pas résilients à une BDD down.
- **DB_PASS vide — surfacé sans fataliser (revue)** : `configProblems()` ne
  bloque pas un `DB_PASS` vide (légitime en local : root EasyPHP). Mais
  `health` et `config-check` le signalent désormais en **warning** quand
  l'hôte de `BASE_URL` n'est pas local (`localhost`/`127.0.0.1`/`::1`) **ou**
  `APP_ENV=production` — un déploiement prod avec mot de passe vide par
  accident doit se voir (helper `diag_dbpass_check()`). La valeur n'est jamais
  affichée.
- **BACKLOG (dette racine notée)** : `Database::getInstance()` fait un `die()`
  **non rattrapable** sur échec de connexion → toute page touchant la BDD meurt
  dur si la BDD tombe. Contourné dans le module (`diag_try_pdo`). Commentaire
  `BACKLOG` posé dans `classes/Database.php` : à terme, remonter une exception
  et en faire un indicateur de santé.
- **Tests dans les 2 sens `[O1]`** : `BASE_URL` absente → 500 (sanction C2) ;
  creds BDD fausses → health **200** « Inaccessible » (non fatal) ; stamp version
  altéré → `alignment` montre la dérive ; statut hors enum → signalé ; hex hors
  bloc fallback → pre-commit **bloque** ; param API invalide → JSON d'erreur ;
  lecture seule (`grep INSERT/UPDATE/DELETE/DROP` = 0) ; aucun secret en clair ;
  smoke 30/30. Chaque anomalie restaurée, `git status` propre.

## [2.6.8] — 2026-06-25 — §6 : `BASE_URL` stricte (jamais dérivée du `Host`)

> **⚠️ Migration** : dès 2.6.8, `config/config.local.php` **doit** définir
> `BASE_URL` (absolue, `http(s)://`, slash final). Sinon `config.php` répond
> **500 au démarrage** (refus net, voulu). Mettre à jour chaque environnement
> avant déploiement.

- **`config/config.php`** : la dérivation de `BASE_URL` depuis
  `$_SERVER['HTTP_HOST']` + `SCRIPT_NAME` est **entièrement retirée**.
  `BASE_URL` est lue **exclusivement** depuis `config.local.php` (anti XSS
  Host-header ; cohérence prod/dev/staging ; valide en webhook/cron où
  `Host` n'existe pas). `ASSETS_URL` en dérive.
- **Sanction au démarrage** : après le chargement de `config.local.php`,
  `\App\configProblems()` (C1) est évaluée ; si la config requise manque ou
  est mal formée → `http_response_code(500)` + message clair pointant vers
  le template. `configProblems()` reste la **source unique** (réutilisée par
  le module diagnostic à venir).
- **Le warning « Constant BASE_URL already defined » disparaît par
  construction** (plus de 2ᵉ `define`), pas via une garde `if(!defined())`.
  Effet de bord prouvé `[O1]` : le smoke en CLI ne produit plus **aucun**
  warning (BASE_URL ni la cascade `session_*`) — 30/30 vert.
- **Doc** : `config.local.php.template` (BASE_URL « REQUISE, jamais dérivée
  du Host, sinon 500 ») + `docs/booking-v3-spec.md` (§ BASE_URL figée
  marquée livrée + checklist pré-bascule mise à jour).
- Recensement `[O1]` : `HTTP_HOST` ne sert plus nulle part à construire une
  URL (seul un commentaire le mentionne). Tests 2 sens : nominal (smoke 0
  warning, `BASE_URL` résolu) ; échec (BASE_URL retirée → 500 + message),
  puis restauration `git status` propre.

## [2.6.7] — 2026-06-25 — Fondation : import SQL robuste au charset (`SET NAMES`) + hook fail-closed

Fix de **reproductibilité de fondation** (pas une correction de données
live : la base locale était déjà saine après re-import utf8mb4). Sans ce
patch, un simple `mysql < seed.sql` depuis un client au charset par
défaut latin1/cp850 (cas du client Windows) **re-corrompt** toutes les
chaînes accentuées à chaque clone — invisible sur les `COUNT(*)`, visible
seulement sur le contenu.

- **`sql/schema.sql` + `sql/seed.sql`** : `SET NAMES utf8mb4;` ajouté en
  1ʳᵉ ligne exécutable. Garantit que l'import interprète les octets du
  fichier en UTF-8 quel que soit le charset par défaut du client.
  Inoffensif sous phpMyAdmin. **Prouvé `[O1]`** : re-import **sans** le
  flag `--default-character-set` → `HEX` propre (`é`=C3A9, `—`=E28094)
  sur `services.name`, `settings.legal_status` **et** les `COMMENT`
  accentués du schéma. Vérif sur le **contenu** (HEX), pas les comptes.
- **`scripts/git-hooks/pre-commit`** : garde-fou AD-5 rendu
  **fail-closed**. Un self-check en tête vérifie que le grep détecte
  réellement un emoji test (😀) ; si le build PCRE ne compile pas le
  motif hors-BMP, le commit est **bloqué avec un message clair** au lieu
  de passer en silence (le `|| true` de la boucle ne peut plus masquer
  une panne de garde-fou). Test end-to-end : un vrai emoji dans un
  fichier cible → commit bloqué (EXIT 1). Source + copie installée alignées.

## [2.6.6] — 2026-06-25 — Outillage : smoke lisible en navigateur + fix faux positif emoji du pre-commit

Deux corrections d'outillage, sans impact fonctionnel sur l'app.

- **`scripts/git-hooks/pre-commit` (garde-fou AD-5 emoji)** : le motif
  `grep -nP '[\xF0-\xF4]'` visait les octets de tête UTF-8 des emoji
  (4 octets). Mais en **locale UTF-8** (Git Bash Windows), PCRE
  interprète `\xF4` comme le **codepoint U+00F4 = `ô`** → faux positifs
  sur tout fichier français (« Rôle », « contrôle », « côté »,
  « plutôt »…), bloquant **chaque** commit. Remplacé par
  `[\x{10000}-\x{10FFFF}]` (tout caractère hors-BMP = 4 octets UTF-8 =
  emoji modernes), **robuste à la locale** : détecte 🛡️🔐✅, ignore
  les accents/flèches/`✓` (BMP). Hook source **et** copie installée
  mis à jour.
- **`tests/smoke.php`** : en mode HTTP, la sortie est désormais
  enveloppée dans `<!doctype html> … <pre>` + en-tête
  `Content-Type: text/html; charset=utf-8`. Avant, le HTML écrasait
  les retours à la ligne du corpus → tout s'affichait sur une seule
  ligne (pavé illisible). Le `<pre>` rend la sortie aussi lisible
  que le terminal (lots, `✓` par ligne, verdict).
- **Gardé par `PHP_SAPI`** : en CLI, sortie **strictement inchangée**
  (mêmes octets, mêmes exit codes 0/1) → aucun impact sur la CI ni
  la perf. Le bloc HTML est simplement sauté hors navigateur.
- **Zéro CSS ajouté** : pas de `<style>`, pas de `main.css` chargé —
  on ne tire pas le design system de l'app dans un outil de test
  (CLAUDE.md §2.2, AD-4). Le `<pre>` natif suffit.
- Le `Warning: Constant BASE_URL already defined` affiché au-dessus
  reste le signal pré-§6 connu (garde `if (!defined())` absente de
  `config/config.php` ligne 60) — hors scope de ce patch.

## [2.6.5] — 2026-06-17 — Trou doc 2.6.4 fermé (Relations + CSS + brandWordmark)

Patch déclenché par un nouveau passage de l'audit indépendant qui
a re-classé chaque hit du grep résiduel et trouvé que **mon
contrôle post-2.6.4 était scopé `README.md` seulement**. L'auditeur
a re-grep `README_TECHNIQUE.md` et trouvé 3 zones **non
neutralisées** qui décrivaient du code mort comme vivant :

1. **`## 🔗 Relations entre fichiers`** — diagramme ASCII pré-purge
   (`booking/index.php → api/booking.php → calendar-module.js →
   api/slots.php`) + table de dépendances qui pointait vers les
   mêmes fichiers supprimés.
2. **`### Chargement CSS par page`** — table avec `booking/index.php
   | main.css + booking.css` et `booking/manage.php | … booking.css`,
   alors que ces chemins n'existent plus.
3. **Exemple `brandWordmark()`** ligne 518 — utilisait encore
   `<span class="accent">` (classe supprimée en 2.4.2 quand le
   wordmark est passé au split bicolore par milieu de caractères).

L'auditeur a explicitement reconnu que son périmètre initial
n'incluait pas « Relations entre fichiers » et « Chargement CSS »,
donc que mon 2.6.4 a fait exactement ce qui était nommé — le
sous-dimensionnement vient de son audit. Mais c'est aussi mon
erreur de ne pas avoir re-fait mon grep sur les **deux** fichiers
de doc. Je le corrige ici.

### 📄 3ᵉ encart d'obsolescence — `## 🔗 Relations entre fichiers`

Traité comme les 2 précédents : encart standardisé qui pointe vers
les sources de vérité (code réel + CHANGELOG + spec). L'encart
mentionne explicitement le **vrai flux v3** pour que la lecture
ne soit pas seulement « obsolète, va voir ailleurs » mais qu'elle
donne un repère immédiat :

> `modules/booking/{index,date,slot,confirm}.php → api/booking-v3-slots.php`
> (lecture) et `modules/booking/process.php → Booking::create() v3`
> (écriture), avec retour Stripe Checkout à venir au §6
> (`api/stripe-webhook.php` + `cron/expire-holds.php`).

Et signale que la nouvelle dépendance importante est le **`LEFT
JOIN services`** dans `Booking::getByToken`/`getById`/`getAll`
(introduit en 2.6.0 avec la purge `service_type`).

### 📄 Correction directe — `### Chargement CSS par page`

L'auditeur proposait au choix encart ou correction directe pour
cette section. Choix : **correction directe** parce que la table
est petite, l'information stable, et la valeur immédiate (un agent
qui cherche « quelle CSS pour `modules/booking/index.php` » trouve
la réponse au lieu d'un encart). Table mise à jour :

| Page | Feuilles `<link>` |
|------|-------------------|
| `index.php` (accueil) | `main.css` + `home.css` |
| `mentions-legales.php` / `confidentialite.php` | `main.css` + `home.css` |
| `modules/booking/index.php` (étape 1 prestation) | `main.css` + `booking-v3.css` |
| `modules/booking/date.php` (étape 2 date) | `main.css` + `booking-v3.css` |
| `modules/booking/slot.php` (étape 3 créneau) | `main.css` + `booking-v3.css` |
| `modules/booking/confirm.php` (étape 4 formulaire) | `main.css` + `booking-v3.css` |
| `modules/booking/success.php` (confirmation) | `main.css` + `booking-v3.css` |
| `modules/booking/manage.php` (espace client par token) | `main.css` + `booking-v3.css` + `manage.css` |
| `admin/index.php` | `main.css` + `admin.css` |

Avant : 2 lignes mentionnaient `booking/...` (supprimé) et
`booking.css` (supprimé). Après : 7 lignes décrivent l'archi
réelle, le `manage.php` v3 est correctement listé avec
`booking-v3.css` + `manage.css` (les deux feuilles qu'il charge
effectivement).

### 📄 Correction d'exemple — `brandWordmark()`

L'exemple ligne 518 disait :

> Ex : « Focus Coach » → `Focus<span class="accent">Coach</span>`.

Faux depuis 2.4.2 (la classe `.accent` a été supprimée à ce
moment-là, remplacée par le split bicolore par milieu). Corrigé en :

> Le mot est split par **milieu de caractères** (snap sur espace ±2)
> et les deux moitiés sont rendues dans `<span class="brand-half-a">`
> (orange par défaut) et `<span class="brand-half-b">` (navy par
> défaut). Ex : « Focus Coach » →
> `<span class="brand-half-a">Focus </span><span class="brand-half-b">Coach</span>`.

### ✅ Tests + vérif post-patch sur les **deux** fichiers

```
=== README.md — hors changelog/encarts ===
(aucun)

=== README_TECHNIQUE.md — hors changelog/encarts ===
11 hits restants — tous DANS un encart d'obsolescence (qui CITE
volontairement les éléments supprimés à des fins pédagogiques)
ou dans la nouvelle table CSS qui liste les VRAIS chemins
modules/booking/.
```

Plus aucun mensonge structurel ouvert dans la doc avant l'ouverture
du §6.

Smoke 24/24 vert (logique pure inchangée).

Bump 2.6.4 → 2.6.5 (semver patch : doc-only).

## [2.6.4] — 2026-06-17 — Neutralisation des sections doc obsolètes

Patch déclenché par la proposition « juste milieu » de l'audit
indépendant, **validée** : neutraliser le mensonge dans la doc
structurelle sans gâcher le travail de refonte qui sera fait au
§10 (quand les §6-§9 auront stabilisé l'arborescence et les
méthodes du chantier Booking v3).

L'audit a correctement séparé deux choses qui se ressemblaient
dans le rapport initial :

- **Les tables de changelog** qui citent `booking/`,
  `api/slots.php`, `getAvailableSlots`, etc. → **factuelles dans
  leur contexte historique**, on les garde (AD-7 : l'historique
  ne doit pas être réécrit).
- **Les sections d'existant** (« Arborescence », « Carte des
  fonctions », etc.) → **mentent au présent**, à neutraliser.

### 📄 Encarts d'obsolescence — `README_TECHNIQUE.md`

Deux sections neutralisées par un encart standardisé :

- **§ Arborescence complète** — listait `booking/`, `api/slots.php`,
  `api/booking.php`, `assets/css/booking.css`, `assets/js/booking.js`,
  `assets/js/calendar-module.js` (supprimés en 2.6.0) ; ignorait
  `modules/booking/`, `api/booking-v3-slots.php`,
  `assets/css/booking-v3.css`, `sql/reset-dev.sql`,
  `scripts/git-hooks/`, `cadrage/`, `docs/booking-v3-spec.md`.
- **§ Carte des fonctions** — listait `Slot::getAvailableSlots`,
  `Slot::getAvailableDates`, `CalendarModule (JS)`, `create()` mode
  legacy, `service_label` (tous supprimés en 2.6.0) ; ignorait
  `Slot::computeCandidates`, `resolveDayWindows`,
  `getActiveBookingsForDate`, `computeSlotsForService`,
  `getServiceAvailableDates`, `getServiceAvailabilityForMonth`,
  `getAvailabilityByDay`, le mode v3 de `Booking::create()`, le
  `LEFT JOIN services` dans `getByToken`/`getById`/`getAll`.

L'encart pointe explicitement vers les 3 sources de vérité d'ici
la régénération v3.0.0 : le code réel, `CHANGELOG.md`,
`docs/booking-v3-spec.md`.

### 📄 Encarts d'obsolescence — `README.md` racine

Cinq sections neutralisées par le même encart (script Python en
heredoc pour patcher en une passe, sans risque de désynchroniser
les bornes ligne à ligne) :

1. **§ Arborescence des fichiers**
2. **§ Relations entre fichiers** (diagramme de flux + dépendances
   des classes)
3. **§ Carte des fonctions**
4. **§ Utilisation** (interface `/booking/` legacy)
5. **§ API Reference** (`/api/slots.php`, `/api/booking.php`
   supprimés)

Les sections **§ Présentation**, **§ Changelog**, **§ Installation**,
**§ Configuration**, **§ Tutoriel Google Service Account** et
**§ Dépannage** restent intactes — elles décrivent des aspects
encore valides ou strictement historiques.

### 🔧 Fix précis — table des constantes & exemple Dépannage

Trois mensonges précis subsistaient hors encarts :

- Table `config/config.php` (`README.md` §Configuration) listait
  `BOOKING_DURATION` (jamais existée dans le code — vérifié par
  `grep -rn BOOKING_DURATION` → 0 hit hors doc), et
  `BOOKING_ADVANCE_MIN_HOURS` / `BOOKING_ADVANCE_MAX_DAYS`
  (supprimées en 2.6.0). Remplacées par les vraies constantes v3 :
  `BOOKING_STEP` (15), `MIN_NOTICE_MIN` (120), `MAX_HORIZON_DAYS` (60).
  Note explicite ajoutée sous la table.
- Section Dépannage : exemple `/api/slots.php?date=2026-02-15`
  (endpoint supprimé) → `/api/booking-v3-slots.php?service=<id>&date=YYYY-MM-DD`.
- Même endroit : « Vérifiez les `WORKING_HOURS` dans `config.php` »
  (constante qui n'a jamais existé) → « Vérifier les fenêtres
  d'ouverture dans la table `availability` ».

### Stats — `README.md`

- Avant : **922 lignes**, ~310 décrivaient du code mort.
- Après : **612 lignes**, mensonge structurel neutralisé.

### Vérif post-patch

```
grep -nE 'getAvailableSlots|getAvailableDates|/api/slots\.|
  /api/booking\.php|booking/index|booking/manage|calendar-module|
  SERVICE_TYPES|BOOKING_ADVANCE|available_slots|CalendarModule|
  BOOKING_DURATION|SLOT_DURATION|WORKING_HOURS' README.md
  | grep -v 'changelog historique ni encart'
```

→ **0 résultat**.

Smoke 24/24 vert (logique pure inchangée).

### Pourquoi 2.6.4 maintenant et pas après §6

L'auditeur a raison sur le timing : **§6 est précisément le
moment où une session va consommer le plus l'arborescence et la
carte des fonctions** comme source de vérité du « qui existe ».
Laisser ces sections mentir au démarrage du chantier Stripe
maximise le risque qu'un agent (ou un humain) parte d'une carte
fausse — exactement le piège AD-10. Patch doc-only, zéro risque,
fait avant que §6 ne s'appuie dessus.

Bump 2.6.3 → 2.6.4 (semver patch : doc-only, zéro touche au
code applicatif).

## [2.6.3] — 2026-06-17 — Style décoratif retiré + checklist pré-bascule prod

Suite du tour de table déclenché par l'audit indépendant : les deux
**nuances honnêtes** du rapport sont traitées ici.

### 🎨 Fix AD-4 — `style="…"` décoratif retiré de `manage.php:155`

Le paragraphe d'aide sous le bouton « Annuler le rendez-vous »
(« Pour déplacer ce rendez-vous, contactez directement
{admin_email}. ») portait un `style="margin-top:0.75rem;
font-size:0.9rem;"` inline, en violation directe de l'AD-4 du
cadrage (« zéro `style=` décoratif, seuls les `display:none`
fonctionnels sont tolérés »).

Régression introduite au **checkpoint §5b** : le `style=` venait
du `booking/manage.php` legacy v2 et a **survécu** à la copie vers
`modules/booking/manage.php` lors de la purge. Pas attrapé par les
garde-fous existants — le hook ne flague pas tous les `style=`
ligne par ligne, seulement les patterns CSS hex et emoji DOM.

Patch :

```diff
- <p class="text-muted text-center" style="margin-top:0.75rem; font-size:0.9rem;">
+ <p class="text-muted text-center bv3-help-note">
```

Classe utilitaire `.bv3-help-note` ajoutée à
`assets/css/booking-v3.css` (section « Helpers utilitaires »).
Vérif post-patch :
`grep 'style="' modules/booking/*.php | grep -v display:none` →
**0 résultat**. Les 3 `style="display:none;"` sur les modales
(`#delete-data-modal`, `#cancel-modal`, `#success-card`) sont
l'exception fonctionnelle tolérée par CLAUDE.md §1.2.

### 📄 Checklist pré-bascule prod (`docs/booking-v3-spec.md` §7.bis)

L'audit a remonté à juste titre que **déférer `APP_ENV='development'`
et la clé cron RGPD au futur `health.php` est insuffisant** : tant
que `health.php` (checkpoint §9) n'est pas livré, il n'y a aucun
filet automatique. D'ici §9, le filet doit être **humain et
explicite**.

Nouvelle section §7.bis ajoutée dans `docs/booking-v3-spec.md` —
checklist à passer point par point avant la bascule prod publique :

- **Code & config** : `APP_ENV = 'production'` ; `config.local.php`
  hors web ; `ADMIN_PASSWORD_HASH` ≠ placeholder ; clés Stripe
  **live** ; `BASE_URL` canonique non dérivée du Host ;
  `scripts/hash-password.php` supprimé après usage ;
  `google-credentials.json` protégé si la sync est activée.
- **BDD** : `mysqldump` complet ; migration 3.0.0 appliquée
  proprement (10 fenêtres + 10 services) ; tous les
  `stripe_price_id` saisis pour les services payants ; crons
  RGPD et expire-holds planifiés.
- **Serveur** : HTTPS forcé ; cookies session `Secure` + `HttpOnly`
  + `SameSite=Lax` ; webhook Stripe testé en sandbox avant le
  basculement live ; PWA `CACHE_VERSION` bumpée.

Verdict : ne pas basculer tant qu'**une seule case** est rouge.
Une fois toutes vertes : test complet en sandbox Stripe
(prestation gratuite → confirmation directe ; prestation payante
→ Checkout → webhook → confirmed + email envoyé une seule fois).

Quand `health.php` arrivera au §9, une partie de ces checks
basculera en garde-fou runtime ; l'autre restera humaine (secrets,
`ADMIN_PASSWORD`, backup) car elle ne se prouve pas sans accès
direct au `config.local.php` réel.

### ✅ Tests

- `tests/smoke.php` : **24/24 vert** (logique pure inchangée).
- 0 hex en dur dans `booking-v3.css` (grep `#[0-9a-fA-F]{3,6}\b` → vide).
- 0 `style="…"` décoratif résiduel dans `modules/booking/*.php`.
- Garde-fous AD-1 / AD-4 / AD-5 / Lot 4 VERTS au commit.

Bump 2.6.2 → 2.6.3 (semver patch : fix AD-4 ponctuel + doc).

## [2.6.2] — 2026-06-17 — Patch régressions purge (Mailer liens email + Icons arrow-left)

Patch déclenché par un audit indépendant qui a installé PHP 8.3 dans
son conteneur pour produire de vraies preuves [O1] (méthodologie
AD-10 : « vérifier avant de croire »). L'audit a remonté 11 écarts
entre la doc et le réel, dont 4 actionnables. Les 2 **régressions
concrètes de la purge 2.6.0** sont corrigées ici. Les 2 autres
(`APP_ENV` / clé cron RGPD en placeholder, dette doc structurelle)
restent au registre pour les checkpoints suivants.

### 🔧 Fix — `Mailer.php` : liens email cassés depuis 2.6.0

`getManageLink()` pointait encore vers
`BASE_URL . "booking/manage.php?token=..."` (chemin supprimé en
2.6.0 — `booking/manage.php` est devenu `modules/booking/manage.php`).
Tout email contenant le bloc « Gérer votre rendez-vous » envoyait
les clients sur une 404. Concrètement, **5 flux email** étaient
cassés :

- `notifyNewBooking()` → email visiteur via `buildVisitorNewBookingEmail()`,
- `notifyConfirmation()`,
- `notifyReschedule()` (déplacement admin),
- `notifyClientRescheduleRequest()` (déplacement client),
- `addManageBlock()` lui-même (helper appelé par les 4 ci-dessus).

Plus, dans le même fichier, 2 liens « prendre un nouveau RDV »
pointaient encore vers `BASE_URL . "booking/"` (tunnel droppé) :

- `notifyCancellation()` (annulation admin),
- `notifyClientCancellation()` (annulation client).

Les 3 chemins basculent sur `modules/booking/...`. Vérifié par
`grep -rn "BASE_URL . \"booking/" *.php` post-patch → 0 résultat.

### 🎨 Fix — `Icons` : `arrow-left` ajoutée aux deux miroirs

Le tunnel v3 utilise `Icons::svg('arrow-left', 20, 'icon-inline')`
6 fois pour les liens « Retour » (`modules/booking/{index, date,
slot, confirm, success, manage}.php`). Or `arrow-left` n'existait
ni dans `classes/Icons.php::LIBRARY` ni dans
`assets/js/icons.js::LIBRARY` — j'avais inventé le nom au §5 sans
vérifier la liste blanche. Le validateur de nom retourne `''` sur
clé inconnue (défense en profondeur, cf. `Icons.php:82`) → 6
rendus d'icône **vides** côté UX.

Path SVG ajouté aux deux miroirs (avant `arrow-right`, ordre
alphabétique) — symétrique exact d'`arrow-right` :

```
'arrow-left'  => '<path d="M19 12H5"/><path d="m12 5-7 7 7 7"/>',
'arrow-right' => '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
```

Validation **runtime [O1]** : un script PHP qui invoque
`App\Icons::svg('arrow-left', 20, 'icon-test')` retourne 269 bytes
de SVG bien formé, et `DOMDocument::loadXML()` accepte le résultat
sans erreur.

### 🧹 Doc inline — commentaires obsolètes corrigés

Le grep résiduel post-patch a montré 4 commentaires mentionnant
encore `booking/` hors `modules/`. Tri :

- `index.php:313` HTML comment « (vers le module /booking/) » → corrigé en `(vers /modules/booking/)`. Le `href` lui-même était déjà bon depuis 2.6.0, le commentaire mentait.
- `api/booking-v3-slots.php:14` docblock qui présentait `api/slots.php` et `booking/index.php` comme cohabitants (alors qu'ils sont supprimés depuis 2.6.0) → reformulé pour dire qu'il s'agit du successeur unique.
- `config/config.php:55` **conservé** : décrit littéralement le regex `(api|admin|booking)` du calcul `BASE_URL` legacy juste en dessous. Factuel dans son contexte. Disparaîtra avec le câblage strict `BASE_URL` du §6.
- `classes/Slot.php:91` **conservé** : mémo historique légitime (« avant la purge 2.6.0, ces méthodes cohabitaient ici »). Factuel.

### 📋 Audit — 3 autres écarts remontés mais non patchés ici

Suivant l'arbitrage avec le user :

1. **`APP_ENV='development'`** → l'API admin est ouverte sans auth tant qu'on ne bascule pas en production. La clé du cron RGPD en placeholder est dans la même veine. Sera traité dans un checkpoint **pré-prod** à grouper avec **§9 `health.php`** (qui doit déjà vérifier ces invariants en runtime).
2. **Sections structurelles de la doc** (`README_TECHNIQUE.md` §2 Arborescence et §4 Carte des fonctions ; `README.md` racine) encore en état pré-2.6.0 — le changelog en tête est à jour mais le corps du document ment. Dette déjà tracée en 2.6.0, **à refondre au passage v3.0.0** (pas avant, ça bouge encore beaucoup d'ici §10).
3. 7 autres points du `RESUME_focuscoach.md` (non listés par le user) restent non actionnables sans accès au `config.local.php` réel ni aux valeurs live de la table `settings` — l'audit les a explicitement étiquetés `[?]` non levables sans accès live.

### ✅ Tests

- `tests/smoke.php` : **24/24 vert** (logique pure inchangée).
- `php -l` propre sur `Mailer.php`, `Icons.php`, `index.php`, `api/booking-v3-slots.php` (4 fichiers PHP touchés).
- Validation runtime ad-hoc d'`Icons::svg('arrow-left')` : retour non vide, XML valide via `DOMDocument::loadXML`.
- Garde-fous AD-1 / AD-4 / AD-5 / Lot 4 VERTS au commit.

Bump 2.6.1 → 2.6.2 (semver patch : corrections de régressions
non fonctionnelles, zéro changement d'API).

## [2.6.1] — 2026-06-17 — `reset-dev.sql` + template purgé / BASE_URL ajouté

Tâche courte de mise en ordre juste avant §6 :

1. **`sql/reset-dev.sql`** créé — workflow de reset dev en trois imports.
2. **`config/config.local.php.template`** purgé de la grille tarifaire mémo
   (mono-source AD-2 : la BDD est la seule source de vérité des prix).
3. **`BASE_URL`** ajoutée au template — anticipation §6 (Stripe Checkout
   exige une URL absolue, fixe par environnement, jamais dérivée du Host).
4. Dettes connues (reschedule client v3, `README.md` racine) confirmées sans
   action — restent au changelog.

### 🛠️ `sql/reset-dev.sql`

Script de remise à zéro complète, **DESTRUCTIF, réservé au DÉVELOPPEMENT**.
Drop toutes les tables manipulées par le projet — v3 (services,
availability, availability_exceptions, packages, package_purchases,
stripe_events_processed, bookings) **et** historiques (settings,
blocked_dates, rgpd_deletion_log, purge_stats, admin_login_attempts) —
dans l'**ordre inverse des Foreign Keys** :

```
bookings ──FK──► services
              └─► package_purchases ──FK──► packages ──FK──► services
```

→ `bookings` → `package_purchases` → `packages` → `services` → indépendantes.

`available_slots` (table morte v2, peut subsister sur une base partiellement
migrée) est également droppée en fin.

**Workflow de reset dev** :

```bash
mysql -u <user> -p <db_dev> < sql/reset-dev.sql
mysql -u <user> -p <db_dev> < sql/schema.sql
mysql -u <user> -p <db_dev> < sql/seed.sql
```

→ base propre, données seed (settings d'identité, 10 fenêtres
`availability` Lu-Ve, 10 services, 3 packages).

**`schema.sql` reste non destructif** (`CREATE TABLE IF NOT EXISTS`,
cadrage §5) — c'est `reset-dev.sql` qui porte exclusivement la
responsabilité de nettoyer. Séparation claire des rôles.

⚠️ À NE JAMAIS appliquer en prod tant qu'un utilisateur réel a posé un
booking.

### 🧹 `config/config.local.php.template` purgé (mono-source AD-2)

La grille tarifaire mémo (« Sport Flash 80 € · Préparation mentale 100 €…
Forfait Élan 5× 420 € ») est **retirée**. Une seule source de vérité pour
les prix : `services.price_cents` + `packages.price_cents` en BDD (peuplée
par `seed.sql`, éditable via /admin).

Le template ne porte plus que :

```
DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_CHARSET, DB_PORT
ADMIN_PASSWORD_HASH
STRIPE_SECRET_KEY, STRIPE_PUBLISHABLE_KEY, STRIPE_WEBHOOK_SECRET
BASE_URL              ← nouveau, anticipation §6
GOOGLE_CREDENTIALS_PATH   (optionnel, commenté)
```

Le commentaire de la section Stripe pointe vers la BDD comme source de
vérité (workflow : créer un Price dans le dashboard Stripe → coller le
`stripe_price_id` dans `/admin` → `health.php` signale les manquants),
sans répéter les valeurs.

### 🔌 `BASE_URL` (anticipation §6)

Ajoutée comme constante définie au niveau du template — URL canonique
absolue (avec slash final) de la racine du site :

```php
define('BASE_URL', 'https://focuscoach.fr/');
```

Sert à Stripe Checkout (`success_url` / `cancel_url`) et à tout endpoint
qui produit une URL absolue de retour. **Jamais dérivée du header HTTP
`Host`** (risque XSS Host-header). Fixe par environnement (prod / staging
/ dev) — exemples fournis dans le commentaire.

Le déplacement effectif de `BASE_URL` depuis `config/config.php` (où elle
est encore calculée à partir de `$_SERVER['HTTP_HOST']`) vers une lecture
**stricte** de `config.local.php` (avec fallback signalé en ORANGE par
`health.php` si absente) fait partie du périmètre §6 — déjà inscrit dans
le prompt de reprise.

### 📄 Dettes confirmées (pas d'action ce tour-ci)

- **Reschedule client v3** — désactivé depuis la purge 2.6.0 (dépendait
  de `calendar-module.js` + `api/slots.php` supprimés). Rebranchement
  sur `api/booking-v3-slots.php?service=<id>&month=…` à faire après
  §6/§7. Reste tracé au changelog 2.6.0.
- **`README.md` racine** — contient encore des références à
  l'arborescence et constantes legacy supprimées (`SERVICE_TYPES`,
  `BOOKING_ADVANCE_*`, `assets/js/calendar-module.js`,
  `Slot::getAvailableDates`, bloc « TYPES DE SERVICES »). À refondre
  au passage v3.0.0. Reste tracé au changelog 2.6.0.

Bump 2.6.0 → 2.6.1 (semver patch : outil de dev + cleanup template, aucun
impact sur l'API publique ni le modèle).

## [2.6.0] — 2026-06-17 — Purge legacy avant §6 (tunnel v2 retiré)

Checkpoint **§5b** du chantier Booking v3 — non prévu dans le
prompt initial, mais déclenché par la confirmation du user que la
phase est purement dev (aucun utilisateur en prod). Plutôt que
d'enchaîner §6 (paiement Stripe) sur une base avec deux tunnels
et deux algos en parallèle, on dégraisse :

- code legacy supprimé (pas commenté, pas conditionnel — supprimé),
- modèle BDD simplifié (`available_slots` droppée, `service_type`
  retiré de `bookings`),
- CTA basculé sur `/modules/booking/` (legacy hors d'atteinte).

§6 attaquera donc un code-base limpide.

### ✂️ Fichiers supprimés

```
booking/
├── index.php            ✂ tunnel v2 SPA-like
└── manage.php           → déplacé en modules/booking/manage.php
api/
├── slots.php            ✂ remplacé par api/booking-v3-slots.php
└── booking.php          ✂ remplacé par modules/booking/process.php
assets/
├── css/booking.css      ✂ remplacé par assets/css/booking-v3.css
└── js/
    ├── booking.js       ✂ logique de tunnel v2 (SPA)
    └── calendar-module.js ✂ widget calendrier partagé v2
```

Le dossier `booking/` (vide après ces retraits) est supprimé.

### 🧱 Classes — modèle simplifié

**`classes/Booking.php`**

- `create()` accepte uniquement le mode v3 — refus explicite si
  `$data['service_id']` est absent ou ≤ 0. La branche legacy
  (`service_type` ENUM) disparaît.
- `getByToken()`, `getById()`, `getAll()` ajoutent un **`LEFT JOIN
  services s ON s.id = b.service_id`** qui expose `service_name`
  et `service_slug` sur chaque booking renvoyé. Une seule requête
  au lieu d'un lookup PHP par booking (résout aussi le N+1).
- `enrichBooking()` retire l'attribut `service_label` (qui était
  un lookup `SERVICE_TYPES[…]`) — `service_name` issu du JOIN est
  la voie unique.

**`classes/Slot.php`**

- Suppression de toutes les méthodes legacy : `getAvailableForDate`,
  `getAvailableDates`, `getAvailabilityForMonth` (consommaient
  `available_slots`), `getSlotsByDay`, `toggleSlot`.
- Seules les méthodes v3 (`computeCandidates` statique pure,
  `resolveDayWindows`, `getActiveBookingsForDate`,
  `computeSlotsForService`, `getServiceAvailableDates`,
  `getServiceAvailabilityForMonth`) restent, plus les utilitaires
  `blocked_dates` (toujours utiles).
- Nouvelle méthode **`getAvailabilityByDay()`** qui remplace
  `getSlotsByDay()` pour le back-office, en lisant la table
  `availability` (planning hebdo récurrent v3).

**`classes/Mailer.php` et `classes/GoogleCalendarSync.php`**

Le lookup `SERVICE_TYPES[$booking['service_type']]` est remplacé
par `$booking['service_name'] ?? 'Prestation'` (utilise la valeur
posée par le JOIN). Plus de dépendance à l'ENUM legacy.

### ⚙️ `config/config.php` — constantes legacy retirées

- `SLOT_DURATION` (30 min — utilisé par le pas du tunnel v2),
- `BOOKING_ADVANCE_MIN_HOURS` (24 h — délai mini legacy),
- `BOOKING_ADVANCE_MAX_DAYS` (60 j — horizon legacy),
- `SERVICE_TYPES` (7 entrées de l'offre conseil),
- champ `'icon'` de `BOOKING_STATUS` (emojis — jamais consommé par
  une vue depuis la bascule SVG Lucide, flag déjà posé dans
  `CLAUDE.md` §10).

Restent : `TIMEZONE`, `BOOKING_STEP`, `MIN_NOTICE_MIN`,
`MAX_HORIZON_DAYS`, `BOOKING_STATUS` (sans `icon`).

### 🔌 `api/admin.php` — case renommé

Le case `slots` (qui exposait `Slot::getSlotsByDay()` lisant
`available_slots`) devient `availability` et expose
`Slot::getAvailabilityByDay()` (fenêtres du planning hebdo v3).
Le CRUD complet (édition / ajout / suppression de fenêtres)
arrivera avec §8.

### 🗄️ Schéma BDD — purge destructive

**`sql/migration-3.0.0.sql`** ajoute une étape 11 destructive :

```sql
DROP TABLE IF EXISTS available_slots;
ALTER TABLE bookings DROP COLUMN service_type;
```

Un commentaire en tête de l'étape avertit que ces lignes doivent
être retirées si quelqu'un veut appliquer la migration sur une
prod historique qui contiendrait des bookings de l'offre conseil
— le user a explicitement confirmé qu'aucun utilisateur n'est
connecté, donc le destructif passe.

**`sql/schema.sql`** retire le `CREATE TABLE available_slots`
(était conservé en cible pour réversibilité) et la colonne
`service_type` de `bookings`.

### 🗂️ `modules/booking/manage.php` (déplacé + v3)

Le nouveau manage v3 affiche le récap (date, horaire, prestation
via `service_name`, statut) + bouton **Annuler** + section
**RGPD** (effacement art. 17). Le **reschedule client est
temporairement désactivé** : il dépendait de `calendar-module.js`
+ `api/slots.php` qu'on vient de supprimer. Le rebranchement sur
`api/booking-v3-slots.php?service=<id>&month=…` est noté comme
dette technique — à faire après §6/§7. Pendant ce temps,
`Booking::reschedule()` reste utilisable côté admin (back-office),
et le client peut annuler puis re-réserver.

`assets/js/manage.js` est simplifié en conséquence (cancel + RGPD
seulement, chemins API en `../../api/...` car la page vit à deux
niveaux sous la racine).

### 🎨 CTA basculé sur le tunnel v3

`index.php` racine (4 liens « Prendre RDV », « Réserver »,
« Prendre rendez-vous ») et `admin/index.php` (lien sidebar
« Page réservation ») pointent désormais sur `/modules/booking/`.
Le dossier `/booking/` n'existe plus côté repo, donc plus de
chemin résiduel.

### 🛡️ Pre-commit hook + miroir

- `hex_files` retire `assets/css/booking.css` (fichier supprimé),
- `emoji_targets` retire `booking/index.php`, `booking/manage.php`,
  `assets/js/booking.js` ; ajoute `modules/booking/manage.php`,
- `escape_targets` retire `booking/index.php`,
  `booking/manage.php` ; ajoute `modules/booking/manage.php`.

`scripts/git-hooks/pre-commit` resynchronisé. Côté Renaud :

```bash
cp scripts/git-hooks/pre-commit .git/hooks/pre-commit
chmod +x .git/hooks/pre-commit
```

### ✅ Tests

- `tests/smoke.php` : **24/24 vert** (les cas testent la logique
  pure de `computeCandidates`, indépendante de la suppression du
  tunnel legacy).
- `php -l` propre sur les 17 fichiers PHP touchés.
- **Vérification visuelle requise** côté Renaud : tester en local
  le flow complet `/modules/booking/` (prestation → date → créneau
  → confirm → success) et `/modules/booking/manage.php?token=…`
  (récap + cancel + RGPD) sur 375 / 768 / 1280.

### 📄 Dette restante (signalée pour propreté)

`README.md` racine contient encore plusieurs références à
l'arborescence et aux constantes legacy supprimées :
`SERVICE_TYPES`, `BOOKING_ADVANCE_*`, `assets/js/calendar-module.js`,
`Slot::getAvailableDates`, le bloc « TYPES DE SERVICES ». À
refondre au passage v3.0.0 — pas bloquant pour le pipeline §6.

### Suite

§6 (paiement Stripe Checkout + webhook idempotent) peut maintenant
attaquer une base limpide :

- pas de mode legacy dans `Booking::create()` à maintenir,
- pas de double endpoint slots,
- pas de constante `STRIPE_PRICES` (purgée en 2.5.3) ni de
  `SERVICE_TYPES` legacy à arbitrer,
- `service_name` posé par le JOIN — utilisable directement dans
  les `metadata` de Stripe Checkout et les emails de
  confirmation.

Le prompt de reprise §6 reste valable (état de la branche à
mettre à jour avec le hash du commit `chore(booking-v3):
checkpoint §5b — purge legacy`).

## [2.5.3] — 2026-06-17 — Cleanup AD-2 : grille tarifaire retirée du template

Patch de cohérence mono-source (AD-2) déclenché par une remarque
en revue : depuis le §3 du chantier v3, la grille tarifaire vit
en BDD (`services.price_cents` + `packages.price_cents`) et les
identifiants Stripe vivent en BDD aussi
(`services.stripe_price_id` + `packages.stripe_price_id`, à saisir
en admin §8). Maintenir **en parallèle** la grille tarifaire dans
`config.local.php.template` (sous forme de commentaires « 80 € /
100 € / … » et d'une constante `STRIPE_PRICES` placeholder)
créerait deux sources qui finiraient par diverger dès que Renaud
ajuste un tarif en admin.

### Vérif avant retrait

`grep -rn STRIPE_PRICES *.php` → 0 hit hors `config/`. La
constante n'est consommée par **aucun** code applicatif — elle
n'était qu'un placeholder de doc dans le template.

### Changement

`config/config.local.php.template` : la constante `STRIPE_PRICES`
et la grille tarifaire commentée sont supprimées et remplacées par
un commentaire qui pointe vers la source unique (`services` +
`packages` en BDD, /admin pour la saisie) et qui résume la grille
actuelle comme un mémo, source de vérité = BDD. Un
`config.local.php` existant qui inclut encore la constante reste
fonctionnel — aucun code ne la lit, donc aucun breakage.

### Suite (rappel)

Reprise du chantier v3 en session neuve sur §6 — paiement Stripe
Checkout + webhook idempotent. Le prompt de reprise §6 est rédigé
hors-commit (à coller au démarrage de la nouvelle session Claude
Code) et porte tous les invariants déjà durcis aux §3-§5.

## [2.5.2] — 2026-06-17 — Booking v3 §5 : tunnel multi-pages PHP

Troisième checkpoint du chantier Booking v3. Pose le **squelette
fonctionnel** du nouveau tunnel sur le modèle multi-pages PHP
(pas de SPA — cohérent avec la stack vanilla et `booking/manage.php`
existant). Le tunnel v2 `/booking/` reste en place, le v3 cohabite
sous `/modules/booking/` ; la bascule du CTA public se fera en fin
de chantier.

⚠️ **Pas de paiement** dans ce checkpoint — `process.php` crée le
booking en `status = 'pending'` / `payment_status = 'none'`
(équivalent legacy v2, validation admin). Stripe Checkout sera
inséré au §6.

⚠️ **Vérification visuelle requise** côté Renaud : un container
éphémère ne permet pas de tester le tunnel dans un navigateur. À
valider en local sur les 3 tailles (375 / 768 / 1280) selon la
checklist CLAUDE.md.

### 🗂️ `modules/booking/` (AD-6 — module autonome)

6 pages PHP, état dans `$_SESSION['booking_draft']` :

| Page | Rôle |
|---|---|
| `index.php`    | Étape 1 — cards prestations par segment (services actifs, groupés sportif/dirigeant/particulier) |
| `date.php`     | Étape 2 — liste des dates disponibles pour le service choisi (`Slot::getServiceAvailableDates`) |
| `slot.php`     | Étape 3 — créneaux du jour (`Slot::computeSlotsForService`) |
| `confirm.php`  | Étape 4 — formulaire identité + récap |
| `process.php`  | POST — CSRF vérifié, crée le booking via `Booking::create()` mode v3 |
| `success.php`  | Confirmation + lien vers `manage.php?token=…` (gestion legacy v2) |

Toutes les sorties HTML passent par `Helpers::escape()`,
`Icons::svg()`, `brandWordmark()`, `cfgField()`, `pwaHead()`,
`pwaRegister()`, `appVersion()`, `cacheVersion()`, `Helpers::csrfMeta()`,
`Helpers::csrfField()`, `date()` — conformes au garde-fou Lot 4
(allowlist d'échappement).

### 🔌 API — `api/booking-v3-slots.php`

Endpoint JSON service-aware (paramètre `?service=<id>` obligatoire,
retourne 400 sinon) avec trois modes :

- `&date=YYYY-MM-DD` → créneaux du jour (`slots`, `slots_count`),
- `&month=YYYY-MM` → vue calendrier mensuelle (`dates` indexé par
  jour avec `available` + `slots_count`),
- sans param → toutes les dates disponibles sur l'horizon
  (`MAX_HORIZON_DAYS`).

L'ancien `api/slots.php` reste en place (consommé par le tunnel
legacy v2, qui continuera de tourner jusqu'à la bascule du CTA en
fin de chantier).

### 🧱 `Booking::create()` accepte le mode v3

Détection par présence de `$data['service_id'] > 0` (pas de
signature nouvelle, pas de duplication) :

- **Mode v3** : insère `service_id`, `duration_min`,
  `buffer_after_min` (figés à la création — indépendants d'une
  édition ultérieure du catalogue), `payment_status = 'none'`,
  `status = 'pending'`.
- **Mode legacy** : signature historique (`service_type` ENUM) —
  inchangé.

L'invariant UNIQUE `active_key` continue de fermer le double-booking
au même départ. La **vérification transactionnelle de chevauchement**
(durées variables, départs différents) sera ajoutée au §6, au même
endroit que le passage en `pending_payment` (POST de paiement).

### 🎨 `assets/css/booking-v3.css`

Préfixe `.bv3-` (pas de collision avec `.booking-` v2). Mobile-first
(breakpoints 768 / 1024) :

- cards prestations 1 → 2 → 3 colonnes,
- steps bar (1 → 4) verticale → horizontale dès 768 px,
- récap (`<dl>`) 1 col → 2 col (`max-content 1fr`) dès 768 px.

Zéro hex en dur (AD-4) — tokens utilisés : `--navy`, `--navy-deep`,
`--orange`, `--cream`, `--cream-dark`, `--text-dark`, `--text-muted`,
`--text-light`, `--success`, `--white`, `--shadow-card`, `--radius-md`,
`--font-display`.

### 🛡️ Pre-commit étendu

Le hook `.git/hooks/pre-commit` est étendu localement (non versionné
par git) :

- `hex_files` → ajoute `assets/css/booking-v3.css`,
- `emoji_targets` → ajoute les 6 pages `modules/booking/*.php`,
- `escape_targets` → ajoute les 6 pages `modules/booking/*.php`.

**Le hook est miroir dans `scripts/git-hooks/pre-commit`** (versionné)
pour que Renaud puisse propager la mise à jour côté son poste :

```bash
cp scripts/git-hooks/pre-commit .git/hooks/pre-commit
chmod +x .git/hooks/pre-commit
```

À faire avant tout commit local qui touche les nouveaux fichiers,
sinon les garde-fous AD-4 / AD-5 / Lot 4 ne mordront pas dessus.

### 📁 Arborescence ajoutée

```
modules/
└── booking/
    ├── index.php        — étape 1 (prestations)
    ├── date.php         — étape 2 (dates)
    ├── slot.php         — étape 3 (créneaux)
    ├── confirm.php      — étape 4 (formulaire)
    ├── process.php      — POST création booking
    └── success.php      — confirmation
api/
└── booking-v3-slots.php — endpoint JSON service-aware
assets/css/
└── booking-v3.css       — styles tunnel v3
scripts/git-hooks/
└── pre-commit           — miroir versionné du hook local étendu
```

État d'avancement mis à jour : §5 marqué **livré**.

## [2.5.1] — 2026-06-17 — Booking v3 §4 : algorithme de calcul des créneaux

Deuxième checkpoint du chantier Booking v3. Pose l'algorithme qui
sera consommé par le tunnel au §5. **Pas d'impact utilisateur
encore** : le tunnel actuel continue d'utiliser l'algo v2
(`getAvailableForDate` legacy), les nouvelles méthodes co-existent
en parallèle dans `Slot.php` jusqu'à la bascule.

### 🧮 `classes/Slot.php` étendu

Méthodes ajoutées (les méthodes v2 sont conservées intactes — AD-3,
zéro duplication : on étend, on ne recrée pas) :

- **`computeCandidates(windows, bookings, duration, buffer, step,
  earliestStart = null): array`** — fonction **pure et statique**,
  testable sans BDD. Cœur de l'algo :
  > pour chaque fenêtre `[W_start, W_end]`, parcourir par pas
  > `step` ; candidat `t` valide si `t + duration ≤ W_end`,
  > `t ≥ earliestStart` (si fourni) et `[t, t + duration + buffer)`
  > ne chevauche aucun intervalle occupé.

  Convention d'intervalle : **`[start, end)` semi-ouvert** — un
  candidat 09:45-11:00 et un booking 11:00-12:00 ne se
  chevauchent **pas** (touche autorisée). Vérifié par un cas
  smoke dédié.

- **`resolveDayWindows(date): array`** — applique la priorité
  `blocked_dates[date]` > `availability_exceptions[date]` >
  `availability[day_of_week]`. Une exception avec
  `is_available = 0` ferme la journée même si d'autres lignes
  existent pour la date.

- **`getActiveBookingsForDate(date): array`** — récupère les
  bookings qui arbitrent le créneau (`status IN ('pending',
  'confirmed', 'pending_payment')`), étend leur intervalle du
  `buffer_after_min`, **filtre les holds expirés**
  (`pending_payment` avec `payment_expires_at < NOW()`) — c'est
  le **lazy-expiry à la lecture** documenté dans `spec §4`,
  redondant avec le cron `cron/expire-holds.php` qui viendra au
  §6 pour persister le statut `expired`.

- **`computeSlotsForService(serviceId, date): array`** —
  orchestrateur : charge le service, applique
  `MAX_HORIZON_DAYS`, résout les fenêtres, charge les bookings,
  calcule `earliestStart` selon `MIN_NOTICE_MIN` (sur J
  uniquement — si `now + MIN_NOTICE` bascule sur le lendemain,
  aucun créneau aujourd'hui), délègue à `computeCandidates`.

- **`getServiceAvailableDates(serviceId, days = null): array`**
  et **`getServiceAvailabilityForMonth(serviceId, year, month):
  array`** — équivalents v3 des méthodes legacy
  `getAvailableDates` / `getAvailabilityForMonth`, basés sur le
  nouveau modèle.

### ⚙️ `config/config.php` — 3 constantes ajoutées

- `BOOKING_STEP = 15` — pas du parcours des fenêtres (minutes).
  Granularité d'offre des candidats.
- `MIN_NOTICE_MIN = 120` — délai minimum avant un créneau pour
  qu'il soit proposé (minutes). Appliqué à J seulement.
- `MAX_HORIZON_DAYS = 60` — horizon de réservation depuis
  aujourd'hui (jours).

Les constantes legacy `BOOKING_ADVANCE_MIN_HOURS` (24 h) et
`BOOKING_ADVANCE_MAX_DAYS` (60 j) restent en place — consommées
par les méthodes v2 du Slot.php tant que §5 n'a pas basculé le
tunnel.

### ✅ Tests — AD-9 (smoke)

`tests/smoke.php` étend de 7 cas en Lot 5 (algo créneaux v3,
logique pure sans BDD) :

1. Fenêtre 09:00-12:00, service 60+15 min, aucun booking,
   step 15 → 9 candidats (09:00, 09:15, …, 11:00).
2. Buffer respecté côté candidat : booking 11:00-12:00 ; un
   service 60+15 min produit 4 candidats (09:00…09:45) ;
   `09:45-11:00` est valide (touche `11:00` sans chevaucher).
3. Buffer côté booking : booking 10:00-11:00 + buffer 15 occupe
   `[10:00, 11:15)` ; aucun candidat possible dans
   `[09:00, 12:00)` pour un service 60+15 → 0 candidat.
4. Plusieurs fenêtres (matin 09-12 + après-midi 14-17) : les
   candidats des deux sont concaténés dans l'ordre temporel.
5. `earliestStart` filtre les candidats du matin (cas
   `MIN_NOTICE` sur J).
6. Défense en profondeur : `duration ≤ 0` ou `step ≤ 0` → liste
   vide (jamais d'exception, jamais de boucle infinie).
7. Convention `[start, end)` semi-ouverte vérifiée : un candidat
   et un booking juxtaposés au même instant **ne** se chevauchent
   **pas** (la borne droite est exclue).

24/24 cas verts au pre-commit (17 anciens + 7 nouveaux).

Les tests des méthodes BDD-dépendantes (`resolveDayWindows`,
`getActiveBookingsForDate`, `computeSlotsForService`,
`getServiceAvailableDates`, `getServiceAvailabilityForMonth`)
iront dans `tests/integration/` au §10 — nécessitent une vraie
base MySQL pour reproduire le comportement de
`bookings.active_key STORED` et des FK.

### 📄 Doc — `docs/booking-v3-spec.md`

§3 réécrite (la section était une « esquisse §4 — à venir ») :

- pseudo-code définitif de l'algo,
- tableau des constantes tunables,
- convention d'intervalle semi-ouvert documentée + conséquence
  pratique (juxtaposition autorisée),
- **co-existence v2 ↔ v3** explicitée : `Slot.php` héberge les
  deux algos en parallèle ; le legacy `getAvailableForDate` reste
  utilisé par `booking/index.php` + `api/slots.php` jusqu'à la
  bascule §5. Le code mort à purger sera identifié à ce
  moment-là.

État d'avancement mis à jour : §4 marqué **livré**.

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
