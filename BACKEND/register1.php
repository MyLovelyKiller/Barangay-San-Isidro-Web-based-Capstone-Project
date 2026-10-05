<?php

/* =========================================================
   START SESSION / OUTPUT BUFFER
========================================================= */

ob_start();
session_start();

include "db_connect.php";

/*
 * Clear accidental output from db_connect.php
 * before sending JSON.
 */
ob_clean();

header('Content-Type: application/json; charset=UTF-8');


/* =========================================================
   1. CSRF TOKEN CHECK
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

    echo json_encode([
        "success" => false,
        "message" => "Invalid CSRF token."
    ]);

    exit();
}


/* =========================================================
   2. REQUEST METHOD
========================================================= */

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Invalid request method."
    ]);

    exit();
}


/* =========================================================
   3. HONEYPOT CHECK
========================================================= */

if (!empty($_POST['bms_reg_v_field'])) {

    echo json_encode([
        "success" => false,
        "message" => "Bot activity detected."
    ]);

    exit();
}


/* =========================================================
   4. REGISTRATION SECURITY SESSION CHECK
========================================================= */

if (
    empty($_SESSION['registration_security_verified']) ||
    $_SESSION['registration_security_verified'] !== true
) {

    echo json_encode([
        "success" => false,
        "message" =>
            "Security verification expired. Please register again."
    ]);

    exit();
}


/* =========================================================
   5. OTP VALIDATION
========================================================= */

$user_otp = trim(
    (string)($_POST['otp_code'] ?? '')
);


if (
    $user_otp === '' ||
    !isset($_SESSION['otp'])
) {

    echo json_encode([
        "success" => false,
        "message" =>
            "Missing OTP or session expired."
    ]);

    exit();
}


/*
 * Use hash_equals() rather than loose comparison.
 *
 * OTP is converted to string to ensure both values
 * are compared consistently.
 */
$sessionOtp = (string)$_SESSION['otp'];

if (!hash_equals($sessionOtp, $user_otp)) {

    echo json_encode([
        "success" => false,
        "message" =>
            "Incorrect OTP. Please check your email."
    ]);

    exit();
}


/* =========================================================
   6. RETRIEVE TEMPORARY REGISTRATION DATA
========================================================= */

$userData =
    $_SESSION['temp_user_data'] ?? null;

$new_file_name =
    $_SESSION['temp_file_name'] ?? null;


if (
    !is_array($userData) ||
    empty($userData)
) {

    echo json_encode([
        "success" => false,
        "message" =>
            "Registration data not found."
    ]);

    exit();
}


/* =========================================================
   7. DATA PREPARATION
========================================================= */

$account_type = trim(
    (string)($userData['account_type'] ?? '')
);

$department = strtoupper(
    trim(
        (string)($userData['department'] ?? '')
    )
);

$satellite_id = (int)(
    $userData['satellite_id'] ?? 0
);

$name = trim(
    (string)($userData['name'] ?? '')
);

$username = trim(
    (string)($userData['username'] ?? '')
);

$email = trim(
    (string)($userData['email'] ?? '')
);

$contact_number = trim(
    (string)($userData['contact_number'] ?? '')
);


/*
 * IMPORTANT:
 * Do NOT trim passwords.
 */
$password =
    (string)($userData['password'] ?? '');

$id_number = trim(
    (string)($userData['id_number'] ?? '')
);


/* =========================================================
   8. BASIC DATA VALIDATION
========================================================= */

if (
    $account_type !== 'resident' &&
    $account_type !== 'official'
) {

    echo json_encode([
        "success" => false,
        "message" => "Invalid account type."
    ]);

    exit();
}


if ($name === '' || $username === '' || $email === '') {

    echo json_encode([
        "success" => false,
        "message" => "Required registration information is missing."
    ]);

    exit();
}


if ($password === '') {

    echo json_encode([
        "success" => false,
        "message" => "Password is required."
    ]);

    exit();
}


