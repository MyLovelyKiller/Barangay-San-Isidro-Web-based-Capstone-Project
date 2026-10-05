<?php
ob_start();
session_start();

/* =========================================================
   CSRF TOKEN
   ========================================================= */

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];

/* =========================================================
   BASIC SESSION CHECK
   ========================================================= */

if (!isset($_SESSION['official_id'])) {
    header("Location: /BMS/CODES/login.php?error=1");
    exit();
}

/* =========================================================
   DATABASE CONNECTION
   ========================================================= */

require_once __DIR__ . '/../BACKEND/db_connect.php';

/* =========================================================
   INITIAL VALUES
   ========================================================= */

$user_full_name = 'Officer';
$satellite_id = 0;

$filter = isset($_GET['filter']) ? trim($_GET['filter']) : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$filtered_requests = [];
$total_requests = 0;
$pending_count = 0;
$approved_count = 0;
$rejected_count = 0;

/* =========================================================
   GET LOGGED-IN OFFICIAL FROM DATABASE
   Do not rely only on session department information.
   ========================================================= */

$official_id = (int)$_SESSION['official_id'];

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
    error_log("Clearance request_backend officer query prepare failed: " . $conn->error);
    http_response_code(500);
    die("Unable to load your account information.");
}

$stmt->bind_param("i", $official_id);

if (!$stmt->execute()) {
    error_log("Clearance request_backend officer query execute failed: " . $stmt->error);
    $stmt->close();
    http_response_code(500);
    die("Unable to load your account information.");
}

$officer_result = $stmt->get_result();

if (!$officer_result || !$row = $officer_result->fetch_assoc()) {
    $stmt->close();

    /* Destroy invalid official session */
    unset($_SESSION['official_id']);

    header("Location: /BMS/CODES/login.php?error=1");
    exit();
}

$user_full_name = $row['name'] ?? 'Officer';
$satellite_id = (int)($row['satellite_id'] ?? 0);

$stmt->close();

/* =========================================================
   MAKE SURE OFFICER HAS A SATELLITE
   ========================================================= */

if ($satellite_id <= 0) {
    http_response_code(403);
    die("Your Clearance account does not have an assigned satellite. Please contact the administrator.");
}

/* =========================================================
   1. STATISTICS - ONLY THIS SATELLITE
   ========================================================= */

/* ---------- TOTAL REQUESTS ---------- */

