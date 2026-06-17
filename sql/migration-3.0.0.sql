-- ============================================
-- MIGRATION 3.0.0 — Module Booking v3 (modèle de données)
-- ============================================
-- Chantier structurant : passe du module conseil (enum service_type
-- figé, créneaux à pas fixe) au module coaching catalogué (services
-- en CRUD admin avec durées variables et buffers, planning hebdo
-- récurrent + exceptions par date, paiement Stripe résilient,
-- forfaits à jetons).
--
-- Prompt source : Mission Claude Code — Module de réservation v3.
-- Spec détaillée : docs/booking-v3-spec.md (modèle, algo de calcul
-- des créneaux, machine à états bookings, machine à états packages).
--
-- ⚠️ MIGRATION STRUCTURANTE — SAUVEGARDE OBLIGATOIRE AVANT EXÉCUTION
-- (6 tables nouvelles + 9 colonnes ajoutées à `bookings` + redéfinition
-- de la colonne générée `active_key` + extension des ENUM `status`).
-- Non destructive sur le papier, mais une faute de frappe peut tout
-- casser et le déploiement OVH mutualisé n'a pas d'atomicité.
--
--   mysqldump --single-transaction --routines --triggers \
--     -u <user> -p <db> > backup-pre-3.0.0-$(date +%F).sql
--
-- Rollback = restore du dump + revert du code (les ALTER ci-dessous
-- sont écrits pour être idempotents au mieux mais ne génèrent pas un
-- DOWN automatique).
--
-- ============================================
-- Prérequis moteur
-- ============================================
--   - MySQL ≥ 5.7.6 OU MariaDB ≥ 10.2 :
--       · colonne générée `STORED` avec CASE,
--       · window functions (LAG, SUM OVER) — pour le regroupement
--         `available_slots` → `availability` (étape 7).
--   - InnoDB (foreign keys).
--   - Charset `utf8mb4_unicode_ci` (héritage des tables existantes).
-- ============================================
--
-- Cibles couvertes en une migration unique (et pas une migration par
-- module-checkpoint §3-§9) parce que le modèle de données doit être
-- en place AVANT que le code §4-§9 ne s'écrive contre lui. Les
-- ALTER suivants peuvent venir s'agréger ici si §4-§9 révèlent un
-- besoin résiduel, tant que la migration n'a pas tourné en prod.
--
-- ⚠️ Seed services/packages DUPLIQUÉ ci-dessous en INSERT IGNORE
-- (idempotent) ET ajouté à `seed.sql`. Règle posée en v2.4.7 : les
-- bases déjà déployées ne rejouent JAMAIS `seed.sql`, seules les
-- `migration-*.sql` tournent. Sans cette duplication, la prod
-- appliquerait la 3.0.0 avec un catalogue vide → tunnel cassé.
-- ============================================


