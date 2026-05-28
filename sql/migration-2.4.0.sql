-- ============================================
-- MIGRATION v2.4.0 — Identité "Focus Coach"
-- ============================================
-- Met à jour les paramètres d'identité/contact/mentions légales.
-- Toutes ces valeurs restent modifiables depuis /admin → Paramètres.
-- À exécuter une fois (idempotent : INSERT ... ON DUPLICATE KEY UPDATE).
-- ============================================
INSERT INTO settings (setting_key, setting_value) VALUES
    ('site_name',     'Focus Coach'),
    ('admin_name',    'Renaud'),
    ('admin_lastname','Heitz'),
    ('admin_email',   'renaud@focuscoach.fr'),
    ('admin_phone',   '06 60 49 17 18'),
    ('admin_address', 'Bordeaux, Nouvelle-Aquitaine'),
    ('admin_siret',   '799 008 313 00022'),
    ('legal_status',  'Entrepreneur individuel — profession libérale non réglementée')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
