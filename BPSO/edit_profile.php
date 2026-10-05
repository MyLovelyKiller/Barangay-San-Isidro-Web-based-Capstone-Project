<?php
require_once __DIR__ . '/../BACKEND/security_helpers.php';
bms_start_secure_session();
bms_send_security_headers();
require_once '../BACKEND/db_connect.php'; // Primary DB (BMS)

/* =========================================================
   CSRF PROTECTION
   ========================================================= */

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];


// 1. Session and Role Validation
if (!isset($_SESSION['official_id']) || $_SESSION['department'] != "BPSO") {
    header("Location: /BMS/CODES/login.php?error=1");
    exit();
}

$official_id = $_SESSION['official_id'];
$message = "";

// 2. Handle POST Request
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (
        !is_string($submittedToken) ||
        !hash_equals($_SESSION['csrf_token'], $submittedToken)
    ) {
        http_response_code(403);
        exit('Invalid CSRF token.');
    }

    $name = trim((string)($_POST['name'] ?? ''));
    $username = trim((string)($_POST['username'] ?? ''));
    if ($name === '' || strlen($username) < 4 || strlen($username) > 50
        || !preg_match('/^[A-Za-z0-9_.-]+$/', $username)
    ) {
        http_response_code(400);
        exit('Invalid profile details.');
    }
    // Add other fields here if they exist in your 'officials' table (e.g., email, contact)

    $upload_ok = true;
    $new_filename = "";
    $stmt = null;

    // Handle Image Upload
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['profile_pic'];
        $maxSize = 5 * 1024 * 1024;
        $mimeExtensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif'];
        $mime = false;
        if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] <= 0 || $file['size'] > $maxSize) {
            $message = "Profile image must be valid and no larger than 5 MB.";
            $upload_ok = false;
        } else {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);
            if (!isset($mimeExtensions[$mime]) || @getimagesize($file['tmp_name']) === false) {
                $message = "Invalid profile image.";
                $upload_ok = false;
            }
        }

        if ($upload_ok) {
            $quarantineDir = __DIR__ . '/../uploads/quarantine';
            if (!is_dir($quarantineDir) && !mkdir($quarantineDir, 0700, true) && !is_dir($quarantineDir)) {
                $message = "Upload storage is unavailable.";
                $upload_ok = false;
            }
            $quarantinePath = $upload_ok
                ? $quarantineDir . DIRECTORY_SEPARATOR . bin2hex(random_bytes(16)) . '.' . $mimeExtensions[$mime]
                : '';
            if ($upload_ok && !move_uploaded_file($file['tmp_name'], $quarantinePath)) {
                $message = "Failed to save profile image.";
                $upload_ok = false;
            }
            if ($upload_ok) {
                $scanMessage = '';
                if (!bms_scan_file_with_clamav($quarantinePath, $scanMessage)) {
                    unlink($quarantinePath);
                    $message = $scanMessage;
                    $upload_ok = false;
                }
            }
            if ($upload_ok) {
                $new_filename = "profile_" . (int)$official_id . "_" . bin2hex(random_bytes(8)) . "." . $mimeExtensions[$mime];
                $target_file = __DIR__ . '/../uploads/profile_pictures/' . $new_filename;
                if (!rename($quarantinePath, $target_file)) {
                    unlink($quarantinePath);
                    $message = "Failed to finalize profile image.";
                    $upload_ok = false;
                }
            }
        }

        if ($upload_ok) {
            $sql = "UPDATE officials SET name=?, username=?, picture_profile=? WHERE official_id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssi", $name, $username, $new_filename, $official_id);
        }
    } else {
        // Update without changing image
        $sql = "UPDATE officials SET name=?, username=? WHERE official_id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $name, $username, $official_id);
    }

    if ($upload_ok && $stmt !== null && $stmt->execute()) {
        echo "<script>alert('Profile updated successfully!'); window.location.href='profile.php';</script>";
        exit;
    } else {
        if ($upload_ok) {
            $message = "Unable to update profile.";
            error_log('BPSO profile update failed: ' . $conn->error);
        }
    }
}

// 3. Fetch current data to fill form
$query = "SELECT * FROM officials WHERE official_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $official_id);
$stmt->execute();
$result = $stmt->get_result();
$officer = $result->fetch_assoc();

