START TRANSACTION;
SET FOREIGN_KEY_CHECKS = 0;

-- Clean (avec nouvelles tables)
DELETE FROM credit_transactions;
DELETE FROM credit_movements;
DELETE FROM contact_messages;

DELETE FROM trip_feedbacks;
DELETE FROM trip_participants;
DELETE FROM trips;
DELETE FROM vehicles;
DELETE FROM users;

-- =========================
-- USERS (credits ajoutés)
-- =========================
-- Logique crédits choisie :
-- - user 1 reçoit 50 crédits (movement) puis paie 20 => reste 30
-- - user 2 reçoit 20 crédits (movement) puis paie 20 puis est remboursée 20 => reste 20
INSERT INTO users (id, full_name, email, password_hash, created_at, rating, credits, role) VALUES
(1, 'florence françois Montaigne', 'montaigneflo@gmail.com',
 '$2y$10$cDKLaKQOZLnDcKwo/rjHaeYxZbRKAV1I/3ud5L33.BdN/tY2m83gC',
 '2025-11-15 19:08:24', NULL, 30.00, 'user'),

(2, 'marie-lise HUYNH', 'marielise.huynh@gmail.com',
 '$2y$10$7gs6u5yhbkKMzrsq6yx7Buo/kRii4.VBw6prigudXgeMMtsAsACHG',
 '2025-11-17 16:40:24', NULL, 20.00, 'user'),

(5, 'admin ecoride', 'admin@ecoride.fr',
 '$2y$10$swbvehL9GHNlXqU3cFACTOWvmfz8DcTMz/O2M2.CmplBdOrNlnh9O',
 '2025-11-28 19:31:57', NULL, 999.00, 'admin'),

(6, 'employee ecoride', 'employee@ecoride.fr',
 '$2y$10$CkYgu5Q.nm7m660RYuw9DuoNcg9wMk/.h/Q/kXZ4nQyTyIn9PWKKa',
 '2025-12-04 12:55:02', NULL, 50.00, 'employee');

ALTER TABLE users AUTO_INCREMENT = 12;

-- =========================
-- VEHICLES
-- =========================
INSERT INTO vehicles (id, user_id, brand, model, color, plate, seats, created_at) VALUES
(1, 1, 'DS', 'DS 3', 'gris', 'GD-747-SP', 4, '2025-11-15 19:10:00'),
(2, 2, 'Mini', 'Cooper', 'noir', 'ED-407-GK', 4, '2025-11-17 16:45:00');

ALTER TABLE vehicles AUTO_INCREMENT = 3;

-- =========================
-- TRIPS
-- ⚠️ cohérence : si is_canceled=1 => status='canceled'
-- =========================
INSERT INTO trips (
  id, driver_id, origin_city, dest_city, price, total_seats, available_seats,
  departure_datetime, arrival_datetime, note, eco, car, plate_display,
  smoker, pets, music, quiet,
  is_canceled, is_started, is_finished, created_at,
  status, started_at, finished_at
) VALUES
(15, 1, 'pontault combault', 'NYONS', 20, 2, 1,
 '2025-11-22 12:00:00', '2025-11-22 14:00:00', '', 0, 'ds', 'GD747SP',
 0, 1, 1, 0,
 1, 0, 0, '2025-11-22 11:39:33',
 'canceled', NULL, NULL),

(16, 2, 'pontault combault', 'NYONS', 20, 2, 1,
 '2025-11-25 09:00:00', '2025-11-25 11:00:00', '', 0, 'mini', 'ed407gk',
 0, 0, 1, 0,
 0, 0, 0, '2025-11-24 18:42:15',
 'planned', NULL, NULL);

ALTER TABLE trips AUTO_INCREMENT = 17;

-- =========================
-- BOOKINGS (trip_participants)
-- Ajout payment_status + paid_at
-- =========================
INSERT INTO trip_participants (
  id, trip_id, user_id, seats, status, created_at,
  confirm_status, rating, comment, review_status,
  payment_status, paid_at
) VALUES
-- user 2 a réservé puis annulé => remboursé
(5, 15, 2, 1, 'canceled', '2025-11-22 11:41:26',
 'pending', NULL, NULL, 'pending',
 'refunded', NULL),

-- user 1 réservation acceptée => payée
(6, 16, 1, 1, 'accepted', '2025-11-24 18:44:01',
 'pending', NULL, NULL, 'pending',
 'paid', '2025-11-24 18:44:10');

ALTER TABLE trip_participants AUTO_INCREMENT = 7;

-- =========================
-- FEEDBACKS
-- =========================
INSERT INTO trip_feedbacks (
  id, trip_id, user_id, status, comment, created_at,
  moderated_by, moderated_at
) VALUES
(1, 15, 2, 'issue', 'nous sommes arrives en retard',
 '2025-11-24 11:06:09', NULL, NULL);

ALTER TABLE trip_feedbacks AUTO_INCREMENT = 5;

-- =========================
-- CREDIT MOVEMENTS (crédits initiaux / admin actions)
-- =========================
INSERT INTO credit_movements (id, user_id, amount, note, created_by, created_at) VALUES
(1, 1, 50.00, 'Recharge initiale (seed)', 5, '2025-11-15 19:12:00'),
(2, 2, 20.00, 'Recharge initiale (seed)', 5, '2025-11-17 16:47:00');

ALTER TABLE credit_movements AUTO_INCREMENT = 3;

-- =========================
-- CREDIT TRANSACTIONS (paiement / remboursement)
-- =========================
-- user 2 : booking trip 15 (débit) puis annulation (crédit)
INSERT INTO credit_transactions (id, user_id, trip_id, participant_id, amount, reason, created_at, meta) VALUES
(1, 2, 15, 5, -20.00, 'trip_booking', '2025-11-22 11:41:30', '{"method":"credits","seats":1,"price":20}'),
(2, 2, 15, 5,  20.00, 'trip_refund',  '2025-11-22 11:50:00', '{"cause":"canceled","method":"credits","seats":1,"price":20}');

-- user 1 : booking trip 16 (débit)
INSERT INTO credit_transactions (id, user_id, trip_id, participant_id, amount, reason, created_at, meta) VALUES
(3, 1, 16, 6, -20.00, 'trip_booking', '2025-11-24 18:44:10', '{"method":"credits","seats":1,"price":20}');

ALTER TABLE credit_transactions AUTO_INCREMENT = 4;

-- =========================
-- CONTACT (optionnel)
-- =========================
INSERT INTO contact_messages (id, name, email, message, created_at, is_read) VALUES
(1, 'Test User', 'test@mail.local', 'Bonjour, test contact.', '2025-11-24 10:00:00', 0);

ALTER TABLE contact_messages AUTO_INCREMENT = 2;

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;
