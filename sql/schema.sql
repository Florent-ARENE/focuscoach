-- ============================================
-- FOCUS COACH — SCHÉMA DE RÉFÉRENCE
-- ============================================
-- Structure pure (DDL). Les données initiales sont dans `seed.sql`.
--
-- Version courante : voir le fichier `VERSION` racine + `CHANGELOG.md`.
--   (AD-1 : aucun numéro de version codé en dur ici — il dériverait et
--    se désynchroniserait, comme l'ancien `database.sql` resté en 2.4.1.)
--
-- Remplace l'ancien `sql/database.sql` (qui mélangeait structure + seed).
--
-- Usage :
--   - Base NEUVE  → importer `schema.sql` puis `seed.sql`.
--   - Base DÉJÀ déployée → ne PAS rejouer ce fichier ; utiliser les
--     `sql/migration-*.sql` correspondants.
--
-- Idempotent : `CREATE TABLE IF NOT EXISTS` (non destructif). Pour un
-- ré-import from scratch volontaire, dropper les tables manuellement
-- d'abord (ordre inverse des dépendances).
--
-- Prérequis moteur :
--   - MySQL ≥ 5.7.6 OU MariaDB ≥ 10.2 (colonne générée `STORED` sur
--     `bookings.active_key`, window functions pour la migration 3.0.0).
--     Sur MariaDB 10.1, le CREATE échoue silencieusement côté client
--     sur ce point — vérifie ton moteur avant import.
-- ============================================

-- Sur OVH mutualisé la base est pré-créée et son nom est imposé :
-- importer directement dans la base fournie. Décommenter uniquement
-- pour un environnement local où l'on crée la base soi-même.
-- CREATE DATABASE IF NOT EXISTS virtualburenaud
--   CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE virtualburenaud;


-- ============================================
-- TABLE : services — catalogue prestations (CRUD admin)
-- ============================================
-- Remplace l'enum figé `service_type` de l'ancienne offre conseil.
-- Chaque service porte sa durée + son temps de compte rendu (buffer)
-- + son prix + son `stripe_price_id` (renseigné en admin après
-- création du Price côté Stripe). `price_cents = 0` → court-circuit
-- Stripe même quand activé (séance Découverte gratuite).
-- ============================================
CREATE TABLE IF NOT EXISTS services (
    id INT AUTO_INCREMENT PRIMARY KEY,

    slug             VARCHAR(80)  NOT NULL,
    segment          ENUM('sportif','dirigeant','particulier') NOT NULL,
    name             VARCHAR(150) NOT NULL,
    description      TEXT         DEFAULT NULL,
    duration_min     INT          NOT NULL,
    buffer_after_min INT          NOT NULL DEFAULT 15
        COMMENT 'Temps de compte rendu post-séance',
    price_cents      INT          NOT NULL DEFAULT 0
        COMMENT '0 → séance gratuite, court-circuit Stripe',
    stripe_price_id  VARCHAR(80)  DEFAULT NULL,
    is_active        TINYINT(1)   NOT NULL DEFAULT 1,
    sort_order       INT          NOT NULL DEFAULT 0,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_services_slug (slug),
    INDEX idx_services_segment_active (segment, is_active),
    INDEX idx_services_sort (sort_order)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================
-- TABLE : availability — planning récurrent hebdo (fenêtres d'ouverture)
-- ============================================
-- Une ligne = une fenêtre d'ouverture continue sur un jour donné.
-- Les créneaux sont CALCULÉS au runtime à partir de (fenêtre,
-- service.duration, service.buffer, BOOKING_STEP).
-- Remplace l'ancienne table `available_slots` (créneaux pré-découpés)
-- — qui reste conservée en base sur les installs migrées, par
-- réversibilité, mais n'est plus utilisée par le code.
-- ============================================
CREATE TABLE IF NOT EXISTS availability (
    id INT AUTO_INCREMENT PRIMARY KEY,

    day_of_week  TINYINT NOT NULL COMMENT '1=Lundi, 7=Dimanche',
    window_start TIME    NOT NULL,
    window_end   TIME    NOT NULL,
    is_active    TINYINT(1) NOT NULL DEFAULT 1,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_availability_window (day_of_week, window_start, window_end),
    INDEX idx_availability_day_active (day_of_week, is_active)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================
-- TABLE : availability_exceptions — overrides par date
-- ============================================
-- Prime sur availability[day_of_week] le jour donné :
--   is_available = 0 → journée fermée,
--   is_available = 1 → la (ou les) fenêtre(s) listée(s) remplace(nt)
--     le récurrent pour ce jour-là.
-- ============================================
CREATE TABLE IF NOT EXISTS availability_exceptions (
    id INT AUTO_INCREMENT PRIMARY KEY,

    exception_date DATE       NOT NULL,
    is_available   TINYINT(1) NOT NULL DEFAULT 1
        COMMENT '0 = fermé toute la journée',
    window_start   TIME       DEFAULT NULL,
    window_end     TIME       DEFAULT NULL,
    reason         VARCHAR(255) DEFAULT NULL,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_availability_exception (exception_date, window_start),
    INDEX idx_availability_exception_date (exception_date)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================
-- TABLE : blocked_dates — dates indisponibles (congés, etc.)
-- ============================================
CREATE TABLE IF NOT EXISTS blocked_dates (
    id INT AUTO_INCREMENT PRIMARY KEY,

    blocked_date DATE         NOT NULL,
    reason       VARCHAR(255) DEFAULT NULL,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    UNIQUE INDEX idx_blocked_date (blocked_date)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================
-- TABLE : packages — forfaits à jetons (CRUD admin)
-- ============================================
CREATE TABLE IF NOT EXISTS packages (
    id INT AUTO_INCREMENT PRIMARY KEY,

    slug            VARCHAR(80)  NOT NULL,
    name            VARCHAR(150) NOT NULL,
    segment         ENUM('sportif','dirigeant','particulier') NOT NULL,
    service_id      INT          NOT NULL
        COMMENT 'La prestation incluse dans le pack',
    sessions_count  INT          NOT NULL,
    price_cents     INT          NOT NULL DEFAULT 0,
    stripe_price_id VARCHAR(80)  DEFAULT NULL,
    validity_days   INT          NOT NULL DEFAULT 365,
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    sort_order      INT          NOT NULL DEFAULT 0,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_packages_slug (slug),
    INDEX idx_packages_segment_active (segment, is_active),
    CONSTRAINT fk_packages_service FOREIGN KEY (service_id)
        REFERENCES services(id) ON DELETE RESTRICT ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================
-- TABLE : package_purchases — achat d'un pack par un client
-- ============================================
-- Porte les jetons (credits_total / credits_used) et le manage_token
-- d'accès à l'espace pack (pack.php?token=…). Un seul jeton par
-- client (pas un par séance issue du pack — sinon doublons d'URL).
-- ============================================
CREATE TABLE IF NOT EXISTS package_purchases (
    id INT AUTO_INCREMENT PRIMARY KEY,

    package_id        INT          NOT NULL,
    client_name       VARCHAR(150) NOT NULL,
    client_email      VARCHAR(150) NOT NULL,
    credits_total     INT          NOT NULL,
    credits_used      INT          NOT NULL DEFAULT 0,
    manage_token      VARCHAR(64)  NOT NULL,
    status            ENUM('pending_payment','active','expired','exhausted') NOT NULL DEFAULT 'pending_payment',
    stripe_session_id VARCHAR(255) DEFAULT NULL,
    purchased_at      DATETIME     DEFAULT CURRENT_TIMESTAMP,
    expires_at        DATETIME     DEFAULT NULL,

    UNIQUE KEY uq_package_purchases_token (manage_token),
    INDEX idx_package_purchases_email (client_email),
    INDEX idx_package_purchases_status (status),
    CONSTRAINT fk_package_purchases_package FOREIGN KEY (package_id)
        REFERENCES packages(id) ON DELETE RESTRICT ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================
-- TABLE : stripe_events_processed — idempotence webhook Stripe
-- ============================================
-- En tête de api/stripe-webhook.php : INSERT IGNORE de l'event_id ;
-- rowCount() == 0 → event déjà traité → réponse 200 no-op.
-- ============================================
CREATE TABLE IF NOT EXISTS stripe_events_processed (
    event_id     VARCHAR(255) PRIMARY KEY,
    processed_at DATETIME DEFAULT CURRENT_TIMESTAMP

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================
-- TABLE : bookings — réservations
-- ============================================
-- v3.0.0 :
--   - service_id / package_purchase_id / duration_min /
--     buffer_after_min / payment_* (catalogue + paiement),
--   - status étendu de 'pending_payment' (hold pendant Stripe) et
--     'expired' (hold libéré sans confirmation),
--   - active_key recalculée pour inclure 'pending_payment' (sinon
--     deux holds concurrents non arbitrés → double paiement).
--   - service_type (ENUM offre conseil) SUPPRIMÉ avec la purge
--     2.6.0 : plus aucun booking en base n'y faisait référence
--     (phase de dev sans utilisateurs). Le nom de la prestation
--     s'obtient via JOIN sur services.name.
-- ============================================
CREATE TABLE IF NOT EXISTS bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Informations visiteur
    visitor_name         VARCHAR(100) NOT NULL,
    visitor_email        VARCHAR(150) NOT NULL,
    visitor_phone        VARCHAR(20)  DEFAULT NULL,
    visitor_organization VARCHAR(150) DEFAULT NULL,

    -- Créneau
    slot_date       DATE NOT NULL,
    slot_time_start TIME NOT NULL,
    slot_time_end   TIME NOT NULL,

    -- Prestation (v3.0.0)
    service_id          INT DEFAULT NULL
        COMMENT 'FK services. NULL pour les bookings issus d''un pack non encore associés ou cas limites',
    package_purchase_id INT DEFAULT NULL
        COMMENT 'FK package_purchases. NULL si paiement à l''unité',
    duration_min        INT DEFAULT NULL
        COMMENT 'Copié de services.duration_min à la création (fige la durée)',
    buffer_after_min    INT DEFAULT NULL
        COMMENT 'Copié de services.buffer_after_min à la création',

    -- Contenu de la demande
    subject VARCHAR(255) DEFAULT NULL,
    message TEXT         DEFAULT NULL,

    -- Statut
    status ENUM('pending','confirmed','cancelled','completed','pending_payment','expired') NOT NULL DEFAULT 'pending',

    -- Paiement Stripe (v3.0.0)
    payment_status             ENUM('none','pending','paid','refunded') NOT NULL DEFAULT 'none',
    stripe_session_id          VARCHAR(255) DEFAULT NULL,
    amount_paid_cents          INT          DEFAULT NULL,
    payment_expires_at         DATETIME     DEFAULT NULL
        COMMENT 'Hold de 15 min sur le créneau pendant le tunnel Stripe',
    confirmation_email_sent_at DATETIME     DEFAULT NULL
        COMMENT 'Garde-fou anti double-email sur retry webhook Stripe',

    -- Google Calendar (synchronisation miroir)
    google_event_id    VARCHAR(255) DEFAULT NULL,
    google_calendar_id VARCHAR(255) DEFAULT NULL,

    -- Token de gestion client (espace personnel) — pour les bookings
    -- à l'unité. NULL pour les séances issues d'un pack (gérées via
    -- pack.php?token=… avec le token du package_purchase).
    manage_token VARCHAR(64) DEFAULT NULL,

    -- Métadonnées
    admin_notes TEXT        DEFAULT NULL,
    ip_address  VARCHAR(45) DEFAULT NULL,
    user_agent  TEXT        DEFAULT NULL,

    -- Timestamps
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    confirmed_at DATETIME DEFAULT NULL,
    cancelled_at DATETIME DEFAULT NULL,

    -- Clé d'unicité du créneau ACTIF (anti race double-booking).
    -- v3.0.0 : inclut 'pending_payment' pour arbitrer deux holds
    -- concurrents sur le même créneau pendant Stripe. NULL pour
    -- cancelled/completed/expired → InnoDB autorise plusieurs NULL,
    -- un créneau libéré redevient réservable.
    -- ⚠️ Collision sur hold expiré : la colonne générée ne peut pas
    -- tester NOW() — un pending_payment dont payment_expires_at est
    -- dépassé garde sa clé tant qu'un lazy-expiry (lecture) ou le
    -- cron `expire-holds` ne l'a pas passé en 'expired'. Géré côté
    -- code par retry sur erreur 23000 (cf. spec §3 du prompt v3).
    active_key VARCHAR(20) GENERATED ALWAYS AS (
        CASE WHEN status IN ('pending','confirmed','pending_payment')
             THEN CONCAT(slot_date, '_', slot_time_start)
             ELSE NULL
        END
    ) STORED,

    INDEX idx_status        (status),
    INDEX idx_slot_date     (slot_date),
    INDEX idx_visitor_email (visitor_email),
    INDEX idx_payment_expires (payment_expires_at),
    UNIQUE INDEX idx_manage_token (manage_token),
    UNIQUE KEY   uq_active_slot   (active_key),

    CONSTRAINT fk_bookings_service FOREIGN KEY (service_id)
        REFERENCES services(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_bookings_package_purchase FOREIGN KEY (package_purchase_id)
        REFERENCES package_purchases(id) ON DELETE SET NULL ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================
-- TABLE : settings — paramètres clé/valeur (identité, config)
-- ============================================
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,

    setting_key   VARCHAR(100) NOT NULL,
    setting_value TEXT         DEFAULT NULL,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE INDEX idx_setting_key (setting_key)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================
-- TABLE : rgpd_deletion_log — traçabilité effacements (art. 24)
-- Conserve un hash de l'email, jamais l'email en clair.
-- ============================================
CREATE TABLE IF NOT EXISTS rgpd_deletion_log (
    id INT AUTO_INCREMENT PRIMARY KEY,

    booking_id          INT          DEFAULT NULL COMMENT 'ID de la réservation supprimée',
    visitor_email_hash  VARCHAR(64)  NOT NULL     COMMENT 'SHA-256 de l''email (pas l''email en clair)',
    deletion_type       ENUM('client_request','admin_request','auto_purge','right_to_erasure') NOT NULL,
    data_deleted        TEXT         DEFAULT NULL COMMENT 'Liste des champs supprimés',
    data_retained       TEXT         DEFAULT NULL COMMENT 'Liste des champs conservés (ex: facturation)',

    requested_by ENUM('client','admin','cron') NOT NULL,
    ip_address   VARCHAR(45) DEFAULT NULL COMMENT 'IP du demandeur (si client)',

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_email_hash (visitor_email_hash),
    INDEX idx_created_at (created_at)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================
-- TABLE : purge_stats — métriques anonymisées des purges cron RGPD
-- ============================================
CREATE TABLE IF NOT EXISTS purge_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,

    purge_date       DATE NOT NULL,
    bookings_deleted INT  DEFAULT 0,
    ips_truncated    INT  DEFAULT 0,
    ips_deleted      INT  DEFAULT 0,
    period_start     DATE DEFAULT NULL,
    period_end       DATE DEFAULT NULL,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    UNIQUE INDEX idx_purge_date (purge_date)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================
-- TABLE : admin_login_attempts — rate limit login admin (Lot 2)
-- 5 essais / 15 min par IP ; fenêtre glissante.
-- ============================================
CREATE TABLE IF NOT EXISTS admin_login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,

    ip_address   VARCHAR(45) NOT NULL,
    success      TINYINT(1)  NOT NULL DEFAULT 0,
    attempted_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_ip_time   (ip_address, attempted_at),
    INDEX idx_attempted (attempted_at)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
