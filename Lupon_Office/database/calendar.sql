-- =========================================================
-- CALENDAR DATABASE MIGRATION
-- Combined from the supplied SQL migration files.
-- =========================================================

-- =========================================================
-- SOURCE: calendar_schema.sql
-- =========================================================

-- =========================================================
--  LUPON CALENDAR MODULE — ADD-ON SCHEMA
--  Verified against if0_41276334_barangay_db (uploaded dump,
--  generated Jul 04, 2026). `cases.id` and `officials.official_id`
--  are both plain signed int(11), so calendar_events.case_id and
--  .created_by below are declared the same way — no signedness
--  mismatch, no adjustment needed.
--
--  This is written with CREATE TABLE IF NOT EXISTS, so it's safe
--  to run once against your LIVE database — it only adds the two
--  new tables, it does not touch or recreate anything else.
--  (Don't use this to re-import your full dump — for that, use
--  the merged dump file instead, on a fresh/empty database.)
-- =========================================================

-- ---------------------------------------------------------
-- 1. Event types lookup table.
--    Kept as a table instead of an ENUM so new hearing/event
--    types can be added later (e.g. "Katarungang Pambarangay
--    Assembly") with a simple INSERT, not an ALTER TABLE.
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `event_types` (
  `id` tinyint(3) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `color_hex` varchar(7) NOT NULL DEFAULT '#0a2351',
  `sort_order` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `event_types` (`name`, `color_hex`, `sort_order`) VALUES
    ('Hearing',           '#0a2351', 1),
    ('Mediation',         '#3ba3ec', 2),
    ('Conciliation',      '#8e44ad', 3),
    ('Barangay Assembly', '#16a085', 4),
    ('Meeting',           '#f39c12', 5),
    ('Other',             '#7f8c8d', 6)
ON DUPLICATE KEY UPDATE name = name;

-- ---------------------------------------------------------
-- 2. Calendar events.
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `calendar_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(150) NOT NULL,
  `event_type_id` tinyint(3) NOT NULL,
  `case_id` int(11) DEFAULT NULL,
  `event_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time DEFAULT NULL,
  `location` varchar(150) NOT NULL DEFAULT 'Barangay Hall - Lupon Office',
  `description` text DEFAULT NULL,
  `status` enum('Scheduled','Completed','Cancelled','Postponed') NOT NULL DEFAULT 'Scheduled',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `event_type_id` (`event_type_id`),
  KEY `case_id` (`case_id`),
  KEY `created_by` (`created_by`),

  -- This is the index that makes the calendar scale: every
  -- page load queries a DATE RANGE (one visible month), never
  -- the whole table, no matter how many years of events pile up.
  KEY `idx_event_date` (`event_date`, `start_time`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Foreign keys added separately (matches the style of your own
-- dump, which adds resident_request's FK the same way at the end).
ALTER TABLE `calendar_events`
  ADD CONSTRAINT `fk_calevt_type`
    FOREIGN KEY (`event_type_id`) REFERENCES `event_types` (`id`)
    ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_calevt_case`
    FOREIGN KEY (`case_id`) REFERENCES `cases` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_calevt_official`
    FOREIGN KEY (`created_by`) REFERENCES `officials` (`official_id`)
    ON DELETE SET NULL ON UPDATE CASCADE;

-- NOTE: this assumes `audit_trail` already exists (it's used
-- elsewhere in the app, e.g. edit_case.php) with columns
-- (action, description, user). The calendar API endpoints reuse
-- that same table rather than creating a new one.
--
-- Also FYI, unrelated to this add-on: your dump's `audit_trail`
-- table has no PRIMARY KEY and its `id` column isn't
-- auto-incrementing — every existing row has id = 0. That's a
-- pre-existing quirk (not something this script touches), but it
-- means you currently can't reference or delete one specific log
-- entry. Not urgent, just flagging it since the calendar writes
-- to this table too.

-- =========================================================
-- SOURCE: add_case_history.sql
-- =========================================================

-- =========================================================
--  CASE HISTORY (field-level audit trail for the `cases` table)
--  Safe to run once against your live database.
--
--  This is intentionally a separate table from the existing
--  `audit_trail` table. `audit_trail` is a shared, free-text
--  activity log used across every module (residents, officials,
--  blotter, calendar, etc.) — repurposing it to hold structured
--  per-field diffs would mean every other module's rows carry
--  columns they never use. `case_history` is purpose-built
--  instead: one row per changed field, per save, on a case.
-- =========================================================

CREATE TABLE IF NOT EXISTS `case_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `case_id` int(11) NOT NULL,
  `field_name` varchar(100) NOT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `changed_by` varchar(100) NOT NULL DEFAULT 'Unknown',
  `changed_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_case_id_changed_at` (`case_id`, `changed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `case_history`
  ADD CONSTRAINT `fk_case_history_case`
    FOREIGN KEY (`case_id`) REFERENCES `cases` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE;

-- =========================================================
-- SOURCE: add_ongoing_status.sql
-- =========================================================

-- =========================================================
--  ADD "ONGOING" CASE STATUS
--  Safe to run once against your live database — this only
--  widens the existing enum, it doesn't touch any data.
--  Existing rows (all currently 'Pending', 'Settled', or 'CFA')
--  are unaffected; they simply gain the option of being set to
--  'Ongoing' going forward.
-- =========================================================

ALTER TABLE `cases`
  MODIFY `status` enum('Pending','Ongoing','Settled','CFA') DEFAULT 'Pending';

-- =========================================================
-- SOURCE: backfill_calendar_from_case_schedules.sql
-- =========================================================

-- =========================================================
--  BACKFILL: CREATE CALENDAR EVENTS FOR EXISTING SCHEDULES
--  One-time migration. Safe to run once against your live
--  database after the calendar_events/event_types tables exist.
--
--  Before this fix, cases.schedule_date could be set directly
--  from Case Management with no matching row in calendar_events,
--  which is exactly the inconsistency being resolved here. This
--  finds every case that currently has a schedule_date but no
--  corresponding calendar event, and creates one so the Calendar
--  actually reflects what Case Management already shows.
--
--  Running this twice is safe — the NOT EXISTS check means a
--  case that already got backfilled (or was already synced
--  through the app) won't get a duplicate event.
-- =========================================================

INSERT INTO calendar_events
    (title, event_type_id, case_id, event_date, start_time, location, status)
SELECT
    CONCAT('Hearing - ', c.case_no),
    (SELECT id FROM event_types WHERE name = 'Hearing' LIMIT 1),
    c.id,
    c.schedule_date,
    '09:00:00',
    'Barangay Hall - Lupon Office',
    'Scheduled'
FROM cases c
WHERE c.schedule_date IS NOT NULL
  AND c.schedule_date != '0000-00-00'
  AND NOT EXISTS (
      SELECT 1 FROM calendar_events e
      WHERE e.case_id = c.id
        AND e.status = 'Scheduled'
  );