-- ============================================
-- 1. services — catalogue prestations (remplace ENUM service_type)
-- ============================================
CREATE TABLE IF NOT EXISTS services (
    id INT AUTO_INCREMENT PRIMARY KEY,

    slug             VARCHAR(80)  NOT NULL,
    segment          ENUM('sportif','dirigeant','particulier') NOT NULL,
    name             VARCHAR(150) NOT NULL,
    description      TEXT         DEFAULT NULL,
    duration_min     INT          NOT NULL,
    buffer_after_min INT          NOT NULL DEFAULT 15
        COMMENT 'Temps de compte rendu post-séance — étend l''intervalle occupé pour le calcul des créneaux',
    price_cents      INT          NOT NULL DEFAULT 0
        COMMENT '0 → séance gratuite, court-circuit Stripe même quand activé',
    stripe_price_id  VARCHAR(80)  DEFAULT NULL
        COMMENT 'Renseigné en admin après création du Price côté Stripe Dashboard',
    is_active        TINYINT(1)   NOT NULL DEFAULT 1,
    sort_order       INT          NOT NULL DEFAULT 0,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_services_slug (slug),
    INDEX idx_services_segment_active (segment, is_active),
    INDEX idx_services_sort (sort_order)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================
-- 2. availability — planning récurrent hebdo (remplace available_slots)
-- ============================================
-- Une ligne = une fenêtre d'ouverture continue sur un jour donné.
-- Les créneaux sont CALCULÉS au runtime à partir de (fenêtre,
-- service.duration, service.buffer, BOOKING_STEP) — plus de slots
-- pré-découpés en base.
-- ============================================
CREATE TABLE IF NOT EXISTS availability (
    id INT AUTO_INCREMENT PRIMARY KEY,

    day_of_week  TINYINT NOT NULL COMMENT '1=Lundi, 7=Dimanche',
    window_start TIME    NOT NULL,
    window_end   TIME    NOT NULL,
    is_active    TINYINT(1) NOT NULL DEFAULT 1,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_availability_window (day_of_week, window_start, window_end),
    INDEX idx_availability_day_active (day_of_week, is_active)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================
-- 3. availability_exceptions — overrides par date (souplesse horaires)
-- ============================================
-- Prime sur availability[day_of_week] le jour donné :
--   - is_available = 0 → la journée est fermée (peu importe window_*),
--   - is_available = 1 → la (ou les) fenêtre(s) listée(s) s'applique(nt)
--     EN LIEU ET PLACE du récurrent.
-- ============================================
CREATE TABLE IF NOT EXISTS availability_exceptions (
    id INT AUTO_INCREMENT PRIMARY KEY,

    exception_date DATE       NOT NULL,
    is_available   TINYINT(1) NOT NULL DEFAULT 1
        COMMENT '0 = fermé toute la journée (window_* ignorés)',
    window_start   TIME       DEFAULT NULL,
    window_end     TIME       DEFAULT NULL,
    reason         VARCHAR(255) DEFAULT NULL,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    -- (date, window_start) unique : permet plusieurs fenêtres sur une
    -- même date d'exception (matin + après-midi décalés du récurrent).
    UNIQUE KEY uq_availability_exception (exception_date, window_start),
    INDEX idx_availability_exception_date (exception_date)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================
-- 4. packages — forfaits à jetons (catalogue, géré en admin)
-- ============================================
CREATE TABLE IF NOT EXISTS packages (
    id INT AUTO_INCREMENT PRIMARY KEY,

    slug            VARCHAR(80)  NOT NULL,
    name            VARCHAR(150) NOT NULL,
    segment         ENUM('sportif','dirigeant','particulier') NOT NULL,
    service_id      INT          NOT NULL
        COMMENT 'La prestation incluse dans le pack (toutes les séances seront de ce service)',
    sessions_count  INT          NOT NULL,
    price_cents     INT          NOT NULL DEFAULT 0,
    stripe_price_id VARCHAR(80)  DEFAULT NULL,
    validity_days   INT          NOT NULL DEFAULT 365,
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    sort_order      INT          NOT NULL DEFAULT 0,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_packages_slug (slug),
    INDEX idx_packages_segment_active (segment, is_active),
    CONSTRAINT fk_packages_service FOREIGN KEY (service_id)
        REFERENCES services(id) ON DELETE RESTRICT ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================
-- 5. package_purchases — achat d'un pack par un client (porte les jetons)
-- ============================================
-- Manage_token = jeton unique d'accès à l'espace pack
-- (`modules/booking/pack.php?token=…`). Réutilise le générateur du
-- manage_token des bookings (Helpers — à valider §7).
-- ============================================
CREATE TABLE IF NOT EXISTS package_purchases (
    id INT AUTO_INCREMENT PRIMARY KEY,

    package_id        INT          NOT NULL,
    client_name       VARCHAR(150) NOT NULL,
    client_email      VARCHAR(150) NOT NULL,
    credits_total     INT          NOT NULL,
    credits_used      INT          NOT NULL DEFAULT 0,
    manage_token      VARCHAR(64)  NOT NULL,
    status            ENUM('pending_payment','active','expired','exhausted') NOT NULL DEFAULT 'pending_payment',
    stripe_session_id VARCHAR(255) DEFAULT NULL,
    purchased_at      DATETIME     DEFAULT CURRENT_TIMESTAMP,
    expires_at        DATETIME     DEFAULT NULL
        COMMENT 'purchased_at + validity_days (calculé à la confirmation du paiement)',

    UNIQUE KEY uq_package_purchases_token (manage_token),
    INDEX idx_package_purchases_email (client_email),
    INDEX idx_package_purchases_status (status),
    CONSTRAINT fk_package_purchases_package FOREIGN KEY (package_id)
        REFERENCES packages(id) ON DELETE RESTRICT ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================
-- 6. stripe_events_processed — idempotence webhook Stripe (§6)
-- ============================================
-- En tête de api/stripe-webhook.php : INSERT IGNORE de l'event_id ;
-- si rowCount() == 0 → l'event a déjà été traité → 200 no-op.
-- ============================================
CREATE TABLE IF NOT EXISTS stripe_events_processed (
    event_id     VARCHAR(255) PRIMARY KEY,
    processed_at DATETIME DEFAULT CURRENT_TIMESTAMP

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================
-- 7. bookings — évolution (sans toucher les anciens enregistrements)
-- ============================================
-- Ordre IMPORTANT :
--   a. DROP de l'index UNIQUE puis de la colonne active_key (qui
--      référence le CASE sur status, qu'on va étendre juste après).
--   b. MODIFY status pour ajouter 'pending_payment' et 'expired'
--      (extension de l'ENUM, ordre conservé sur les valeurs
--      existantes → pas d'impact sur les ordinaux).
--   c. ADD COLUMNs nouvelles (service_id NULLABLE → les anciens
--      bookings restent NULL, voir note ci-dessous).
--   d. ADD la nouvelle active_key (CASE étendu pour inclure
--      'pending_payment' — sinon double-hold non arbitré, double
--      paiement possible) + UNIQUE.
--   e. ADD FKs (après que la colonne service_id existe).
-- ============================================
-- ⚠️ Anciens bookings : `service_id` reste NULL. Ces lignes portent
-- un `service_type` (ancienne offre conseil : 'diagnostic',
-- 'optimisation', …) qui ne correspond à AUCUNE prestation
-- coaching. PAS de tentative `UPDATE … JOIN services ON slug =
-- service_type` — ça ne matcherait rien (ou pire, donnerait
-- l'illusion d'un mapping). On garde service_type en archive.
-- ============================================

ALTER TABLE bookings
    DROP INDEX uq_active_slot,
    DROP COLUMN active_key;

ALTER TABLE bookings
    MODIFY COLUMN status ENUM(
        'pending','confirmed','cancelled','completed',
        'pending_payment','expired'
    ) NOT NULL DEFAULT 'pending';

ALTER TABLE bookings
    ADD COLUMN service_id                 INT          DEFAULT NULL,
    ADD COLUMN package_purchase_id        INT          DEFAULT NULL,
    ADD COLUMN duration_min               INT          DEFAULT NULL
        COMMENT 'Copié depuis services.duration_min à la création — fige la durée du booking',
    ADD COLUMN buffer_after_min           INT          DEFAULT NULL
        COMMENT 'Copié depuis services.buffer_after_min à la création',
    ADD COLUMN payment_status             ENUM('none','pending','paid','refunded') NOT NULL DEFAULT 'none',
    ADD COLUMN stripe_session_id          VARCHAR(255) DEFAULT NULL,
    ADD COLUMN amount_paid_cents          INT          DEFAULT NULL,
    ADD COLUMN payment_expires_at         DATETIME     DEFAULT NULL
        COMMENT 'Hold de 15 min sur le créneau pendant le tunnel Stripe (§6)',
    ADD COLUMN confirmation_email_sent_at DATETIME     DEFAULT NULL
        COMMENT 'Garde-fou anti double-email sur retry webhook Stripe (§6)';

-- Nouvelle active_key incluant 'pending_payment' (sinon deux holds
-- concurrents sur le même créneau ne sont pas arbitrés par l'UNIQUE
-- → double paiement possible). Cf. spec §3 du prompt v3.
ALTER TABLE bookings
    ADD COLUMN active_key VARCHAR(20) GENERATED ALWAYS AS (
        CASE WHEN status IN ('pending','confirmed','pending_payment')
             THEN CONCAT(slot_date, '_', slot_time_start)
             ELSE NULL
        END
    ) STORED,
    ADD UNIQUE KEY uq_active_slot (active_key);

-- FKs ajoutées en fin (après que les colonnes existent ET que les
-- tables référencées soient créées). ON DELETE SET NULL : un
-- service ou un package_purchase supprimé ne supprime pas les
-- bookings historiques (audit), il les détache.
ALTER TABLE bookings
    ADD CONSTRAINT fk_bookings_service
        FOREIGN KEY (service_id) REFERENCES services(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    ADD CONSTRAINT fk_bookings_package_purchase
        FOREIGN KEY (package_purchase_id) REFERENCES package_purchases(id)
        ON DELETE SET NULL ON UPDATE CASCADE;


-- ============================================
-- 8. Migration des données available_slots → availability
-- ============================================
-- Regroupement des créneaux consécutifs (`time_end[i] == time_start[i+1]`)
-- d'un même jour en une fenêtre unique. Un trou (pause déjeuner,
-- créneau retiré) coupe naturellement en deux fenêtres.
--
-- Sur les défauts inchangés (12 créneaux de 30 min/jour : 9-12 + 14-17
-- × 5 jours = 60 lignes) → RÉSULTAT ATTENDU : 10 fenêtres (matin +
-- après-midi × 5 jours, sur lundi-vendredi).
--
-- Si le résultat compté après cette étape n'est pas cohérent avec
-- l'inspection visuelle d'`available_slots`, NE PAS PASSER À L'ÉTAPE
-- SUIVANTE — restaurer le dump et revoir.
-- ============================================
INSERT IGNORE INTO availability (day_of_week, window_start, window_end, is_active)
SELECT day_of_week,
       MIN(time_start) AS window_start,
       MAX(time_end)   AS window_end,
       1
FROM (
    SELECT day_of_week,
           time_start,
           time_end,
           SUM(CASE
               WHEN LAG(time_end) OVER (PARTITION BY day_of_week ORDER BY time_start) = time_start
               THEN 0 ELSE 1
           END) OVER (PARTITION BY day_of_week ORDER BY time_start) AS grp
    FROM available_slots
    WHERE is_active = 1
) AS grouped
GROUP BY day_of_week, grp;

-- VÉRIF MANUELLE recommandée après cette étape :
--   SELECT day_of_week, COUNT(*) FROM availability GROUP BY day_of_week;
-- Sur les défauts : 2 fenêtres/jour × 5 jours = 10 lignes.
--
-- `available_slots` n'est PAS supprimée par cette migration
-- (réversibilité). Elle devient une table morte côté code après
-- bascule de Slot.php sur `availability` (§4). Une migration future
-- pourra la dropper après validation en prod.


-- ============================================
-- 9. Seed services — DUPLIQUÉ depuis seed.sql (idempotent)
-- ============================================
-- ⚠️ Bases déjà déployées ne rejouent PAS seed.sql. Sans cette
-- duplication, la prod arriverait en 3.0.0 avec un catalogue vide.
--
-- price_cents = grille tarifaire Focus Coach (montants en centimes,
-- alignés sur les commentaires `STRIPE_PRICES` de
-- `config.local.php.template`). La séance Découverte reste à 0
-- (gratuite par design — court-circuit Stripe).
-- stripe_price_id NULL → renseigné en admin après création du Price
-- côté Stripe Dashboard. health.php (§9) signalera les services
-- actifs avec price_cents > 0 mais sans stripe_price_id si les clés
-- Stripe sont présentes — tant que c'est le cas, le tunnel ne peut
-- pas démarrer pour ce service (refus côté §6 + signal santé).
-- ============================================
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
    -- Découverte (gratuite par design, segment 'particulier' par
    -- défaut — éditable en admin)
    ('seance-decouverte',   'particulier','Séance découverte',    30, 15,      0, 1, 100);


-- ============================================
-- 10. Seed packages — DUPLIQUÉ depuis seed.sql (idempotent)
-- ============================================
-- Référence le service inclus via sous-requête sur slug (les services
-- ci-dessus viennent d'être insérés). validity_days = 365 par défaut.
-- price_cents = grille Focus Coach.
-- Choix du service inclus pour Parcours Dirigeant / Forfait Élan :
-- arbitraire (Manager Miroir / Reset Mental) — modifiable en admin.
-- ============================================
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

-- ============================================
-- 11. Purge legacy — destructif (v2.6.0)
-- ============================================
-- ⚠️ DESTRUCTIF. À appliquer SEULEMENT en environnement de dev,
-- jamais sur une prod qui contiendrait des bookings de l'offre
-- conseil (ENUM service_type). Le user a confirmé qu'aucun
-- utilisateur n'est connecté et que la phase est purement dev :
-- on peut donc nettoyer le schéma au lieu d'empiler du legacy.
--
-- Les opérations ci-dessous :
--   - libèrent la table `available_slots` après migration vers
--     `availability` (étape 8 — la copie a déjà eu lieu),
--   - retirent la colonne `service_type` de `bookings` (l'ENUM de
--     l'offre conseil — la prestation v3 vit dans `service_id`
--     et son nom via JOIN sur `services`).
--
-- Si tu déploies cette migration sur une base de prod historique
-- qui contiendrait des bookings de l'offre conseil, supprime ces
-- DROP / ALTER avant exécution et garde l'archive — ils ne sont
-- pas indispensables au fonctionnement du tunnel v3.
-- ============================================

DROP TABLE IF EXISTS available_slots;

ALTER TABLE bookings DROP COLUMN service_type;


-- ============================================
-- Fin de la migration 3.0.0 — modèle de données.
-- ============================================
-- Tables suivantes touchées par les checkpoints §6-§9 (paiement,
-- packs, admin, health) : aucune nouvelle structure attendue, sauf
-- imprévu — auquel cas ajouter ici AVANT que la migration ne
-- tourne en prod.
-- ============================================
