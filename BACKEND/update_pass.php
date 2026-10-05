<?php
require_once __DIR__ . '/security_helpers.php';
bms_start_secure_session();
bms_send_security_headers();
require __DIR__ . '/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Invalid request method.');
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (
    !is_string($csrfToken) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $csrfToken)
) {
    http_response_code(403);
    exit('Invalid CSRF token.');
}

if (!bms_rate_limit('password-reset-complete', 10, 3600)) {
    http_response_code(429);
    exit('Too many attempts. Please try again later.');
}

$token = trim((string)($_POST['token'] ?? ''));
$password = (string)($_POST['password'] ?? '');
$confirm = (string)($_POST['confirm_password'] ?? '');

if (!preg_match('/^[a-f0-9]{64}$/i', $token) || !bms_password_is_strong($password)) {
    header('Location: /BMS/CODES/login.php?error=invalid_token');
    exit();
}
if (!hash_equals($password, $confirm)) {
    header('Location: /BMS/CODES/reset_pass.php?token=' . rawurlencode($token) . '&error=mismatch');
    exit();
}

$tokenHash = hash('sha256', $token);
$stmt = $conn->prepare(
    'SELECT email, account_type FROM password_resets WHERE token = ? AND expires_at > NOW() LIMIT 1'
);
$stmt->bind_param('s', $tokenHash);
$stmt->execute();
$result = $stmt->get_result();
$reset = $result->fetch_assoc();
$stmt->close();

if (!$reset || !in_array($reset['account_type'], ['official', 'resident'], true)) {
    header('Location: /BMS/CODES/login.php?error=invalid_token');
    exit();
}

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
if ($hashedPassword === false) {
    error_log('BMS password hashing failed during password reset.');
    http_response_code(500);
    exit('Unable to update password.');
}

$table = $reset['account_type'] === 'official' ? 'officials' : 'residents';
$conn->begin_transaction();
try {
    $stmt = $conn->prepare("UPDATE {$table} SET password = ? WHERE email = ?");
    $stmt->bind_param('ss', $hashedPassword, $reset['email']);
    if (!$stmt->execute() || $stmt->affected_rows !== 1) {
        throw new RuntimeException('Password update did not affect one account.');
    }
    $stmt->close();

    $stmt = $conn->prepare('DELETE FROM password_resets WHERE token = ?');
    $stmt->bind_param('s', $tokenHash);
    if (!$stmt->execute()) {
        throw new RuntimeException('Reset token cleanup failed.');
    }
    $stmt->close();
    $conn->commit();
} catch (Throwable $exception) {
    $conn->rollback();
    error_log('BMS password reset transaction failed.');
    http_response_code(500);
    exit('Unable to update password. Please request a new reset link.');
}

session_regenerate_id(true);
header('Location: /BMS/CODES/login.php?reset=success');
exit();
