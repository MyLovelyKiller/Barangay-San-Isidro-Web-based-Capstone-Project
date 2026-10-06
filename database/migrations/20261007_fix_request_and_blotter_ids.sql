-- One-time migration for existing databases. Take a tested backup first.
-- Duplicate legacy IDs are reassigned in table order; attachments tied to a
-- duplicated ID are copied to each corresponding new record ID.

CREATE TEMPORARY TABLE `_bms_request_attachment_backup` AS
SELECT attachment_id, record_id, file_path
FROM record_attachments
WHERE record_type = 'request';

CREATE TEMPORARY TABLE `_bms_blotter_attachment_backup` AS
SELECT attachment_id, record_id, file_path
FROM record_attachments
WHERE record_type = 'blotter';

ALTER TABLE resident_request
  ADD COLUMN `_migration_request_id` int(11) NOT NULL AUTO_INCREMENT UNIQUE;

INSERT INTO record_attachments (record_type, record_id, file_path)
SELECT 'request', rr.`_migration_request_id`, backup.file_path
FROM resident_request rr
JOIN `_bms_request_attachment_backup` backup
  ON backup.record_id = rr.request_id;

DELETE FROM record_attachments
WHERE attachment_id IN (
  SELECT attachment_id FROM `_bms_request_attachment_backup`
);

UPDATE resident_request
SET request_id = -`_migration_request_id`;

UPDATE resident_request
SET request_id = `_migration_request_id`;

ALTER TABLE resident_request
  DROP COLUMN `_migration_request_id`,
  ADD PRIMARY KEY (request_id),
  MODIFY request_id int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE blotter
  ADD COLUMN `_migration_blotter_id` int(11) NOT NULL AUTO_INCREMENT UNIQUE;

INSERT INTO record_attachments (record_type, record_id, file_path)
SELECT 'blotter', b.`_migration_blotter_id`, backup.file_path
FROM blotter b
JOIN `_bms_blotter_attachment_backup` backup
  ON backup.record_id = b.id;

DELETE FROM record_attachments
WHERE attachment_id IN (
  SELECT attachment_id FROM `_bms_blotter_attachment_backup`
);

UPDATE blotter
SET id = -`_migration_blotter_id`;

UPDATE blotter
SET id = `_migration_blotter_id`;

ALTER TABLE blotter
  DROP COLUMN `_migration_blotter_id`,
  ADD PRIMARY KEY (id),
  MODIFY id int(11) NOT NULL AUTO_INCREMENT;

DROP TEMPORARY TABLE `_bms_request_attachment_backup`;
DROP TEMPORARY TABLE `_bms_blotter_attachment_backup`;
