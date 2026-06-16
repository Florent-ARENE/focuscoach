-- ============================================
-- BASE DE DONNÉES : FOCUS COACH BOOKING SYSTEM
-- ============================================
-- Version : 2.4.1
-- Description : Schéma COMPLET (réimport propre from scratch).
--   Intègre toutes les migrations : manage_token (2.2.0), tables RGPD
--   rgpd_deletion_log + purge_stats (2.3.0), seed identité Focus Coach
--   (2.4.0) et clé admin_activity (2.4.1). Les fichiers migration-*.sql
--   ne servent qu'aux bases DÉJÀ déployées.
-- ============================================

CREATE DATABASE IF NOT EXISTS virtualburenaud 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE virtualburenaud;

-- ============================================
-- SUPPRESSION DES TABLES EXISTANTES
-- ============================================
DROP TABLE IF EXISTS bookings;
DROP TABLE IF EXISTS available_slots;
DROP TABLE IF EXISTS blocked_dates;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS rgpd_deletion_log;
DROP TABLE IF EXISTS purge_stats;
DROP TABLE IF EXISTS admin_login_attempts;

-- ============================================
-- TABLE : bookings
-- ============================================
CREATE TABLE bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Informations visiteur
    visitor_name VARCHAR(100) NOT NULL,
    visitor_email VARCHAR(150) NOT NULL,
    visitor_phone VARCHAR(20) DEFAULT NULL,
    visitor_organization VARCHAR(150) DEFAULT NULL,
    
    -- Créneau
    slot_date DATE NOT NULL,
    slot_time_start TIME NOT NULL,
    slot_time_end TIME NOT NULL,
    
    -- Demande
    service_type ENUM('diagnostic','optimisation','changement','pilotage','cohesion','certification','autre') DEFAULT 'autre',
    subject VARCHAR(255) DEFAULT NULL,
    message TEXT DEFAULT NULL,
    
    -- Statut
    status ENUM('pending', 'confirmed', 'cancelled', 'completed') DEFAULT 'pending',
    
    -- Google Calendar (synchronisation miroir)
    google_event_id VARCHAR(255) DEFAULT NULL,
    google_calendar_id VARCHAR(255) DEFAULT NULL,
    
    -- Token de gestion client (espace personnel)
    manage_token VARCHAR(64) DEFAULT NULL,
    
    -- Métadonnées
    admin_notes TEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    
    -- Timestamps
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    confirmed_at DATETIME DEFAULT NULL,
    cancelled_at DATETIME DEFAULT NULL,

    -- Clé d'unicité créneau actif (race double-booking)
    -- NULL si annulé/terminé → InnoDB autorise plusieurs NULL sur UNIQUE,
    -- donc un créneau libéré peut être re-réservé.
    active_key VARCHAR(20) GENERATED ALWAYS AS (
        CASE WHEN status IN ('pending','confirmed')
             THEN CONCAT(slot_date, '_', slot_time_start)
             ELSE NULL
        END
    ) STORED,

    -- Index
    INDEX idx_status (status),
    INDEX idx_slot_date (slot_date),
    INDEX idx_visitor_email (visitor_email),
    UNIQUE INDEX idx_manage_token (manage_token),
    UNIQUE KEY uq_active_slot (active_key)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE : available_slots
-- ============================================
CREATE TABLE available_slots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    day_of_week TINYINT NOT NULL COMMENT '1=Lundi, 7=Dimanche',
    time_start TIME NOT NULL,
    time_end TIME NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_day_active (day_of_week, is_active)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE : blocked_dates