if ($satellite_id <= 0) {

    echo json_encode([
        "success" => false,
        "message" => "Invalid barangay satellite."
    ]);

    exit();
}


/* =========================================================
   9. TEMPORARY FILE VALIDATION
========================================================= */

if (
    empty($new_file_name) ||
    !is_string($new_file_name)
) {

    echo json_encode([
        "success" => false,
        "message" => "Registration proof file is missing."
    ]);

    exit();
}


/*
 * Only allow a safe filename.
 *
 * This prevents values containing:
 * ../
 * directory separators
 * null bytes
 * or other unexpected path data.
 */
if (
    !preg_match(
        '/^[A-Za-z0-9._-]+$/',
        $new_file_name
    )
) {

    echo json_encode([
        "success" => false,
        "message" => "Invalid registration file."
    ]);

    exit();
}


/* =========================================================
   10. QUARANTINE FILE PATH
========================================================= */

/*
 * The file was already scanned by ClamAV in send_otp.php.
 *
 * It remains in quarantine until the user successfully
 * verifies the OTP.
 */
$quarantine_directory = realpath(
    __DIR__ . DIRECTORY_SEPARATOR . "../UPLOADS/quarantine"
);


if (
    $quarantine_directory === false ||
    !is_dir($quarantine_directory)
) {

    echo json_encode([
        "success" => false,
        "message" => "Upload quarantine directory not found."
    ]);

    exit();
}


/*
 * Build the quarantine file path using the resolved directory.
 */
$quarantine_file_path =
    $quarantine_directory .
    DIRECTORY_SEPARATOR .
    $new_file_name;


/* =========================================================
   11. VERIFY QUARANTINE FILE EXISTS
========================================================= */

if (!is_file($quarantine_file_path)) {

    echo json_encode([
        "success" => false,
        "message" => "Registration proof file was not found."
    ]);

    exit();
}


/* =========================================================
   12. VERIFY FILE IS INSIDE QUARANTINE DIRECTORY
========================================================= */

$real_file_path = realpath($quarantine_file_path);

if ($real_file_path === false) {

    echo json_encode([
        "success" => false,
        "message" => "Invalid registration file."
    ]);

    exit();
}


$quarantine_directory_normalized =
    rtrim(
        str_replace(
            '\\',
            '/',
            $quarantine_directory
        ),
        '/'
    );


$real_file_path_normalized =
    str_replace(
        '\\',
        '/',
        $real_file_path
    );


if (
    strpos(
        $real_file_path_normalized,
        $quarantine_directory_normalized . '/'
    ) !== 0
) {

    echo json_encode([
        "success" => false,
        "message" => "Invalid registration file location."
    ]);

    exit();
}


/*
 * IMPORTANT:
 * Do NOT run ClamAV again here.
 *
 * send_otp.php already scanned this exact file before
 * placing it in quarantine.
 */


/* =========================================================
   13. ENCRYPTION CONFIGURATION
========================================================= */

$ciphering = "AES-128-CTR";

define(
    "ENCRYPTION_KEY",
    "BarangaySanIsidro2026"
);

define(
    "ENCRYPTION_IV",
    "1234567891011121"
);


/* =========================================================
   14. ID NUMBER ENCRYPTION
========================================================= */

$clean_id = preg_replace(
    '/[^0-9]/',
    '',
    $id_number
);


/*
 * Make sure an ID was actually provided.
 */
if ($clean_id === '') {

    @unlink($real_file_path);

    echo json_encode([
        "success" => false,
        "message" => "Invalid ID number."
    ]);

    exit();
}


$encrypted_id = openssl_encrypt(
    $clean_id,
    $ciphering,
    ENCRYPTION_KEY,
    0,
    ENCRYPTION_IV
);


if ($encrypted_id === false) {

    @unlink($real_file_path);

    echo json_encode([
        "success" => false,
        "message" => "Unable to secure ID number."
    ]);

    exit();
}


