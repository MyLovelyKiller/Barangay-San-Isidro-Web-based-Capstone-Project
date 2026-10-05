<?php
ob_start();
session_start();

require_once '../BACKEND/db_connect.php';


/* =========================================================
   CHECK IF USER IS LOGGED IN
   ========================================================= */

if (!isset($_SESSION['official_id'])) {
    header("Location: /BMS/CODES/login.php?error=1");
    exit();
}

$official_id = (int)$_SESSION['official_id'];

if ($official_id <= 0) {
    header("Location: /BMS/CODES/login.php?error=1");
    exit();
}


/* =========================================================
   DEFAULT DASHBOARD VALUES
   ========================================================= */

$user_full_name = "Officer";
$satellite_id = 0;

$pending_count = 0;
$approved_count = 0;
$rejected_count = 0;
$total_requests = 0;

$recent_requests = [];


/* =========================================================
   VERIFY OFFICIAL AND GET ASSIGNED SATELLITE
   ========================================================= */

$officer_query = "
    SELECT
        official_id,
        name,
        department,
        satellite_id
    FROM officials
    WHERE official_id = ?
      AND department = 'CLEARANCE'
    LIMIT 1
";

$stmt = $conn->prepare($officer_query);

if (!$stmt) {
    error_log(
        "Clearance dashboard officer query prepare failed: " .
        $conn->error
    );

    http_response_code(500);
    exit("Unable to load dashboard.");
}

$stmt->bind_param("i", $official_id);

if (!$stmt->execute()) {
    error_log(
        "Clearance dashboard officer query execute failed: " .
        $stmt->error
    );

    $stmt->close();

    http_response_code(500);
    exit("Unable to load dashboard.");
}

$result = $stmt->get_result();

if (!$result || !$row = $result->fetch_assoc()) {
    $stmt->close();

    header("Location: /BMS/CODES/login.php?error=1");
    exit();
}

$user_full_name = $row['name'] ?? "Officer";
$satellite_id = (int)($row['satellite_id'] ?? 0);

$stmt->close();


/* =========================================================
   MAKE SURE OFFICER HAS AN ASSIGNED SATELLITE
   ========================================================= */

if ($satellite_id <= 0) {
    http_response_code(403);
    exit(
        "Your Clearance account does not have an assigned " .
        "satellite. Please contact the administrator."
    );
}


/* =========================================================
   DASHBOARD COUNTS
   ALL COUNTS ARE LIMITED TO THIS SATELLITE
   ========================================================= */


/* ---------------------------------------------------------
   PENDING
   --------------------------------------------------------- */

$stmt = $conn->prepare("
    SELECT COUNT(*) AS count
    FROM resident_request
    WHERE satellite_id = ?
      AND LOWER(TRIM(status)) = 'pending'
");

if (!$stmt) {
    error_log(
        "Clearance dashboard pending query prepare failed: " .
        $conn->error
    );

    http_response_code(500);
    exit("Unable to load dashboard.");
}

$stmt->bind_param("i", $satellite_id);

if (!$stmt->execute()) {
    error_log(
        "Clearance dashboard pending query execute failed: " .
        $stmt->error
    );

    $stmt->close();

    http_response_code(500);
    exit("Unable to load dashboard.");
}

$countResult = $stmt->get_result();

if ($countResult) {
    $countRow = $countResult->fetch_assoc();

    if ($countRow) {
        $pending_count = (int)$countRow['count'];
    }
}

$stmt->close();


/* ---------------------------------------------------------
   APPROVED
   --------------------------------------------------------- */

$stmt = $conn->prepare("
    SELECT COUNT(*) AS count
    FROM resident_request
    WHERE satellite_id = ?
      AND LOWER(TRIM(status)) = 'approved'
");

if (!$stmt) {
    error_log(
        "Clearance dashboard approved query prepare failed: " .
        $conn->error
    );

    http_response_code(500);
    exit("Unable to load dashboard.");
}

$stmt->bind_param("i", $satellite_id);

if (!$stmt->execute()) {
    error_log(
        "Clearance dashboard approved query execute failed: " .
        $stmt->error
    );

    $stmt->close();

    http_response_code(500);
    exit("Unable to load dashboard.");
}

$countResult = $stmt->get_result();

if ($countResult) {
    $countRow = $countResult->fetch_assoc();

    if ($countRow) {
        $approved_count = (int)$countRow['count'];
    }
}

$stmt->close();


/* ---------------------------------------------------------
   REJECTED
   --------------------------------------------------------- */

$stmt = $conn->prepare("
    SELECT COUNT(*) AS count
    FROM resident_request
    WHERE satellite_id = ?
      AND LOWER(TRIM(status)) = 'rejected'
");

if (!$stmt) {
    error_log(
        "Clearance dashboard rejected query prepare failed: " .
        $conn->error
    );

    http_response_code(500);
    exit("Unable to load dashboard.");
}

$stmt->bind_param("i", $satellite_id);

if (!$stmt->execute()) {
    error_log(
        "Clearance dashboard rejected query execute failed: " .
        $stmt->error
    );

    $stmt->close();

    http_response_code(500);
    exit("Unable to load dashboard.");
}

$countResult = $stmt->get_result();

if ($countResult) {
    $countRow = $countResult->fetch_assoc();

    if ($countRow) {
        $rejected_count = (int)$countRow['count'];
    }
}

$stmt->close();


/* ---------------------------------------------------------
   TOTAL REQUESTS
   --------------------------------------------------------- */

$stmt = $conn->prepare("
    SELECT COUNT(*) AS count
    FROM resident_request
    WHERE satellite_id = ?
");

if (!$stmt) {
    error_log(
        "Clearance dashboard total query prepare failed: " .
        $conn->error
    );

    http_response_code(500);
    exit("Unable to load dashboard.");
}

$stmt->bind_param("i", $satellite_id);

if (!$stmt->execute()) {
    error_log(
        "Clearance dashboard total query execute failed: " .
        $stmt->error
    );

    $stmt->close();

    http_response_code(500);
    exit("Unable to load dashboard.");
}

$countResult = $stmt->get_result();

if ($countResult) {
    $countRow = $countResult->fetch_assoc();

    if ($countRow) {
        $total_requests = (int)$countRow['count'];
    }
}

$stmt->close();


/* =========================================================
   RECENT REQUESTS
   ONLY REQUESTS FROM THIS SATELLITE
   ========================================================= */

$recent_query = "
    SELECT
        r.request_id,
        r.fullname,
        r.purpose,
        r.status,
        r.submitted_at,
        dt.name AS request_type
    FROM resident_request r
    LEFT JOIN document_types dt
        ON r.document_type_id = dt.document_type_id
    WHERE r.satellite_id = ?
    ORDER BY r.submitted_at DESC
    LIMIT 10
";

$stmt = $conn->prepare($recent_query);

if (!$stmt) {
    error_log(
        "Clearance dashboard recent requests query prepare failed: " .
        $conn->error
    );

    http_response_code(500);
    exit("Unable to load dashboard.");
}

$stmt->bind_param("i", $satellite_id);

if (!$stmt->execute()) {
    error_log(
        "Clearance dashboard recent requests query execute failed: " .
        $stmt->error
    );

    $stmt->close();

    http_response_code(500);
    exit("Unable to load dashboard.");
}

$result = $stmt->get_result();

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recent_requests[] = $row;
    }
}

$stmt->close();


/* =========================================================
   FINAL SANITIZED GREETING
   ========================================================= */

$greeting =
    'Good Day, ' .
    htmlspecialchars(
        (string)$user_full_name,
        ENT_QUOTES,
        'UTF-8'
    );
?>
