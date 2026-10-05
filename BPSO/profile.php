<?php
session_start();
include "../BACKEND/db_connect.php";
include 'session_time-out.php';

/* =========================================================
   CSRF PROTECTION
   ========================================================= */

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];

// --- ENCRYPTION SETTINGS ---
// Ensure these match your registration script
if (
    !isset($_SESSION['official_id']) ||
    !isset($_SESSION['department']) ||
    strtoupper(trim($_SESSION['department'])) !== "BPSO"
) {
    header("Location: /BMS/CODES/login.php?error=1");
    exit();
}

$official_id = (int) $_SESSION['official_id'];
$today = date('Y-m-d');
$now_time = date('H:i:s');
$now_dt = date('Y-m-d H:i:s');

/* GET OFFICIAL INFO */
$officer_query = "SELECT * FROM officials WHERE official_id = ?";
$stmt = $conn->prepare($officer_query);
$stmt->bind_param("i", $official_id);
$stmt->execute();
$officer_result = $stmt->get_result();
$officer = $officer_result->fetch_assoc();
$stmt->close();

if (!$officer) {
    session_destroy();
    header("Location: /BMS/CODES/login.php?error=1");
    exit();
}

/* HANDLE ATTENDANCE POST */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['attendance_action'])) {

    /* =====================================================
       VALIDATE CSRF TOKEN
       ===================================================== */

    $submitted_token = $_POST['csrf_token'] ?? '';

    if (
        empty($submitted_token) ||
        !hash_equals($_SESSION['csrf_token'], $submitted_token)
    ) {
        http_response_code(403);
        die("Invalid CSRF token.");
    }


    $action = $_POST['attendance_action'];

    if ($action === 'time_in') {
        $check_stmt = $conn->prepare("SELECT id FROM attendance WHERE official_id = ? AND date = ? LIMIT 1");
        $check_stmt->bind_param("is", $official_id, $today);
        $check_stmt->execute();

        if ($check_stmt->get_result()->num_rows === 0) {
            $insert_stmt = $conn->prepare("INSERT INTO attendance (official_id, date, time_in, status, is_auto_timeout, created_at) VALUES (?, ?, ?, 'Present', 0, ?)");
            $insert_stmt->bind_param("isss", $official_id, $today, $now_time, $now_dt);
            $insert_stmt->execute();
            $insert_stmt->close();
        }

        $check_stmt->close();
    }

    if ($action === 'time_out') {
        $check_stmt = $conn->prepare("SELECT id, time_in FROM attendance WHERE official_id = ? AND date = ? AND time_out IS NULL LIMIT 1");
        $check_stmt->bind_param("is", $official_id, $today);
        $check_stmt->execute();

        $attendance_row = $check_stmt->get_result()->fetch_assoc();
        $check_stmt->close();

        if ($attendance_row) {
            $start = new DateTime($today . ' ' . $attendance_row['time_in']);
            $end = new DateTime($now_dt);

            $diff_seconds = max(
                0,
                $end->getTimestamp() - $start->getTimestamp()
            );

            $work_hours = number_format($diff_seconds / 3600, 2);

            $update_stmt = $conn->prepare("UPDATE attendance SET time_out = ?, work_hours = ?, updated_at = ? WHERE id = ?");
            $update_stmt->bind_param(
                "sssi",
                $now_time,
                $work_hours,
                $now_dt,
                $attendance_row['id']
            );

            $update_stmt->execute();
            $update_stmt->close();
        }
    }

    header("Location: profile.php");
    exit();
}

/* CHECK ATTENDANCE STATUS FOR UI */
$status_stmt = $conn->prepare("SELECT time_in, time_out FROM attendance WHERE official_id = ? AND date = ? LIMIT 1");
$status_stmt->bind_param("is", $official_id, $today);
$status_stmt->execute();
$status_result = $status_stmt->get_result();

$attendance_status = "time_in"; 

if ($row = $status_result->fetch_assoc()) {

    if (!empty($row['time_in']) && empty($row['time_out'])) {
        $attendance_status = "time_out";

    } elseif (!empty($row['time_in']) && !empty($row['time_out'])) {
        $attendance_status = "completed"; 
    }
}

$status_stmt->close();

/* DECRYPTION */
$decrypted_id = "N/A"; 

if (!empty($officer['id_number'])) {
    $decrypted_id = bms_decrypt_profile_id($officer['id_number']);

    if ($decrypted_id === false) {
        $decrypted_id = "Encryption Error";
    }
}

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
    <title>My Profile</title>

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <link rel="stylesheet" href="main.css">
    <link rel="stylesheet" href="sidebar.css">
    <link rel="stylesheet" href="profile.css">
