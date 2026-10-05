<?php
// 1. Start buffering
ob_start();
session_start();
include 'db_connect.php';

/* =========================================================
   CSRF PROTECTION
   ========================================================= */

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

// Get and trim inputs
$token    = trim($_POST['token'] ?? '');
$password = trim($_POST['password'] ?? '');
$confirm  = trim($_POST['confirm_password'] ?? '');

// 1. VALIDATION: Check for empty fields
if (empty($token) || empty($password) || empty($confirm)) {
    header("Location: /BMS/CODES/login.php?error=missing_fields");
    exit();
}

// 2. VALIDATION: Check if passwords match
if ($password !== $confirm) {
    header("Location: /BMS/CODES/reset_password.php?token=$token&error=mismatch");
    exit();
}

/* --- 3. VERIFY THE RESET TOKEN (SQL Injection Protected) --- */
$sql = "SELECT email, account_type FROM password_resets WHERE token = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Token not found or expired
    header("Location: /BMS/CODES/login.php?error=invalid_token");
    exit();
}

$row = $result->fetch_assoc();
$email = $row['email'];
$account_type = $row['account_type'];
$stmt->close();

/* --- 4. HASH THE NEW PASSWORD --- */
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

/* --- 5. UPDATE THE CORRECT TABLE --- */
if ($account_type === "official") {
    $update_sql = "UPDATE officials SET password = ? WHERE email = ?";
} else {
    $update_sql = "UPDATE residents SET password = ? WHERE email = ?";
}

$update_stmt = $conn->prepare($update_sql);
$update_stmt->bind_param("ss", $hashed_password, $email);

if (!$update_stmt->execute()) {
    header("Location: /BMS/CODES/login.php?error=db_error");
    exit();
}
$update_stmt->close();

/* --- 6. CLEANUP: DELETE THE USED TOKEN --- */
// Once the password is changed, the token should never be usable again
$delete_sql = "DELETE FROM password_resets WHERE token = ?";
$delete_stmt = $conn->prepare($delete_sql);
$delete_stmt->bind_param("s", $token);
$delete_stmt->execute();
$delete_stmt->close();

// Final success redirect
header("Location: /BMS/CODES/login.php?reset=success");
exit();
?>