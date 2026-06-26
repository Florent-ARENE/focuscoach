-- ============================================
--  SEED DÉMO (DEV uniquement) — 5 réservations d'exemple
-- ============================================
--  ⚠️ NE PAS appliquer en PRODUCTION. `seed.sql` reste la source de
--  défauts SANS fausses données (AD-8) ; ce fichier sert à peupler
--  l'admin en local pour visualiser badges/actions/filtres.
--
--  Idempotent : purge d'abord les démos (email @demo.focuscoach.test),
--  puis réinsère. Rejouable sans doublon.
--
--  Statuts couverts : pending, confirmed (dont 1 AUJOURD'HUI),
--  cancelled, completed, pending_payment (hold actif).
--  `active_key` est GÉNÉRÉE (non insérée).
-- ============================================
SET NAMES utf8mb4;

DELETE FROM bookings WHERE visitor_email LIKE '%@demo.focuscoach.test';

INSERT INTO bookings
  (visitor_name, visitor_email, visitor_phone, visitor_organization,
   slot_date, slot_time_start, slot_time_end,
   service_id, duration_min, buffer_after_min,
   subject, message,
   status, payment_status, stripe_session_id, amount_paid_cents, payment_expires_at,
   manage_token, created_at, confirmed_at, cancelled_at)
VALUES
  -- 1) EN ATTENTE — Sport Flash (45 min), lundi prochain
  ('Camille Restout', 'camille@demo.focuscoach.test', '06 11 22 33 44', NULL,
   '2026-06-29', '10:00:00', '10:45:00',
   1, 45, 15,
   'Préparation compétition', 'Disponible plutôt en matinée si possible.',
   'pending', 'none', NULL, NULL, NULL,
   'a1b2c3d4e5f60718293a4b5c6d7e8f90112233445566778899aabbccddeeff01',
   '2026-06-25 09:12:00', NULL, NULL),

  -- 2) CONFIRMÉE — Coaching Fondateur (60 min), AUJOURD'HUI
  ('Julien Mercier', 'julien@demo.focuscoach.test', '06 55 66 77 88', 'NovaTech SAS',
   '2026-06-26', '14:00:00', '15:00:00',
   5, 60, 15,
   'Cadrage levée de fonds', NULL,
   'confirmed', 'none', NULL, NULL, NULL,
   'b2c3d4e5f60718293a4b5c6d7e8f90112233445566778899aabbccddeeff0102',
   '2026-06-23 16:40:00', '2026-06-24 08:05:00', NULL),

  -- 3) ANNULÉE — Préparation mentale (60 min)
  ('Sophie Bernard', 'sophie@demo.focuscoach.test', NULL, NULL,
   '2026-06-30', '11:00:00', '12:00:00',
   2, 60, 15,
   'Gestion du stress', 'Finalement indisponible, je recontacterai.',
   'cancelled', 'none', NULL, NULL, NULL,
   'c3d4e5f60718293a4b5c6d7e8f90112233445566778899aabbccddeeff010203',
   '2026-06-22 10:00:00', NULL, '2026-06-24 18:30:00'),

  -- 4) TERMINÉE — Déclic 30 (30 min), passée
  ('Marc Dubois', 'marc@demo.focuscoach.test', '07 12 34 56 78', 'Cabinet Dubois',
   '2026-06-20', '09:00:00', '09:30:00',
   7, 30, 15,
   'Premier échange', NULL,
   'completed', 'none', NULL, NULL, NULL,
   'd4e5f60718293a4b5c6d7e8f90112233445566778899aabbccddeeff01020304',
   '2026-06-15 14:20:00', '2026-06-16 09:00:00', NULL),

  -- 5) PAIEMENT EN COURS — Duo Aligné (90 min), hold actif
  ('Léa Fontaine', 'lea@demo.focuscoach.test', '06 98 76 54 32', NULL,
   '2026-07-03', '16:00:00', '17:30:00',
   6, 90, 15,
   'Alignement associés', 'Nous serons deux.',
   'pending_payment', 'pending', 'cs_test_demo_lea_5', 12000, '2026-06-26 23:59:00',
   'e5f60718293a4b5c6d7e8f90112233445566778899aabbccddeeff0102030405',
   '2026-06-26 12:30:00', NULL, NULL);