$profileFilename = basename((string)($officer['picture_profile'] ?? ''));
$profileStoragePath = __DIR__ . '/../uploads/profile_pictures/' . $profileFilename;
$legacyProfilePath = __DIR__ . '/../IMAGES/' . $profileFilename;
$profile_picture = $profileFilename !== '' && is_file($profileStoragePath)
    ? "../uploads/profile_pictures/" . rawurlencode($profileFilename)
    : ($profileFilename !== '' && is_file($legacyProfilePath)
        ? "../IMAGES/" . rawurlencode($profileFilename)
        : "../IMAGES/default-avatar.png");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - BPSO System</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <link rel="stylesheet" href="edit_profile.css">
    <link rel="stylesheet" href="main.css">
</head>

<body>

    <?php include 'sidebar.php'; ?>

<div class="main-wrapper">

<div class="content">

<div class="edit-profile-wrapper">

<div class="edit-profile-container">

    <h1 class="page-title">
        <i class="fas fa-edit"></i> Edit My Profile
    </h1>

    <form
        method="POST"
        action="update_profile.php"
        enctype="multipart/form-data">

        <!-- CSRF TOKEN -->
        <input
            type="hidden"
            name="csrf_token"
            value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">

        <!-- PROFILE PICTURE SECTION -->
        <div class="profile-picture-section">

            <img
                id="profilePreview"
                src="<?php echo htmlspecialchars($profile_picture); ?>"
                alt="Profile Picture"
                class="profile-picture-preview">

            <button
                type="button"
                class="btn-choose-photo"
                onclick="document.getElementById('profilePictureInput').click()">

                Choose Photo

            </button>

            <input
                type="file"
                id="profilePictureInput"
                name="picture_profile"
                class="profile-picture-input"
                accept="image/*">

            <div
                class="file-name"
                id="fileName">
                No file chosen
            </div>

        </div>

        <!-- PERSONAL INFORMATION -->
        <div class="form-section">

            <h2 class="form-section-title">
                Personal Information
            </h2>

            <div class="form-grid">

                <div class="form-group">

                    <label>Full Name</label>

                    <input
                        type="text"
                        name="name"
                        value="<?php echo htmlspecialchars($officer['name']); ?>"
                        required />

                </div>


                <div class="form-group">

                    <label>Username</label>

                    <input
                        type="text"
                        name="username"
                        value="<?php echo htmlspecialchars($officer['username']); ?>"
                        required />

                </div>


                <div class="form-group">

                    <label>Email Address</label>

                    <input
                        type="email"
                        name="email"
                        value="<?php echo htmlspecialchars($officer['email']); ?>"
                        required />

                </div>


                <div class="form-group">

                    <label>Contact Number</label>

                    <input
                        type="text"
                        name="contact_number"
                        value="<?php echo htmlspecialchars($officer['contact_number']); ?>"
                        required />

                </div>


                <div class="form-group">

                    <label>Position</label>

                    <input
                        type="text"
                        name="position"
                        value="<?php echo htmlspecialchars($officer['position']); ?>"
                        required />

                </div>

            </div>

        </div>


        <!-- CHANGE PASSWORD -->
        <div class="form-section">

            <h2 class="form-section-title">
                Change Password (Optional)
            </h2>

            <div class="form-grid">

                <div class="form-group">

                    <label>Current Password</label>

                    <input
                        type="password"
                        name="current_password"
                        placeholder="Enter current password" />

                </div>


                <div class="form-group">

                    <label>New Password</label>

                    <input
                        type="password"
                        name="new_password"
                        placeholder="Enter new password" />

                </div>


                <div class="form-group">

                    <label>Confirm New Password</label>

                    <input
                        type="password"
                        name="confirm_password"
                        placeholder="Confirm new password" />

                </div>

            </div>

        </div>


        <!-- FORM ACTIONS -->
        <div class="form-actions">

            <button
                type="submit"
                class="btn-save">

                <i class="fas fa-save"></i>
                Save Changes

            </button>


            <a
                href="profile.php"
                class="btn-cancel">

                <i class="fas fa-times"></i>
                Cancel

            </a>

        </div>

    </form>

</div>

</div>

</div>

</div>


<script>

    // Profile picture preview
    document.getElementById('profilePictureInput').addEventListener('change', function(e) {

        const file = e.target.files[0];

        if (file) {

            const reader = new FileReader();

            reader.onload = function(event) {

                document.getElementById('profilePreview').src =
                    event.target.result;

            };

            reader.readAsDataURL(file);

            document.getElementById('fileName').textContent =
                file.name;

        } else {

            document.getElementById('fileName').textContent =
                'No file chosen';

        }

    });

</script>

</body>
</html>