</head>

<body>

    <?php include 'sidebar.php'; ?>

    <div class="main-wrapper">

        <div class="content">

            <div class="profile-wrapper">

                <div class="profile-header">

                    <div class="profile-avatar-section">

                        <div class="profile-avatar <?php echo (empty($officer['picture_profile']) ? 'no-image' : ''); ?>">

                            <img
                                src="<?php echo htmlspecialchars($profile_picture); ?>"
                                alt="Profile">

                        </div>

                    </div>


                    <div class="profile-info">

                        <h1 class="profile-name">
                            <?php echo htmlspecialchars($officer['name']); ?>
                        </h1>

                        <p class="profile-position">
                            Department -
                            <?php echo htmlspecialchars($officer['department']); ?>
                            Officer
                        </p>


                        <div class="profile-meta">

                            <div class="meta-item">

                                <span class="meta-label">
                                    Status
                                </span>

                                <span class="meta-value">
                                    <?php echo htmlspecialchars($officer['status']); ?>
                                </span>

                            </div>

                        </div>


                        <div class="profile-actions">

                            <?php if ($attendance_status === "time_in"): ?>

                                <form method="POST">

                                    <!-- CSRF TOKEN -->
                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">

                                    <button
                                        type="submit"
                                        name="attendance_action"
                                        value="time_in"
                                        class="btn-attendance btn-time-in">

                                        <i class="fas fa-sign-in-alt"></i>
                                        Time In

                                    </button>

                                </form>


                            <?php elseif ($attendance_status === "time_out"): ?>

                                <form method="POST">

                                    <!-- CSRF TOKEN -->
                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">

                                    <button
                                        type="submit"
                                        name="attendance_action"
                                        value="time_out"
                                        class="btn-attendance btn-time-out">

                                        <i class="fas fa-sign-out-alt"></i>
                                        Time Out

                                    </button>

                                </form>


                            <?php else: ?>

                                <button
                                    class="btn-attendance"
                                    style="background: #28a745; cursor: default; border: none; color: white;"
                                    disabled>

                                    <i class="fas fa-check-double"></i>
                                    Shift Completed

                                </button>

                            <?php endif; ?>


                            <a
                                href="edit_profile.php"
                                class="btn-edit-profile">

                                <i class="fas fa-edit"></i>
                                Edit Profile

                            </a>

                        </div>

                    </div>

                </div>


                <div class="profile-details">

                    <h2 class="section-title">
                        <i class="fas fa-info-circle"></i>
                        Official Information
                    </h2>


                    <div class="details-grid">

                        <div class="detail-column">

                            <div class="detail-item">

                                <span class="detail-label">
                                    Full Name
                                </span>

                                <span class="detail-value">
                                    <?php echo htmlspecialchars($officer['name']); ?>
                                </span>

                            </div>


                            <div class="detail-item">

                                <span class="detail-label">
                                    Official ID
                                </span>

                                <span class="detail-value">
                                    <?php echo htmlspecialchars($decrypted_id); ?>
                                </span>

                            </div>


                            <div class="detail-item">

                                <span class="detail-label">
                                    Username
                                </span>

                                <span class="detail-value">
                                    <?php echo htmlspecialchars($officer['username']); ?>
                                </span>

                            </div>

                        </div>


                        <div class="detail-column">

                            <div class="detail-item">

                                <span class="detail-label">
                                    Department
                                </span>

                                <span class="detail-value">
                                    <?php echo htmlspecialchars($officer['department']); ?>
                                </span>

                            </div>


                            <div class="detail-item">

                                <span class="detail-label">
                                    Contact Number
                                </span>

                                <span class="detail-value">
                                    <?php echo htmlspecialchars($officer['contact_number']); ?>
                                </span>

                            </div>

                        </div>


                        <div class="detail-column">

                            <div class="detail-item">

                                <span class="detail-label">
                                    Email Address
                                </span>

                                <span class="detail-value">
                                    <?php echo htmlspecialchars($officer['email']); ?>
                                </span>

                            </div>


                            <div class="detail-item">

                                <span class="detail-label">
                                    Date Joined
                                </span>

                                <span class="detail-value">

                                    <?php
                                    echo !empty($officer['created_at'])
                                        ? date('M d, Y', strtotime($officer['created_at']))
                                        : 'N/A';
                                    ?>

                                </span>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

</body>
</html>