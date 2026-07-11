# TIPS SQL — Focus Coach (runbook exploitation)

> Manips SQL courantes pour l'exploitation / le dépannage. À jouer dans
> **phpMyAdmin** (onglet SQL) ou en CLI. Rien ici n'est appelé par le
> code : c'est de l'outillage humain.
>
> - ⚠️ = destructif / irréversible → **sauvegarde d'abord** (phpMyAdmin → Exporter).
> - Structure de référence : [`schema.sql`](schema.sql) · données par défaut : [`seed.sql`](seed.sql).
> - En **CLI Windows** (mysql.exe), toujours forcer le charset sinon mojibake :
>   `mysql --default-character-set=utf8mb4 -u USER -p BASE < fichier.sql`

---

## 🔐 Accès admin (`/admin/`)

**Débloquer le login (« Trop de tentatives, réessayez dans 15 minutes »)**
Le rate-limit (5 essais ratés / 15 min / IP) est vérifié AVANT le mot de passe :
tant qu'il bloque, même le bon mot de passe est refusé.
```sql
DELETE FROM admin_login_attempts;
```

**Voir les tentatives récentes (diagnostic)**
```sql
SELECT ip_address, success, attempted_at
FROM admin_login_attempts
ORDER BY attempted_at DESC
LIMIT 20;
```

> Le mot de passe admin n'est **pas** en base : c'est un hash bcrypt dans
> `config.local.php` (`ADMIN_PASSWORD_HASH`). Pour le changer :
> `php -r "echo password_hash('NOUVEAU_MDP', PASSWORD_DEFAULT), PHP_EOL;"`
> puis coller le hash (entre **apostrophes simples**) dans `config.local.php`.

---

## ♻️ Repartir de zéro (base NEUVE ou de test)

⚠️ **Efface TOUTES les données.** Réservé au test, ou à une prod qu'on
réinitialise volontairement. Sauvegarde avant.
```sql
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS available_slots, admin_login_attempts, blocked_dates,
  bookings, purge_stats, rgpd_deletion_log, settings, services, availability,
  availability_exceptions, packages, package_purchases, stripe_events_processed;
SET FOREIGN_KEY_CHECKS = 1;
```
Puis réimporter dans l'ordre : **`schema.sql`** → **`seed.sql`**.
(`available_slots` = ancienne table pré-v3 ; on la drope au passage si elle traîne.)

> 💡 Version scriptée équivalente : [`reset-dev.sql`](reset-dev.sql) (drop dans
> l'ordre inverse des FK). Workflow reset complet :
> `mysql < reset-dev.sql && mysql < schema.sql && mysql < seed.sql`.

---

## 📅 Réservations & créneaux

**Réservations à venir**
```sql
SELECT id, visitor_name, visitor_email, slot_date, slot_time_start, status
FROM bookings
WHERE slot_date >= CURDATE()
ORDER BY slot_date, slot_time_start;
```

**Libérer un créneau coincé en « hold » Stripe expiré**
Un `pending_payment` dont le hold est dépassé garde le créneau réservé
tant que le lazy-expiry / cron ne l'a pas passé en `expired`.
```sql
UPDATE bookings
SET status = 'expired'
WHERE status = 'pending_payment'
  AND payment_expires_at IS NOT NULL
  AND payment_expires_at < NOW();
```

**Voir qui occupe un créneau donné (anti double-booking)**
`active_key` = clé du créneau ACTIF (NULL si annulé/terminé/expiré).
```sql
SELECT id, visitor_name, status, active_key
FROM bookings
WHERE slot_date = '2026-07-20';
```

**Annuler une réservation (à la main)**
```sql
UPDATE bookings
SET status = 'cancelled', cancelled_at = NOW()
WHERE id = 123;
```

**Dates fermées (congés)**
```sql
-- ajouter
INSERT INTO blocked_dates (blocked_date, reason) VALUES ('2026-08-15', 'Congés');
-- retirer
DELETE FROM blocked_dates WHERE blocked_date = '2026-08-15';
```

---

## 🎟️ Forfaits à jetons (§7)

**État des forfaits d'un client (jetons restants)**
```sql
SELECT pp.id, pp.client_email, p.name AS forfait, pp.status,
       pp.credits_total, pp.credits_used,
       (pp.credits_total - pp.credits_used) AS restants,
       pp.expires_at
FROM package_purchases pp
JOIN packages p ON p.id = pp.package_id
WHERE pp.client_email = 'client@example.com';
```

**Retrouver le lien d'espace d'un client** (token → `espace.php?token=…` / `pack.php?token=…`)
```sql
SELECT client_email, manage_token, status FROM package_purchases
ORDER BY purchased_at DESC LIMIT 10;
```

**Expirer les forfaits périmés (normalement fait par le cron)**
```sql
UPDATE package_purchases
SET status = 'expired'
WHERE status = 'active'
  AND expires_at IS NOT NULL
  AND expires_at < NOW();
```

**Contrôle d'alignement : forfait actif pointant vers un service actif ?**
(doit renvoyer 0 ligne — sinon un forfait vend une prestation désactivée)
```sql
SELECT p.id, p.name
FROM packages p
JOIN services s ON s.id = p.service_id
WHERE p.is_active = 1 AND s.is_active = 0;
```

---

## 💳 Stripe / catalogue

**Renseigner le `stripe_price_id` d'une prestation** (après création du Price côté Stripe)
```sql
UPDATE services  SET stripe_price_id = 'price_XXXX' WHERE slug = 'preparation-mentale';
UPDATE packages  SET stripe_price_id = 'price_YYYY' WHERE slug = 'forfait-performance';
```

**Services actifs payants SANS Stripe price** (le tunnel refusera de démarrer pour eux)
```sql
SELECT id, slug, name, price_cents
FROM services
WHERE is_active = 1 AND price_cents > 0
  AND (stripe_price_id IS NULL OR stripe_price_id = '');
```

---

## ⚙️ Paramètres (identité, config)

**Lire tous les réglages**
```sql
SELECT setting_key, setting_value FROM settings ORDER BY setting_key;
```

**Modifier un réglage** (préférer /admin → Paramètres ; SQL en dépannage)
```sql
UPDATE settings SET setting_value = 'Focus Coach' WHERE setting_key = 'site_name';
```

---

## 🧹 Données de démo / test

**Supprimer les réservations de démo** (emails en `@demo.focuscoach.test`)
```sql
DELETE FROM bookings WHERE visitor_email LIKE '%@demo.focuscoach.test';
```

**Supprimer les forfaits de démo**
```sql
DELETE FROM package_purchases WHERE client_email LIKE '%@demo.focuscoach.test';
```

> Jeu de démo prêt à l'emploi : [`seed-demo.sql`](seed-demo.sql) (optionnel,
> à importer APRÈS `seed.sql` sur une base de test uniquement).

---

## 🔎 Divers diagnostic

**Compter les lignes de chaque table applicative**
```sql
SELECT table_name, table_rows
FROM information_schema.tables
WHERE table_schema = DATABASE()
ORDER BY table_name;
```

**Vérifier le charset des tables (doit être utf8mb4)**
```sql
SELECT table_name, table_collation
FROM information_schema.tables
WHERE table_schema = DATABASE();
```
