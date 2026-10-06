<?php

/* =========================================================
   SESSION / OUTPUT
========================================================= */

ob_start();
session_start();

require_once __DIR__ . "/db_connect.php";

ob_clean();

header('Content-Type: application/json; charset=UTF-8');


/* =========================================================
   HELPER FUNCTIONS
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


/*
 * Remove all temporary registration information
 * while keeping the normal session and CSRF token.
 */
function cleanupRegistrationSession()
{
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
}


/*
 * Delete the quarantine file belonging to the
 * current registration.
 */
function cleanupQuarantineFile()
{
    $filePath = $_SESSION['temp_file_path'] ?? '';

    if (
        is_string($filePath) &&
        $filePath !== '' &&
        is_file($filePath)
    ) {
        @unlink($filePath);
    }
}


/* =========================================================
   REQUEST METHOD
========================================================= */

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    jsonResponse(
        false,
        "Invalid request method.",
        405
    );
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
    jsonResponse(
        false,
        "Invalid CSRF token.",
        403
    );
}


/* =========================================================
   HONEYPOT
========================================================= */

if (!empty($_POST['bms_reg_v_field'])) {
    cleanupQuarantineFile();
    cleanupRegistrationSession();

    jsonResponse(
        false,
        "Bot activity detected.",
        403
    );
}


/* =========================================================
   REGISTRATION SECURITY CHECK
========================================================= */

if (
    empty($_SESSION['registration_security_verified']) ||
    $_SESSION['registration_security_verified'] !== true
) {
    jsonResponse(
        false,
        "Registration session is invalid. Please start again.",
        403
    );
}


/* =========================================================
   TEMPORARY REGISTRATION DATA
========================================================= */

$tempData =
    $_SESSION['temp_user_data'] ?? null;


if (
    !is_array($tempData) ||
    empty($_SESSION['temp_file_name']) ||
    empty($_SESSION['temp_file_path'])
) {
    cleanupRegistrationSession();

    jsonResponse(
        false,
        "Registration session has expired. Please register again.",
        403
    );
}


/* =========================================================
   OTP EXPIRATION
========================================================= */

$otpExpires =
    (int)(
        $_SESSION['registration_otp_expires'] ?? 0
    );


if (
    $otpExpires <= 0 ||
    time() > $otpExpires
) {
    cleanupQuarantineFile();
    cleanupRegistrationSession();

    jsonResponse(
        false,
        "OTP has expired. Please request a new OTP.",
        410
    );
}


/* =========================================================
   OTP ATTEMPTS
========================================================= */

$currentAttempts =
    (int)(
        $_SESSION['registration_otp_attempts'] ?? 0
    );


$maxAttempts =
    (int)(
        $_SESSION['registration_otp_max_attempts'] ?? 5
    );


if ($maxAttempts <= 0) {
    $maxAttempts = 5;
}


if ($currentAttempts >= $maxAttempts) {

    cleanupQuarantineFile();
    cleanupRegistrationSession();

    jsonResponse(
        false,
        "Too many incorrect OTP attempts. Please start registration again.",
        429
    );
}


/* =========================================================
   OTP INPUT
========================================================= */

$submittedOtp = trim(
    (string)(
        $_POST['otp_code'] ?? ''
    )
);


if (
    !preg_match(
        '/^[0-9]{6}$/',
        $submittedOtp
    )
) {

    $_SESSION['registration_otp_attempts'] =
        $currentAttempts + 1;

    if (
        $_SESSION['registration_otp_attempts']
        >= $maxAttempts
    ) {
        cleanupQuarantineFile();
        cleanupRegistrationSession();

        jsonResponse(
            false,
            "Too many incorrect OTP attempts. Please start registration again.",
            429
        );
    }

    jsonResponse(
        false,
        "Please enter the 6-digit OTP.",
        422
    );
}


/* =========================================================
   STORED OTP
========================================================= */

$storedOtp =
    (string)(
        $_SESSION['otp'] ?? ''
    );


