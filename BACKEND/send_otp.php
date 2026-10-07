```php
<?php

/* =========================================================
   START SESSION / OUTPUT BUFFER
========================================================= */

ob_start();
session_start();

include "db_connect.php";

ob_clean();

header('Content-Type: application/json; charset=UTF-8');


/* =========================================================
   HELPER
========================================================= */

function jsonResponse($success, $message, $httpCode = 200)
{
    http_response_code($httpCode);

    echo json_encode([
        "success" => $success,
        "message" => $message
    ]);

    exit();
}


/* =========================================================
   REQUEST METHOD
========================================================= */

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    jsonResponse(false, "Invalid request method.", 405);
}


/* =========================================================
   CSRF
========================================================= */

$csrfToken = $_POST['csrf_token'] ?? '';

if (
    empty($csrfToken) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals(
        $_SESSION['csrf_token'],
        $csrfToken
    )
) {
    jsonResponse(false, "Invalid CSRF token.", 403);
}


/* =========================================================
   HONEYPOT
========================================================= */

if (!empty($_POST['bms_reg_v_field'])) {
    jsonResponse(false, "Bot activity detected.", 403);
}


/* =========================================================
   RECAPTCHA ENTERPRISE
========================================================= */

$captchaToken = trim(
    (string)($_POST['g-recaptcha-response'] ?? '')
);

if ($captchaToken === '') {
    jsonResponse(
        false,
        "Security verification is missing.",
        403
    );
}


/*
 * Load the same configuration used by login.
 */
$recaptchaConfigFile = __DIR__ . "/recaptcha_config.php";

if (!is_file($recaptchaConfigFile)) {
    jsonResponse(
        false,
        "Security verification is unavailable.",
        500
    );
}

$recaptchaConfig = require $recaptchaConfigFile;

$projectId = trim(
    (string)($recaptchaConfig['project_id'] ?? '')
);

$apiKey = trim(
    (string)($recaptchaConfig['api_key'] ?? '')
);

$siteKey = trim(
    (string)($recaptchaConfig['site_key'] ?? '')
);

$expectedAction = 'register';

$minimumScore = (float)(
    $recaptchaConfig['minimum_score'] ?? 0.3
);


if (
    $projectId === '' ||
    $apiKey === '' ||
    $siteKey === ''
) {
    jsonResponse(
        false,
        "Security verification is unavailable.",
        500
    );
}


/*
 * Google reCAPTCHA Enterprise assessment.
 */
$assessmentUrl =
    "https://recaptchaenterprise.googleapis.com/v1/projects/" .
    rawurlencode($projectId) .
    "/assessments?key=" .
    rawurlencode($apiKey);


$assessmentPayload = [
    'event' => [
        'token' => $captchaToken,
        'siteKey' => $siteKey,
        'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'userIpAddress' => $_SERVER['REMOTE_ADDR'] ?? '',
        'expectedAction' => $expectedAction
    ]
];


$ch = curl_init($assessmentUrl);

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json'
    ],
    CURLOPT_POSTFIELDS => json_encode(
        $assessmentPayload
    ),
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 15
]);


$assessmentResponse = curl_exec($ch);

$curlError = curl_error($ch);

$httpCode = (int)curl_getinfo(
    $ch,
    CURLINFO_HTTP_CODE
);

curl_close($ch);


if (
    $assessmentResponse === false ||
    $curlError !== ''
) {
    jsonResponse(
        false,
        "Security verification could not be completed. Please try again.",
        503
    );
}


$assessment = json_decode(
    $assessmentResponse,
    true
);


if (
    !is_array($assessment) ||
    $httpCode < 200 ||
    $httpCode >= 300
) {
    jsonResponse(
        false,
        "Security verification failed. Please try again.",
        403
    );
}


/*
 * Token validity.
 */
$tokenProperties =
    $assessment['tokenProperties'] ?? [];

if (
    empty($tokenProperties['valid']) ||
    $tokenProperties['valid'] !== true
) {
    jsonResponse(
        false,
        "Security verification failed. Please try again.",
        403
    );
}


/*
 * Verify returned action.
 */
$returnedAction =
    (string)(
        $tokenProperties['action'] ?? ''
    );

if ($returnedAction !== $expectedAction) {
    jsonResponse(
        false,
        "Security verification failed. Please try again.",
        403
    );
}


/*
 * Verify risk score.
 */
$riskAnalysis =
    $assessment['riskAnalysis'] ?? [];

$score = (float)(
    $riskAnalysis['score'] ?? 0
);

if ($score < $minimumScore) {
    jsonResponse(
        false,
        "Security verification failed. Please try again.",
        403
    );
}


/* =========================================================
   PREVENT MULTIPLE ACTIVE REGISTRATION REQUESTS
========================================================= */

/*
 * If the user already has an active OTP session,
 * don't silently replace it.
 *
 * This prevents repeated requests from constantly
 * replacing the registration data.
 */

if (
    !empty($_SESSION['registration_otp_expires']) &&
    time() <
    (int)$_SESSION['registration_otp_expires'] &&
    !empty($_SESSION['temp_user_data']) &&
    !empty($_SESSION['otp'])
) {
    jsonResponse(
        false,
        "A registration verification is already active. Please check your email for the OTP.",
        429
    );
}


/* =========================================================
   BASIC INPUT
========================================================= */

$account_type = strtolower(
    trim(
        (string)($_POST['account_type'] ?? '')
    )
);

$department = strtoupper(
    trim(
        (string)($_POST['department'] ?? '')
    )
);

$satellite_id = filter_var(
    $_POST['satellite_id'] ?? null,
    FILTER_VALIDATE_INT
);

$name = trim(
    (string)($_POST['name'] ?? '')
);

$username = trim(
    (string)($_POST['username'] ?? '')
);

$email = trim(
    (string)($_POST['email'] ?? '')
);

$contact_number = trim(
    (string)($_POST['contact_number'] ?? '')
);

$password =
    (string)($_POST['password'] ?? '');

$confirm_password =
    (string)($_POST['confirm_password'] ?? '');

$id_number = trim(
    (string)($_POST['id_number'] ?? '')
);


/* =========================================================
   ACCOUNT TYPE
========================================================= */

if (
    !in_array(
        $account_type,
        ['resident', 'official'],
        true
    )
) {
    jsonResponse(
        false,
        "Invalid account type.",
        422
    );
}


/* =========================================================
   DEPARTMENT
========================================================= */

$allowedDepartments = [
    'ADMIN',
    'BPSO',
    'CLEARANCE',
    'LUPON'
];

if ($account_type === 'official') {

    if (
        !in_array(
            $department,
            $allowedDepartments,
            true
        )
    ) {
        jsonResponse(
            false,
            "Please select a valid department.",
            422
        );
    }

} else {

    /*
     * Residents must not submit an official department.
     */
    $department = '';
}


/* =========================================================
   SATELLITE
========================================================= */

if (
    $satellite_id === false ||
    $satellite_id <= 0
) {
    jsonResponse(
        false,
        "Please select a valid barangay satellite.",
        422
    );
}


/* =========================================================
   NAME
========================================================= */

if (
    $name === '' ||
    mb_strlen($name) > 150
) {
    jsonResponse(
        false,
        "Please provide a valid name.",
        422
    );
}


/* =========================================================
   USERNAME
========================================================= */

if (
    !preg_match(
        '/^[A-Za-z0-9_.-]{4,50}$/',
        $username
    )
) {
    jsonResponse(
        false,
        "Username must contain 4-50 letters, numbers, dots, underscores, or hyphens.",
        422
    );
}


/* =========================================================
   EMAIL
========================================================= */

if (
    !filter_var(
        $email,
        FILTER_VALIDATE_EMAIL
    ) ||
    strlen($email) > 254
) {
    jsonResponse(
        false,
        "Please provide a valid email address.",
        422
    );
}


/* =========================================================
   CONTACT NUMBER
========================================================= */

if (
    !preg_match(
        '/^[0-9]{11}$/',
        $contact_number
    )
) {
    jsonResponse(
        false,
        "Contact number must contain exactly 11 digits.",
        422
    );
}


/* =========================================================
   PASSWORD
========================================================= */

if (
    strlen($password) < 8 ||
    strlen($password) > 128
) {
    jsonResponse(
        false,
        "Password must be between 8 and 128 characters.",
        422
    );
}


if ($password !== $confirm_password) {
    jsonResponse(
        false,
        "Passwords do not match.",
        422
    );
}


/*
 * Match the password-strength rules used by register.js.
 */
if (
    !preg_match('/[A-Z]/', $password) ||
    !preg_match('/[a-z]/', $password) ||
    !preg_match('/[0-9]/', $password) ||
    !preg_match('/[@$!%*?&#^()_\-+=]/', $password)
) {
    jsonResponse(
        false,
        "Password must contain uppercase, lowercase, number, and special character.",
        422
    );
}


/* =========================================================
   ID NUMBER
========================================================= */

if (
    !preg_match(
        '/^[0-9]{12}$/',
        $id_number
    )
) {
    jsonResponse(
        false,
        "ID number must contain exactly 12 digits.",
        422
    );
}


/* =========================================================
   VERIFY SATELLITE
========================================================= */

$satelliteCheck = $conn->prepare("
    SELECT satellite_id
    FROM satellites
    WHERE satellite_id = ?
    AND status = 'Active'
    LIMIT 1
");

if (!$satelliteCheck) {
    jsonResponse(
        false,
        "Unable to verify barangay satellite.",
        500
    );
}

$satelliteCheck->bind_param(
    "i",
    $satellite_id
);

$satelliteCheck->execute();

$satelliteCheck->store_result();

if ($satelliteCheck->num_rows === 0) {

    $satelliteCheck->close();

    jsonResponse(
        false,
        "Invalid barangay satellite selected.",
        422
    );
}

$satelliteCheck->close();


/* =========================================================
   DUPLICATE ACCOUNT CHECK
========================================================= */

/*
 * Check both tables so a username/email cannot be
 * duplicated between resident and official accounts.
 */

$duplicateFound = false;


/* ---------- Residents ---------- */

$stmt = $conn->prepare("
    SELECT resident_id
    FROM residents
    WHERE username = ?
    OR email = ?
    LIMIT 1
");

if (!$stmt) {
    jsonResponse(
        false,
        "Unable to verify account information.",
        500
    );
}

$stmt->bind_param(
    "ss",
    $username,
    $email
);

$stmt->execute();

$stmt->store_result();

if ($stmt->num_rows > 0) {
    $duplicateFound = true;
}

$stmt->close();


/* ---------- Officials ---------- */

if (!$duplicateFound) {

    $stmt = $conn->prepare("
        SELECT official_id
        FROM officials
        WHERE username = ?
        OR email = ?
        LIMIT 1
    ");

    if (!$stmt) {
        jsonResponse(
            false,
            "Unable to verify account information.",
            500
        );
    }

    $stmt->bind_param(
        "ss",
        $username,
        $email
    );

    $stmt->execute();

    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $duplicateFound = true;
    }

    $stmt->close();
}


if ($duplicateFound) {
    jsonResponse(
        false,
        "Username or Email already exists.",
        409
    );
}


/* =========================================================
   FILE UPLOAD
========================================================= */

if (
    !isset($_FILES['proof']) ||
    $_FILES['proof']['error'] !== UPLOAD_ERR_OK
) {
    jsonResponse(
        false,
        "Please upload a valid proof of valid ID.",
        422
    );
}

$file = $_FILES['proof'];


/*
 * PHP upload error check.
 */
if ($file['size'] <= 0) {
    jsonResponse(
        false,
        "The uploaded file is empty.",
        422
    );
}


if ($file['size'] > 5 * 1024 * 1024) {
    jsonResponse(
        false,
        "The proof file must not exceed 5 MB.",
        422
    );
}


/* =========================================================
   FILE EXTENSION
========================================================= */

$allowedExtensions = [
    'jpg',
    'jpeg',
    'png',
    'gif',
    'webp',
    'pdf'
];

$extension = strtolower(
    pathinfo(
        $file['name'],
        PATHINFO_EXTENSION
    )
);

if (
    !in_array(
        $extension,
        $allowedExtensions,
        true
    )
) {
    jsonResponse(
        false,
        "Invalid file type.",
        422
    );
}


/* =========================================================
   MIME VALIDATION
========================================================= */

$finfo = new finfo(FILEINFO_MIME_TYPE);

$detectedMime = $finfo->file(
    $file['tmp_name']
);


$allowedMimeTypes = [
    'jpg'  => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
    'png'  => ['image/png'],
    'gif'  => ['image/gif'],
    'webp' => ['image/webp'],
    'pdf'  => ['application/pdf']
];


if (
    !isset(
        $allowedMimeTypes[$extension]
    ) ||
    !in_array(
        $detectedMime,
        $allowedMimeTypes[$extension],
        true
    )
) {
    jsonResponse(
        false,
        "The uploaded file content does not match its file type.",
        422
    );
}


/* =========================================================
   ACTUAL FILE CONTENT
========================================================= */

if ($extension !== 'pdf') {

    $imageInfo = @getimagesize(
        $file['tmp_name']
    );

    if ($imageInfo === false) {
        jsonResponse(
            false,
            "Invalid image file.",
            422
        );
    }

} else {

    $handle = @fopen(
        $file['tmp_name'],
        'rb'
    );

    $pdfHeader = $handle
        ? fread($handle, 5)
        : '';

    if ($handle) {
        fclose($handle);
    }

    if ($pdfHeader !== '%PDF-') {
        jsonResponse(
            false,
            "Invalid PDF file.",
            422
        );
    }
}


/* =========================================================
   QUARANTINE DIRECTORY
========================================================= */

$quarantineDirectoryPath =
    __DIR__ .
    DIRECTORY_SEPARATOR .
    "../UPLOADS/quarantine";


if (
    !is_dir($quarantineDirectoryPath) &&
    !mkdir(
        $quarantineDirectoryPath,
        0755,
        true
    )
) {
    jsonResponse(
        false,
        "Upload storage is unavailable.",
        500
    );
}


$quarantineDirectory =
    realpath(
        $quarantineDirectoryPath
    );


if (
    $quarantineDirectory === false ||
    !is_dir($quarantineDirectory)
) {
    jsonResponse(
        false,
        "Upload quarantine directory is unavailable.",
        500
    );
}


/* =========================================================
   SAFE RANDOM FILE NAME
========================================================= */

$newFileName =
    'proof_' .
    bin2hex(random_bytes(16)) .
    '.' .
    $extension;


$quarantinePath =
    $quarantineDirectory .
    DIRECTORY_SEPARATOR .
    $newFileName;


if (
    !move_uploaded_file(
        $file['tmp_name'],
        $quarantinePath
    )
) {
    jsonResponse(
        false,
        "Failed to save uploaded file.",
        500
    );
}


/* =========================================================
   CLAMAV
========================================================= */

$clamScanPath =
    'C:\Users\Gary\Downloads\clamav-1.5.4.win.x64\clamav-1.5.4.win.x64\clamscan.exe';


if (
    !is_file($clamScanPath) ||
    !function_exists('exec')
) {

    @unlink($quarantinePath);

    jsonResponse(
        false,
        "File security scanner is unavailable.",
        503
    );
}


$output = [];

$exitCode = -1;

$command =
    escapeshellarg($clamScanPath) .
    ' --no-summary ' .
    escapeshellarg($quarantinePath);


exec(
    $command,
    $output,
    $exitCode
);


if ($exitCode !== 0) {

    @unlink($quarantinePath);

    if ($exitCode === 1) {

        jsonResponse(
            false,
            "Uploaded file was detected as infected.",
            422
        );
    }

    jsonResponse(
        false,
        "File security scan failed.",
        503
    );
}


/* =========================================================
   HASH PASSWORD BEFORE SESSION STORAGE
========================================================= */

$hashedPassword = password_hash(
    $password,
    PASSWORD_DEFAULT
);


if ($hashedPassword === false) {

    @unlink($quarantinePath);

    jsonResponse(
        false,
        "Unable to secure password.",
        500
    );
}


/* =========================================================
   GENERATE OTP
========================================================= */

$otp = (string)random_int(
    100000,
    999999
);


/*
 * OTP expires after 10 minutes.
 */
$otpExpires =
    time() + (10 * 60);


/*
 * Maximum incorrect OTP attempts.
 */
$otpMaxAttempts = 5;


/* =========================================================
   STORE TEMPORARY REGISTRATION DATA
========================================================= */

/*
 * Store ONLY the information required by register1.php.
 *
 * The plaintext password is NOT stored.
 * The reCAPTCHA token is NOT stored.
 * The honeypot is NOT stored.
 */
$_SESSION['temp_user_data'] = [
    'account_type'    => $account_type,
    'department'      => $department,
    'satellite_id'    => $satellite_id,
    'name'            => $name,
    'username'        => $username,
    'email'           => $email,
    'contact_number'  => $contact_number,
    'password_hash'   => $hashedPassword,
    'id_number'       => $id_number
];


$_SESSION['temp_file_name'] =
    $newFileName;


$_SESSION['temp_file_path'] =
    $quarantinePath;


$_SESSION['otp'] =
    $otp;


$_SESSION['registration_security_verified'] =
    true;


$_SESSION['registration_otp_expires'] =
    $otpExpires;


$_SESSION['registration_otp_attempts'] =
    0;


$_SESSION['registration_otp_max_attempts'] =
    $otpMaxAttempts;


/* =========================================================
   SEND OTP WITH RESEND
========================================================= */

$safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
$safeOtp = htmlspecialchars($otp, ENT_QUOTES, 'UTF-8');
$emailHtml =
    "Hello <b>{$safeName}</b>,<br><br>" .
    "Your verification code for Barangay San Isidro is:<br>" .
    "<h2>{$safeOtp}</h2>" .
    "<p>This code will expire in 10 minutes.</p>" .
    "<p>Please do not share this code.</p>";

try {
    bms_send_email($email, 'Verify Your Registration', $emailHtml);
    jsonResponse(true, "OTP sent successfully.");
} catch (RuntimeException $exception) {

    /*
     * Delete the clean quarantine file if email
     * delivery could not be completed.
     */
    if (is_file($quarantinePath)) {
        @unlink($quarantinePath);
    }


    unset(
        $_SESSION['temp_user_data'],
        $_SESSION['temp_file_name'],
        $_SESSION['temp_file_path'],
        $_SESSION['otp'],
        $_SESSION['registration_security_verified'],
        $_SESSION['registration_otp_expires'],
        $_SESSION['registration_otp_attempts'],
        $_SESSION['registration_otp_max_attempts']
    );


    jsonResponse(
        false,
        "Unable to send OTP. Please try again later.",
        503
    );
}
