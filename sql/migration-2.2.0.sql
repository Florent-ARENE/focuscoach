-- ============================================
-- MIGRATION : Ajout token de gestion client
-- ============================================
-- Version : 2.2.0
-- À exécuter sur la base existante
-- ============================================

-- Ajouter la colonne manage_token
ALTER TABLE bookings 
ADD COLUMN manage_token VARCHAR(64) DEFAULT NULL AFTER google_calendar_id,
ADD UNIQUE INDEX idx_manage_token (manage_token);

-- Générer des tokens pour les réservations existantes
UPDATE bookings 
SET manage_token = SHA2(CONCAT(id, visitor_email, created_at, RAND()), 256)
WHERE manage_token IS NULL;