if (
    $storedOtp === '' ||
    !hash_equals(
        $storedOtp,
        $submittedOtp
    )
) {

    $_SESSION['registration_otp_attempts'] =
        $currentAttempts + 1;

    $remaining =
        $maxAttempts -
        $_SESSION['registration_otp_attempts'];


    if ($remaining <= 0) {

        cleanupQuarantineFile();
        cleanupRegistrationSession();

        jsonResponse(
            false,
            "Too many incorrect OTP attempts. Please start registration again.",
            429
        );
    }


    jsonResponse(
        false,
        "Incorrect OTP. {$remaining} attempt(s) remaining.",
        422
    );
}


/* =========================================================
   VALIDATE TEMP DATA
========================================================= */

$account_type =
    strtolower(
        trim(
            (string)(
                $tempData['account_type'] ?? ''
            )
        )
    );


$department =
    strtoupper(
        trim(
            (string)(
                $tempData['department'] ?? ''
            )
        )
    );


$satellite_id =
    filter_var(
        $tempData['satellite_id'] ?? null,
        FILTER_VALIDATE_INT
    );


$name =
    trim(
        (string)(
            $tempData['name'] ?? ''
        )
    );


$username =
    trim(
        (string)(
            $tempData['username'] ?? ''
        )
    );


$email =
    trim(
        (string)(
            $tempData['email'] ?? ''
        )
    );


$contact_number =
    trim(
        (string)(
            $tempData['contact_number'] ?? ''
        )
    );


$passwordHash =
    (string)(
        $tempData['password_hash'] ?? ''
    );


$id_number =
    trim(
        (string)(
            $tempData['id_number'] ?? ''
        )
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
    cleanupQuarantineFile();
    cleanupRegistrationSession();

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
        cleanupQuarantineFile();
        cleanupRegistrationSession();

        jsonResponse(
            false,
            "Invalid department.",
            422
        );
    }

} else {

    $department = '';
}


/* =========================================================
   SATELLITE
========================================================= */

if (
    $satellite_id === false ||
    $satellite_id <= 0
) {
    cleanupQuarantineFile();
    cleanupRegistrationSession();

    jsonResponse(
        false,
        "Invalid barangay satellite.",
        422
    );
}


/* =========================================================
   NAME
========================================================= */

