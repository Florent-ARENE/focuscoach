# Spec — Module de réservation v3 (Focus Coach)

> **Statut** : en construction, v3.0.0 (chantier en cours).
> Cible : v3.0.0 figée. Tant que la version racine (`VERSION`) reste
> en `2.X.Y`, le module est partiel : voir § *État d'avancement*.

Ce document est la mémoire technique du chantier Booking v3. Il
documente le modèle de données, l'algorithme de calcul des créneaux,
les machines à états, les invariants de course (`active_key`,
double-hold), le pipeline Stripe et la procédure de rollback. Il est
**rédigé au fil de l'eau** (AD-7) et doit refléter l'état réel du
code, pas une cible théorique.

## État d'avancement (chantier ouvert)

| Checkpoint | Module | Statut |
|---|---|---|
| §3 | Modèle de données (migration + schema + seed) | **livré** (v2.5.0) |
| §4 | Algorithme de calcul des créneaux | **livré** (v2.5.1) |
| §5 | Tunnel de réservation (prestation → date → créneau) | **livré** (v2.5.2) |
| §6 | Paiement Stripe Checkout + webhook idempotent | à venir |
| §7 | Forfaits & jetons (espace pack) | à venir |
| §8 | Admin (CRUD services/availability/packages) | à venir |
| §9 | `health.php` (AD-8 runtime) | à venir |
| §10 | Tests (smoke / integration / endpoints) | à venir |
| livraison finale | bump 3.0.0, doc, mysqldump pré-prod | à venir |

---

## 1. Modèle de données

Schéma cible (DDL exacte : `sql/schema.sql` ; chemin d'upgrade :
`sql/migration-3.0.0.sql`).

### Nouvelles tables

- **`services`** — catalogue prestations (CRUD admin) ; remplace
  l'ancien `ENUM service_type`. Porte `duration_min`,
  `buffer_after_min`, `price_cents`, `stripe_price_id` (renseigné en
  admin), `segment` ∈ {sportif, dirigeant, particulier}. Unique sur
  `slug`. `price_cents = 0` → court-circuit Stripe (séance gratuite).