-- ============================================
CREATE TABLE blocked_dates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    blocked_date DATE NOT NULL,
    reason VARCHAR(255) DEFAULT NULL,
    
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE INDEX idx_blocked_date (blocked_date)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE : settings
-- ============================================
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT DEFAULT NULL,
    
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE INDEX idx_setting_key (setting_key)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE : rgpd_deletion_log
-- Trace des demandes d'effacement (accountability, art. 24)
-- Conserve un hash de l'email — jamais l'email en clair
-- ============================================
CREATE TABLE rgpd_deletion_log (
    id INT AUTO_INCREMENT PRIMARY KEY,

    booking_id INT DEFAULT NULL COMMENT 'ID de la réservation supprimée',
    visitor_email_hash VARCHAR(64) NOT NULL COMMENT 'SHA-256 de l''email (pas l''email en clair)',
    deletion_type ENUM('client_request', 'admin_request', 'auto_purge', 'right_to_erasure') NOT NULL,
    data_deleted TEXT DEFAULT NULL COMMENT 'Liste des champs supprimés',
    data_retained TEXT DEFAULT NULL COMMENT 'Liste des champs conservés (ex: facturation)',

    requested_by ENUM('client', 'admin', 'cron') NOT NULL,
    ip_address VARCHAR(45) DEFAULT NULL COMMENT 'IP du demandeur (si client)',

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_email_hash (visitor_email_hash),
    INDEX idx_created_at (created_at)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE : purge_stats
-- Métriques anonymisées des purges automatiques (cron RGPD)
-- ============================================
CREATE TABLE purge_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,

    purge_date DATE NOT NULL,
    bookings_deleted INT DEFAULT 0,
    ips_truncated INT DEFAULT 0,
    ips_deleted INT DEFAULT 0,
    period_start DATE DEFAULT NULL,
    period_end DATE DEFAULT NULL,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    UNIQUE INDEX idx_purge_date (purge_date)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE : admin_login_attempts
-- Trace des tentatives de login admin (rate limit 5 essais / 15 min par IP)
-- ============================================
CREATE TABLE admin_login_attempts (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    ip_address   VARCHAR(45)  NOT NULL,
    success      TINYINT(1)   NOT NULL DEFAULT 0,
    attempted_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_ip_time   (ip_address, attempted_at),
    INDEX idx_attempted (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- DONNÉES INITIALES : Paramètres par défaut
-- ============================================
INSERT INTO settings (setting_key, setting_value) VALUES
('google_calendar_enabled', '0'),
('google_calendar_id', ''),
('admin_email', 'renaud@focuscoach.fr'),
('admin_name', 'Renaud'),
('admin_lastname', 'Heitz'),
('admin_phone', '06 60 49 17 18'),
('admin_address', 'Bordeaux, Nouvelle-Aquitaine'),
('admin_siret', '799 008 313 00022'),
('legal_status', 'Entrepreneur individuel — profession libérale non réglementée'),
('admin_activity', 'Coach, accompagnateur de transformations, préparateur mental et formateur'),
('site_name', 'Focus Coach');

-- ============================================
-- DONNÉES INITIALES : Créneaux par défaut
-- Lundi à Vendredi, 9h-12h et 14h-17h (30 min)
-- ============================================
INSERT INTO available_slots (day_of_week, time_start, time_end) VALUES
-- Lundi
(1, '09:00:00', '09:30:00'), (1, '09:30:00', '10:00:00'),
(1, '10:00:00', '10:30:00'), (1, '10:30:00', '11:00:00'),
(1, '11:00:00', '11:30:00'), (1, '11:30:00', '12:00:00'),
(1, '14:00:00', '14:30:00'), (1, '14:30:00', '15:00:00'),
(1, '15:00:00', '15:30:00'), (1, '15:30:00', '16:00:00'),
(1, '16:00:00', '16:30:00'), (1, '16:30:00', '17:00:00'),
-- Mardi
(2, '09:00:00', '09:30:00'), (2, '09:30:00', '10:00:00'),
(2, '10:00:00', '10:30:00'), (2, '10:30:00', '11:00:00'),
(2, '11:00:00', '11:30:00'), (2, '11:30:00', '12:00:00'),
(2, '14:00:00', '14:30:00'), (2, '14:30:00', '15:00:00'),
(2, '15:00:00', '15:30:00'), (2, '15:30:00', '16:00:00'),
(2, '16:00:00', '16:30:00'), (2, '16:30:00', '17:00:00'),
-- Mercredi
(3, '09:00:00', '09:30:00'), (3, '09:30:00', '10:00:00'),
(3, '10:00:00', '10:30:00'), (3, '10:30:00', '11:00:00'),
(3, '11:00:00', '11:30:00'), (3, '11:30:00', '12:00:00'),
(3, '14:00:00', '14:30:00'), (3, '14:30:00', '15:00:00'),
(3, '15:00:00', '15:30:00'), (3, '15:30:00', '16:00:00'),
(3, '16:00:00', '16:30:00'), (3, '16:30:00', '17:00:00'),
-- Jeudi
(4, '09:00:00', '09:30:00'), (4, '09:30:00', '10:00:00'),
(4, '10:00:00', '10:30:00'), (4, '10:30:00', '11:00:00'),
(4, '11:00:00', '11:30:00'), (4, '11:30:00', '12:00:00'),
(4, '14:00:00', '14:30:00'), (4, '14:30:00', '15:00:00'),
(4, '15:00:00', '15:30:00'), (4, '15:30:00', '16:00:00'),
(4, '16:00:00', '16:30:00'), (4, '16:30:00', '17:00:00'),
-- Vendredi
(5, '09:00:00', '09:30:00'), (5, '09:30:00', '10:00:00'),
(5, '10:00:00', '10:30:00'), (5, '10:30:00', '11:00:00'),
(5, '11:00:00', '11:30:00'), (5, '11:30:00', '12:00:00'),
(5, '14:00:00', '14:30:00'), (5, '14:30:00', '15:00:00'),
(5, '15:00:00', '15:30:00'), (5, '15:30:00', '16:00:00'),
(5, '16:00:00', '16:30:00'), (5, '16:30:00', '17:00:00');
