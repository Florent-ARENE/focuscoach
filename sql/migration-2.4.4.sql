-- ============================================
-- MIGRATION 2.4.4 — Lot 2 : auth admin durcie
-- ============================================
-- À appliquer sur une base déjà déployée. Le schéma complet
-- (sql/database.sql) intègre déjà ces changements pour les
-- nouvelles installations.
--
-- Ajoute la table de tracking des tentatives de login admin
-- pour le rate limit (5 essais / 15 min par IP).

CREATE TABLE IF NOT EXISTS admin_login_attempts (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    ip_address   VARCHAR(45)  NOT NULL,
    success      TINYINT(1)   NOT NULL DEFAULT 0,
    attempted_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_ip_time   (ip_address, attempted_at),
    INDEX idx_attempted (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
