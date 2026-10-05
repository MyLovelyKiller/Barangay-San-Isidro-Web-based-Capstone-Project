<?php
session_start();
include "config.php";

date_default_timezone_set('Asia/Manila');


/* =========================================================
   CSRF TOKEN CHECK
========================================================= */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit("Invalid request method.");
}

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


if (!isset($_SESSION['username'])) {
    echo "Not logged in.";
    exit();
}

$username = $_SESSION['username'];

/* GET OFFICIAL INFORMATION */
$stmt = $conn->prepare("
    SELECT official_id, satellite_id
    FROM officials
    WHERE username = ?
    LIMIT 1
");

if (!$stmt) {
    exit("Database error.");
}

$stmt->bind_param("s", $username);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    exit("User not found.");
}

$official = $result->fetch_assoc();
$stmt->close();

$official_id = (int)$official['official_id'];
$satellite_id = (int)($official['satellite_id'] ?? 0);

/* MAKE SURE OFFICIAL HAS A SATELLITE */
if ($satellite_id <= 0) {
    exit("Your account does not have an assigned satellite. Please contact the administrator.");
}

$date = date("Y-m-d");
$time = date("H:i:s");

/* MAKE SURE ACTION EXISTS */
if (!isset($_POST['action'])) {
    header("Location: profile.php");
    exit();
}

$action = $_POST['action'];

/* ======================
   CLOCK IN
====================== */
if ($action === "clockin") {

    /* CHECK IF ALREADY CLOCKED IN TODAY */
    $check = $conn->prepare("
        SELECT id
        FROM attendance
        WHERE official_id = ?
        AND satellite_id = ?
        AND date = ?
        LIMIT 1
    ");

    if (!$check) {
        exit("Database error.");
    }

    $check->bind_param(
        "iis",
        $official_id,
        $satellite_id,
        $date
    );

    $check->execute();

    $existing = $check->get_result();

    if ($existing->num_rows === 0) {

        /* INSERT ATTENDANCE WITH SATELLITE */
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

        if ($insert) {

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

    $check->close();
}

/* ======================
   CLOCK OUT
====================== */
if ($action === "clockout") {

    /*
     * Only update the attendance record belonging to:
     * - this official
     * - this official's satellite
     * - today's date
     * - an attendance record that has not been clocked out
     */
    $update = $conn->prepare("
        UPDATE attendance
        SET time_out = ?
        WHERE official_id = ?
        AND satellite_id = ?
        AND date = ?
        AND time_out IS NULL
    ");

    if (!$update) {
        exit("Database error.");
    }

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

$conn->close();

header("Location: profile.php");
exit();
?>