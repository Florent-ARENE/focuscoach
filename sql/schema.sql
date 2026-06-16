-- ============================================
-- FOCUS COACH — schema.sql
-- ============================================
-- SCHÉMA DE RÉFÉRENCE. Source unique de la structure BDD.
--
--   ▸ Une seule règle d'or : à chaque modif de schéma, ce fichier est
--     MIS À JOUR dans le même commit que la migration correspondante.
--   ▸ Idempotent (CREATE TABLE IF NOT EXISTS, pas de DROP) — peut être
--     exécuté sur une base vide ou existante sans casser les données.
--   ▸ Ne contient AUCUNE donnée. Pour les valeurs par défaut
--     (settings + créneaux Lu-Ve), exécuter `sql/seed.sql` ensuite.
--
-- ─── INSTALLATION FRAÎCHE ───────────────────────────────────
--   mysql -u <user> -p <db> < sql/schema.sql
--   mysql -u <user> -p <db> < sql/seed.sql
--
-- ─── BASE DÉJÀ DÉPLOYÉE ─────────────────────────────────────
--   Ne pas relancer schema.sql — exécuter la migration de la
--   version cible (sql/migration-vX.Y.Z.sql) qui contient les
--   ALTER incrémentaux. Les migrations restent l'historique des
--   changements appliqués en prod.
--
-- ─── PRÉREQUIS ──────────────────────────────────────────────
--   MySQL ≥ 5.7.6 ou MariaDB ≥ 10.2 (colonnes générées STORED,
--   utilisées par bookings.active_key).
-- ============================================

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- ============================================
-- TABLE : bookings
-- Réservations clients (cœur du système).
-- ============================================
CREATE TABLE IF NOT EXISTS bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Visiteur
    visitor_name         VARCHAR(100) NOT NULL,
    visitor_email        VARCHAR(150) NOT NULL,
    visitor_phone        VARCHAR(20)  DEFAULT NULL,
    visitor_organization VARCHAR(150) DEFAULT NULL,

    -- Créneau
    slot_date       DATE NOT NULL,
    slot_time_start TIME NOT NULL,
    slot_time_end   TIME NOT NULL,

    -- Demande
    service_type ENUM(
        'diagnostic','optimisation','changement','pilotage',
        'cohesion','certification','autre'
    ) DEFAULT 'autre',
    subject VARCHAR(255) DEFAULT NULL,
    message TEXT         DEFAULT NULL,

    -- Statut
    status ENUM('pending','confirmed','cancelled','completed') DEFAULT 'pending',

    -- Google Calendar (miroir, synchronisation bidirectionnelle)
    google_event_id    VARCHAR(255) DEFAULT NULL,
    google_calendar_id VARCHAR(255) DEFAULT NULL,

    -- Espace client (lien à token pour reschedule/cancel)
    manage_token VARCHAR(64) DEFAULT NULL,

    -- Métadonnées
    admin_notes TEXT       DEFAULT NULL,
    ip_address  VARCHAR(45) DEFAULT NULL,
    user_agent  TEXT        DEFAULT NULL,

    -- Timestamps
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    confirmed_at DATETIME DEFAULT NULL,
    cancelled_at DATETIME DEFAULT NULL,

    -- Clé d'unicité créneau actif (Lot 3 v2.4.5).
    -- NULL si annulé/terminé : InnoDB autorise plusieurs NULL sur
    -- UNIQUE → un créneau libéré peut être re-réservé.
    active_key VARCHAR(20) GENERATED ALWAYS AS (
        CASE WHEN status IN ('pending','confirmed')
             THEN CONCAT(slot_date, '_', slot_time_start)
             ELSE NULL
        END
    ) STORED,

    INDEX idx_status        (status),
    INDEX idx_slot_date     (slot_date),
    INDEX idx_visitor_email (visitor_email),
    UNIQUE INDEX idx_manage_token (manage_token),
    UNIQUE KEY  uq_active_slot    (active_key)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE : available_slots
-- Créneaux types par jour de semaine (1=Lundi … 7=Dimanche).
-- ============================================
CREATE TABLE IF NOT EXISTS available_slots (
    id INT AUTO_INCREMENT PRIMARY KEY,

    day_of_week TINYINT NOT NULL COMMENT '1=Lundi, 7=Dimanche',
    time_start  TIME    NOT NULL,
    time_end    TIME    NOT NULL,
    is_active   BOOLEAN DEFAULT TRUE,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_day_active (day_of_week, is_active)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE : blocked_dates
-- Jours fériés / vacances / journées off (admin).
-- ============================================
CREATE TABLE IF NOT EXISTS blocked_dates (
    id INT AUTO_INCREMENT PRIMARY KEY,

    blocked_date DATE         NOT NULL,
    reason       VARCHAR(255) DEFAULT NULL,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    UNIQUE INDEX idx_blocked_date (blocked_date)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE : settings
-- Paramétrage clé/valeur (identité, contact, Google Calendar…).
-- Toutes les clés consommées par le code sont listées dans seed.sql.
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
-- TABLE : rgpd_deletion_log
-- Trace des effacements (accountability, art. 24 RGPD).
-- Stocke un SHA-256 de l'email, jamais l'email en clair.
-- ============================================
CREATE TABLE IF NOT EXISTS rgpd_deletion_log (
    id INT AUTO_INCREMENT PRIMARY KEY,

    booking_id          INT          DEFAULT NULL COMMENT 'ID de la réservation supprimée',
    visitor_email_hash  VARCHAR(64)  NOT NULL     COMMENT 'SHA-256 de l''email',
    deletion_type ENUM('client_request','admin_request','auto_purge','right_to_erasure') NOT NULL,
    data_deleted   TEXT DEFAULT NULL COMMENT 'Champs supprimés',
    data_retained  TEXT DEFAULT NULL COMMENT 'Champs conservés (ex: facturation)',

    requested_by ENUM('client','admin','cron') NOT NULL,
    ip_address   VARCHAR(45) DEFAULT NULL COMMENT 'IP du demandeur (si client)',

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_email_hash (visitor_email_hash),
    INDEX idx_created_at (created_at)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE : purge_stats
-- Métriques anonymisées des purges automatiques (cron RGPD).
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
-- TABLE : admin_login_attempts
-- Trace des tentatives de login admin (rate limit 5 essais / 15 min par IP).
-- Lot 2 v2.4.4.
-- ============================================
CREATE TABLE IF NOT EXISTS admin_login_attempts (
    id           INT          AUTO_INCREMENT PRIMARY KEY,
    ip_address   VARCHAR(45)  NOT NULL,
    success      TINYINT(1)   NOT NULL DEFAULT 0,
    attempted_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_ip_time   (ip_address, attempted_at),
    INDEX idx_attempted (attempted_at)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