if (
    $name === '' ||
    strlen($name) > 150
) {
    cleanupQuarantineFile();
    cleanupRegistrationSession();

    jsonResponse(
        false,
        "Invalid name.",
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
    cleanupQuarantineFile();
    cleanupRegistrationSession();

    jsonResponse(
        false,
        "Invalid username.",
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
    cleanupQuarantineFile();
    cleanupRegistrationSession();

    jsonResponse(
        false,
        "Invalid email address.",
        422
    );
}


/* =========================================================
   CONTACT
========================================================= */

if (
    !preg_match(
        '/^[0-9]{11}$/',
        $contact_number
    )
) {
    cleanupQuarantineFile();
    cleanupRegistrationSession();

    jsonResponse(
        false,
        "Invalid contact number.",
        422
    );
}


/* =========================================================
   PASSWORD HASH
========================================================= */

if (
    $passwordHash === '' ||
    strlen($passwordHash) < 20
) {
    cleanupQuarantineFile();
    cleanupRegistrationSession();

    jsonResponse(
        false,
        "Invalid password security data.",
        422
    );
}


/*
 * Make sure it is actually a valid password hash.
 */
$passwordInfo =
    password_get_info(
        $passwordHash
    );


if (
    empty($passwordInfo['algo'])
) {
    cleanupQuarantineFile();
    cleanupRegistrationSession();

    jsonResponse(
        false,
        "Invalid password security data.",
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
    cleanupQuarantineFile();
    cleanupRegistrationSession();

    jsonResponse(
        false,
        "Invalid ID number.",
        422
    );
}


/* =========================================================
   VERIFY SATELLITE AGAIN
========================================================= */

$satelliteCheck = $conn->prepare("
    SELECT satellite_id
    FROM satellites
    WHERE satellite_id = ?
    AND status = 'Active'
    LIMIT 1
");


if (!$satelliteCheck) {
    cleanupQuarantineFile();
    cleanupRegistrationSession();

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

    cleanupQuarantineFile();
    cleanupRegistrationSession();

    jsonResponse(
        false,
        "The selected barangay satellite is no longer available.",
        422
    );
}


$satelliteCheck->close();


/* =========================================================
   DUPLICATE ACCOUNT CHECK
========================================================= */

/*
 * Check residents and officials again.
 *
 * This is important because the database could have
 * changed between send_otp.php and this final step.
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
    cleanupQuarantineFile();
    cleanupRegistrationSession();

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
        cleanupQuarantineFile();
        cleanupRegistrationSession();

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

    cleanupQuarantineFile();
    cleanupRegistrationSession();

    jsonResponse(
        false,
        "Username or Email already exists.",
        409
    );
}


/* =========================================================
   VERIFY QUARANTINE FILE
========================================================= */

$fileName =
    basename(
        (string)(
            $_SESSION['temp_file_name'] ?? ''
        )
    );


if (
    $fileName === '' ||
    !preg_match(
        '/^[A-Za-z0-9._-]+$/',
        $fileName
    )
) {
    cleanupQuarantineFile();
    cleanupRegistrationSession();

    jsonResponse(
        false,
        "Invalid uploaded file.",
        422
    );
}


/* =========================================================
   QUARANTINE DIRECTORY
========================================================= */

$quarantineDirectory =
    realpath(
        __DIR__ .
        DIRECTORY_SEPARATOR .
        "../UPLOADS/quarantine"
    );


if (
    $quarantineDirectory === false ||
    !is_dir($quarantineDirectory)
) {
    cleanupQuarantineFile();
    cleanupRegistrationSession();

    jsonResponse(
        false,
        "Upload quarantine directory is unavailable.",
        500
    );
}


$quarantinePath =
    $quarantineDirectory .
    DIRECTORY_SEPARATOR .
    $fileName;


$realQuarantineFile =
    realpath(
        $quarantinePath
    );


if (
    $realQuarantineFile === false ||
    !is_file($realQuarantineFile)
) {
    cleanupRegistrationSession();

    jsonResponse(
        false,
        "Uploaded file is missing. Please register again.",
        422
    );
}


/*
 * Prevent path traversal / unexpected file access.
 */
$quarantinePrefix =
    rtrim(
        $quarantineDirectory,
        DIRECTORY_SEPARATOR
    ) .
    DIRECTORY_SEPARATOR;


if (
    strncmp(
        $realQuarantineFile,
        $quarantinePrefix,
        strlen($quarantinePrefix)
    ) !== 0
) {
    cleanupQuarantineFile();
    cleanupRegistrationSession();

    jsonResponse(
        false,
        "Invalid uploaded file location.",
        403
    );
}


/* =========================================================
   VERIFY FILE SIZE AGAIN
========================================================= */

$fileSize =
    filesize(
        $realQuarantineFile
    );


if (
    $fileSize === false ||
    $fileSize <= 0 ||
    $fileSize > 5 * 1024 * 1024
) {
    cleanupQuarantineFile();
    cleanupRegistrationSession();

    jsonResponse(
        false,
        "Uploaded file is invalid.",
        422
    );
}


/* =========================================================
   ENCRYPT ID NUMBER
========================================================= */

/*
 * New IDs use the authenticated AES-256-GCM format configured for profile IDs.
 * Existing values remain readable through bms_decrypt_profile_id().
 */
$encryptedId = bms_encrypt_profile_id($id_number);

if ($encryptedId === false) {

    cleanupQuarantineFile();
    cleanupRegistrationSession();

    jsonResponse(
        false,
        "ID encryption is not configured or failed.",
        500
    );
}


/* =========================================================
   FINAL UPLOAD DIRECTORY
========================================================= */

$uploadDirectoryPath =
    __DIR__ .
    DIRECTORY_SEPARATOR .
    "../uploads";


if (
    !is_dir($uploadDirectoryPath) &&
    !mkdir(
        $uploadDirectoryPath,
        0755,
        true
    )
) {
    cleanupQuarantineFile();
    cleanupRegistrationSession();

    jsonResponse(
        false,
        "Final upload storage is unavailable.",
        500
    );
}


$uploadDirectory =
    realpath(
        $uploadDirectoryPath
    );


if (
    $uploadDirectory === false ||
    !is_dir($uploadDirectory)
) {
    cleanupQuarantineFile();
    cleanupRegistrationSession();

    jsonResponse(
        false,
        "Final upload storage is unavailable.",
        500
    );
}


/* =========================================================
   FINAL FILE NAME
========================================================= */

$finalFileName =
    $fileName;


$finalFilePath =
    $uploadDirectory .
    DIRECTORY_SEPARATOR .
    $finalFileName;


/*
 * The randomized filename should normally never exist,
 * but check anyway.
 */
if (file_exists($finalFilePath)) {

    cleanupQuarantineFile();
    cleanupRegistrationSession();

    jsonResponse(
        false,
        "A file conflict occurred. Please try again.",
        500
    );
}


/* =========================================================
   DATABASE TRANSACTION
========================================================= */

$conn->begin_transaction();

$fileMoved = false;


try {

    /* =====================================================
       MOVE CLEAN FILE FROM QUARANTINE TO FINAL STORAGE
    ===================================================== */

    if (
        !rename(
            $realQuarantineFile,
            $finalFilePath
        )
    ) {
        throw new Exception(
            "Unable to move uploaded file."
        );
    }


    $fileMoved = true;


    /* =====================================================
       INSERT RESIDENT
    ===================================================== */

    if ($account_type === 'resident') {

        $stmt = $conn->prepare("
            INSERT INTO residents
            (
                name,
                username,
                email,
                contact_number,
                password,
                proof_file,
                id_number,
                satellite_id,
                status
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                'Pending'
            )
        ");


        if (!$stmt) {
            throw new Exception(
                "Unable to prepare resident registration."
            );
        }


        $stmt->bind_param(
            "sssssssi",
            $name,
            $username,
            $email,
            $contact_number,
            $passwordHash,
            $finalFileName,
            $encryptedId,
            $satellite_id
        );


        if (!$stmt->execute()) {

            $error =
                $stmt->error;

            $stmt->close();

            throw new Exception(
                $error
            );
        }


        $stmt->close();


    /* =====================================================
       INSERT OFFICIAL
    ===================================================== */

    } else {

        $stmt = $conn->prepare("
            INSERT INTO officials
            (
                name,
                username,
                email,
                contact_number,
                password,
                department,
                satellite_id,
                proof_file,
                id_number,
                status
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                'Pending'
            )
        ");


        if (!$stmt) {
            throw new Exception(
                "Unable to prepare official registration."
            );
        }


        $stmt->bind_param(
            "ssssssiss",
            $name,
            $username,
            $email,
            $contact_number,
            $passwordHash,
            $department,
            $satellite_id,
            $finalFileName,
            $encryptedId
        );


        if (!$stmt->execute()) {

            $error =
                $stmt->error;

            $stmt->close();

            throw new Exception(
                $error
            );
        }


        $stmt->close();
    }


    /* =====================================================
       COMMIT
    ===================================================== */

    if (!$conn->commit()) {
        throw new Exception(
            "Unable to complete registration."
        );
    }


} catch (Throwable $e) {

    /*
     * Roll back database changes.
     */
    $conn->rollback();


    /*
     * If the file was already moved to the final
     * directory, delete it.
     */
    if (
        $fileMoved &&
        is_file($finalFilePath)
    ) {
        @unlink($finalFilePath);
    }


    /*
     * If the file wasn't moved yet, remove the
     * quarantine copy.
     */
    if (
        !$fileMoved &&
        is_file($realQuarantineFile)
    ) {
        @unlink($realQuarantineFile);
    }


    /*
     * Do not expose database/internal errors
     * to the user.
     */
    error_log(
        "BMS registration error: " .
        $e->getMessage()
    );


    cleanupRegistrationSession();


    jsonResponse(
        false,
        "Unable to complete registration. Please try again later.",
        500
    );
}


/* =========================================================
   SUCCESS
========================================================= */

/*
 * Registration is complete.
 *
 * Keep the normal session alive, but remove all
 * temporary registration information.
 */
cleanupRegistrationSession();


/*
 * Regenerate the session ID after successful
 * registration to reduce session-fixation risk.
 *
 * The CSRF token remains available.
 */
session_regenerate_id(true);


jsonResponse(
    true,
    "Registration successful. Your account is now pending approval."
);

?>
