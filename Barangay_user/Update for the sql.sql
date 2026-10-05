CREATE TABLE `blotter` (
  `id` int(11) NOT NULL,
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

CREATE TABLE `resident_request` (
  `request_id` int(11) NOT NULL,
  `resident_id` int(11) NOT NULL,
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

CREATE TABLE `record_attachments` (
  `attachment_id` int(11) NOT NULL,
  `record_type` enum('request','blotter') NOT NULL,
  `record_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL
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

CREATE TABLE `document_types` (
  `document_type_id` int(11) NOT NULL,
  `category` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `requirements` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `document_types` (`document_type_id`, `category`, `name`, `price`, `requirements`) VALUES
(1, 'Certification', 'Cedula', 0.00, 'Valid Government ID, Proof of Residency'),
(2, 'Certification', 'First Time Jobseeker', 0.00, 'Valid Government ID, Oath of Undertaking (First Time Jobseeker Act)'),
(3, 'Certification', 'Good Moral', 0.00, 'Valid Government ID, Barangay Clearance'),
(4, 'Certification', 'Indigency', 0.00, 'Valid Government ID, Certificate of Low Income / Case Study'),
(5, 'Certification', 'Residency', 0.00, 'Valid Government ID, Proof of Billing (Utility Bill under your name/address)'),
(6, 'Clearance Permit', 'Barangay Permit', 0.00, 'DTI/SEC Registration, Lease Contract or Land Title, Barangay Clearance'),
(7, 'Clearance Permit', 'Business Clearance', 0.00, 'DTI Registration, Mayor\'s Permit Application, Valid ID'),
(8, 'Clearance Permit', 'Barangay Clearance', 30.00, 'Valid Government ID, Proof of Residency, Cedula'),
(9, 'Special ID', 'Barangay ID', 60.00, '1x1 ID Picture, Proof of Residency, Valid ID'),
(10, 'Special ID', 'Solo Parent', 0.00, 'Birth Certificate of Child, Barangay Certification of Solo Parent status, Valid ID'),
(11, 'Special ID', 'Senior Citizen', 0.00, 'OSCA Booklet/ID application, Proof of Age (Birth Certificate), Valid ID'),
(12, 'Special ID', 'PWD', 0.00, 'Medical Certificate/Disability Assessment, 1x1 ID Picture, Valid ID'),
(15, 'Incident Report', 'Blotter', 0.00, 'Incident Report Form / Sworn Statement, Valid ID');