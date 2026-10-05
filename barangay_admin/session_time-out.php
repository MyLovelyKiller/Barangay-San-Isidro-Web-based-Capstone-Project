<?php

// Set timeout duration (in seconds)
$timeout = 1800; // 30 minutes

if (isset($_SESSION['last_activity'])) {
    $inactive_time = time() - $_SESSION['last_activity'];

    if ($inactive_time > $timeout) {
        session_unset();
        session_destroy();

        // Redirect to login with timeout flag
        header("Location: /BMS/CODES/login.php?timeout=1");
        exit();
    }
}

// Update last activity
$_SESSION['last_activity'] = time();
?>