- **`availability`** — planning récurrent hebdomadaire (fenêtres
  d'ouverture continues, pas des créneaux pré-découpés). Une ligne =
  un jour + une fenêtre `[window_start, window_end]`. Unique sur
  `(day_of_week, window_start, window_end)`.
- **`availability_exceptions`** — overrides par date.
  `is_available = 0` → journée fermée ; `is_available = 1` + une
  fenêtre → cette fenêtre remplace le récurrent pour ce jour.
- **`packages`** — forfaits à jetons. Réfère 1 service inclus.
  Unique sur `slug`.
- **`package_purchases`** — achat d'un pack par un client. Porte les
  crédits (`credits_total`, `credits_used`) et le `manage_token`
  unique d'accès à l'espace pack (`pack.php?token=…`). Un seul jeton
  par client — les séances issues du pack n'ont **pas** leur
  `manage_token` propre (cf. invariant § 4).
- **`stripe_events_processed`** — idempotence du webhook Stripe.
  PK = `event_id`. Cf. § 4.

### Tables existantes touchées

- **`bookings`** — colonnes ajoutées :
  - `service_id` (FK NULL — anciens bookings conseil restent NULL),
  - `package_purchase_id` (FK NULL),
  - `duration_min`, `buffer_after_min` (copiés du service à la
    création — figent la durée du booking),
  - `payment_status` ∈ {none, pending, paid, refunded},
  - `stripe_session_id`, `amount_paid_cents`,
  - `payment_expires_at` (hold de 15 min pendant Stripe),
  - `confirmation_email_sent_at` (garde-fou anti double-email sur
    retry webhook),
  - `status` étendu de `'pending_payment'` et `'expired'`,
  - `active_key` redéfinie pour inclure `'pending_payment'` (sinon
    deux holds concurrents non arbitrés → double paiement possible).
- `service_type` (ancienne ENUM) conservée pour les bookings
  archivés de l'offre conseil — plus utilisée par le code v3.

### Tables conservées (intactes)

`blocked_dates`, `settings`, `rgpd_deletion_log`, `purge_stats`,
`admin_login_attempts`.

### Table dépréciée

`available_slots` — créneaux pré-découpés de l'ancien modèle. La
migration 3.0.0 regroupe les créneaux consécutifs en `availability`
et **ne supprime pas** la table (réversibilité). Une migration
future pourra la dropper après validation prod. `schema.sql` la
recrée vide pour les installs neuves (compat avec un rejeu de la
migration), mais le code ne la lit plus dès le checkpoint §4
(bascule de `Slot.php` sur `availability`).

---

## 2. Algorithme de regroupement `available_slots` → `availability`

Implémenté en SQL pur dans `sql/migration-3.0.0.sql` (étape 8) :

```sql
INSERT IGNORE INTO availability (day_of_week, window_start, window_end, is_active)
SELECT day_of_week, MIN(time_start), MAX(time_end), 1
FROM (
    SELECT day_of_week, time_start, time_end,
           SUM(CASE
               WHEN LAG(time_end) OVER (PARTITION BY day_of_week ORDER BY time_start) = time_start
               THEN 0 ELSE 1
           END) OVER (PARTITION BY day_of_week ORDER BY time_start) AS grp
    FROM available_slots
    WHERE is_active = 1
) AS grouped
GROUP BY day_of_week, grp;
```

Idée : pour chaque jour, trier par `time_start` ; un créneau ouvre
un nouveau groupe si son `time_start` ≠ `time_end` du précédent
(c'est-à-dire qu'il y a un trou). On agrège chaque groupe en une
fenêtre `[MIN(time_start), MAX(time_end)]`.

**Résultat attendu sur les défauts inchangés** (12 créneaux de
30 min/jour : 9-12 + 14-17 × 5 jours = 60 lignes) → **10 fenêtres**
(matin + après-midi × 5 jours).

Vérification manuelle après migration :
```sql
SELECT day_of_week, COUNT(*) FROM availability GROUP BY day_of_week;
```
→ 2 lignes par jour de 1 à 5.

Si le résultat diverge → ne pas continuer, restaurer le dump,
inspecter `available_slots` à la main.

---

## 3. Algorithme de calcul des créneaux (livré v2.5.1)

Implémenté dans `classes/Slot.php` :

- **`computeCandidates(windows, bookings, duration, buffer, step,
  earliestStart = null)`** — fonction **pure et statique**
  testable sans BDD (corpus smoke AD-9, Lot 5). Cœur de l'algo.
- **`resolveDayWindows(date)`** — applique la priorité
  `blocked_dates` > `availability_exceptions[date]` >
  `availability[day_of_week]`.
- **`getActiveBookingsForDate(date)`** — récupère les bookings
  arbitrant le créneau (`pending`, `confirmed`, `pending_payment`),
  étend leur intervalle du `buffer_after_min`, **filtre les holds
  expirés** (`pending_payment` avec `payment_expires_at < NOW()`)
  — lazy-expiry à la lecture, redondant avec le cron
  `cron/expire-holds.php` (à créer §6).
- **`computeSlotsForService(serviceId, date)`** — orchestrateur
  qui appelle les trois précédents + applique `MIN_NOTICE_MIN` /
  `MAX_HORIZON_DAYS`.
- **`getServiceAvailableDates(serviceId, days = null)`** —
  équivalent v3 de l'ancien `getAvailableDates()`.
- **`getServiceAvailabilityForMonth(serviceId, year, month)`** —
  équivalent v3 de `getAvailabilityForMonth()`.

### Pseudo-code (cœur)

Pour `(service, date)` :

1. Résoudre la disponibilité du jour :
   - si `blocked_dates[date]` → `[]`,
   - sinon si `availability_exceptions[date]` :
     - une seule ligne avec `is_available = 0` → `[]`,
     - sinon → fenêtres de l'exception (peut être plusieurs),
   - sinon → `availability[day_of_week]` actives.
2. Charger les bookings actifs du jour (`status IN ('pending',
   'confirmed', 'pending_payment')`), filtrer les holds expirés.
   Chacun occupe `[start, end + buffer_after_min)`.
3. Pour chaque fenêtre `[W_start, W_end]`, parcourir par pas
   `BOOKING_STEP` :
   - candidat `t` ; il occupe `[t, t + duration + buffer)`,
   - valide si `t + duration ≤ W_end` **et** `t ≥ earliestStart`
     (sur J seulement) **et** `[t, t + duration + buffer)` ne
     chevauche aucun intervalle occupé.
4. Sur J, si `now + MIN_NOTICE_MIN` bascule sur le lendemain →
   aucun créneau aujourd'hui.
5. Hors horizon (`date < today` ou `date > today + MAX_HORIZON_DAYS`)
   → aucun créneau.
6. Retourner les `(t, t + duration)` valides.

### Conventions d'intervalle

`[start, end)` semi-ouvert. Conséquence pratique : un candidat
09:45-11:00 et un booking 11:00-12:00 ne se chevauchent **pas**
(la borne `11:00` est exclue à droite et incluse à gauche → pas
d'intersection). Convention vérifiée par un cas smoke dédié.

### Constantes tunables

`config/config.php` (pas `config.local.php` — ces valeurs ne sont
pas des secrets, elles sont identiques en dev / staging / prod) :

| Constante | Défaut | Rôle |
|---|---|---|
| `BOOKING_STEP` | 15 | Pas de proposition des candidats (minutes) |
| `MIN_NOTICE_MIN` | 120 | Délai mini avant un créneau sur J |
| `MAX_HORIZON_DAYS` | 60 | Horizon de réservation |

⚠️ Les constantes legacy `BOOKING_ADVANCE_MIN_HOURS` /
`BOOKING_ADVANCE_MAX_DAYS` restent en place et sont consommées
par les méthodes `getAvailableForDate()` /
`getAvailabilityForMonth()` du Slot.php v2 — utilisées par
`booking/index.php` + `api/slots.php` tant que §5 n'a pas
basculé le tunnel sur le nouvel algo.

### Co-existence v2 ↔ v3 (transitoire)

Slot.php héberge les deux algos en parallèle :

- **v2** (legacy) — `getAvailableForDate`, `getAvailableDates`,
  `getAvailabilityForMonth`, basés sur `available_slots`
  pré-découpés et un délai mini en heures
  (`BOOKING_ADVANCE_MIN_HOURS`). Consommés par le tunnel actuel
  (`api/slots.php`) — **encore utilisés en prod tant que §5
  n'a pas basculé**.
- **v3** — `computeSlotsForService`, `getServiceAvailableDates`,
  `getServiceAvailabilityForMonth`, basés sur `availability` +
  `availability_exceptions` + `services.duration_min` + buffer +
  `BOOKING_STEP` + `MIN_NOTICE_MIN`. Pas encore branchés sur le
  tunnel (sera fait §5).

Lorsque §5 aura basculé `api/slots.php` (ou créé un nouvel
endpoint `api/booking-v3-slots.php`) et que `booking/index.php`
appellera l'algo v3, les méthodes v2 deviendront du code mort à
purger.

---

## 4. Invariants de course (anti-double-booking)

Deux gardes complémentaires, à conserver ensemble :

### `active_key` — clé d'unicité au départ identique

Colonne générée `STORED` sur `bookings` :

```sql
active_key = CASE WHEN status IN ('pending','confirmed','pending_payment')
                  THEN CONCAT(slot_date, '_', slot_time_start)
                  ELSE NULL END
```

UNIQUE KEY `uq_active_slot` dessus. Arbitre côté SQL deux INSERT
concurrents sur le même `(date, start)`. NULL pour
`cancelled/completed/expired` → InnoDB autorise plusieurs NULL → un
créneau libéré redevient réservable.

⚠️ **Pourquoi inclure `pending_payment`** : sans cela, deux holds
concurrents de 15 min sur le même créneau ne sont pas arbitrés —
les deux clients arrivent sur Stripe, les deux paient → double
booking, refund manuel.

### Vérification de chevauchement (durées variables)

L'`active_key` seule ne couvre **pas** les départs différents qui
se chevauchent (ex. 10h00-11h00 vs 10h30-11h30). À la création d'un
booking (§5/§6 — à venir) :

```
BEGIN;
SELECT id, slot_time_start, duration_min, buffer_after_min
  FROM bookings
  WHERE slot_date = ?
    AND status IN ('pending','confirmed','pending_payment')
  FOR UPDATE;
-- vérifier en PHP que [t, t + duration + buffer] ne chevauche
-- aucun [b.start, b.end + b.buffer]
INSERT INTO bookings (...);
COMMIT;
```

Les deux gardes coexistent : `active_key` est rapide et déclenche
sur le cas simple (même départ) ; la vérification transactionnelle
ferme le cas durée variable.

### Collision sur hold expiré

La colonne générée ne peut pas tester `NOW()` : un `pending_payment`
dont `payment_expires_at` est dépassé garde sa clé tant que le
lazy-expiry à la lecture (§4) ou le cron `cron/expire-holds.php`
(§6) ne l'a pas passé en `expired`.

À l'insertion, sur erreur SQLSTATE `23000` :
1. Charger la ligne en conflit (`slot_date`, `slot_time_start`,
   `status`, `payment_expires_at`).
2. Si `status = 'pending_payment'` et `payment_expires_at < NOW()`
   → `UPDATE … SET status = 'expired'` (sa `active_key` devient
   NULL).
3. Réessayer l'INSERT.
4. Sinon, remonter le 23000 en message UX cohérent (« Ce créneau
   vient d'être réservé »).

---

## 5. Machines à états

### `bookings.status`

```
        + pending  ────────────────► confirmed ──► completed
        │   ▲                          │  ▲           │
[création]  │ [retry / admin]          │  └─[admin]   │
        ▼   │                          ▼              │
  pending_payment ──[Stripe OK]──► confirmed ─────────►
        │                              │
        ├─[expiry / cron]──► expired   ├─[client/admin]──► cancelled
        │                              │
        └─[admin]──► cancelled         │
```

- **`pending`** : booking en attente de validation admin (mode sans
  paiement — Stripe inactif).
- **`pending_payment`** : hold de 15 min pendant Stripe Checkout.
  `payment_expires_at = created_at + 15 min`. À expiration, passé en
  `expired` par le cron OU lazy à la lecture (cf. § 4 et § 6).
- **`confirmed`** : créneau réservé (paiement OK ou mode sans
  paiement avec validation admin).
- **`cancelled`** : annulé par le client ou l'admin. Libère
  l'`active_key`.
- **`completed`** : passé, archivé.
- **`expired`** : hold expiré sans paiement. Libère l'`active_key`.

### `package_purchases.status`

```
pending_payment ──[Stripe OK]──► active
                                   │
                                   ├─[credits_used == credits_total]──► exhausted
                                   └─[NOW() > expires_at]─────────────► expired
```

`expires_at = purchased_at + packages.validity_days`. Garde-fou à la
planification d'une séance : refus si `exhausted` ou `expired`.

---

## 6. Tunnel & paiement (§5/§6 — à venir, esquisse)

### Tunnel multi-pages PHP

`prestation → date → créneau → formulaire → (paiement) → confirmation`.

L'état du tunnel vit dans `$_SESSION['booking_draft']` entre les
étapes (pas de SPA — cohérent avec la stack vanilla et le
`booking/manage.php` existant).

### Stripe Checkout — résilience

- **Clés absentes** (`STRIPE_SECRET_KEY` non définie dans
  `config.local.php`) → tunnel bascule en `pending` (validation
  admin), `payment_status = none`. Jamais de plantage.
- **`service.price_cents == 0`** → court-circuit Stripe (refuse une
  session à 0 €). Booking direct en `confirmed`, `payment_status =
  none`.
- **Clés présentes + prix > 0 + `stripe_price_id` NULL ou invalide**
  → refus de démarrer le tunnel pour ce service (signal `health.php`,
  message UX explicite côté tunnel). C'est l'état attendu au sortir
  du §3 jusqu'à ce que Renaud ait créé les Price côté Stripe et
  renseigné les ids en admin.
- **Clés présentes + prix > 0 + `stripe_price_id` valide** → flux
  Checkout standard.

### `BASE_URL` figée

Stripe Checkout exige des URLs de succès/annulation absolues. Pour
fermer le risque XSS Host-header et garantir la cohérence
prod/dev/staging, `BASE_URL` vit dans `config.local.php` (variable
par environnement, fixe — **jamais** dérivée du header `Host`).

⚠️ État actuel (§3) : `config/config.php` dérive encore `BASE_URL`
de `$_SERVER['HTTP_HOST']`. À corriger au §6 (déplacement dans
`config.local.php` + fallback si absente : refus de démarrer le
tunnel Stripe + signal `health.php`).

### Webhook idempotent

`api/stripe-webhook.php` (à créer §6) :

1. Vérifie la signature HMAC (`STRIPE_WEBHOOK_SECRET`).
2. **Exempté de CSRF** (allowlist explicite — pas de session
   navigateur, authentification = signature Stripe).
3. `INSERT IGNORE INTO stripe_events_processed (event_id, …)`.
   `rowCount() == 0` → event déjà traité → 200 no-op.
4. Sur `checkout.session.completed` : `UPDATE bookings SET status
   = 'confirmed', payment_status = 'paid', amount_paid_cents = …`
   (idempotent).
5. Effets de bord rendus idempotents **chacun séparément** (pas via
   le flag d'event) :
   - **Sync GCal** : rattrapée par le cron Google existant
     (`confirmed` + `google_event_id IS NULL`) — un timeout côté
     webhook ne perd pas la sync,
   - **Email confirmation** : gardé par
     `bookings.confirmation_email_sent_at` (envoyé une seule fois)
     — un retry Stripe ne double pas l'email.

---

## 7. RGPD

`package_purchases` est un **nouveau** puits de données nominatives
(`client_name`, `client_email`). À couvrir par le cron de purge
RGPD existant (qui ne couvre aujourd'hui que `bookings`) :

- Effacer `client_name` et `client_email` après
  `expires_at + RGPD_RETENTION_DAYS`.
- Tracer dans `rgpd_deletion_log` (`deletion_type =
  'auto_purge'`).

À implémenter avec §7 (espace pack).

---

## 8. Rollback de la migration 3.0.0

⚠️ Migration structurante (6 tables + colonnes `bookings`) sur
hébergement OVH mutualisé **sans déploiement atomique**.

**Avant exécution** :

```bash
mysqldump --single-transaction --routines --triggers \
  -u <user> -p <db> > backup-pre-3.0.0-$(date +%F).sql
```

**Rollback** :
1. Restore du dump (`mysql -u <user> -p <db> < backup-pre-3.0.0-…sql`).
2. Revert du code (`git checkout <commit-avant-3.0.0>` puis FTP).

Les ALTER de la migration sont écrits pour rester idempotents au
mieux (`IF NOT EXISTS` sur les CREATE, `IGNORE` sur les INSERT),
mais MySQL **ne propose pas de DOWN automatique** sur un ALTER.
La sauvegarde est la garantie de réversibilité.
