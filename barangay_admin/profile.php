<?php
session_start();
require_once '../BACKEND/db_connect.php';
include 'session_time-out.php';

if (!isset($_SESSION['username'])) {
    echo "User not logged in.";
    exit();
}

date_default_timezone_set('Asia/Manila');

/* ===== CSRF TOKEN ===== */
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];

$username = $_SESSION['username'];

/* GET USER */
$stmtUser = $conn->prepare("SELECT * FROM officials WHERE username = ?");
$stmtUser->bind_param("s", $username);
$stmtUser->execute();
$query = $stmtUser->get_result();
$row = $query->fetch_assoc();

if (!$row) {
    echo "User account not found.";
    exit();
}

// ADDED THIS: Sync the name from the database to the Session for the Header
if ($row) {
    $_SESSION['name'] = $row['name'];
}

$official_id = $row['official_id'];
$date = date("Y-m-d");
$time = date("H:i:s");

/* =========================
    HANDLE BUTTON CLICK
========================= */
if (isset($_POST['action'])) {

    /* ===== CSRF VALIDATION ===== */
    $submitted_token = $_POST['csrf_token'] ?? '';

    if (
        empty($submitted_token) ||
        !isset($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $submitted_token)
    ) {
        http_response_code(403);
        die("Invalid CSRF token.");
    }

    if ($_POST['action'] == "clockin") {

        $check = $conn->prepare("
            SELECT * FROM attendance
            WHERE official_id = ?
            AND date = ?
        ");
        $check->bind_param("is", $official_id, $date);
        $check->execute();
        $checkResult = $check->get_result();

        if ($checkResult->num_rows == 0) {

            $insert = $conn->prepare("
                INSERT INTO attendance (official_id, date, time_in, status)
                VALUES (?, ?, ?, 'Present')
            ");
            $insert->bind_param("iss", $official_id, $date, $time);
            $insert->execute();
        }
    }

    if ($_POST['action'] == "clockout") {

        $update = $conn->prepare("
            UPDATE attendance
            SET time_out = ?
            WHERE official_id = ?
            AND date = ?
            AND time_out IS NULL
        ");
        $update->bind_param("sis", $time, $official_id, $date);
        $update->execute();
    }

    // refresh page (no redirect issue)
    header("Location: profile.php");
    exit();
}

/* =========================
    ATTENDANCE CHECK
========================= */
$clockStatus = "clockin";
$message = "";

$stmtAttendance = $conn->prepare("
    SELECT * FROM attendance 
    WHERE official_id = ?
    AND date = ?
");
$stmtAttendance->bind_param("is", $official_id, $date);
$stmtAttendance->execute();
$result = $stmtAttendance->get_result();

if ($result && $result->num_rows > 0) {
    $att = $result->fetch_assoc();

    if (empty($att['time_out'])) {
        $clockStatus = "clockout";
    } else {
        $clockStatus = "done";
    }
}

// 1. Define Encryption Settings (Must match your registration/edit script)


// 2. Decryption for ID No. (Changed $officer to $row)
$decrypted_id = "N/A"; 

if (!empty($row['id_number'])) {
    $decrypted_id = bms_decrypt_profile_id($row['id_number']);

    // Fallback if decryption fails
    if ($decrypted_id === false) {
        $decrypted_id = "Encryption Error";
    }
}

// 3. Profile Picture Logic (Changed $officer to $row)
$profile_picture = (!empty($row['picture_profile']) && file_exists("../IMAGES/" . $row['picture_profile']))
    ? "../IMAGES/" . $row['picture_profile']
    : "../IMAGES/default-avatar.png";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Profile</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="style/main.css">
    <link rel="stylesheet" href="style/sidebar.css">
    <link rel="stylesheet" href="style/header.css">
    <link rel="stylesheet" href="style/profile.css">
</head>

<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-wrapper">
        <?php include 'header.php'; ?>

        <div class="content">
            <div class="page-title">
                My Profile
            </div>

            <div class="profile-header">
                <div class="profile-left">
                    <?php $profile = !empty($row['proof_file']) ? $row['proof_file'] : "default.png"; ?>
                    <img src="uploads/<?php echo htmlspecialchars($profile, ENT_QUOTES, 'UTF-8'); ?>" class="profile-avatar" alt="User Avatar">
                    <div>
                        <div class="profile-name">
                            <?php echo htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                        <div class="profile-sub">
                            Barangay Official
                        </div>
                    </div>
                </div>

                <div class="profile-actions">
                    <a href="edit_profile.php" class="edit-profile-btn">
                        Edit Profile
                    </a>

                    <?php if (!empty($message)) : ?>
                        <p class="clock-message" style="color: green; font-weight: bold;">
                            <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
                        </p>
                    <?php endif; ?>

                    <form method="POST">
                        <input type="hidden" name="csrf_token"
                               value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">

                        <?php if ($clockStatus == "clockin") : ?>
                            <button type="submit" name="action" value="clockin" class="clock-btn clock-in">
                                <i class="fa fa-clock"></i> Clock In
                            </button>
                        <?php elseif ($clockStatus == "clockout") : ?>
                            <button type="submit" name="action" value="clockout" class="clock-btn clock-out">
                                <i class="fa fa-clock"></i> Clock Out
                            </button>
                        <?php else : ?>
                            <button class="clock-btn" style="background:#27ae60; cursor:not-allowed;" disabled>
                                <i class="fa fa-check"></i> Shift Completed
                            </button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <div class="info-card">
                <div class="info-title">
                    Personal Information
                </div>

                <div class="info-grid">
                    <div class="info-item">
                        <label>Full Name</label>
                        <p><?php echo htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>

                    <div class="info-item">
                        <label>Email Address</label>
                        <p><?php echo htmlspecialchars($row['email'], ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>

                    <div class="info-item">
                        <label>Username</label>
                        <p><?php echo htmlspecialchars($row['username'], ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>

                    <div class="info-item">
                        <label>Contact Number</label>
                        <p><?php echo htmlspecialchars($row['contact_number'], ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>

                    <div class="info-item">
                        <label>Department</label>
                        <p><?php echo htmlspecialchars($row['department'], ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
