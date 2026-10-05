<?php
session_start();

/* =========================================================
   CSRF PROTECTION
   ========================================================= */

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Invalid request method.";
    exit();
}

$csrfToken = $_POST['csrf_token'] ?? '';

if (
    empty($csrfToken) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $csrfToken)
) {
    http_response_code(403);
    echo "Invalid CSRF token.";
    exit();
}

/* =========================================================
   OFFICIAL SESSION CHECK
   ========================================================= */

if (!isset($_SESSION['official_id'])) {
    http_response_code(403);
    echo "Unauthorized";
    exit();
}

/* =========================================================
   DATABASE CONNECTION
   ========================================================= */

require_once __DIR__ . '/../BACKEND/db_connect.php';

$official_id = (int)$_SESSION['official_id'];
$username = $_SESSION['username'] ?? 'Unknown';
$satellite_id = 0;

/* =========================================================
   VERIFY OFFICIAL DIRECTLY FROM DATABASE
   Do not rely only on the session department value.
   ========================================================= */

$officer = $conn->prepare("
    SELECT
        official_id,
        department,
        satellite_id
    FROM officials
    WHERE official_id = ?
      AND department = 'CLEARANCE'
    LIMIT 1
");

if (!$officer) {
    error_log("Clearance process_request officer query prepare failed: " . $conn->error);
    http_response_code(500);
    echo "Unable to verify your account.";
    exit();
}

$officer->bind_param("i", $official_id);

if (!$officer->execute()) {
    error_log("Clearance process_request officer query execute failed: " . $officer->error);
    $officer->close();
    http_response_code(500);
    echo "Unable to verify your account.";
    exit();
}

$officer_result = $officer->get_result();

if (!$officer_result || !$officer_row = $officer_result->fetch_assoc()) {
    $officer->close();

    http_response_code(403);
    echo "Unauthorized";
    exit();
}

$satellite_id = (int)($officer_row['satellite_id'] ?? 0);

$officer->close();

/* =========================================================
   SATELLITE VALIDATION
   ========================================================= */

if ($satellite_id <= 0) {
    http_response_code(403);
    echo "Your Clearance account does not have an assigned satellite.";
    exit();
}

/* =========================================================
   INPUTS
   ========================================================= */

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

$action = isset($_POST['action'])
    ? trim($_POST['action'])
    : '';

$reason_message = isset($_POST['reason_message'])
    ? trim($_POST['reason_message'])
    : '';

if ($id <= 0) {
    http_response_code(400);
    echo "Invalid ID.";
    exit();
}

/* =========================================================
   ACTION MAP
   ========================================================= */

$status_map = [
    'approve' => 'Approved',
    'reject'  => 'Rejected',
    'pending' => 'Pending'
];

if (!isset($status_map[$action])) {
    http_response_code(400);
    echo "Invalid action.";
    exit();
}

$new_status = $status_map[$action];

/* =========================================================
   REJECTION VALIDATION
   ========================================================= */

if ($action === 'reject' && $reason_message === '') {
    http_response_code(400);
    echo "A reason is required when rejecting a request.";
    exit();
}

/* Optional maximum length to prevent oversized input */
if (mb_strlen($reason_message) > 2000) {
    http_response_code(400);
    echo "Reason is too long.";
    exit();
}

/* =========================================================
   GET REQUEST
   MUST BELONG TO OFFICER'S SATELLITE
   ========================================================= */

$get = $conn->prepare("
    SELECT
        request_id,
        fullname,
        status,
        satellite_id
    FROM resident_request
    WHERE request_id = ?
      AND satellite_id = ?
    LIMIT 1
");

if (!$get) {
    error_log("Clearance process_request request query prepare failed: " . $conn->error);
    http_response_code(500);
    echo "Unable to load the request.";
    exit();
}

$get->bind_param("ii", $id, $satellite_id);

if (!$get->execute()) {
    error_log("Clearance process_request request query execute failed: " . $get->error);
    $get->close();
    http_response_code(500);
    echo "Unable to load the request.";
    exit();
}

$res = $get->get_result()->fetch_assoc();

$get->close();

if (!$res) {
    http_response_code(403);
    echo "You are not authorized to modify this request.";
    exit();
}

$fullname = $res['fullname'] ?? 'Unknown';
$current_status = trim($res['status'] ?? '');

/* =========================================================
   STATUS PROTECTION
   ========================================================= */

/*
 * Approved and Rejected requests are considered finalized.
 * They cannot be changed back to Pending or changed to another
 * status through this endpoint.
 */

if (in_array($current_status, ['Approved', 'Rejected'], true)) {
    http_response_code(409);
    echo "This request has already been finalized and cannot be modified.";
    exit();
}

/*
 * Only Pending requests should normally reach this endpoint.
 * This prevents unexpected/custom statuses from being modified.
 */
if (stripos($current_status, 'Pending') !== 0) {
    http_response_code(409);
    echo "This request cannot be modified in its current status.";
    exit();
}

/* =========================================================
   AUDIT INFORMATION
   ========================================================= */

if ($action === 'approve') {

    $desc = "Approved request for $fullname";
    $audit_action = "APPROVE";

} elseif ($action === 'reject') {

    $desc = "Rejected request for $fullname (Reason: $reason_message)";
    $audit_action = "REJECT";

} else {

    $desc = "Marked request as pending for $fullname";
    $audit_action = "PENDING";
}

/* =========================================================
   TRANSACTION
   Update request and audit together.
   ========================================================= */

$transaction_started = false;

try {

    if (!$conn->begin_transaction()) {
        throw new Exception("Unable to start database transaction.");
    }

    $transaction_started = true;

    /* =====================================================
       UPDATE REQUEST
       Satellite restriction is enforced again.
       ===================================================== */

    $query = "
        UPDATE resident_request
        SET
            status = ?,
            reason_message = ?,
            updated_at = NOW()
        WHERE request_id = ?
          AND satellite_id = ?
          AND status LIKE 'Pending%'
    ";

    $stmt = $conn->prepare($query);

    if (!$stmt) {
        throw new Exception("Request update prepare failed: " . $conn->error);
    }

    $stmt->bind_param(
        "ssii",
        $new_status,
        $reason_message,
        $id,
        $satellite_id
    );

    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();

        throw new Exception("Request update failed: " . $error);
    }

    /*
     * Make sure an actual record was updated.
     * This protects against race conditions where another
     * process changed the request after the SELECT above.
     */
    if ($stmt->affected_rows !== 1) {
        $stmt->close();

        throw new Exception("Request was changed by another process.");
    }

    $stmt->close();

    /* =====================================================
       AUDIT TRAIL
       ===================================================== */

    $log = $conn->prepare("
        INSERT INTO audit_trail
            (action, description, user)
        VALUES
            (?, ?, ?)
    ");

    if (!$log) {
        throw new Exception("Audit trail prepare failed: " . $conn->error);
    }

    $log->bind_param(
        "sss",
        $audit_action,
        $desc,
        $username
    );

    if (!$log->execute()) {
        $error = $log->error;
        $log->close();

        throw new Exception("Audit trail insert failed: " . $error);
    }

    $log->close();

    /* =====================================================
       COMMIT
       ===================================================== */

    if (!$conn->commit()) {
        throw new Exception("Database commit failed.");
    }

    $transaction_started = false;

    echo "Success";

} catch (Throwable $e) {

    if ($transaction_started) {
        $conn->rollback();
    }

    error_log(
        "Clearance process_request failed for request ID {$id}: "
        . $e->getMessage()
    );

    http_response_code(500);
    echo "Unable to process the request. Please try again.";
}

$conn->close();
?>
