-- ============================================
-- MIGRATION 2.4.5 — Lot 3 : intégrité
-- ============================================
-- À appliquer sur une base déjà déployée. Le schéma complet
-- (sql/database.sql) intègre déjà ces changements pour les
-- nouvelles installations.
--
-- 1. `bookings.active_key` (colonne générée + UNIQUE) — ferme la race
--    isSlotTaken() → INSERT non atomique : deux requêtes concurrentes
--    pouvaient passer la vérif PHP puis insérer deux lignes sur le
--    même créneau. Désormais l'UNIQUE arbitre côté SQL (23000) et le
--    code traduit en message UX cohérent.
--
--    NULL pour les statuts cancelled/completed : InnoDB autorise
--    plusieurs NULL sur une colonne UNIQUE — un créneau annulé puis
--    re-réservé reste OK.

ALTER TABLE bookings
    ADD COLUMN active_key VARCHAR(20) GENERATED ALWAYS AS (
        CASE WHEN status IN ('pending','confirmed')
             THEN CONCAT(slot_date, '_', slot_time_start)
             ELSE NULL
        END
    ) STORED,
    ADD UNIQUE KEY uq_active_slot (active_key);
