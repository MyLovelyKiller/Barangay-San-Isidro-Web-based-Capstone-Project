<?php
session_start();

if (!isset($_SESSION['official_id'])) {
    header("Location: login.php");
    exit();
}

require_once '../BACKEND/db_connect.php';

/* =========================================================
   CSRF PROTECTION
   ========================================================= */

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];

$submitted_token = $_POST['csrf_token'] ?? '';

if (
    empty($submitted_token) ||
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
   GET OFFICIAL ID
   ========================================================= */

$official_id = $_SESSION['official_id'];


/* =========================================================
   GET FORM DATA
   ========================================================= */

$name = $_POST['name'] ?? '';
$username = $_POST['username'] ?? '';
$email = $_POST['email'] ?? '';
$contact_number = $_POST['contact_number'] ?? '';
$id_number = $_POST['id_number'] ?? '';
$position = $_POST['position'] ?? '';


/* =========================================================
   HANDLE PROFILE PICTURE
   ========================================================= */

$picture_profile = null;
$old_picture = null;
$new_picture_path = null;

if (
    isset($_FILES['picture_profile']) &&
    $_FILES['picture_profile']['error'] !== UPLOAD_ERR_NO_FILE
) {

    /* ---------------------------------------------------------
       Check upload error
       --------------------------------------------------------- */

    if ($_FILES['picture_profile']['error'] !== UPLOAD_ERR_OK) {
        die("Profile picture upload failed.");
    }


    /* ---------------------------------------------------------
       Maximum file size: 5 MB
       --------------------------------------------------------- */

    $max_file_size = 5 * 1024 * 1024;

    if ($_FILES['picture_profile']['size'] > $max_file_size) {
        die("Profile picture is too large. Maximum size is 5 MB.");
    }


    /* ---------------------------------------------------------
       Directories
       --------------------------------------------------------- */

    $upload_dir = '../IMAGES/';
    $quarantine_dir = '../UPLOADS/quarantine/';

    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    if (!is_dir($quarantine_dir)) {
        mkdir($quarantine_dir, 0755, true);
    }


    /* ---------------------------------------------------------
       Validate extension
       --------------------------------------------------------- */

    $file_extension = strtolower(
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

    if (!in_array($file_extension, $allowed_extensions, true)) {
        die("Invalid profile picture format.");
    }


    /* ---------------------------------------------------------
       Verify that the uploaded file is actually an image
       --------------------------------------------------------- */

    $image_info = getimagesize(
        $_FILES['picture_profile']['tmp_name']
    );

    if ($image_info === false) {
        die("The uploaded file is not a valid image.");
    }


    /* ---------------------------------------------------------
       Generate a unique filename
       --------------------------------------------------------- */

    $file_name =
        'profile_' .
        $official_id .
        '_' .
        bin2hex(random_bytes(8)) .
        '.' .
        $file_extension;


    /* ---------------------------------------------------------
       Quarantine path
       --------------------------------------------------------- */

    $quarantine_path = $quarantine_dir . $file_name;

    $target_path = $upload_dir . $file_name;


    /* ---------------------------------------------------------
       Move uploaded file to quarantine
       --------------------------------------------------------- */

    if (!move_uploaded_file(
        $_FILES['picture_profile']['tmp_name'],
        $quarantine_path
    )) {
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

        die("Profile picture was rejected: " . $scanMessage);
    }


    /* ---------------------------------------------------------
       Move clean file from quarantine to IMAGES
       --------------------------------------------------------- */

    if (!rename($quarantine_path, $target_path)) {

        if (file_exists($quarantine_path)) {
            unlink($quarantine_path);
        }

        die("Failed to store profile picture.");
    }


    /*
     * Store only the filename in the database.
     *
     * Example:
     * profile_15_a82f91c3.png
     *
     * profile.php can then use:
     * ../IMAGES/<filename>
     */
    $picture_profile = $file_name;

    $new_picture_path = $target_path;


    /* ---------------------------------------------------------
       Get old profile picture
       --------------------------------------------------------- */

    $get_old = $conn->prepare(
        "SELECT picture_profile
         FROM officials
         WHERE official_id = ?"
    );

    $get_old->bind_param("i", $official_id);
    $get_old->execute();

    $old_res = $get_old->get_result()->fetch_assoc();

    $old_picture = $old_res['picture_profile'] ?? null;

    $get_old->close();
}


/* =========================================================
   UPDATE DATABASE
   ========================================================= */

if ($picture_profile !== null) {

    $update_query = "
        UPDATE officials
        SET
            name = ?,
            username = ?,
            email = ?,
            contact_number = ?,
            id_number = ?,
            position = ?,
            picture_profile = ?
        WHERE official_id = ?
    ";

    $stmt = $conn->prepare($update_query);

    $stmt->bind_param(
        "sssssssi",
        $name,
        $username,
        $email,
        $contact_number,
        $id_number,
        $position,
        $picture_profile,
        $official_id
    );

} else {

    $update_query = "
        UPDATE officials
        SET
            name = ?,
            username = ?,
            email = ?,
            contact_number = ?,
            id_number = ?,
            position = ?
        WHERE official_id = ?
    ";

    $stmt = $conn->prepare($update_query);

    $stmt->bind_param(
        "ssssssi",
        $name,
        $username,
        $email,
        $contact_number,
        $id_number,
        $position,
        $official_id
    );
}


/* =========================================================
   EXECUTE DATABASE UPDATE
   ========================================================= */

if ($stmt->execute()) {

    /*
     * Delete old picture ONLY AFTER the database update
     * succeeds.
     */

    if (
        $picture_profile !== null &&
        !empty($old_picture)
    ) {

        $old_file_path = null;

        /*
         * Existing records may contain the old full path:
         *
         * ../UPLOADS/profile_pictures/file.jpg
         *
         * New records contain only:
         *
         * file.jpg
         */

        if (file_exists($old_picture)) {

            $old_file_path = $old_picture;

        } else {

            $old_filename = basename($old_picture);

            $possible_old_path = '../IMAGES/' . $old_filename;

            if (file_exists($possible_old_path)) {
                $old_file_path = $possible_old_path;
            }
        }


        /*
         * Don't delete the newly uploaded file.
         */
        if (
            $old_file_path !== null &&
            realpath($old_file_path) !== realpath($new_picture_path)
        ) {
            unlink($old_file_path);
        }
    }


    header("Location: profile.php?success=1");
    exit();

} else {

    /*
     * Database update failed.
     *
     * Remove the newly uploaded clean picture so that
     * an unused file isn't left behind.
     */
    if (
        $new_picture_path !== null &&
        file_exists($new_picture_path)
    ) {
        unlink($new_picture_path);
    }

    header("Location: edit-profile.php?error=1");
    exit();
}


$stmt->close();
$conn->close();
?>