/* =========================================================
   15. PASSWORD HASHING
========================================================= */

$hashed_password = password_hash(
    $password,
    PASSWORD_DEFAULT
);


if ($hashed_password === false) {

    @unlink($real_file_path);

    echo json_encode([
        "success" => false,
        "message" => "Unable to secure password."
    ]);

    exit();
}


/* =========================================================
   16. VERIFY SATELLITE
========================================================= */

$satellite_check = $conn->prepare("
    SELECT satellite_id
    FROM satellites
    WHERE satellite_id = ?
    AND status = 'Active'
    LIMIT 1
");


if (!$satellite_check) {

    @unlink($real_file_path);

    echo json_encode([
        "success" => false,
        "message" => "Unable to verify barangay satellite."
    ]);

    exit();
}


$satellite_check->bind_param(
    "i",
    $satellite_id
);

$satellite_check->execute();

$satellite_check->store_result();


if ($satellite_check->num_rows === 0) {

    $satellite_check->close();

    @unlink($real_file_path);

    echo json_encode([
        "success" => false,
        "message" =>
            "Invalid barangay satellite selected."
    ]);

    exit();
}


$satellite_check->close();


/* =========================================================
   17. PREPARE FINAL UPLOAD DIRECTORY
========================================================= */

$uploads_directory_path =
    __DIR__ .
    DIRECTORY_SEPARATOR .
    "../uploads";


if (
    !is_dir($uploads_directory_path) &&
    !mkdir($uploads_directory_path, 0755, true)
) {

    @unlink($real_file_path);

    echo json_encode([
        "success" => false,
        "message" => "Upload storage is unavailable."
    ]);

    exit();
}


$uploads_directory = realpath(
    $uploads_directory_path
);


if (
    $uploads_directory === false ||
    !is_dir($uploads_directory)
) {

    @unlink($real_file_path);

    echo json_encode([
        "success" => false,
        "message" => "Upload directory not found."
    ]);

    exit();
}


/*
 * Final permanent file location.
 */
$final_file_path =
    $uploads_directory .
    DIRECTORY_SEPARATOR .
    $new_file_name;


/* =========================================================
   18. RESIDENT REGISTRATION
========================================================= */

if ($account_type === "resident") {


    /* =====================================================
       CHECK DUPLICATE USERNAME / EMAIL
    ===================================================== */

    $check_stmt = $conn->prepare("
        SELECT resident_id
        FROM residents
        WHERE username = ?
        OR email = ?
        LIMIT 1
    ");


    if (!$check_stmt) {

        @unlink($real_file_path);

        echo json_encode([
            "success" => false,
            "message" =>
                "Unable to verify registration information."
        ]);

        exit();
    }


    $check_stmt->bind_param(
        "ss",
        $username,
        $email
    );

    $check_stmt->execute();

    $check_stmt->store_result();


    if ($check_stmt->num_rows > 0) {

        $check_stmt->close();

        @unlink($real_file_path);

        echo json_encode([
            "success" => false,
            "message" =>
                "Username or Email already exists."
        ]);

        exit();
    }


    $check_stmt->close();


    /* =====================================================
       MOVE CLEAN FILE FROM QUARANTINE TO FINAL STORAGE
    ===================================================== */

    if (!rename($real_file_path, $final_file_path)) {

        echo json_encode([
            "success" => false,
            "message" =>
                "Failed to finalize registration proof file."
        ]);

        exit();
    }


    /*
     * From this point onward, the file is in permanent
     * upload storage.
     */
    $real_file_path = $final_file_path;


    /* =====================================================
       INSERT RESIDENT
    ===================================================== */

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
            'pending'
        )
    ");


    if (!$stmt) {

        @unlink($real_file_path);

        echo json_encode([
            "success" => false,
            "message" =>
                "Unable to prepare registration."
        ]);

        exit();
    }


    $stmt->bind_param(
        "sssssssi",
        $name,
        $username,
        $email,
        $contact_number,
        $hashed_password,
        $new_file_name,
        $encrypted_id,
        $satellite_id
    );


    if ($stmt->execute()) {

        $stmt->close();

        /*
         * Registration completed.
         *
         * Destroy the temporary registration session.
         */
        session_unset();
        session_destroy();


        echo json_encode([
            "success" => true
        ]);

        exit();
    }


    $stmt->close();


    /*
     * Database insertion failed.
     * Remove finalized file because registration
     * was not completed.
     */
    if (is_file($real_file_path)) {
        @unlink($real_file_path);
    }


    echo json_encode([
        "success" => false,
        "message" =>
            "Could not complete registration."
    ]);

    exit();
}


/* =========================================================
   19. OFFICIAL REGISTRATION
========================================================= */

if ($account_type === "official") {


    /* =====================================================
       VALIDATE DEPARTMENT
    ===================================================== */

    $allowed_departments = [
        'ADMIN',
        'BPSO',
        'CLEARANCE',
        'LUPON'
    ];


    if (
        !in_array(
            $department,
            $allowed_departments,
            true
        )
    ) {

        @unlink($real_file_path);

        echo json_encode([
            "success" => false,
            "message" =>
                "Invalid department selected."
        ]);

        exit();
    }


    /* =====================================================
       CHECK DUPLICATE USERNAME / EMAIL
    ===================================================== */

    $check_stmt = $conn->prepare("
        SELECT official_id
        FROM officials
        WHERE username = ?
        OR email = ?
        LIMIT 1
    ");


    if (!$check_stmt) {

        @unlink($real_file_path);

        echo json_encode([
            "success" => false,
            "message" =>
                "Unable to verify registration information."
        ]);

        exit();
    }


    $check_stmt->bind_param(
        "ss",
        $username,
        $email
    );

    $check_stmt->execute();

    $check_stmt->store_result();


    if ($check_stmt->num_rows > 0) {

        $check_stmt->close();

        @unlink($real_file_path);

        echo json_encode([
            "success" => false,
            "message" =>
                "Username or Email already exists."
        ]);

        exit();
    }


    $check_stmt->close();


    /* =====================================================
       MOVE CLEAN FILE FROM QUARANTINE TO FINAL STORAGE
    ===================================================== */

    if (!rename($real_file_path, $final_file_path)) {

        echo json_encode([
            "success" => false,
            "message" =>
                "Failed to finalize registration proof file."
        ]);

        exit();
    }


    /*
     * From this point onward, the file is in permanent
     * upload storage.
     */
    $real_file_path = $final_file_path;


    /* =====================================================
       INSERT OFFICIAL
    ===================================================== */

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
            'pending'
        )
    ");


    if (!$stmt) {

        @unlink($real_file_path);

        echo json_encode([
            "success" => false,
            "message" =>
                "Unable to prepare registration."
        ]);

        exit();
    }


    $stmt->bind_param(
        "ssssssiss",
        $name,
        $username,
        $email,
        $contact_number,
        $hashed_password,
        $department,
        $satellite_id,
        $new_file_name,
        $encrypted_id
    );


    if ($stmt->execute()) {

        $stmt->close();

        /*
         * Registration completed.
         */
        session_unset();
        session_destroy();


        echo json_encode([
            "success" => true
        ]);

        exit();
    }


    $stmt->close();


    /*
     * Database insertion failed.
     * Remove finalized file.
     */
    if (is_file($real_file_path)) {
        @unlink($real_file_path);
    }


    echo json_encode([
        "success" => false,
        "message" =>
            "Could not complete registration."
    ]);

    exit();
}


/* =========================================================
   20. FALLBACK
========================================================= */

if (is_file($real_file_path)) {
    @unlink($real_file_path);
}


echo json_encode([
    "success" => false,
    "message" =>
        "Could not complete registration."
]);

exit();

?>