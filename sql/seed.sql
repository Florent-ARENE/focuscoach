-- ============================================
-- FOCUS COACH — DONNÉES INITIALES (SEED)
-- ============================================
-- À importer APRÈS `schema.sql`, sur une base NEUVE.
--
-- - settings           : idempotent (`INSERT IGNORE`, clé unique sur
--                        setting_key). Rejouable sans risque ; ne réécrit
--                        pas une valeur déjà posée.
-- - availability       : motif par défaut (Lun-Ven, 09:00-12:00 +
--                        14:00-17:00 → 10 fenêtres). Idempotent via
--                        `UNIQUE KEY uq_availability_window`.
-- - services           : catalogue coaching v3.0.0 (10 prestations).
--                        Idempotent via `UNIQUE KEY uq_services_slug`.
--                        ⚠️ Aussi dupliqué dans `migration-3.0.0.sql`
--                        car les bases déjà déployées ne rejouent JAMAIS
--                        `seed.sql`.
-- - packages           : 3 forfaits. Idempotent via `uq_packages_slug`.
--                        Même règle de duplication dans la migration.
--
-- Identité : valeurs réelles Focus Coach. Si tu préfères le flux
-- « tout via l'admin », laisse-les vides — `cfgField()` affichera alors
-- les badges [À compléter dans Paramètres] sur les pages publiques.
-- ============================================

-- Charset de connexion (cf. schema.sql / CHANGELOG 2.6.7). SANS ce
-- SET NAMES, un import via un client défaut latin1/cp850 corrompt
-- toutes les chaînes accentuées (« Préparation » → « PrÃ©paration »,
-- « — » → « ÔÇö »). À garder en 1ʳᵉ ligne exécutable du fichier.
SET NAMES utf8mb4;

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
('google_calendar_id',      ''),
-- Validation des RDV : '1' = confirmation automatique (défaut), '0' = mise
-- en attente de validation admin. Piloté par Booking::initialStatus() et
-- appliqué IDENTIQUEMENT à tous les chemins (séance simple ET forfait).
('booking_auto_confirm',    '1');

-- ── Fenêtres d'ouverture hebdomadaires par défaut (v3.0.0) ──
-- Lundi(1) → Vendredi(5), matin 09:00-12:00 + après-midi 14:00-17:00.
-- 10 fenêtres (2 par jour × 5 jours). Les créneaux sont calculés au
-- runtime à partir de (fenêtre, service.duration, service.buffer,
-- BOOKING_STEP) — plus de slots pré-découpés.
INSERT IGNORE INTO availability (day_of_week, window_start, window_end, is_active) VALUES
(1,'09:00:00','12:00:00',1), (1,'14:00:00','17:00:00',1),
(2,'09:00:00','12:00:00',1), (2,'14:00:00','17:00:00',1),
(3,'09:00:00','12:00:00',1), (3,'14:00:00','17:00:00',1),
(4,'09:00:00','12:00:00',1), (4,'14:00:00','17:00:00',1),
(5,'09:00:00','12:00:00',1), (5,'14:00:00','17:00:00',1);

-- ── Catalogue de prestations (v3.0.0) ──
-- price_cents reflète la grille tarifaire Focus Coach (montants en
-- centimes d'euro). Source : Renaud, déjà publique dans
-- `config/config.local.php.template` (commentaires sous
-- `STRIPE_PRICES`). Cohérence à maintenir manuellement si la grille
-- évolue : éditer la table `services` via /admin (siteConfig() /
-- Settings n'intervient pas ici). La séance Découverte reste à 0
-- (gratuite par design → court-circuit Stripe).
-- stripe_price_id NULL → renseigné en admin après création du Price
-- côté Stripe Dashboard. health.php (§9) signale les services actifs
-- avec price_cents > 0 mais sans stripe_price_id quand les clés
-- Stripe sont présentes — tant que c'est le cas, le tunnel ne peut
-- pas démarrer pour ce service (refus côté §6 + signal).
-- Buffer par défaut : 15 min (éditable par prestation en admin).
INSERT IGNORE INTO services
    (slug, segment, name, duration_min, buffer_after_min, price_cents, is_active, sort_order) VALUES
    -- Sportifs
    ('sport-flash',         'sportif',    'Sport Flash',          45, 15,   8000, 1, 10),
    ('preparation-mentale', 'sportif',    'Préparation mentale',  60, 15,  10000, 1, 20),
    -- Dirigeants
    ('decision-express',    'dirigeant',  'Décision Express',     45, 15,  20000, 1, 30),
    ('manager-miroir',      'dirigeant',  'Manager Miroir',       60, 15,  25000, 1, 40),
    ('coaching-fondateur',  'dirigeant',  'Coaching Fondateur',   60, 15,  28000, 1, 50),
    ('duo-aligne',          'dirigeant',  'Duo Aligné',           90, 15,  35000, 1, 60),
    -- Particuliers
    ('declic-30',           'particulier','Déclic 30',            30, 15,   7000, 1, 70),
    ('reset-mental',        'particulier','Reset Mental',         60, 15,  10000, 1, 80),
    ('rebond',              'particulier','Rebond',               60, 15,  10000, 1, 90),
    -- Découverte (gratuite par design ; segment 'particulier' par
    -- défaut — éditable en admin)
    ('seance-decouverte',   'particulier','Séance découverte',    30, 15,      0, 1, 100);

-- ── Forfaits à jetons (v3.0.0) ──
-- Référence le service inclus via sous-requête sur slug (les services
-- ci-dessus viennent d'être insérés dans le même fichier).
-- price_cents = grille Focus Coach (cf. template).
-- Choix du service inclus pour Parcours Dirigeant / Forfait Élan :
-- arbitraire (Manager Miroir / Reset Mental) — modifiable en admin.
INSERT IGNORE INTO packages
    (slug, name, segment, service_id, sessions_count, price_cents, validity_days, is_active, sort_order) VALUES
    ('forfait-performance', 'Forfait Performance', 'sportif',
        (SELECT id FROM services WHERE slug = 'preparation-mentale' LIMIT 1),
        5,  42000, 365, 1, 10),
    ('parcours-dirigeant',  'Parcours Dirigeant',  'dirigeant',
        (SELECT id FROM services WHERE slug = 'manager-miroir' LIMIT 1),
        6, 150000, 365, 1, 20),
    ('forfait-elan',        'Forfait Élan',        'particulier',
        (SELECT id FROM services WHERE slug = 'reset-mental' LIMIT 1),
        5,  42000, 365, 1, 30);
