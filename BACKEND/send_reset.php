<?php
require_once __DIR__ . '/security_helpers.php';
bms_start_secure_session();
bms_send_security_headers();
require __DIR__ . '/db_connect.php';

$genericRedirect = 'Location: /BMS/CODES/login.php?reset=sent';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Invalid request method.');
}

if (!bms_rate_limit('password-reset-request', 3, 3600)) {
    header($genericRedirect);
    exit();
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

$email = filter_var(trim((string)($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
if ($email === false) {
    header($genericRedirect);
    exit();
}

$accountType = '';
$stmt = $conn->prepare('SELECT official_id FROM officials WHERE email = ? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $accountType = 'official';
}
$stmt->close();

if ($accountType === '') {
    $stmt = $conn->prepare('SELECT resident_id FROM residents WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $accountType = 'resident';
    }
    $stmt->close();
}

if ($accountType !== '') {
    try {
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + 1800);

        $stmt = $conn->prepare('DELETE FROM password_resets WHERE email = ?');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare(
            'INSERT INTO password_resets (email, token, expires_at, account_type) VALUES (?, ?, ?, ?)'
        );
        $stmt->bind_param('ssss', $email, $tokenHash, $expiresAt, $accountType);
        if (!$stmt->execute()) {
            throw new RuntimeException('Could not save reset request.');
        }
        $stmt->close();

        $baseUrlValue = getenv('BMS_APP_BASE_URL');
        if ($baseUrlValue === false || $baseUrlValue === '') {
            throw new RuntimeException('Application base URL is not configured.');
        }
        $baseUrl = rtrim($baseUrlValue, '/');
        if (!preg_match('#^https?://[A-Za-z0-9.-]+(?::[0-9]+)?(?:/[A-Za-z0-9._/-]*)?$#', $baseUrl)) {
            throw new RuntimeException('Invalid application base URL configuration.');
        }
        $resetLink = $baseUrl . '/CODES/reset_pass.php?token=' . rawurlencode($token);
        $safeLink = htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8');

        $html = '<p>We received a request to reset your Barangay San Isidro account password.</p>'
            . '<p><a href="' . $safeLink . '">Reset your password</a>. This link expires in 30 minutes.</p>'
            . '<p>If you did not request this, you can ignore this email.</p>';
        bms_send_email($email, 'Password Reset Request - Barangay San Isidro', $html);
    } catch (RuntimeException $exception) {
        error_log('BMS password reset email could not be processed.');
    }
}

header($genericRedirect);
exit();
