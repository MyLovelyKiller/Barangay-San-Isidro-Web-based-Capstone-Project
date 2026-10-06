```php id="x2k7md"
<?php

/* =========================
   SESSION TIMEOUT
   ========================= */

/* 30 minutes */
$timeout = 1800;

/*
 * Check whether the session has been inactive
 * for longer than the allowed timeout.
 */
if (isset($_SESSION['last_activity'])) {

    $inactive_time = time() - (int)$_SESSION['last_activity'];

    if ($inactive_time > $timeout) {

        /* Clear all session data */
        $_SESSION = [];

        /* Remove the session cookie if one exists */
        if (ini_get('session.use_cookies')) {

            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        /* Destroy the current session */
        session_destroy();

        /* Redirect to login with timeout indicator */
        header("Location: /BMS/CODES/login.php?timeout=1");
        exit();
    }
}

/*
 * Update activity timestamp after the timeout check.
 */
$_SESSION['last_activity'] = time();

?>