$stmt = $conn->prepare("
    SELECT COUNT(*) AS count
    FROM resident_request
    WHERE satellite_id = ?
");

if (!$stmt) {
    error_log("Clearance total requests query prepare failed: " . $conn->error);
    http_response_code(500);
    die("Unable to load request statistics.");
}

$stmt->bind_param("i", $satellite_id);

if (!$stmt->execute()) {
    error_log("Clearance total requests query execute failed: " . $stmt->error);
    $stmt->close();
    http_response_code(500);
    die("Unable to load request statistics.");
}

$result = $stmt->get_result();

if ($result) {
    $row = $result->fetch_assoc();
    $total_requests = (int)($row['count'] ?? 0);
}

$stmt->close();

/* ---------- PENDING REQUESTS ---------- */

$stmt = $conn->prepare("
    SELECT COUNT(*) AS count
    FROM resident_request
    WHERE satellite_id = ?
      AND status LIKE ?
");

if (!$stmt) {
    error_log("Clearance pending requests query prepare failed: " . $conn->error);
    http_response_code(500);
    die("Unable to load request statistics.");
}

$status_p = 'Pending%';

$stmt->bind_param("is", $satellite_id, $status_p);

if (!$stmt->execute()) {
    error_log("Clearance pending requests query execute failed: " . $stmt->error);
    $stmt->close();
    http_response_code(500);
    die("Unable to load request statistics.");
}

$result = $stmt->get_result();

if ($result) {
    $row = $result->fetch_assoc();
    $pending_count = (int)($row['count'] ?? 0);
}

$stmt->close();

/* ---------- APPROVED REQUESTS ---------- */

$stmt = $conn->prepare("
    SELECT COUNT(*) AS count
    FROM resident_request
    WHERE satellite_id = ?
      AND status = ?
");

if (!$stmt) {
    error_log("Clearance approved requests query prepare failed: " . $conn->error);
    http_response_code(500);
    die("Unable to load request statistics.");
}

$status_a = 'Approved';

$stmt->bind_param("is", $satellite_id, $status_a);

if (!$stmt->execute()) {
    error_log("Clearance approved requests query execute failed: " . $stmt->error);
    $stmt->close();
    http_response_code(500);
    die("Unable to load request statistics.");
}

$result = $stmt->get_result();

if ($result) {
    $row = $result->fetch_assoc();
    $approved_count = (int)($row['count'] ?? 0);
}

$stmt->close();

/* ---------- REJECTED REQUESTS ---------- */

$stmt = $conn->prepare("
    SELECT COUNT(*) AS count
    FROM resident_request
    WHERE satellite_id = ?
      AND status = ?
");

if (!$stmt) {
    error_log("Clearance rejected requests query prepare failed: " . $conn->error);
    http_response_code(500);
    die("Unable to load request statistics.");
}

$status_r = 'Rejected';

$stmt->bind_param("is", $satellite_id, $status_r);

if (!$stmt->execute()) {
    error_log("Clearance rejected requests query execute failed: " . $stmt->error);
    $stmt->close();
    http_response_code(500);
    die("Unable to load request statistics.");
}

$result = $stmt->get_result();

if ($result) {
    $row = $result->fetch_assoc();
    $rejected_count = (int)($row['count'] ?? 0);
}

$stmt->close();

/* =========================================================
   2. MAIN REQUESTS QUERY
   ========================================================= */

$sql = "
    SELECT
        r.request_id,
        r.resident_id,
        r.fullname,
        r.birthdate,
        r.purpose,
        r.phone,
        r.email,
        r.address,
        GROUP_CONCAT(ra.file_path SEPARATOR ', ') AS attachment,
        r.status,
        r.reason_message,
        r.submitted_at,
        r.updated_at,
        r.document_type_id,
        r.payment_method,
        r.ref_number,
        r.payment_receipt,
        r.digital_signature,
        dt.category,
        dt.name AS type,
        dt.name AS request_type,
        dt.price AS document_fee
    FROM resident_request r
    LEFT JOIN document_types dt
        ON r.document_type_id = dt.document_type_id
    LEFT JOIN record_attachments ra
        ON ra.record_id = r.request_id
        AND ra.record_type = 'request'
    WHERE r.satellite_id = ?
";

$params = [$satellite_id];
$types = "i";

/* =========================================================
   STATUS FILTER
   ========================================================= */

if ($filter !== 'all') {

    if (strcasecmp($filter, 'Pending') === 0) {

        $sql .= " AND r.status LIKE ?";
        $params[] = "Pending%";
        $types .= "s";

    } elseif (in_array($filter, ['Approved', 'Rejected'], true)) {

        $sql .= " AND r.status = ?";
        $params[] = $filter;
        $types .= "s";
    }
}

/* =========================================================
   SEARCH FILTER
   ========================================================= */

if ($search !== '') {

    $sql .= " AND r.fullname LIKE ?";
    $params[] = "%" . $search . "%";
    $types .= "s";
}

/* =========================================================
   ORDERING
   ========================================================= */

$sql .= "
    GROUP BY r.request_id
    ORDER BY r.submitted_at DESC
";

/* =========================================================
   EXECUTE MAIN REQUEST QUERY
   ========================================================= */

$stmt = $conn->prepare($sql);

if (!$stmt) {
    error_log("Clearance main request query prepare failed: " . $conn->error);
    http_response_code(500);
    die("Unable to load clearance requests.");
}

$stmt->bind_param($types, ...$params);

if (!$stmt->execute()) {
    error_log("Clearance main request query execute failed: " . $stmt->error);
    $stmt->close();
    http_response_code(500);
    die("Unable to load clearance requests.");
}

$result = $stmt->get_result();

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $filtered_requests[] = $row;
    }
}

$stmt->close();
?>
