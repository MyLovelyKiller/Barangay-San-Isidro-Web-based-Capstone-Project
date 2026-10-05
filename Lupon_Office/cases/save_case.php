<?php
/* SQL Injection Defense:
   Using Prepared Statements ($stmt->prepare) and Parameter Binding ($stmt->bind_param)
   to separate the SQL logic from the user-provided data.
*/
ob_start();
session_start();
include(__DIR__ . '/../includes/db_connect.php');
require_once(__DIR__ . '/../includes/calendar_helpers.php');
require_once(__DIR__ . '/../includes/case_history_helpers.php');

/* =========================================================
   CSRF TOKEN
========================================================= */

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* ACCESS CONTROL */
if (
    !isset($_SESSION['official_id']) ||
    !isset($_SESSION['department']) ||
    strtoupper(trim($_SESSION['department'])) !== "LUPON"
) {
    header("Location: /BMS/CODES/login.php?error=1");
    exit();
}

$username = $_SESSION['username'] ?? 'Unknown';
$official_id = (int) $_SESSION['official_id'];

/* =====================================================
   GET LOGGED-IN LUPON OFFICER'S SATELLITE
   The satellite comes from the authenticated officer's
   account — NOT from the submitted form.
===================================================== */
$official_stmt = $conn->prepare("
    SELECT o.satellite_id, s.satellite_name
    FROM officials o
    LEFT JOIN satellites s ON s.satellite_id = o.satellite_id
    WHERE o.official_id = ?
      AND UPPER(TRIM(o.department)) = 'LUPON'
    LIMIT 1
");

if (!$official_stmt) {
    error_log("Official lookup prepare failed: " . $conn->error);
    header("Location: /BMS/Lupon_Office/cases/cases.php?msg=error");
    exit();
}

$official_stmt->bind_param("i", $official_id);
$official_stmt->execute();

$official_data = $official_stmt->get_result()->fetch_assoc();
$official_stmt->close();

if (!$official_data || empty($official_data['satellite_id'])) {
    error_log("Lupon officer {$official_id} has no assigned satellite.");
    header("Location: /BMS/Lupon_Office/cases/cases.php?msg=no_satellite");
    exit();
}

$satellite_id = (int) $official_data['satellite_id'];

/* =====================================================
   PROCESS NEW CASE
===================================================== */
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    /* =====================================================
       CSRF VALIDATION
    ===================================================== */

    $csrfToken = $_POST['csrf_token'] ?? '';

    if (
        empty($csrfToken) ||
        empty($_SESSION['csrf_token']) ||
        !hash_equals(
            $_SESSION['csrf_token'],
            $csrfToken
        )
    ) {
        http_response_code(403);
        exit("Invalid CSRF token.");
    }

    /* Collect Inputs */
    $case_type  = $_POST['case_type'] ?? 'Uncategorized';
    $date_filed = $_POST['date_filed'] ?? date('Y-m-d');
    $schedule   = $_POST['schedule'] ?? '';
    $status     = "Pending";

    $c_name     = $_POST['c_name'] ?? '';
    $c_contact  = $_POST['c_contact'] ?? '';
    $c_address  = $_POST['c_address'] ?? '';

    $r_name     = $_POST['r_name'] ?? '';
    $r_contact  = $_POST['r_contact'] ?? '';
    $r_address  = $_POST['r_address'] ?? '';

    $details    = $_POST['details'] ?? '';

    /* Establishes the same append-only journal format used later in Edit Case */
    $details = case_append_entry('', $details, $username);

    /* =====================================================
       INSERT CASE
       satellite_id is taken from the authenticated officer.
    ===================================================== */
    $sql = "INSERT INTO cases (
                case_no,
                case_type,
                complainant_name,
                complainant_contact,
                complainant_address,
                respondent_name,
                respondent_contact,
                respondent_address,
                status,
                date_filed,
                schedule_date,
                complaint_details,
                satellite_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    /* =====================================================
       GENERATE CASE NUMBER WITH RETRY ON COLLISION
    ===================================================== */
    $max_attempts = 5;
    $new_case_id = null;
    $case_no = null;

    for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {

        $case_no = date("Y") . "-" .
            str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            error_log(
                "Database Preparation Error: " . $conn->error
            );
            break;
        }

        /*
         * 12 strings + 1 integer
         *
         * s = case_no
         * s = case_type
         * s = complainant_name
         * s = complainant_contact
         * s = complainant_address
         * s = respondent_name
         * s = respondent_contact
         * s = respondent_address
         * s = status
         * s = date_filed
         * s = schedule
         * s = complaint_details
         * i = satellite_id
         */
        $stmt->bind_param(
            "ssssssssssssi",
            $case_no,
            $case_type,
            $c_name,
            $c_contact,
            $c_address,
            $r_name,
            $r_contact,
            $r_address,
            $status,
            $date_filed,
            $schedule,
            $details,
            $satellite_id
        );

        if ($stmt->execute()) {

            $new_case_id = $stmt->insert_id;

            $stmt->close();

            break;
        }

        /* errno 1062 = duplicate case number */
        $is_collision = $stmt->errno === 1062;

        if (!$is_collision) {

            error_log(
                "Database Execution Error: " . $stmt->error
            );

            $stmt->close();

            break;
        }

        $stmt->close();
    }

    /* =====================================================
       CASE CREATED SUCCESSFULLY
    ===================================================== */
    if ($new_case_id !== null) {

        /* If schedule was set, add it to Calendar */
        if (trim($schedule) !== '') {

            calendar_apply_case_schedule_change(
                $conn,
                $new_case_id,
                $case_no,
                $schedule,
                $official_id
            );
        }

        header(
            "Location: /BMS/Lupon_Office/cases/cases.php?msg=added"
        );
        exit();

    } else {

        header(
            "Location: /BMS/Lupon_Office/cases/cases.php?msg=error"
        );
        exit();
    }
}

$conn->close();