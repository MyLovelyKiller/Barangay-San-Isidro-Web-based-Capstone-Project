<?php
ob_start();
session_start();
require_once "config.php";

/* =====================================================
   CSRF TOKEN
===================================================== */

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!isset($_SESSION['username'])) {
    header("Location: /BMS/CODES/login.php");
    exit();
}

$username = $_SESSION['username'];

/* GET OFFICIAL ID, DEPARTMENT AND SATELLITE */
$stmt = $conn->prepare("
    SELECT official_id, department, satellite_id
    FROM officials
    WHERE username = ?
    LIMIT 1
");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    header("Location: /BMS/CODES/login.php?error=usernotfound");
    exit();
}

$official_id = (int)$user['official_id'];
$department = strtoupper(trim($user['department'] ?? ''));
$satellite_id = (int)($user['satellite_id'] ?? 0);

/* ONLY BPSO OFFICIALS */
if ($department !== 'BPSO') {
    header("Location: /BMS/CODES/login.php?error=unauthorized");
    exit();
}

/* OFFICIAL MUST HAVE A SATELLITE */
if ($satellite_id <= 0) {
    header("Location: profile.php?error=satellite_not_assigned");
    exit();
}

/* =====================================================
   PROCESS POST ACTIONS
===================================================== */

$date = date("Y-m-d");
$time = date("H:i:s");
$action = $_POST['action'] ?? '';

/* =====================================================
   CSRF VALIDATION
   Required for clock-in and clock-out because both
   actions modify attendance records.
===================================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $csrfToken = $_POST['csrf_token'] ?? '';

    if (
        empty($csrfToken) ||
        empty($_SESSION['csrf_token']) ||
        !hash_equals(
            $_SESSION['csrf_token'],
            $csrfToken
        )
    ) {
        http_response_code(403);
        exit("Invalid CSRF token.");
    }
}

/* CLOCK IN */
if ($action === "clockin") {

    $check = $conn->prepare("
        SELECT id
        FROM attendance
        WHERE official_id = ?
        AND satellite_id = ?
        AND date = ?
        LIMIT 1
    ");

    $check->bind_param(
        "iis",
        $official_id,
        $satellite_id,
        $date
    );

    $check->execute();
    $already_exists = $check->get_result()->num_rows > 0;
    $check->close();

    if (!$already_exists) {

        $insert = $conn->prepare("
            INSERT INTO attendance
            (
                official_id,
                satellite_id,
                date,
                time_in,
                status
            )
            VALUES (?, ?, ?, ?, 'Present')
        ");

        $insert->bind_param(
            "iiss",
            $official_id,
            $satellite_id,
            $date,
            $time
        );

        $insert->execute();
        $insert->close();
    }
}

/* CLOCK OUT */
if ($action === "clockout") {

    $update = $conn->prepare("
        UPDATE attendance
        SET time_out = ?
        WHERE official_id = ?
        AND satellite_id = ?
        AND date = ?
        AND time_out IS NULL
    ");

    $update->bind_param(
        "siis",
        $time,
        $official_id,
        $satellite_id,
        $date
    );

    $update->execute();
    $update->close();
}

header("Location: profile.php");
exit();
?>