-- ============================================
-- FOCUS COACH — seed.sql
-- ============================================
-- DONNÉES PAR DÉFAUT. À exécuter après `sql/schema.sql` sur une
-- nouvelle installation. Sûr à ré-exécuter (idempotent) : utilise
-- INSERT ... ON DUPLICATE KEY UPDATE pour `settings` et
-- INSERT IGNORE pour `available_slots`.
--
--   ▸ Règle d'or : à chaque ajout d'une clé `settings` consommée
--     par le code (`Settings::get`, `siteConfig()`), une entrée
--     correspondante DOIT être ajoutée ici dans le même commit.
--
-- ─── INSTALLATION FRAÎCHE ───────────────────────────────────
--   mysql -u <user> -p <db> < sql/schema.sql
--   mysql -u <user> -p <db> < sql/seed.sql
--
-- ─── BASE EXISTANTE ─────────────────────────────────────────
--   Peut être ré-exécuté sans risque : ne touche aux valeurs déjà
--   personnalisées en admin que si la clé n'existait pas avant.
--   Si tu veux RÉINITIALISER une valeur, fais-le depuis
--   /admin → Paramètres (pas via ce fichier).
-- ============================================

SET NAMES utf8mb4;

-- ============================================
-- Paramètres par défaut (table `settings`)
-- ============================================
-- Identité / contact / mentions légales — tout est modifiable
-- depuis /admin → Paramètres et propagé partout via siteConfig().
INSERT INTO settings (setting_key, setting_value) VALUES
    -- Identité
    ('site_name',      'Focus Coach'),
    ('admin_name',     'Renaud'),
    ('admin_lastname', 'Heitz'),
    ('admin_activity', 'Coach, accompagnateur de transformations, préparateur mental et formateur'),

    -- Contact
    ('admin_email',    'renaud@focuscoach.fr'),
    ('admin_phone',    '06 60 49 17 18'),
    ('admin_address',  'Bordeaux, Nouvelle-Aquitaine'),

    -- Mentions légales
    ('admin_siret',    '799 008 313 00022'),
    ('legal_status',   'Entrepreneur individuel — profession libérale non réglementée'),

    -- Google Calendar (désactivé par défaut, activable depuis /admin)
    ('google_calendar_enabled', '0'),
    ('google_calendar_id',      '')

ON DUPLICATE KEY UPDATE setting_value = setting_value;
-- ↑ Ne touche PAS aux valeurs déjà personnalisées. Pour une vraie
-- réinitialisation, remplacer la ligne ci-dessus par
-- "ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)".

-- ============================================
-- Créneaux types (table `available_slots`)
-- Lundi → Vendredi, 9h-12h et 14h-17h, par tranches de 30 min.
-- ============================================
-- INSERT IGNORE évite les doublons si on re-seed une base
-- partiellement initialisée. Pas d'unicité (day, time_start) au
-- niveau du schéma → si tu veux vraiment garantir l'unicité,
-- ajouter un UNIQUE KEY (day_of_week, time_start, time_end) dans
-- schema.sql ET conserver l'INSERT IGNORE ici.
INSERT IGNORE INTO available_slots (day_of_week, time_start, time_end) VALUES
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
