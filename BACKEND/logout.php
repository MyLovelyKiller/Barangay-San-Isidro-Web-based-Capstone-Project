<?php
session_start();

/* =========================================================
   CSRF PROTECTION
   ========================================================= */

/* Only allow POST requests */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed.');
}

/* Get CSRF token */
$csrfToken = $_POST['csrf_token'] ?? '';

/* Validate CSRF token */
if (
    empty($csrfToken) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $csrfToken)
) {
    http_response_code(403);
    exit('Invalid CSRF token.');
}

/* =========================================================
   LOGOUT
   ========================================================= */

/* Unset all session variables */
$_SESSION = [];

/* Delete session cookie */
if (ini_get("session.use_cookies")) {

    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        [
            'expires'  => time() - 42000,
            'path'     => $params['path'],
            'domain'   => $params['domain'],
            'secure'   => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax'
        ]
    );
}

/* Destroy session */
session_destroy();

/* =========================================================
   PREVENT CACHE
   ========================================================= */

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

/* =========================================================
   REDIRECT
   ========================================================= */

header("Location: /BMS/index.php?logout=success");
exit();
?>
