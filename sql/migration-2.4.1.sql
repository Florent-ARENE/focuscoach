-- ============================================
-- MIGRATION v2.4.1 — Activité paramétrable + nettoyage marque
-- ============================================
-- Ajoute la clé `admin_activity` (affichée dans la politique de
-- confidentialité — responsable du traitement). Remplace l'ancienne
-- activité codée en dur « Qualiticien — Performance des Systèmes
-- Collectifs de Travail ». Modifiable depuis /admin → Paramètres.
-- À exécuter une fois (idempotent : INSERT ... ON DUPLICATE KEY UPDATE).
-- ============================================
INSERT INTO settings (setting_key, setting_value) VALUES
    ('admin_activity', 'Coach, accompagnateur de transformations, préparateur mental et formateur')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
