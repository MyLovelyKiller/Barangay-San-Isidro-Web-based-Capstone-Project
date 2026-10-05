<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if (!isset($_SESSION['official_id']) || strtoupper(trim($_SESSION['department'] ?? '')) !== "CLEARANCE") {
    header("Location: /BMS/CODES/login.php?error=1");
    exit();
}

require_once '../BACKEND/db_connect.php';

$officer = null;
$user_full_name = 'Officer';
$official_id = (int)$_SESSION['official_id'];
$today = date('Y-m-d');
$is_timed_in = false;
$profile_picture = '';
$satellite_id = 0;

/* GET OFFICER INFORMATION */
$officer_query = "
    SELECT *
    FROM officials
    WHERE official_id = ?
    AND department = 'CLEARANCE'
    LIMIT 1
";

$stmt = $conn->prepare($officer_query);

if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param("i", $official_id);
$stmt->execute();

$result = $stmt->get_result();
$officer = $result->fetch_assoc();

$stmt->close();

if (!$officer) {
    die("Officer not found.");
}

$user_full_name = $officer['name'] ?? 'Officer';
$satellite_id = (int)($officer['satellite_id'] ?? 0);

/* REQUIRE ASSIGNED SATELLITE */
if ($satellite_id <= 0) {
    die("Your Clearance account does not have an assigned satellite. Please contact the administrator.");
}

/* PROFILE PICTURE */
if (!empty($officer['picture_profile'])) {
    $profile_picture = $officer['picture_profile'];
}

/* ATTENDANCE ACTION */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['attendance_action'])) {

    $action = $_POST['attendance_action'];
    $current_time = date('H:i:s');
    $current_datetime = date('Y-m-d H:i:s');

    /* ================= TIME IN ================= */

    if ($action === 'time_in') {

        $check = $conn->prepare("
            SELECT id
            FROM attendance
            WHERE official_id = ?
            AND satellite_id = ?
            AND date = ?
            AND time_out IS NULL
            LIMIT 1
        ");

        if ($check) {

            $check->bind_param(
                "iis",
                $official_id,
                $satellite_id,
                $today
            );

            $check->execute();

            $existing = $check->get_result()->fetch_assoc();

            $check->close();

            if (!$existing) {

                $status = 'Present';
                $auto_timeout = 0;

                $insert = $conn->prepare("
                    INSERT INTO attendance
                    (
                        official_id,
                        satellite_id,
                        date,
                        time_in,
                        status,
                        is_auto_timeout,
                        created_at
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");

                if ($insert) {

                    $insert->bind_param(
                        "iisssis",
                        $official_id,
                        $satellite_id,
                        $today,
                        $current_time,
                        $status,
                        $auto_timeout,
                        $current_datetime
                    );

                    $insert->execute();
                    $insert->close();
                }
            }
        }
    }

    /* ================= TIME OUT ================= */

    if ($action === 'time_out') {

        $fetch = $conn->prepare("
            SELECT id, time_in
            FROM attendance
            WHERE official_id = ?
            AND satellite_id = ?
            AND date = ?
            AND time_out IS NULL
            ORDER BY id DESC
            LIMIT 1
        ");

        if ($fetch) {

            $fetch->bind_param(
                "iis",
                $official_id,
                $satellite_id,
                $today
            );

            $fetch->execute();

            $attendance = $fetch->get_result()->fetch_assoc();

            $fetch->close();

            if ($attendance) {

                $work_hours = "0.00";

                if (!empty($attendance['time_in'])) {

                    $start = new DateTime($attendance['time_in']);
                    $end = new DateTime($current_time);

                    $interval = $start->diff($end);

                    $work_hours = number_format(
                        $interval->h + ($interval->i / 60),
                        2
                    );
                }

                $attendance_id = (int)$attendance['id'];

                $update = $conn->prepare("
                    UPDATE attendance
                    SET
                        time_out = ?,
                        work_hours = ?,
                        updated_at = ?
                    WHERE id = ?
                    AND official_id = ?
                    AND satellite_id = ?
                ");

                if ($update) {

                    $update->bind_param(
                        "sssiii",
                        $current_time,
                        $work_hours,
                        $current_datetime,
                        $attendance_id,
                        $official_id,
                        $satellite_id
                    );

                    $update->execute();
                    $update->close();
                }
            }
        }
    }

    header("Location: profile.php");
    exit();
}

/* CHECK IF TIMED IN TODAY */

$status_query = "
    SELECT id
    FROM attendance
    WHERE official_id = ?
    AND satellite_id = ?
    AND date = ?
    AND time_out IS NULL
    ORDER BY id DESC
    LIMIT 1
";

$status_stmt = $conn->prepare($status_query);

if ($status_stmt) {

    $status_stmt->bind_param(
        "iis",
        $official_id,
        $satellite_id,
        $today
    );

    $status_stmt->execute();

    $row = $status_stmt->get_result()->fetch_assoc();

    $status_stmt->close();

    $is_timed_in = $row ? true : false;
}

/* CHECK ACTIVE SESSION */

$check_active = $conn->prepare("
    SELECT id
    FROM attendance
    WHERE official_id = ?
    AND satellite_id = ?
    AND date = ?
    AND time_out IS NULL
    LIMIT 1
");

$check_active->bind_param(
    "iis",
    $official_id,
    $satellite_id,
    $today
);

$check_active->execute();

$has_active = $check_active->get_result()->fetch_assoc();

$check_active->close();

/* CHECK COMPLETED SHIFT */

$check_completed = $conn->prepare("
    SELECT id
    FROM attendance
    WHERE official_id = ?
    AND satellite_id = ?
    AND date = ?
    AND time_out IS NOT NULL
    LIMIT 1
");

$check_completed->bind_param(
    "iis",
    $official_id,
    $satellite_id,
    $today
);

$check_completed->execute();

$has_completed = $check_completed->get_result()->fetch_assoc();

$check_completed->close();

/* DEFINE FRONTEND ATTENDANCE STATUS */

if ($has_active) {
    $attendance_status = "time_out";
} elseif ($has_completed) {
    $attendance_status = "completed";
} else {
    $attendance_status = "time_in";
}

/* DECRYPTION FOR ID NUMBER */

$decrypted_id = "N/A";

if (!empty($officer['id_number'])) {

    $decrypted_id = openssl_decrypt(
        $officer['id_number'],
        $ciphering,
        $encryption_key,
        0,
        $encryption_iv
    );

    if ($decrypted_id === false) {
        $decrypted_id = "Encryption Error";
    }
}
?>