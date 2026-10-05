<?php
session_start();
include 'config.php';

/* CHECK LOGIN */
if (!isset($_SESSION['username'])) {
    header("Location: /BMS/CODES/login.php");
    exit();
}

/* CSRF CHECK */
$submitted_token = $_POST['csrf_token'] ?? '';

if (
    empty($submitted_token) ||
    !isset($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $submitted_token)
) {
    http_response_code(403);
    die("Invalid CSRF token.");
}

/* MARK ALL UNREAD MESSAGES AS READ */
$stmt = $conn->prepare("
    UPDATE messages
    SET is_read = 1
    WHERE is_read = 0
");

$stmt->execute();

/* SAFE REDIRECT */
$redirect = $_SERVER['HTTP_REFERER'] ?? 'admin_dashboard.php';

header("Location: " . $redirect);
exit();
?>