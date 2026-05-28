-- ============================================
-- MIGRATION : Correction encodage HTML
-- ============================================
-- À exécuter sur la base existante pour nettoyer
-- les apostrophes encodées en &#039;
-- ============================================

-- Corriger le champ subject
UPDATE bookings 
SET subject = REPLACE(subject, '&#039;', '''')
WHERE subject LIKE '%&#039;%';

-- Corriger le champ message
UPDATE bookings 
SET message = REPLACE(message, '&#039;', '''')
WHERE message LIKE '%&#039;%';

-- Corriger le champ visitor_name
UPDATE bookings 
SET visitor_name = REPLACE(visitor_name, '&#039;', '''')
WHERE visitor_name LIKE '%&#039;%';

-- Corriger le champ visitor_organization
UPDATE bookings 
SET visitor_organization = REPLACE(visitor_organization, '&#039;', '''')
WHERE visitor_organization LIKE '%&#039;%';

-- Corriger aussi les guillemets doubles si nécessaire
UPDATE bookings SET subject = REPLACE(subject, '&quot;', '"') WHERE subject LIKE '%&quot;%';
UPDATE bookings SET message = REPLACE(message, '&quot;', '"') WHERE message LIKE '%&quot;%';
UPDATE bookings SET visitor_name = REPLACE(visitor_name, '&quot;', '"') WHERE visitor_name LIKE '%&quot;%';

-- Corriger les & encodés
UPDATE bookings SET subject = REPLACE(subject, '&amp;', '&') WHERE subject LIKE '%&amp;%';
UPDATE bookings SET message = REPLACE(message, '&amp;', '&') WHERE message LIKE '%&amp;%';

-- Corriger < et >
UPDATE bookings SET subject = REPLACE(subject, '&lt;', '<') WHERE subject LIKE '%&lt;%';
UPDATE bookings SET subject = REPLACE(subject, '&gt;', '>') WHERE subject LIKE '%&gt;%';
UPDATE bookings SET message = REPLACE(message, '&lt;', '<') WHERE message LIKE '%&lt;%';
UPDATE bookings SET message = REPLACE(message, '&gt;', '>') WHERE message LIKE '%&gt;%';
