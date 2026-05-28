-- ============================================
-- MIGRATION RGPD : Tables de conformité
-- ============================================
-- Version : 2.3.0
-- À exécuter sur la base existante
-- ============================================

-- Table de log des demandes de suppression RGPD (accountability, art. 24)
-- Conserve la trace des demandes d'effacement pour prouver la conformité
CREATE TABLE IF NOT EXISTS rgpd_deletion_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Données minimales pour la traçabilité
    booking_id INT DEFAULT NULL COMMENT 'ID de la réservation supprimée',
    visitor_email_hash VARCHAR(64) NOT NULL COMMENT 'SHA-256 de l''email (pas l''email en clair)',
    deletion_type ENUM('client_request', 'admin_request', 'auto_purge', 'right_to_erasure') NOT NULL,
    data_deleted TEXT DEFAULT NULL COMMENT 'Liste des champs supprimés',
    data_retained TEXT DEFAULT NULL COMMENT 'Liste des champs conservés (ex: facturation)',
    
    -- Métadonnées
    requested_by ENUM('client', 'admin', 'cron') NOT NULL,
    ip_address VARCHAR(45) DEFAULT NULL COMMENT 'IP du demandeur (si client)',
    
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_email_hash (visitor_email_hash),
    INDEX idx_created_at (created_at)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table optionnelle de stats de purge (pour conserver des métriques anonymisées)
CREATE TABLE IF NOT EXISTS purge_stats (
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
