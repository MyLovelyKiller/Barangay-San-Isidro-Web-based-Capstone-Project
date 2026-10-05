<?php
error_reporting(0);
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../PHPMailer/src/Exception.php';
require __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require __DIR__ . '/../PHPMailer/src/SMTP.php';

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false, "message" => "Invalid Request."]);
    exit();
}

/* ---------- CSRF ---------- */
$csrfToken = $_POST['csrf_token'] ?? '';

if (
    empty($csrfToken) ||
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

/* ---------- Basic data ---------- */
$email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
$name = trim($_POST['name'] ?? '');

if (!$email || $name === '') {
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
$clamScanPath =
    'C:\Users\Gary\Downloads\clamav-1.5.4.win.x64\clamav-1.5.4.win.x64\clamscan.exe';

if (!is_file($clamScanPath) || !function_exists('exec')) {
    unlink($quarantine_path);
    echo json_encode(["success" => false, "message" => "File security scanner is unavailable."]);
    exit();
}

$output = [];
$exitCode = -1;

$command =
    escapeshellarg($clamScanPath) .
    ' --no-summary ' .
    escapeshellarg($quarantine_path);

exec($command, $output, $exitCode);

if ($exitCode !== 0) {
    unlink($quarantine_path);

    $message = ($exitCode === 1)
        ? "Uploaded file was detected as infected."
        : "File security scan failed.";

    echo json_encode(["success" => false, "message" => $message]);
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
$_SESSION['registration_security_verified'] = true;

/* ---------- SEND OTP ---------- */
$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->SMTPDebug = 0;
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;

    $mail->Username = 'christianmorales602@gmail.com';
    $mail->Password = 'flfnjwrbavgelzgu';

    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'allow_self_signed' => false
        ]
    ];

    $mail->setFrom('christianmorales602@gmail.com', 'Barangay San Isidro');
    $mail->addAddress($email, $name);
    $mail->isHTML(true);
    $mail->Subject = 'Verify Your Registration';

    $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');

    $mail->Body =
        "Hello <b>{$safeName}</b>,<br><br>" .
        "Your verification code for Barangay San Isidro is: " .
        "<h2>{$otp}</h2><br>" .
        "Please do not share this code.";

    $mail->send();

    echo json_encode([
        "success" => true,
        "message" => "OTP Sent successfully!"
    ]);
    exit();

} catch (Exception $e) {

    if (file_exists($quarantine_path)) {
        unlink($quarantine_path);
    }

    unset(
        $_SESSION['temp_user_data'],
        $_SESSION['temp_file_name'],
        $_SESSION['temp_file_path'],
        $_SESSION['otp'],
        $_SESSION['registration_security_verified']
    );

    echo json_encode([
        "success" => false,
        "message" => "Unable to send OTP. Please try again later."
    ]);
    exit();
}
?>