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
--     `bookings.active_key`). Sur MariaDB 10.1, le CREATE échoue
--     silencieusement côté client sur ce point — vérifie ton moteur
--     avant import.
-- ============================================

-- Sur OVH mutualisé la base est pré-créée et son nom est imposé :
-- importer directement dans la base fournie. Décommenter uniquement
-- pour un environnement local où l'on crée la base soi-même.
-- CREATE DATABASE IF NOT EXISTS virtualburenaud
--   CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE virtualburenaud;

-- ============================================
-- TABLE : bookings — réservations
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

    -- Demande
    -- NB: l'enum service_type reflète l'offre conseil actuelle. À revisiter
    -- quand l'offre coaching (sportifs/dirigeants/particuliers) + Stripe
    -- sera implémentée — chantier séparé.
    service_type ENUM('diagnostic','optimisation','changement','pilotage','cohesion','certification','autre') DEFAULT 'autre',
    subject      VARCHAR(255) DEFAULT NULL,
    message      TEXT         DEFAULT NULL,

    -- Statut
    status ENUM('pending','confirmed','cancelled','completed') DEFAULT 'pending',

    -- Google Calendar (synchronisation miroir)
    google_event_id    VARCHAR(255) DEFAULT NULL,
    google_calendar_id VARCHAR(255) DEFAULT NULL,

    -- Token de gestion client (espace personnel)
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

    -- Clé d'unicité du créneau ACTIF (anti race double-booking, Lot 3).
    -- NULL pour cancelled/completed → InnoDB autorise plusieurs NULL sur
    -- un UNIQUE, donc un créneau libéré redevient réservable.
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
    UNIQUE KEY   uq_active_slot   (active_key)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE : available_slots — règles de disponibilité hebdo
-- ============================================
CREATE TABLE IF NOT EXISTS available_slots (
    id INT AUTO_INCREMENT PRIMARY KEY,

    day_of_week TINYINT NOT NULL COMMENT '1=Lundi, 7=Dimanche',
    time_start  TIME    NOT NULL,
    time_end    TIME    NOT NULL,
    is_active   BOOLEAN DEFAULT TRUE,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_day_active (day_of_week, is_active),
    -- Unicité (jour, début, fin) : rend le seed idempotent et empêche
    -- les doublons silencieux si quelqu'un rejoue `seed.sql` par erreur.
    UNIQUE KEY uq_slot (day_of_week, time_start, time_end)

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
