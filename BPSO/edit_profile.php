<?php
session_start();
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
    $name    = $_POST['name'];
    $username = $_POST['username'];
    // Add other fields here if they exist in your 'officials' table (e.g., email, contact)

    $upload_ok = true;
    $new_filename = "";

    // Handle Image Upload
    if (!empty($_FILES['profile_pic']['name'])) {
        $target_dir = "../IMAGES/";
        $file_extension = strtolower(pathinfo($_FILES["profile_pic"]["name"], PATHINFO_EXTENSION));
        $new_filename = "profile_" . $official_id . "_" . time() . "." . $file_extension;
        $target_file = $target_dir . $new_filename;

        // Check if image file is actual image
        $check = getimagesize($_FILES["profile_pic"]["tmp_name"]);
        if($check !== false) {
            if (move_uploaded_file($_FILES["profile_pic"]["tmp_name"], $target_file)) {
                // Update with image
                $sql = "UPDATE officials SET name=?, username=?, picture_profile=? WHERE official_id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssi", $name, $username, $new_filename, $official_id);
            } else {
                $message = "Failed to upload image.";
                $upload_ok = false;
            }
        } else {
            $message = "File is not an image.";
            $upload_ok = false;
        }
    } else {
        // Update without changing image
        $sql = "UPDATE officials SET name=?, username=? WHERE official_id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $name, $username, $official_id);
    }

    if ($upload_ok && $stmt->execute()) {
        echo "<script>alert('Profile updated successfully!'); window.location.href='profile.php';</script>";
        exit;
    } else {
        $message = "Error updating profile: " . $conn->error;
    }
}

// 3. Fetch current data to fill form
$query = "SELECT * FROM officials WHERE official_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $official_id);
$stmt->execute();
$result = $stmt->get_result();
$officer = $result->fetch_assoc();

$profile_picture = (!empty($officer['picture_profile'])) ? "../IMAGES/" . $officer['picture_profile'] : "../IMAGES/default-avatar.png";
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