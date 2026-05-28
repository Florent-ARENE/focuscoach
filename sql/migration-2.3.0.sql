-- ============================================
-- MIGRATION v2.3.0 — Paramètres supplémentaires
-- ============================================
-- Ajoute les clés de configuration manquantes
-- pour la propagation dynamique dans les pages légales
-- 
-- Exécuter après migration-rgpd.sql
-- ============================================

-- Nouvelles clés settings (INSERT IGNORE = pas d'erreur si déjà présent)
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('admin_name', 'Renaud'),
('admin_lastname', ''),
('admin_phone', ''),
('admin_address', ''),
('admin_siret', ''),
('legal_status', '');
