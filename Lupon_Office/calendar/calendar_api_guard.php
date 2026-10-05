<?php
/**
 * Shared guard for every calendar_*.php AJAX/JSON endpoint.
 * Include this FIRST, before db_connect.php, in each API file.
 *
 * Responsibilities:
 *   1. Start the session and respond with JSON (these are API
 *      calls, not page loads — a header() redirect would just
 *      look like a broken response to fetch()).
 *   2. Enforce the same 30-minute inactivity timeout as
 *      session_time-out.php, but return JSON instead of redirecting.
 *   3. Enforce the same LUPON-department access control used
 *      across the rest of the app.
 *   4. Verify a CSRF token on any request that isn't a GET.
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

const CALENDAR_SESSION_TIMEOUT = 1800; // 30 minutes, matches session_time-out.php

function calendar_deny($message, $code = 401) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit();
}

/* ---- SESSION TIMEOUT ---- */
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > CALENDAR_SESSION_TIMEOUT) {
    session_unset();
    session_destroy();
    calendar_deny('Your session has expired. Please log in again.', 401);
}
$_SESSION['last_activity'] = time();

/* ---- ACCESS CONTROL ---- */
if (
    !isset($_SESSION['official_id']) ||
    !isset($_SESSION['department']) ||
    strtoupper(trim($_SESSION['department'])) !== "LUPON"
) {
    calendar_deny('You must be logged in as a Lupon official to do this.', 401);
}

/* ---- CSRF CHECK (state-changing requests only) ---- */
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    $sent_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
    if (empty($_SESSION['csrf_token']) || empty($sent_token) || !hash_equals($_SESSION['csrf_token'], $sent_token)) {
        calendar_deny('Invalid or expired security token. Please refresh the page and try again.', 403);
    }
}
