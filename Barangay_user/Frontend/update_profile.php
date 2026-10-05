<?php
session_start();
include "../../BACKEND/db_connect.php";

/* =========================================================
   1. SECURE SESSION CHECK
   ========================================================= */

if (!isset($_SESSION['resident_id']) && !isset($_SESSION['username'])) {
    header("Location: /BMS/CODES/login.php");
    exit();
}


/* =========================================================
   2. GET RESIDENT ID FROM SESSION
   ========================================================= */

/*
 * Do NOT trust resident_id from POST.
 * The logged-in session determines which account can be updated.
 */
if (!isset($_SESSION['resident_id'])) {
    header("Location: /BMS/CODES/login.php");
    exit();
}

$resident_id = (int)$_SESSION['resident_id'];


/* =========================================================
   3. CSRF TOKEN VALIDATION
   ========================================================= */

$submitted_token = $_POST['csrf_token'] ?? '';

if (
    empty($submitted_token) ||
    !isset($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $submitted_token)
) {
    http_response_code(403);
    die("Invalid CSRF token.");
}


/* =========================================================
   4. GET FORM DATA
   ========================================================= */

$full_name = trim($_POST['full_name'] ?? '');
$username = trim($_POST['username'] ?? '');
$birthdate = $_POST['birthdate'] ?? '';
$sex = $_POST['sex'] ?? '';
$email = trim($_POST['email'] ?? '');
$contact_number = trim($_POST['contact_number'] ?? '');
$address = trim($_POST['address'] ?? '');

$current_password = $_POST['current_password'] ?? '';
$new_password = $_POST['new_password'] ?? '';


/* =========================================================
   5. FETCH CURRENT DATA
   ========================================================= */

$stmt = $conn->prepare("
    SELECT password, profile_picture
    FROM residents
    WHERE resident_id = ?
    LIMIT 1
");

$stmt->bind_param("i", $resident_id);
$stmt->execute();

$userData = $stmt->get_result()->fetch_assoc();

$stmt->close();


if (!$userData) {
    die("Resident account was not found.");
}


/* =========================================================
   6. CLAMAV SCANNER
   ========================================================= */

function scanFileWithClamAV($filePath, &$scanMessage = null)
{
    $clamScanPath = getenv('BMS_CLAMSCAN_PATH') ?: '';

    if (!is_file($clamScanPath)) {
        $scanMessage = 'ClamAV scanner was not found.';
        return false;
    }

    if (!is_file($filePath)) {
        $scanMessage = 'File to scan was not found.';
        return false;
    }

    if (!function_exists('exec')) {
        $scanMessage = 'PHP exec() function is disabled.';
        return false;
    }

    $command =
        escapeshellarg($clamScanPath) .
        ' --no-summary --max-filesize=20M --max-scansize=100M ' .
        escapeshellarg($filePath);

    $output = [];
    $exitCode = -1;

    exec($command, $output, $exitCode);

    /*
     * ClamAV:
     *
     * 0 = Clean
     * 1 = Infected
     * Other = Scanner error
     */

    if ($exitCode === 0) {
        $scanMessage = 'Clean';
        return true;
    }

    if ($exitCode === 1) {
        $scanMessage = 'File was detected as infected.';
        return false;
    }

    $scanMessage = 'ClamAV scan failed. Exit code: ' . $exitCode;
    return false;
}


/* =========================================================
   7. HANDLE PROFILE PICTURE
   ========================================================= */

$profile_picture = $userData['profile_picture'];
$new_picture_path = null;

if (
    isset($_FILES['profile_picture']) &&
    $_FILES['profile_picture']['error'] !== UPLOAD_ERR_NO_FILE
) {

    /* ---------------------------------------------------------
       Check upload error
       --------------------------------------------------------- */

    if ($_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {

        header(
            "Location: ../Frontend/Editprofile.php" .
            "?status=error&message=Profile picture upload failed"
        );

        exit();
    }


    /* ---------------------------------------------------------
       Maximum file size: 5 MB
       --------------------------------------------------------- */

    $max_file_size = 5 * 1024 * 1024;

    if ($_FILES['profile_picture']['size'] > $max_file_size) {

        header(
            "Location: ../Frontend/Editprofile.php" .
            "?status=error&message=Profile picture is too large"
        );

        exit();
    }


    /* ---------------------------------------------------------
       Directories
       --------------------------------------------------------- */

    $target_dir = "../picture/";

    /*
     * Shared quarantine directory.
     */
    $quarantine_dir = "../../UPLOADS/quarantine/";


    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0755, true);
    }

    if (!is_dir($quarantine_dir)) {
        mkdir($quarantine_dir, 0755, true);
    }


    /* ---------------------------------------------------------
       Validate extension
       --------------------------------------------------------- */

    $file_extension = strtolower(
        pathinfo(
            $_FILES['profile_picture']['name'],
            PATHINFO_EXTENSION
        )
    );

    $allowed = [
        'jpg',
        'jpeg',
        'png',
        'gif',
        'webp'
    ];

    if (!in_array($file_extension, $allowed, true)) {

        header(
            "Location: ../Frontend/Editprofile.php" .
            "?status=error&message=Invalid profile picture format"
        );

        exit();
    }


    /* ---------------------------------------------------------
       Verify actual image content
       --------------------------------------------------------- */

    $image_info = getimagesize(
        $_FILES['profile_picture']['tmp_name']
    );

    if ($image_info === false) {

        header(
            "Location: ../Frontend/Editprofile.php" .
            "?status=error&message=Uploaded file is not a valid image"
        );

        exit();
    }


    /* ---------------------------------------------------------
       Generate secure random filename
       --------------------------------------------------------- */

    $unique_filename =
        "profile_" .
        $resident_id .
        "_" .
        bin2hex(random_bytes(8)) .
        "." .
        $file_extension;


    $quarantine_path =
        $quarantine_dir . $unique_filename;

    $target_file =
        $target_dir . $unique_filename;


    /* ---------------------------------------------------------
       Move upload to quarantine
       --------------------------------------------------------- */

    if (!move_uploaded_file(
        $_FILES['profile_picture']['tmp_name'],
        $quarantine_path
    )) {

        header(
            "Location: ../Frontend/Editprofile.php" .
            "?status=error&message=Failed to upload profile picture"
        );

        exit();
    }


    /* ---------------------------------------------------------
       CLAMAV SCAN
       --------------------------------------------------------- */

    $scanMessage = '';

    $scanResult = scanFileWithClamAV(
        $quarantine_path,
        $scanMessage
    );


    /* ---------------------------------------------------------
       Reject infected file OR scanner failure
       --------------------------------------------------------- */

    if (!$scanResult) {

        if (file_exists($quarantine_path)) {
            unlink($quarantine_path);
        }

        header(
            "Location: ../Frontend/Editprofile.php" .
            "?status=error&message=" .
            urlencode("Profile picture rejected: " . $scanMessage)
        );

        exit();
    }


    /* ---------------------------------------------------------
       Move clean file to permanent storage
       --------------------------------------------------------- */

    if (!rename($quarantine_path, $target_file)) {

        if (file_exists($quarantine_path)) {
            unlink($quarantine_path);
        }

        header(
            "Location: ../Frontend/Editprofile.php" .
            "?status=error&message=Failed to store profile picture"
        );

        exit();
    }


    /*
     * Remember new clean picture.
     */
    $profile_picture = $unique_filename;
    $new_picture_path = $target_file;
}


