-- Schema-only setup for Railway MySQL. No application records are included.
SET NAMES utf8mb4;

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `official_id` int(11) NOT NULL,
  `satellite_id` int(11) DEFAULT NULL,
  `date` date NOT NULL,
  `time_in` time DEFAULT NULL,
  `time_out` time DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Present',
  `work_hours` decimal(5,2) DEFAULT 0.00,
  `is_auto_timeout` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `audit_trail` (
  `id` int(11) NOT NULL,
  `action` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `user` varchar(100) DEFAULT NULL,
  `date_time` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `blotter` (
  `id` int(11) NOT NULL,
  `satellite_id` int(11) DEFAULT NULL,
  `complaint` varchar(255) DEFAULT NULL,
  `complainants` varchar(255) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `officer` varchar(100) DEFAULT NULL,
  `summary_remarks` text DEFAULT NULL,
  `response` text DEFAULT NULL,
  `status` enum('Pending','Rejected','Resolved','Transferred to Lupon') DEFAULT 'Pending',
  `resident_id` int(11) DEFAULT NULL,
  `digital_signature` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `borrowing` (
  `id` int(11) NOT NULL,
  `satellite_id` int(11) DEFAULT NULL,
  `borrower_name` varchar(100) DEFAULT NULL,
  `items` text DEFAULT NULL,
  `purpose` varchar(255) DEFAULT NULL,
  `time_borrowed` datetime DEFAULT NULL,
  `status` enum('Borrowed','Returned') DEFAULT 'Borrowed'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `calendar_events` (
  `id` int(11) NOT NULL,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `cases` (
  `id` int(11) NOT NULL,
  `satellite_id` int(11) DEFAULT NULL,
  `case_no` varchar(20) NOT NULL,
  `case_type` varchar(100) DEFAULT NULL,
  `complainant_name` varchar(255) DEFAULT NULL,
  `complainant_contact` varchar(20) DEFAULT NULL,
  `complainant_address` text DEFAULT NULL,
  `respondent_name` varchar(255) DEFAULT NULL,
  `respondent_contact` varchar(20) DEFAULT NULL,
  `respondent_address` text DEFAULT NULL,
  `status` enum('Pending','Ongoing','Settled','CFA') DEFAULT 'Pending',
  `date_filed` date DEFAULT NULL,
  `schedule_date` date DEFAULT NULL,
  `complaint_details` text DEFAULT NULL,
  `summary_discussions` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `case_history` (
  `id` int(11) NOT NULL,
  `case_id` int(11) NOT NULL,
  `field_name` varchar(100) NOT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `changed_by` varchar(100) NOT NULL DEFAULT 'Unknown',
  `changed_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `chatbot_logs` (
  `id` int(11) NOT NULL,
  `user_question` text NOT NULL,
  `matched_faq_id` int(11) DEFAULT NULL,
  `response_type` enum('faq','ai','failed') NOT NULL DEFAULT 'failed',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `departments` (
  `department_id` int(11) NOT NULL,
  `department_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `document_types` (
  `document_type_id` int(11) NOT NULL,
  `category` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `requirements` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `event_types` (
  `id` tinyint(3) NOT NULL,
  `name` varchar(50) NOT NULL,
  `color_hex` varchar(7) NOT NULL DEFAULT '#0a2351',
  `sort_order` tinyint(3) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `faq` (
  `id` int(11) NOT NULL,
  `question` text DEFAULT NULL,
  `answer` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `medicine_tracking` (
  `id` int(11) NOT NULL,
  `resident_id` int(11) NOT NULL,
  `satellite` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `medicine_name` varchar(150) DEFAULT NULL,
  `status` enum('Needs Maintenance','No Maintenance') DEFAULT 'Needs Maintenance',
  `last_check` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `satellite_id` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `contact` varchar(20) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `officials` (
  `official_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `position` varchar(100) NOT NULL,
  `department` enum('ADMIN','BPSO','CLEARANCE','LUPON') NOT NULL,
  `satellite_id` int(11) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `email` varchar(150) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `status` enum('Pending','Active','Inactive') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `proof_file` varchar(255) NOT NULL,
  `id_number` varchar(255) DEFAULT NULL,
  `picture_profile` varchar(255) DEFAULT NULL,
  `picture` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `date_added` timestamp NOT NULL DEFAULT current_timestamp(),
  `salary` decimal(10,2) NOT NULL DEFAULT 0.00,
  `is_locked` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `password_resets` (
  `reset_id` int(11) NOT NULL,
  `email` varchar(150) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `account_type` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `record_attachments` (
  `attachment_id` int(11) NOT NULL,
  `record_type` enum('request','blotter') NOT NULL,
  `record_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `residents` (
  `resident_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `sex` enum('Male','Female') DEFAULT NULL,
  `birthdate` date DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `email` varchar(150) NOT NULL,
  `address` text DEFAULT NULL,
  `satellite_id` int(11) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `proof_file` varchar(255) NOT NULL,
  `id_number` varchar(100) NOT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `is_locked` tinyint(1) NOT NULL DEFAULT 0,
  `failed_attempts` int(11) DEFAULT 0,
  `lock_until` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `resident_medicine` (
  `id` int(11) NOT NULL,
  `resident_id` int(11) DEFAULT NULL,
  `medicine_name` varchar(150) DEFAULT NULL,
  `dosage` varchar(100) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Pending',
  `submitted_at` datetime DEFAULT current_timestamp(),
  `digital_signature` varchar(255) DEFAULT NULL,
  `purpose` text DEFAULT NULL,
  `quantity_given` int(11) DEFAULT NULL,
  `quantity_remaining` int(11) DEFAULT NULL,
  `refill_threshold` int(11) DEFAULT NULL,
  `last_given` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `resident_request` (
  `request_id` int(11) NOT NULL,
  `resident_id` int(11) NOT NULL,
  `satellite_id` int(11) DEFAULT NULL,
  `document_type_id` int(11) NOT NULL,
  `fullname` varchar(255) NOT NULL,
  `birthdate` date NOT NULL,
  `purpose` varchar(255) NOT NULL,
  `phone` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `address` text NOT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'Pending',
  `submitted_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `reason_message` text DEFAULT NULL,
  `price` decimal(10,2) DEFAULT 0.00,
  `approved_at` datetime DEFAULT NULL,
  `payment_method` enum('GCash','Walk-in') DEFAULT 'Walk-in',
  `ref_number` varchar(50) DEFAULT NULL,
  `payment_receipt` varchar(255) DEFAULT NULL,
  `digital_signature` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `satellites` (
  `satellite_id` int(11) NOT NULL,
  `satellite_name` varchar(100) NOT NULL,
  `satellite_code` varchar(20) DEFAULT NULL,
  `address` varchar(255) NOT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `vehicle_logs` (
  `id` int(11) NOT NULL,
  `satellite_id` int(11) DEFAULT NULL,
  `plate_number` varchar(20) DEFAULT NULL,
  `driver_name` varchar(100) DEFAULT NULL,
  `vehicle_type` varchar(50) DEFAULT NULL,
  `patrol_area` varchar(100) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `status` enum('Inside','Left') DEFAULT 'Inside'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `official_id` (`official_id`),
  ADD KEY `date` (`date`);

ALTER TABLE `audit_trail`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `blotter`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_blotter_satellite` (`satellite_id`);

ALTER TABLE `borrowing`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `calendar_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `event_type_id` (`event_type_id`),
  ADD KEY `case_id` (`case_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_event_date` (`event_date`,`start_time`),
  ADD KEY `idx_status` (`status`);

ALTER TABLE `cases`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `case_no` (`case_no`),
  ADD KEY `fk_case_satellite` (`satellite_id`);

ALTER TABLE `case_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_case_id_changed_at` (`case_id`,`changed_at`);

ALTER TABLE `chatbot_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `matched_faq_id` (`matched_faq_id`),
  ADD KEY `response_type` (`response_type`),
  ADD KEY `created_at` (`created_at`);

ALTER TABLE `departments`
  ADD PRIMARY KEY (`department_id`),
  ADD UNIQUE KEY `department_name` (`department_name`);

ALTER TABLE `document_types`
  ADD PRIMARY KEY (`document_type_id`);

ALTER TABLE `event_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

ALTER TABLE `faq`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `medicine_tracking`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `officials`
  ADD PRIMARY KEY (`official_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `fk_official_satellite` (`satellite_id`);

ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`reset_id`);

ALTER TABLE `residents`
  ADD PRIMARY KEY (`resident_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `fk_resident_satellite` (`satellite_id`);

ALTER TABLE `resident_request`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `fk_request_satellite` (`satellite_id`);

ALTER TABLE `satellites`
  ADD PRIMARY KEY (`satellite_id`),
  ADD UNIQUE KEY `satellite_code` (`satellite_code`);

ALTER TABLE `vehicle_logs`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `blotter`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `audit_trail`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `borrowing`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `calendar_events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `cases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `case_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `chatbot_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `departments`
  MODIFY `department_id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `document_types`
  MODIFY `document_type_id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `event_types`
  MODIFY `id` tinyint(3) NOT NULL AUTO_INCREMENT;

ALTER TABLE `faq`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `officials`
  MODIFY `official_id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `password_resets`
  MODIFY `reset_id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `residents`
  MODIFY `resident_id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `resident_request`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `satellites`
  MODIFY `satellite_id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `vehicle_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `blotter`
  ADD CONSTRAINT `fk_blotter_satellite` FOREIGN KEY (`satellite_id`) REFERENCES `satellites` (`satellite_id`);

ALTER TABLE `calendar_events`
  ADD CONSTRAINT `fk_calevt_case` FOREIGN KEY (`case_id`) REFERENCES `cases` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_calevt_official` FOREIGN KEY (`created_by`) REFERENCES `officials` (`official_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_calevt_type` FOREIGN KEY (`event_type_id`) REFERENCES `event_types` (`id`) ON UPDATE CASCADE;

ALTER TABLE `cases`
  ADD CONSTRAINT `fk_case_satellite` FOREIGN KEY (`satellite_id`) REFERENCES `satellites` (`satellite_id`);

ALTER TABLE `case_history`
  ADD CONSTRAINT `fk_case_history_case` FOREIGN KEY (`case_id`) REFERENCES `cases` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `officials`
  ADD CONSTRAINT `fk_official_satellite` FOREIGN KEY (`satellite_id`) REFERENCES `satellites` (`satellite_id`);

ALTER TABLE `residents`
  ADD CONSTRAINT `fk_resident_satellite` FOREIGN KEY (`satellite_id`) REFERENCES `satellites` (`satellite_id`);

ALTER TABLE `resident_request`
  ADD CONSTRAINT `fk_request_satellite` FOREIGN KEY (`satellite_id`) REFERENCES `satellites` (`satellite_id`);
