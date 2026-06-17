-- ============================================
-- FOCUS COACH — RESET DE BASE DE DÉV
-- ============================================
-- ⚠️ DESTRUCTIF — réservé au DÉVELOPPEMENT.
-- Drop toutes les tables manipulées par le projet (v3.0.0 + tables
-- historiques) pour permettre un import propre :
--
--   mysql -u <user> -p <db_dev> < sql/reset-dev.sql
--   mysql -u <user> -p <db_dev> < sql/schema.sql
--   mysql -u <user> -p <db_dev> < sql/seed.sql
--
-- À NE JAMAIS appliquer en prod tant qu'un utilisateur réel a posé
-- un booking : `sql/schema.sql` reste, lui, non destructif
-- (`CREATE TABLE IF NOT EXISTS`, AD-5 du cadrage) — c'est ce fichier
-- de reset qui porte exclusivement la responsabilité de nettoyer.
--
-- Ordre INVERSE des dépendances Foreign Key :
--   bookings ──FK──► services
--                 └─► package_purchases ──FK──► packages ──FK──► services
--
--   1. bookings              (référence services + package_purchases)
--   2. package_purchases     (référence packages)
--   3. packages              (référence services)
--   4. services              (référencée, mais ses référenceurs sont
--                              désormais droppés)
--   5. availability_exceptions, availability, blocked_dates,
--      stripe_events_processed, settings, rgpd_deletion_log,
--      purge_stats, admin_login_attempts  (pas de FK entre elles)
--   6. available_slots       (table morte v2 — peut traîner sur une
--                              base partiellement migrée)
-- ============================================

-- 1. Tables avec FK sortantes (réordonner pour respecter les
--    dépendances : on drop d'abord ceux qui RÉFÉRENCENT).

DROP TABLE IF EXISTS bookings;
DROP TABLE IF EXISTS package_purchases;
DROP TABLE IF EXISTS packages;

-- 2. Table référencée (plus de référenceur après l'étape 1).

DROP TABLE IF EXISTS services;

-- 3. Tables indépendantes (Booking v3).

DROP TABLE IF EXISTS availability_exceptions;
DROP TABLE IF EXISTS availability;
DROP TABLE IF EXISTS stripe_events_processed;

-- 4. Tables historiques et techniques (pas de FK).

DROP TABLE IF EXISTS blocked_dates;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS rgpd_deletion_log;
DROP TABLE IF EXISTS purge_stats;
DROP TABLE IF EXISTS admin_login_attempts;

-- 5. Table héritée v2 (peut subsister sur une base partiellement
--    migrée depuis v2.4.x).

DROP TABLE IF EXISTS available_slots;

-- Fin du reset. Importer ensuite `schema.sql` puis `seed.sql`.
