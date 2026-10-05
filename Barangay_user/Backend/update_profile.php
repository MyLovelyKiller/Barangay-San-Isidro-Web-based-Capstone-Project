<?php
// 1. Start buffering
ob_start();
session_start();

include "../../BACKEND/db_connect.php";


/* =========================================================
   CHECK IF USER IS LOGGED IN
   ========================================================= */

if (!isset($_SESSION['resident_id'])) {
    header("Location: /BMS/CODES/login.php");
    exit();
}

$resident_id = $_SESSION['resident_id'];


/* =========================================================
   CSRF TOKEN VALIDATION
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
   CLAMAV SCANNER
   ========================================================= */

function scanFileWithClamAV($filePath, &$scanMessage = null)
{
    /*
     * Change this path if your ClamAV installation
     * is located somewhere else.
     */
    $clamScanPath =
        'C:\Users\Gary\Downloads\clamav-1.5.4.win.x64\clamav-1.5.4.win.x64\clamscan.exe';

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
        ' --no-summary ' .
        escapeshellarg($filePath);

    $output = [];
    $exitCode = -1;

    exec($command, $output, $exitCode);

    /*
     * ClamAV exit codes:
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
        $scanMessage = 'File was detected as infected.';
        return false;
    }

    $scanMessage = 'ClamAV scan failed. Exit code: ' . $exitCode;
    return false;
}


/* =========================================================
   GET AND TRIM FORM FIELDS
   ========================================================= */

$full_name = trim($_POST['full_name'] ?? '');
$username  = trim($_POST['username'] ?? '');
$birthdate = $_POST['birthdate'] ?? '';
$sex       = $_POST['sex'] ?? '';
$email     = trim($_POST['email'] ?? '');
$contact   = trim($_POST['contact_number'] ?? '');
$address   = trim($_POST['address'] ?? '');


/* =========================================================
   HANDLE PROFILE PICTURE UPLOAD
   ========================================================= */

$file_name = null;
$new_picture_path = null;

if (
    isset($_FILES['profile_picture']) &&
    $_FILES['profile_picture']['error'] !== UPLOAD_ERR_NO_FILE
) {

    /* ---------------------------------------------------------
       Check upload error
       --------------------------------------------------------- */

    if ($_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
        die("Profile picture upload failed.");
    }


    /* ---------------------------------------------------------
       Maximum file size: 5 MB
       --------------------------------------------------------- */

    $max_file_size = 5 * 1024 * 1024;

    if ($_FILES['profile_picture']['size'] > $max_file_size) {
        die("Profile picture is too large. Maximum size is 5 MB.");
    }


    /* ---------------------------------------------------------
       Directories
       --------------------------------------------------------- */

    $target_dir = "../picture/";

    /*
     * Existing shared quarantine folder.
     */
    $quarantine_dir = "../../UPLOADS/quarantine/";


    /* ---------------------------------------------------------
       Create directories if necessary
       --------------------------------------------------------- */

    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0755, true);
    }

    if (!is_dir($quarantine_dir)) {
        mkdir($quarantine_dir, 0755, true);
    }


    /* ---------------------------------------------------------
       Validate file extension
       --------------------------------------------------------- */

    $file_extension = strtolower(
        pathinfo(
            $_FILES['profile_picture']['name'],
            PATHINFO_EXTENSION
        )
    );

    $allowed_extensions = [
        'jpg',
        'jpeg',
        'png',
        'gif'
    ];

    if (!in_array($file_extension, $allowed_extensions, true)) {
        die("Invalid profile picture format.");
    }


    /* ---------------------------------------------------------
       Verify that the uploaded file is actually an image
       --------------------------------------------------------- */

    $image_info = getimagesize(
        $_FILES['profile_picture']['tmp_name']
    );

    if ($image_info === false) {
        die("The uploaded file is not a valid image.");
    }


    /* ---------------------------------------------------------
       Generate a random filename
       --------------------------------------------------------- */

    $file_name =
        'resident_' .
        $resident_id .
        '_' .
        bin2hex(random_bytes(8)) .
        '.' .
        $file_extension;


    /* ---------------------------------------------------------
       Quarantine path
       --------------------------------------------------------- */

    $quarantine_path = $quarantine_dir . $file_name;

    $target_file = $target_dir . $file_name;


    /* ---------------------------------------------------------
       Move uploaded file to quarantine
       --------------------------------------------------------- */

    if (!move_uploaded_file(
        $_FILES['profile_picture']['tmp_name'],
        $quarantine_path
    )) {
        $file_name = null;
        die("Failed to upload profile picture.");
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

        $file_name = null;

        die(
            "Profile picture was rejected: " .
            htmlspecialchars(
                $scanMessage,
                ENT_QUOTES,
                'UTF-8'
            )
        );
    }


    /* ---------------------------------------------------------
       Move clean file to permanent picture directory
       --------------------------------------------------------- */

    if (!rename($quarantine_path, $target_file)) {

        if (file_exists($quarantine_path)) {
            unlink($quarantine_path);
        }

        $file_name = null;

        die("Failed to store profile picture.");
    }


    /*
     * Remember the newly stored file so that we can
     * remove it if the database update fails.
     */
    $new_picture_path = $target_file;
}


/* =========================================================
   SECURE SQL UPDATE
   ========================================================= */

if ($file_name) {

    /*
     * Case 1:
     * Updating profile with a NEW picture
     */

    $sql = "UPDATE residents SET 
            name = ?, 
            username = ?, 
            birthdate = ?, 
            sex = ?, 
            email = ?, 
            contact_number = ?, 
            address = ?, 
            profile_picture = ?
            WHERE resident_id = ?";

    $stmt = $conn->prepare($sql);

    $stmt->bind_param(
        "ssssssssi",
        $full_name,
        $username,
        $birthdate,
        $sex,
        $email,
        $contact,
        $address,
        $file_name,
        $resident_id
    );

} else {

    /*
     * Case 2:
     * Updating profile WITHOUT changing the picture
     */

    $sql = "UPDATE residents SET 
            name = ?, 
            username = ?, 
            birthdate = ?, 
            sex = ?, 
            email = ?, 
            contact_number = ?, 
            address = ?
            WHERE resident_id = ?";

    $stmt = $conn->prepare($sql);

    $stmt->bind_param(
        "sssssssi",
        $full_name,
        $username,
        $birthdate,
        $sex,
        $email,
        $contact,
        $address,
        $resident_id
    );
}


/* =========================================================
   EXECUTE DATABASE UPDATE
   ========================================================= */

if ($stmt->execute()) {

    /*
     * Database update succeeded.
     */

    $stmt->close();

    header("Location: ../Frontend/Profile.php?success=1");
    exit();

} else {

    /*
     * Database update failed.
     *
     * Delete the newly uploaded clean picture so that
     * an unused file isn't left behind.
     */

    if (
        $new_picture_path !== null &&
        file_exists($new_picture_path)
    ) {
        unlink($new_picture_path);
    }

    echo "Error updating record: " .
         htmlspecialchars(
             $stmt->error,
             ENT_QUOTES,
             'UTF-8'
         );

    $stmt->close();
}

?>