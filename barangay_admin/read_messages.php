<?php

session_start();

include("../config/db.php");

/* Check if user is logged in */
if (!isset($_SESSION['username'])) {
    http_response_code(401);
    exit("Unauthorized.");
}

/* Generate CSRF token */
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* Only allow POST */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit("Method Not Allowed.");
}

/* Validate CSRF token */
$submitted_token = $_POST['csrf_token'] ?? '';

if (
    empty($submitted_token) ||
    !isset($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $submitted_token)
) {
    http_response_code(403);
    exit("Invalid CSRF token.");
}

/* Mark unread messages as read */
$stmt = $conn->prepare("
    UPDATE messages
    SET status = 'read'
    WHERE status = 'unread'
");

if (!$stmt) {
    http_response_code(500);
    exit("Database error.");
}

if (!$stmt->execute()) {
    $stmt->close();
    http_response_code(500);
    exit("Database error.");
}

$stmt->close();
$conn->close();

http_response_code(200);
echo "Messages marked as read.";
?>