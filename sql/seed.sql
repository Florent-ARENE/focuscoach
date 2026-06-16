-- ============================================
-- FOCUS COACH — DONNÉES INITIALES (SEED)
-- ============================================
-- À importer APRÈS `schema.sql`, sur une base NEUVE.
--
-- - settings    : idempotent (`INSERT IGNORE`, clé unique sur setting_key).
--                 Rejouable sans risque ; ne réécrit pas une valeur déjà posée.
-- - available_slots : motif par défaut (Lun-Ven, 9h-12h / 14h-17h, 30 min).
--                 idempotent depuis v2.4.8 (UNIQUE KEY `uq_slot` sur
--                 (day, time_start, time_end) + `INSERT IGNORE`). Rejouable
--                 sans risque de doublons.
--
-- Identité : valeurs réelles Focus Coach. Si tu préfères le flux
-- « tout via l'admin », laisse-les vides — `cfgField()` affichera alors
-- les badges [À compléter dans Paramètres] sur les pages publiques.
-- ============================================

-- ── Paramètres / identité ──
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('site_name',               'Focus Coach'),
('admin_name',              'Renaud'),
('admin_lastname',          'Heitz'),
('admin_email',             'renaud@focuscoach.fr'),
('admin_phone',             '06 60 49 17 18'),
('admin_address',           'Bordeaux, Nouvelle-Aquitaine'),
('admin_siret',             '799 008 313 00022'),
('legal_status',            'Entrepreneur individuel — profession libérale non réglementée'),
('admin_activity',          'Coach, accompagnateur de transformations, préparateur mental et formateur'),
('google_calendar_enabled', '0'),
('google_calendar_id',      '');

-- ── Créneaux hebdomadaires par défaut ──
-- Lundi(1) → Vendredi(5), matin 09:00-12:00 + après-midi 14:00-17:00, pas de 30 min.
INSERT IGNORE INTO available_slots (day_of_week, time_start, time_end) VALUES
-- Lundi
(1,'09:00:00','09:30:00'),(1,'09:30:00','10:00:00'),(1,'10:00:00','10:30:00'),
(1,'10:30:00','11:00:00'),(1,'11:00:00','11:30:00'),(1,'11:30:00','12:00:00'),
(1,'14:00:00','14:30:00'),(1,'14:30:00','15:00:00'),(1,'15:00:00','15:30:00'),
(1,'15:30:00','16:00:00'),(1,'16:00:00','16:30:00'),(1,'16:30:00','17:00:00'),
-- Mardi
(2,'09:00:00','09:30:00'),(2,'09:30:00','10:00:00'),(2,'10:00:00','10:30:00'),
(2,'10:30:00','11:00:00'),(2,'11:00:00','11:30:00'),(2,'11:30:00','12:00:00'),
(2,'14:00:00','14:30:00'),(2,'14:30:00','15:00:00'),(2,'15:00:00','15:30:00'),
(2,'15:30:00','16:00:00'),(2,'16:00:00','16:30:00'),(2,'16:30:00','17:00:00'),
-- Mercredi
(3,'09:00:00','09:30:00'),(3,'09:30:00','10:00:00'),(3,'10:00:00','10:30:00'),
(3,'10:30:00','11:00:00'),(3,'11:00:00','11:30:00'),(3,'11:30:00','12:00:00'),
(3,'14:00:00','14:30:00'),(3,'14:30:00','15:00:00'),(3,'15:00:00','15:30:00'),
(3,'15:30:00','16:00:00'),(3,'16:00:00','16:30:00'),(3,'16:30:00','17:00:00'),
-- Jeudi
(4,'09:00:00','09:30:00'),(4,'09:30:00','10:00:00'),(4,'10:00:00','10:30:00'),
(4,'10:30:00','11:00:00'),(4,'11:00:00','11:30:00'),(4,'11:30:00','12:00:00'),
(4,'14:00:00','14:30:00'),(4,'14:30:00','15:00:00'),(4,'15:00:00','15:30:00'),
(4,'15:30:00','16:00:00'),(4,'16:00:00','16:30:00'),(4,'16:30:00','17:00:00'),
-- Vendredi
(5,'09:00:00','09:30:00'),(5,'09:30:00','10:00:00'),(5,'10:00:00','10:30:00'),
(5,'10:30:00','11:00:00'),(5,'11:00:00','11:30:00'),(5,'11:30:00','12:00:00'),
(5,'14:00:00','14:30:00'),(5,'14:30:00','15:00:00'),(5,'15:00:00','15:30:00'),
(5,'15:30:00','16:00:00'),(5,'16:00:00','16:30:00'),(5,'16:30:00','17:00:00');
