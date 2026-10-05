<?php
require_once __DIR__ . '/security_helpers.php';
bms_start_secure_session();
bms_send_security_headers();

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Invalid Request."]);
    exit();
}

if (!bms_rate_limit('registration-otp', 5, 3600)) {
    http_response_code(429);
    echo json_encode(["success" => false, "message" => "Too many attempts. Please try again later."]);
    exit();
}

/* ---------- CSRF ---------- */
$csrfToken = $_POST['csrf_token'] ?? '';

if (
    empty($csrfToken) ||
    !is_string($csrfToken) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $csrfToken)
) {
    echo json_encode(["success" => false, "message" => "Invalid CSRF token."]);
    exit();
}

/* ---------- Honeypot ---------- */
if (!empty($_POST['bms_reg_v_field'])) {
    echo json_encode(["success" => false, "message" => "Bot activity detected."]);
    exit();
}

if (!bms_verify_recaptcha(trim((string)($_POST['g-recaptcha-response'] ?? '')), 'register')) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Security verification failed. Please try again."]);
    exit();
}

/* ---------- Basic data ---------- */
$emailInput = $_POST['email'] ?? '';
$nameInput = $_POST['name'] ?? '';
$passwordInput = $_POST['password'] ?? '';
$confirmPasswordInput = $_POST['confirm_password'] ?? '';
$accountTypeInput = $_POST['account_type'] ?? '';
$usernameInput = $_POST['username'] ?? '';
$departmentInput = $_POST['department'] ?? '';
$email = is_string($emailInput) ? filter_var($emailInput, FILTER_VALIDATE_EMAIL) : false;
$name = is_string($nameInput) ? trim($nameInput) : '';
$password = is_string($passwordInput) ? $passwordInput : '';
$confirmPassword = is_string($confirmPasswordInput) ? $confirmPasswordInput : '';
$accountType = is_string($accountTypeInput) ? strtolower(trim($accountTypeInput)) : '';
$username = is_string($usernameInput) ? trim($usernameInput) : '';
$department = is_string($departmentInput) ? strtoupper(trim($departmentInput)) : '';
$satelliteId = filter_var($_POST['satellite_id'] ?? null, FILTER_VALIDATE_INT);

if (
    !$email ||
    $name === '' ||
    strlen($name) > 150 ||
    !in_array($accountType, ['resident', 'official'], true) ||
    strlen($username) < 4 ||
    strlen($username) > 50 ||
    !preg_match('/^[A-Za-z0-9_.-]+$/', $username) ||
    $satelliteId === false ||
    $satelliteId === null ||
    $satelliteId <= 0 ||
    ($accountType === 'official' && !in_array($department, ['ADMIN', 'BPSO', 'CLEARANCE', 'LUPON'], true)) ||
    !bms_password_is_strong($password) ||
    !hash_equals($password, $confirmPassword)
) {
    echo json_encode(["success" => false, "message" => "Please provide valid registration details."]);
    exit();
}

/* ---------- FILE UPLOAD ---------- */
if (!isset($_FILES['proof']) || $_FILES['proof']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(["success" => false, "message" => "Please upload a valid proof of residency."]);
    exit();
}

$file = $_FILES['proof'];

if ($file['size'] <= 0 || $file['size'] > 5 * 1024 * 1024) {
    echo json_encode(["success" => false, "message" => "File must be between 1 byte and 5MB."]);
    exit();
}

$allowed = [
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'gif'  => 'image/gif',
    'pdf'  => 'application/pdf'
];

$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!isset($allowed[$extension])) {
    echo json_encode(["success" => false, "message" => "Invalid file type."]);
    exit();
}

/* ---------- Validate actual file content ---------- */
$finfo = new finfo(FILEINFO_MIME_TYPE);
$detectedMime = $finfo->file($file['tmp_name']);
if ($detectedMime !== $allowed[$extension]) {
    echo json_encode(["success" => false, "message" => "File content does not match its extension."]);
    exit();
}

if ($extension !== 'pdf') {
    if (@getimagesize($file['tmp_name']) === false) {
        echo json_encode(["success" => false, "message" => "Invalid image file."]);
        exit();
    }
} else {
    $handle = @fopen($file['tmp_name'], 'rb');
    $header = $handle ? fread($handle, 5) : '';
    if ($handle) fclose($handle);

    if ($header !== '%PDF-') {
        echo json_encode(["success" => false, "message" => "Invalid PDF file."]);
        exit();
    }
}

/* ---------- Quarantine ---------- */
$quarantine_dir = "../UPLOADS/quarantine/";

if (!is_dir($quarantine_dir) && !mkdir($quarantine_dir, 0755, true)) {
    echo json_encode(["success" => false, "message" => "Upload storage is unavailable."]);
    exit();
}

$new_file_name = 'proof_' . bin2hex(random_bytes(16)) . '.' . $extension;
$quarantine_path = $quarantine_dir . $new_file_name;

if (!move_uploaded_file($file['tmp_name'], $quarantine_path)) {
    echo json_encode(["success" => false, "message" => "Failed to save uploaded file."]);
    exit();
}

/* ---------- ClamAV ---------- */
$scanMessage = '';
if (!bms_scan_file_with_clamav($quarantine_path, $scanMessage)) {
    unlink($quarantine_path);
    echo json_encode(["success" => false, "message" => $scanMessage]);
    exit();
}

/* ---------- Keep clean file in quarantine until OTP verification ---------- */

/*
 * The clean file stays in:
 *
 * ../UPLOADS/quarantine/
 *
 * It will be moved to:
 *
 * ../uploads/
 *
 * only after the OTP is successfully verified
 * inside register1.php.
 */


/* ---------- OTP & SESSION ---------- */
$otp = (string)random_int(100000, 999999);

$_SESSION['temp_user_data'] = $_POST;
$_SESSION['temp_file_name'] = $new_file_name;
$_SESSION['temp_file_path'] = $quarantine_path;
$_SESSION['otp'] = $otp;
$_SESSION['otp_created_at'] = time();
$_SESSION['otp_attempts'] = 0;
$_SESSION['registration_security_verified'] = true;

/* ---------- SEND OTP ---------- */
try {
    $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $html =
        "Hello <b>{$safeName}</b>,<br><br>" .
        "Your verification code for Barangay San Isidro is: " .
        "<h2>{$otp}</h2><br>" .
        "Please do not share this code.";

    bms_send_email($email, 'Verify Your Registration', $html);

    echo json_encode([
        "success" => true,
        "message" => "OTP Sent successfully!"
    ]);
    exit();

} catch (RuntimeException $e) {

    if (file_exists($quarantine_path)) {
        unlink($quarantine_path);
    }

    unset(
        $_SESSION['temp_user_data'],
        $_SESSION['temp_file_name'],
        $_SESSION['temp_file_path'],
        $_SESSION['otp'],
        $_SESSION['otp_created_at'],
        $_SESSION['otp_attempts'],
        $_SESSION['registration_security_verified']
    );

    echo json_encode([
        "success" => false,
        "message" => "Unable to send OTP. Please try again later."
    ]);
    exit();
}
?>