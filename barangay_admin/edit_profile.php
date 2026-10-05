<?php
session_start();
require_once 'config.php';


/* =========================================================
   CSRF TOKEN
========================================================= */

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];


if(!isset($_SESSION['username'])){
    echo "User not logged in.";
    exit();
}

$username = $_SESSION['username'];


/* =========================================================
   CLAMAV FUNCTION
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

    exec(
        $command,
        $output,
        $exitCode
    );

    if ($exitCode === 0) {
        $scanMessage = 'Clean';
        return true;
    }

    if ($exitCode === 1) {
        $scanMessage = 'File was detected as infected.';
        return false;
    }

    $scanMessage =
        'ClamAV scan failed. Exit code: ' .
        $exitCode;

    return false;
}


/* =========================================================
   GET CURRENT DATA
========================================================= */

$stmt = $conn->prepare("
    SELECT *
    FROM officials
    WHERE username = ?
");

$stmt->bind_param("s", $username);
$stmt->execute();

$result = $stmt->get_result();
$row = $result->fetch_assoc();

$stmt->close();


/* =========================================================
   UPDATE PROFILE
========================================================= */

if(isset($_POST['update'])){


    /* =====================================================
       CSRF CHECK
    ===================================================== */

    $submitted_token = $_POST['csrf_token'] ?? '';

    if (
        empty($submitted_token) ||
        empty($_SESSION['csrf_token']) ||
        !hash_equals(
            $_SESSION['csrf_token'],
            $submitted_token
        )
    ) {
        http_response_code(403);
        exit("Invalid CSRF token.");
    }


    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $department = trim($_POST['department'] ?? '');


    /* =====================================================
       IMAGE UPLOAD
    ===================================================== */

    $has_profile_upload =
        isset($_FILES['profile']) &&
        $_FILES['profile']['error'] !== UPLOAD_ERR_NO_FILE;


    if($has_profile_upload){


        /* UPLOAD ERROR */
        if ($_FILES['profile']['error'] !== UPLOAD_ERR_OK) {
            exit("Profile picture upload failed.");
        }


        $file = $_FILES['profile'];


        /* FILE SIZE */
        if (
            $file['size'] <= 0 ||
            $file['size'] > 5 * 1024 * 1024
        ) {
            exit("Profile picture must be between 1 byte and 5MB.");
        }


        /* ALLOWED EXTENSIONS */
        $allowed = [
            'jpg',
            'jpeg',
            'png',
            'gif'
        ];


        $extension = strtolower(
            pathinfo(
                $file['name'],
                PATHINFO_EXTENSION
            )
        );


        if (!in_array($extension, $allowed, true)) {
            exit("Invalid profile picture type.");
        }


        /* ACTUAL IMAGE VALIDATION */
        if (@getimagesize($file['tmp_name']) === false) {
            exit("Invalid image file.");
        }


        /* =================================================
           QUARANTINE DIRECTORY
        ================================================= */

        $quarantine_dir =
            __DIR__ .
            DIRECTORY_SEPARATOR .
            "../UPLOADS/quarantine";


        if (
            !is_dir($quarantine_dir) &&
            !mkdir($quarantine_dir, 0755, true)
        ) {
            exit("Upload storage is unavailable.");
        }


        /*
         * Generate a random filename.
         * Do not use the original filename.
         */
        $filename =
            'official_' .
            (int)$row['official_id'] .
            '_' .
            bin2hex(random_bytes(16)) .
            '.' .
            $extension;


        $quarantine_path =
            $quarantine_dir .
            DIRECTORY_SEPARATOR .
            $filename;


        /* MOVE TO QUARANTINE */
        if (
            !move_uploaded_file(
                $file['tmp_name'],
                $quarantine_path
            )
        ) {
            exit("Failed to save profile picture.");
        }


        /* =================================================
           CLAMAV SCAN
        ================================================= */

        $scanMessage = null;

        $scanClean = scanFileWithClamAV(
            $quarantine_path,
            $scanMessage
        );


        /*
         * FAIL CLOSED
         */
        if (!$scanClean) {

            if (is_file($quarantine_path)) {
                @unlink($quarantine_path);
            }

            exit("Profile picture could not be accepted.");
        }


        /* =================================================
           FINAL UPLOAD DIRECTORY
        ================================================= */

        $upload_dir =
            __DIR__ .
            DIRECTORY_SEPARATOR .
            "uploads";


        if (
            !is_dir($upload_dir) &&
            !mkdir($upload_dir, 0755, true)
        ) {

            @unlink($quarantine_path);

            exit("Upload storage is unavailable.");
        }


        $final_path =
            $upload_dir .
            DIRECTORY_SEPARATOR .
            $filename;


        /*
         * Move clean file from quarantine to permanent
         * storage.
         */
        if (!rename($quarantine_path, $final_path)) {

            @unlink($quarantine_path);

            exit("Failed to finalize profile picture.");
        }


        /* =================================================
           UPDATE DATABASE
        ================================================= */

        $sql = "
            UPDATE officials
            SET
                name = ?,
                email = ?,
                contact_number = ?,
                department = ?,
                proof_file = ?
            WHERE username = ?
        ";


        $update_stmt = $conn->prepare($sql);


        if (!$update_stmt) {

            if (is_file($final_path)) {
                @unlink($final_path);
            }

            exit("Database error.");
        }


        $update_stmt->bind_param(
            "ssssss",
            $name,
            $email,
            $contact_number,
            $department,
            $filename,
            $username
        );

    } else {


        /* =================================================
           UPDATE WITHOUT IMAGE
        ================================================= */

        $sql = "
            UPDATE officials
            SET
                name = ?,
                email = ?,
                contact_number = ?,
                department = ?
            WHERE username = ?
        ";


        $update_stmt = $conn->prepare($sql);


        if (!$update_stmt) {
            exit("Database error.");
        }


        $update_stmt->bind_param(
            "sssss",
            $name,
            $email,
            $contact_number,
            $department,
            $username
        );
    }


    /* =====================================================
       EXECUTE UPDATE
    ===================================================== */

    if($update_stmt->execute()){

        $update_stmt->close();

        header("Location: profile.php?success=1");
        exit();

    } else {

        /*
         * If a new file was uploaded but the database
         * update failed, remove the new file.
         */
        if (
            isset($final_path) &&
            is_file($final_path)
        ) {
            @unlink($final_path);
        }

        echo "Error updating record: " .
             $conn->error;
    }

    $update_stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Profile</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="style/main.css">
	<link rel="stylesheet" href="style/sidebar.css">
	<link rel="stylesheet" href="style/header.css">
	<link rel="stylesheet" href="style/edit_profile.css">
</head>

<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-wrapper">
        <?php include 'header.php'; ?>

        <div class="content">
            <div class="edit-card">
                <h2>Edit Profile</h2>

                <form method="POST" enctype="multipart/form-data">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>"
                    >

                    <div class="form-group">
                        <label>Full Name</label>
                        <input
                            type="text"
                            name="name"
                            value="<?php echo htmlspecialchars($row['name']); ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label>Email</label>
                        <input
                            type="email"
                            name="email"
                            value="<?php echo htmlspecialchars($row['email']); ?>"
                        >
                    </div>

                    <div class="form-group">
                        <label>Contact Number</label>
                        <input
                            type="text"
                            name="contact_number"
                            value="<?php echo htmlspecialchars($row['contact_number']); ?>"
                        >
                    </div>

                    <div class="form-group">
                        <label>Department</label>
                        <input
                            type="text"
                            name="department"
                            value="<?php echo htmlspecialchars($row['department']); ?>"
                        >
                    </div>

                    <div class="form-group">
                        <label>Profile Picture</label>
                        <input
                            type="file"
                            name="profile"
                            accept="image/jpeg,image/png,image/gif"
                        >
                    </div>

                    <button
                        type="submit"
                        name="update"
                        class="update-btn"
                    >
                        Update Profile
                    </button>

                </form>
            </div>
        </div>
    </div>

    <script src="style/sidebar.js"></script>
</body>
</html>