/* =========================================================
   8. PASSWORD UPDATE LOGIC
   ========================================================= */

$password_to_save = $userData['password'];


/*
 * If either password field is supplied, require both.
 */
if (
    !empty($current_password) ||
    !empty($new_password)
) {

    if (
        empty($current_password) ||
        empty($new_password)
    ) {

        /*
         * Remove newly uploaded picture if password
         * validation fails.
         */
        if (
            $new_picture_path !== null &&
            file_exists($new_picture_path)
        ) {
            unlink($new_picture_path);
        }

        header(
            "Location: ../Frontend/Editprofile.php" .
            "?status=error&message=Both password fields are required"
        );

        exit();
    }


    if (
        !password_verify(
            $current_password,
            $userData['password']
        )
    ) {

        /*
         * Remove newly uploaded picture because the
         * profile update will not proceed.
         */
        if (
            $new_picture_path !== null &&
            file_exists($new_picture_path)
        ) {
            unlink($new_picture_path);
        }

        header(
            "Location: ../Frontend/Editprofile.php" .
            "?status=error&message=Incorrect current password"
        );

        exit();
    }


    /*
     * Hash new password.
     */
    $password_to_save =
        password_hash(
            $new_password,
            PASSWORD_DEFAULT
        );
}


/* =========================================================
   9. FINAL UPDATE QUERY
   ========================================================= */

$updateQuery = "
    UPDATE residents
    SET
        name = ?,
        username = ?,
        birthdate = ?,
        sex = ?,
        email = ?,
        contact_number = ?,
        address = ?,
        password = ?,
        profile_picture = ?
    WHERE resident_id = ?
";

$stmt = $conn->prepare($updateQuery);

$stmt->bind_param(
    "sssssssssi",
    $full_name,
    $username,
    $birthdate,
    $sex,
    $email,
    $contact_number,
    $address,
    $password_to_save,
    $profile_picture,
    $resident_id
);


/* =========================================================
   10. EXECUTE UPDATE
   ========================================================= */

if ($stmt->execute()) {

    /*
     * Database update succeeded.
     *
     * Now remove the old picture.
     */

    if (
        $new_picture_path !== null &&
        !empty($userData['profile_picture'])
    ) {

        $old_picture_path =
            "../picture/" . $userData['profile_picture'];

        /*
         * Make sure we do not accidentally delete
         * the newly uploaded picture.
         */
        if (
            file_exists($old_picture_path) &&
            realpath($old_picture_path) !==
            realpath($new_picture_path)
        ) {
            unlink($old_picture_path);
        }
    }


    $stmt->close();
    $conn->close();

    header(
        "Location: ../Frontend/Profile.php?status=success"
    );

    exit();

} else {

    /*
     * Database update failed.
     *
     * Remove the newly uploaded clean picture.
     */
    if (
        $new_picture_path !== null &&
        file_exists($new_picture_path)
    ) {
        unlink($new_picture_path);
    }

    error_log(
        "Update error: " . $stmt->error
    );

    $stmt->close();
    $conn->close();

    header(
        "Location: ../Frontend/Editprofile.php" .
        "?status=error&message=Database Error"
    );

    exit();
}
?>