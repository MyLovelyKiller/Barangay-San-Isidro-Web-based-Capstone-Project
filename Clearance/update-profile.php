<?php

session_start();

require_once '../BACKEND/db_connect.php';


/* =========================================================
   1. CHECK LOGIN
   ========================================================= */

if (!isset($_SESSION['official_id'])) {
    header("Location: /BMS/CODES/login.php");
    exit();
}

$official_id = (int)$_SESSION['official_id'];

if ($official_id <= 0) {
    header("Location: /BMS/CODES/login.php");
    exit();
}


/* =========================================================
   2. ONLY ALLOW POST REQUEST
   ========================================================= */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header("Allow: POST");
    exit("Method not allowed.");
}


/* =========================================================
   3. VERIFY CLEARANCE OFFICER FROM DATABASE
   ========================================================= */

$verifyStmt = $conn->prepare("
    SELECT
        official_id,
        department,
        password
    FROM officials
    WHERE official_id = ?
      AND department = 'CLEARANCE'
    LIMIT 1
");

if (!$verifyStmt) {

    error_log(
        "Clearance profile verification prepare failed: " .
        $conn->error
    );

    http_response_code(500);
    exit("Unable to verify account.");
}

$verifyStmt->bind_param(
    "i",
    $official_id
);

if (!$verifyStmt->execute()) {

    error_log(
        "Clearance profile verification execute failed: " .
        $verifyStmt->error
    );

    $verifyStmt->close();

    http_response_code(500);
    exit("Unable to verify account.");
}

$verifyResult = $verifyStmt->get_result();

if (
    !$verifyResult ||
    $verifyResult->num_rows !== 1
) {

    $verifyStmt->close();

    http_response_code(403);
    exit("Access denied.");
}

$officialAccount = $verifyResult->fetch_assoc();

$verifyStmt->close();


/* =========================================================
   4. CSRF TOKEN VALIDATION
   ========================================================= */

$submitted_token = $_POST['csrf_token'] ?? '';

if (
    !is_string($submitted_token) ||
    $submitted_token === '' ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals(
        (string)$_SESSION['csrf_token'],
        $submitted_token
    )
) {

    http_response_code(403);
    exit("Invalid CSRF token.");
}


/* =========================================================
   5. GET PROFILE INFORMATION
   ========================================================= */

$name = trim($_POST['name'] ?? '');
$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$contact_number = trim($_POST['contact_number'] ?? '');
$position = trim($_POST['position'] ?? '');


/* =========================================================
   6. GET PASSWORD FIELDS
   ========================================================= */

$current_password = $_POST['current_password'] ?? '';
$new_password = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

if (!is_string($current_password)) {
    $current_password = '';
}

if (!is_string($new_password)) {
    $new_password = '';
}

if (!is_string($confirm_password)) {
    $confirm_password = '';
}


/* =========================================================
   7. BASIC PROFILE VALIDATION
   ========================================================= */

if ($name === '') {

    header(
        "Location: edit-profile.php?error=invalid_name"
    );

    exit();
}

if (mb_strlen($name) > 150) {

    header(
        "Location: edit-profile.php?error=invalid_name"
    );

    exit();
}


/* =========================================================
   USERNAME VALIDATION
   ========================================================= */

if ($username === '') {

    header(
        "Location: edit-profile.php?error=invalid_username"
    );

    exit();
}

if (
    mb_strlen($username) < 3 ||
    mb_strlen($username) > 100
) {

    header(
        "Location: edit-profile.php?error=invalid_username"
    );

    exit();
}


/*
 * Allowed username characters:
 *
 * A-Z
 * a-z
 * 0-9
 * _
 * .
 * -
 */

if (!preg_match(
    '/^[A-Za-z0-9._-]+$/',
    $username
)) {

    header(
        "Location: edit-profile.php?error=invalid_username"
    );

    exit();
}


/* =========================================================
   EMAIL VALIDATION
   ========================================================= */

if (
    $email !== '' &&
    !filter_var(
        $email,
        FILTER_VALIDATE_EMAIL
    )
) {

    header(
        "Location: edit-profile.php?error=invalid_email"
    );

    exit();
}

if (mb_strlen($email) > 150) {

    header(
        "Location: edit-profile.php?error=invalid_email"
    );

    exit();
}


/* =========================================================
   CONTACT NUMBER VALIDATION
   ========================================================= */

if (mb_strlen($contact_number) > 50) {

    header(
        "Location: edit-profile.php?error=invalid_contact"
    );

    exit();
}


/* =========================================================
   POSITION VALIDATION
   ========================================================= */

if (mb_strlen($position) > 150) {

    header(
        "Location: edit-profile.php?error=invalid_position"
    );

    exit();
}


/* =========================================================
   8. CHECK USERNAME DUPLICATE
   ========================================================= */

$duplicateStmt = $conn->prepare("
    SELECT official_id
    FROM officials
    WHERE username = ?
      AND official_id <> ?
    LIMIT 1
");

if (!$duplicateStmt) {

    error_log(
        "Clearance username check prepare failed: " .
        $conn->error
    );

    http_response_code(500);
    exit("Unable to validate account information.");
}

$duplicateStmt->bind_param(
    "si",
    $username,
    $official_id
);

if (!$duplicateStmt->execute()) {

    error_log(
        "Clearance username check execute failed: " .
        $duplicateStmt->error
    );

    $duplicateStmt->close();

    http_response_code(500);
    exit("Unable to validate account information.");
}

$duplicateResult = $duplicateStmt->get_result();

if (
    $duplicateResult &&
    $duplicateResult->num_rows > 0
) {

    $duplicateStmt->close();

    header(
        "Location: edit-profile.php?error=username_exists"
    );

    exit();
}

$duplicateStmt->close();


/* =========================================================
   9. CHECK EMAIL DUPLICATE
   ========================================================= */

if ($email !== '') {

    $emailStmt = $conn->prepare("
        SELECT official_id
        FROM officials
        WHERE email = ?
          AND official_id <> ?
        LIMIT 1
    ");

    if (!$emailStmt) {

        error_log(
            "Clearance email check prepare failed: " .
            $conn->error
        );

        http_response_code(500);
        exit("Unable to validate account information.");
    }

    $emailStmt->bind_param(
        "si",
        $email,
        $official_id
    );

    if (!$emailStmt->execute()) {

        error_log(
            "Clearance email check execute failed: " .
            $emailStmt->error
        );

        $emailStmt->close();

        http_response_code(500);
        exit("Unable to validate account information.");
    }

    $emailResult = $emailStmt->get_result();

    if (
        $emailResult &&
        $emailResult->num_rows > 0
    ) {

        $emailStmt->close();

        header(
            "Location: edit-profile.php?error=email_exists"
        );

        exit();
    }

    $emailStmt->close();
}


/* =========================================================
   10. PASSWORD CHANGE VALIDATION
   ========================================================= */

/*
 * If all password fields are empty:
 *     No password change.
 *
 * If any password field is provided:
 *     All three must be provided.
 */

$changing_password = (
    $current_password !== '' ||
    $new_password !== '' ||
    $confirm_password !== ''
);


$new_password_hash = null;


if ($changing_password) {


    /* -----------------------------------------------------
       Current password required
       ----------------------------------------------------- */

    if ($current_password === '') {

        header(
            "Location: edit-profile.php?error=current_password_required"
        );

        exit();
    }


    /* -----------------------------------------------------
       New password required
       ----------------------------------------------------- */

    if ($new_password === '') {

        header(
            "Location: edit-profile.php?error=new_password_required"
        );

        exit();
    }


    /* -----------------------------------------------------
       Confirmation required
       ----------------------------------------------------- */

    if ($confirm_password === '') {

        header(
            "Location: edit-profile.php?error=confirm_password_required"
        );

        exit();
    }


    /* -----------------------------------------------------
       Minimum password length
       ----------------------------------------------------- */

    if (strlen($new_password) < 8) {

        header(
            "Location: edit-profile.php?error=password_too_short"
        );

        exit();
    }


    /*
     * Prevent excessively large password input.
     * This also helps protect password hashing resources.
     */

    if (strlen($new_password) > 255) {

        header(
            "Location: edit-profile.php?error=password_too_long"
        );

        exit();
    }


    /* -----------------------------------------------------
       Password confirmation
       ----------------------------------------------------- */

    if (!hash_equals(
        $new_password,
        $confirm_password
    )) {

        header(
            "Location: edit-profile.php?error=password_mismatch"
        );

        exit();
    }


    /* -----------------------------------------------------
       Verify current password
       ----------------------------------------------------- */

    $stored_password = (string)(
        $officialAccount['password'] ?? ''
    );

    if (
        $stored_password === '' ||
        !password_verify(
            $current_password,
            $stored_password
        )
    ) {

        header(
            "Location: edit-profile.php?error=current_password_invalid"
        );

        exit();
    }


    /* -----------------------------------------------------
       Prevent reusing the same password
       ----------------------------------------------------- */

    if (password_verify(
        $new_password,
        $stored_password
    )) {

        header(
            "Location: edit-profile.php?error=password_same"
        );

        exit();
    }


    /* -----------------------------------------------------
       Generate secure password hash
       ----------------------------------------------------- */

    $new_password_hash = password_hash(
        $new_password,
        PASSWORD_DEFAULT
    );

    if ($new_password_hash === false) {

        error_log(
            "Clearance password hashing failed for official_id: " .
            $official_id
        );

        header(
            "Location: edit-profile.php?error=password_failed"
        );

        exit();
    }
}


/* =========================================================
   11. CLAMAV SCANNER
   ========================================================= */

function scanFileWithClamAV(
    $filePath,
    &$scanMessage = null
) {

    /*
     * Change this path if your ClamAV installation
     * is located somewhere else.
     */

    $clamScanPath =
        'C:\Users\Gary\Downloads\clamav-1.5.4.win.x64\clamav-1.5.4.win.x64\clamscan.exe';


    if (!is_file($clamScanPath)) {

        $scanMessage =
            'ClamAV scanner was not found.';

        return false;
    }


    if (!is_file($filePath)) {

        $scanMessage =
            'File to scan was not found.';

        return false;
    }


    if (!function_exists('exec')) {

        $scanMessage =
            'PHP exec() function is disabled.';

        return false;
    }


    $command =
        escapeshellarg($clamScanPath) .
        ' --no-summary ' .
        escapeshellarg($filePath);


    $output = [];

    $exitCode = -1;


    exec(
        $command,
        $output,
        $exitCode
    );


    /*
     * ClamAV:
     *
     * 0 = Clean
     * 1 = Infected
     * Other = Scan error
     */

    if ($exitCode === 0) {

        $scanMessage = 'Clean';

        return true;
    }


    if ($exitCode === 1) {

        $scanMessage =
            'File was detected as infected.';

        return false;
    }


    $scanMessage =
        'ClamAV scan failed. Exit code: ' .
        $exitCode;

    return false;
}


/* =========================================================
   12. HANDLE PROFILE PICTURE
   ========================================================= */

$picture_profile = null;
$new_picture_path = null;


if (
    isset($_FILES['picture_profile']) &&
    $_FILES['picture_profile']['error'] !==
    UPLOAD_ERR_NO_FILE
) {


    /* -----------------------------------------------------
       Upload error
       ----------------------------------------------------- */

    if (
        $_FILES['picture_profile']['error'] !==
        UPLOAD_ERR_OK
    ) {

        header(
            "Location: edit-profile.php?error=upload_failed"
        );

        exit();
    }


    /* -----------------------------------------------------
       Maximum size: 5 MB
       ----------------------------------------------------- */

    $max_file_size =
        5 * 1024 * 1024;


    if (
        !isset($_FILES['picture_profile']['size']) ||
        $_FILES['picture_profile']['size'] <= 0 ||
        $_FILES['picture_profile']['size'] >
        $max_file_size
    ) {

        header(
            "Location: edit-profile.php?error=file_too_large"
        );

        exit();
    }


    /* -----------------------------------------------------
       Temporary upload validation
       ----------------------------------------------------- */

    $tmpFile =
        $_FILES['picture_profile']['tmp_name'];


    if (
        !is_uploaded_file($tmpFile) ||
        !is_file($tmpFile)
    ) {

        header(
            "Location: edit-profile.php?error=invalid_file"
        );

        exit();
    }


    /* -----------------------------------------------------
       Directories
       ----------------------------------------------------- */

    $target_dir =
        "../IMAGES/";

    $quarantine_dir =
        "../UPLOADS/quarantine/";


    /* -----------------------------------------------------
       Create directories
       ----------------------------------------------------- */

    if (!is_dir($target_dir)) {

        if (!mkdir(
            $target_dir,
            0755,
            true
        )) {

            header(
                "Location: edit-profile.php?error=storage_failed"
            );

            exit();
        }
    }


    if (!is_dir($quarantine_dir)) {

        if (!mkdir(
            $quarantine_dir,
            0755,
            true
        )) {

            header(
                "Location: edit-profile.php?error=storage_failed"
            );

            exit();
        }
    }


    /* -----------------------------------------------------
       Validate extension
       ----------------------------------------------------- */

    $file_extension =
        strtolower(
            pathinfo(
                $_FILES['picture_profile']['name'],
                PATHINFO_EXTENSION
            )
        );


    $allowed_extensions = [
        'jpg',
        'jpeg',
        'png',
        'gif'
    ];


    if (!in_array(
        $file_extension,
        $allowed_extensions,
        true
    )) {

        header(
            "Location: edit-profile.php?error=invalid_file"
        );

        exit();
    }


    /* -----------------------------------------------------
       Verify actual image content
       ----------------------------------------------------- */

    $image_info =
        @getimagesize($tmpFile);


    if ($image_info === false) {

        header(
            "Location: edit-profile.php?error=invalid_image"
        );

        exit();
    }


    /* -----------------------------------------------------
       Match MIME/image type with extension
       ----------------------------------------------------- */

    $allowed_image_types = [

        IMAGETYPE_JPEG => [
            'jpg',
            'jpeg'
        ],

        IMAGETYPE_PNG => [
            'png'
        ],

        IMAGETYPE_GIF => [
            'gif'
        ]

    ];


    $detected_image_type =
        $image_info[2] ?? 0;


    if (
        !isset(
            $allowed_image_types[
                $detected_image_type
            ]
        ) ||
        !in_array(
            $file_extension,
            $allowed_image_types[
                $detected_image_type
            ],
            true
        )
    ) {

        header(
            "Location: edit-profile.php?error=invalid_image"
        );

        exit();
    }


    /* -----------------------------------------------------
       Generate secure random filename
       ----------------------------------------------------- */

    try {

        $random_name =
            bin2hex(
                random_bytes(16)
            );

    } catch (Throwable $e) {

        error_log(
            "Clearance profile picture random filename error: " .
            $e->getMessage()
        );

        header(
            "Location: edit-profile.php?error=upload_failed"
        );

        exit();
    }


    $file_name =
        'official_' .
        $official_id .
        '_' .
        $random_name .
        '.' .
        $file_extension;


    $quarantine_path =
        $quarantine_dir .
        $file_name;


    $target_file =
        $target_dir .
        $file_name;


    /* -----------------------------------------------------
       Move upload to quarantine
       ----------------------------------------------------- */

    if (!move_uploaded_file(
        $tmpFile,
        $quarantine_path
    )) {

        header(
            "Location: edit-profile.php?error=upload_failed"
        );

        exit();
    }


    /* -----------------------------------------------------
       CLAMAV SCAN
       ----------------------------------------------------- */

    $scanMessage = '';


    $scanResult =
        scanFileWithClamAV(
            $quarantine_path,
            $scanMessage
        );


    /* -----------------------------------------------------
       Reject infected file or scanner failure
       ----------------------------------------------------- */

    if (!$scanResult) {

        if (file_exists($quarantine_path)) {
            @unlink($quarantine_path);
        }


        if (
            stripos(
                $scanMessage,
                'infected'
            ) !== false
        ) {

            $errorCode =
                'infected_file';

        } else {

            $errorCode =
                'scan_failed';
        }


        header(
            "Location: edit-profile.php?error=" .
            $errorCode
        );

        exit();
    }


    /* -----------------------------------------------------
       Move clean file to permanent storage
       ----------------------------------------------------- */

    if (!rename(
        $quarantine_path,
        $target_file
    )) {

        if (file_exists($quarantine_path)) {
            @unlink($quarantine_path);
        }

        header(
            "Location: edit-profile.php?error=storage_failed"
        );

        exit();
    }


    /*
     * Store filename only.
     */

    $picture_profile =
        $file_name;

    $new_picture_path =
        $target_file;
}


/* =========================================================
   13. UPDATE DATABASE
   ========================================================= */

if ($picture_profile !== null) {


    /* -----------------------------------------------------
       Profile + picture + optional password
       ----------------------------------------------------- */

    if ($changing_password) {

        $update_query = "
            UPDATE officials
            SET
                name = ?,
                username = ?,
                email = ?,
                contact_number = ?,
                position = ?,
                picture_profile = ?,
                password = ?
            WHERE official_id = ?
              AND department = 'CLEARANCE'
        ";


        $stmt =
            $conn->prepare($update_query);


        if (!$stmt) {

            if (
                $new_picture_path !== null &&
                file_exists($new_picture_path)
            ) {

                @unlink($new_picture_path);
            }


            error_log(
                "Clearance profile/password update prepare failed: " .
                $conn->error
            );


            header(
                "Location: edit-profile.php?error=1"
            );

            exit();
        }


        $stmt->bind_param(
            "sssssssi",
            $name,
            $username,
            $email,
            $contact_number,
            $position,
            $picture_profile,
            $new_password_hash,
            $official_id
        );


    } else {

        /* -------------------------------------------------
           Profile + picture only
           ------------------------------------------------- */

        $update_query = "
            UPDATE officials
            SET
                name = ?,
                username = ?,
                email = ?,
                contact_number = ?,
                position = ?,
                picture_profile = ?
            WHERE official_id = ?
              AND department = 'CLEARANCE'
        ";


        $stmt =
            $conn->prepare($update_query);


        if (!$stmt) {

            if (
                $new_picture_path !== null &&
                file_exists($new_picture_path)
            ) {

                @unlink($new_picture_path);
            }


            error_log(
                "Clearance profile update prepare failed: " .
                $conn->error
            );


            header(
                "Location: edit-profile.php?error=1"
            );

            exit();
        }


        $stmt->bind_param(
            "ssssssi",
            $name,
            $username,
            $email,
            $contact_number,
            $position,
            $picture_profile,
            $official_id
        );
    }


} else {


    /* -----------------------------------------------------
       No new picture
       ----------------------------------------------------- */

    if ($changing_password) {

        $update_query = "
            UPDATE officials
            SET
                name = ?,
                username = ?,
                email = ?,
                contact_number = ?,
                position = ?,
                password = ?
            WHERE official_id = ?
              AND department = 'CLEARANCE'
        ";


        $stmt =
            $conn->prepare($update_query);


        if (!$stmt) {

            error_log(
                "Clearance profile/password update prepare failed: " .
                $conn->error
            );


            header(
                "Location: edit-profile.php?error=1"
            );

            exit();
        }


        $stmt->bind_param(
            "ssssssi",
            $name,
            $username,
            $email,
            $contact_number,
            $position,
            $new_password_hash,
            $official_id
        );


    } else {

        /* -------------------------------------------------
           Profile information only
           ------------------------------------------------- */

        $update_query = "
            UPDATE officials
            SET
                name = ?,
                username = ?,
                email = ?,
                contact_number = ?,
                position = ?
            WHERE official_id = ?
              AND department = 'CLEARANCE'
        ";


        $stmt =
            $conn->prepare($update_query);


        if (!$stmt) {

            error_log(
                "Clearance profile update prepare failed: " .
                $conn->error
            );


            header(
                "Location: edit-profile.php?error=1"
            );

            exit();
        }


        $stmt->bind_param(
            "sssssi",
            $name,
            $username,
            $email,
            $contact_number,
            $position,
            $official_id
        );
    }
}


/* =========================================================
   14. EXECUTE DATABASE UPDATE
   ========================================================= */

if (!$stmt->execute()) {

    error_log(
        "Clearance profile update execute failed: " .
        $stmt->error
    );


    /*
     * Delete newly uploaded picture if
     * database update failed.
     */

    if (
        $new_picture_path !== null &&
        file_exists($new_picture_path)
    ) {

        @unlink($new_picture_path);
    }


    $stmt->close();
    $conn->close();


    header(
        "Location: edit-profile.php?error=1"
    );

    exit();
}


/* =========================================================
   15. SUCCESS
   ========================================================= */

$stmt->close();
$conn->close();


header(
    "Location: profile.php?success=1"
);

exit();

?>
