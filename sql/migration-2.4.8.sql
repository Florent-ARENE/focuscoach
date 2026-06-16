-- ============================================
-- MIGRATION 2.4.8 — available_slots idempotent
-- ============================================
-- À appliquer sur une base déjà déployée. Le schéma complet
-- (sql/schema.sql) intègre déjà ces changements pour les
-- nouvelles installations.
--
-- Objectif : rendre `seed.sql` rejouable sans créer de doublons.
--
-- Étapes :
--   1. DÉDOUBLONNAGE : supprime les lignes en doublon sur
--      (day_of_week, time_start, time_end) en gardant l'id le plus
--      ancien. Safe : ne supprime rien si la table était déjà propre.
--   2. UNIQUE KEY : ajoute la contrainte qui garantit l'idempotence
--      future.
--
-- Si l'étape 2 échoue avec « Duplicate entry … for key 'uq_slot' »,
-- c'est que l'étape 1 n'a pas tout nettoyé — vérifier la liste des
-- doublons restants :
--   SELECT day_of_week, time_start, time_end, COUNT(*)
--   FROM available_slots
--   GROUP BY day_of_week, time_start, time_end
--   HAVING COUNT(*) > 1;
-- ============================================

-- 1. Dédoublonnage
DELETE a1 FROM available_slots a1
INNER JOIN available_slots a2
  ON a1.day_of_week = a2.day_of_week
 AND a1.time_start  = a2.time_start
 AND a1.time_end    = a2.time_end
 AND a1.id          > a2.id;

-- 2. Contrainte d'unicité
ALTER TABLE available_slots
    ADD UNIQUE KEY uq_slot (day_of_week, time_start, time_end);
