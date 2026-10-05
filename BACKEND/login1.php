<?php

/* =========================================================
   SECURE SESSION SETTINGS
   ========================================================= */

$secureCookie = (
    isset($_SERVER['HTTPS']) &&
    $_SERVER['HTTPS'] !== 'off'
);

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $secureCookie,
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();

require_once "db_connect.php";


/* =========================================================
   BASIC REQUEST CHECK
   ========================================================= */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: /BMS/CODES/login.php?error=invalid");
    exit();
}


/* =========================================================
   CSRF TOKEN CHECK
   ========================================================= */

if (
    !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
    !hash_equals(
        $_SESSION['csrf_token'],
        $_POST['csrf_token']
    )
) {
    header("Location: /BMS/CODES/login.php?error=invalid");
    exit();
}


/* =========================================================
   HONEYPOT CHECK
   ========================================================= */

if (!empty($_POST['bms_v_field'] ?? '')) {
    header("Location: /BMS/CODES/login.php?error=bot_detected");
    exit();
}


/* =========================================================
   REQUIRED INPUTS
   ========================================================= */

$account_type = strtolower(trim($_POST['account_type'] ?? ''));
$username     = trim($_POST['username'] ?? '');
$password     = $_POST['password'] ?? '';
$recaptchaToken = trim($_POST['g-recaptcha-response'] ?? '');


if (
    $account_type === '' ||
    $username === '' ||
    $password === '' ||
    $recaptchaToken === ''
) {
    header("Location: /BMS/CODES/login.php?error=invalid");
    exit();
}


/* =========================================================
   ACCOUNT TYPE VALIDATION
   ========================================================= */

if (
    $account_type !== 'resident' &&
    $account_type !== 'official'
) {
    header("Location: /BMS/CODES/login.php?error=invalid");
    exit();
}


/* =========================================================
   USERNAME VALIDATION
   ========================================================= */

if (
    strlen($username) < 4 ||
    strlen($username) > 50 ||
    !preg_match('/^[A-Za-z0-9_.-]+$/', $username)
) {
    header("Location: /BMS/CODES/login.php?error=invalid");
    exit();
}


/* =========================================================
   PASSWORD LENGTH PROTECTION
   ========================================================= */

if (strlen($password) > 128) {
    header("Location: /BMS/CODES/login.php?error=invalid");
    exit();
}


/* =========================================================
   RECAPTCHA ENTERPRISE CONFIG
   ========================================================= */

$configPath = __DIR__ . "/recaptcha_config.php";

if (!file_exists($configPath)) {
    header("Location: /BMS/CODES/login.php?error=captcha_failed");
    exit();
}

$recaptchaConfig = require $configPath;

$projectId = $recaptchaConfig['project_id'] ?? '';
$apiKey = $recaptchaConfig['api_key'] ?? '';
$siteKey = $recaptchaConfig['site_key'] ?? '';
$expectedAction = $recaptchaConfig['expected_action'] ?? 'login';
$minimumScore = (float)($recaptchaConfig['minimum_score'] ?? 0.3);


/* =========================================================
   CHECK RECAPTCHA CONFIG
   ========================================================= */

if (
    $projectId === '' ||
    $apiKey === '' ||
    $siteKey === ''
) {
    header("Location: /BMS/CODES/login.php?error=captcha_failed");
    exit();
}


/* =========================================================
   RECAPTCHA ENTERPRISE ASSESSMENT
   ========================================================= */

$recaptchaUrl =
    "https://recaptchaenterprise.googleapis.com/v1/projects/" .
    rawurlencode($projectId) .
    "/assessments?key=" .
    rawurlencode($apiKey);


/* =========================================================
   RECAPTCHA PAYLOAD
   ========================================================= */

$payload = [
    'event' => [
        'token' => $recaptchaToken,
        'siteKey' => $siteKey,
        'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'userIpAddress' => $_SERVER['REMOTE_ADDR'] ?? '',
        'expectedAction' => $expectedAction
    ]
];

$jsonPayload = json_encode($payload);


/* =========================================================
   SEND RECAPTCHA REQUEST
   ========================================================= */

$ch = curl_init($recaptchaUrl);

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json'
    ],
    CURLOPT_POSTFIELDS => $jsonPayload,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_CONNECTTIMEOUT => 5
]);

$recaptchaResponse = curl_exec($ch);

$curlError = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);


/* =========================================================
   RECAPTCHA REQUEST FAILURE
   ========================================================= */

if (
    $recaptchaResponse === false ||
    $curlError !== '' ||
    $httpCode < 200 ||
    $httpCode >= 300
) {
    header("Location: /BMS/CODES/login.php?error=captcha_failed");
    exit();
}


/* =========================================================
   DECODE RECAPTCHA RESPONSE
   ========================================================= */

$recaptchaData = json_decode(
    $recaptchaResponse,
    true
);

if (!is_array($recaptchaData)) {
    header("Location: /BMS/CODES/login.php?error=captcha_failed");
    exit();
}


/* =========================================================
   CHECK TOKEN VALIDITY
   ========================================================= */

$tokenProperties =
    $recaptchaData['tokenProperties'] ?? [];

$riskAnalysis =
    $recaptchaData['riskAnalysis'] ?? [];

$tokenValid =
    $tokenProperties['valid'] ?? false;

$returnedAction =
    $tokenProperties['action'] ?? '';

$score =
    isset($riskAnalysis['score'])
        ? (float)$riskAnalysis['score']
        : 0.0;


/* =========================================================
   RECAPTCHA VALIDATION
   ========================================================= */

if (!$tokenValid) {
    header("Location: /BMS/CODES/login.php?error=captcha_failed");
    exit();
}


/* =========================================================
   CHECK RECAPTCHA ACTION
   ========================================================= */

if ($returnedAction !== $expectedAction) {
    header("Location: /BMS/CODES/login.php?error=captcha_failed");
    exit();
}


/* =========================================================
   CHECK RECAPTCHA SCORE
   ========================================================= */

if ($score < $minimumScore) {
    header("Location: /BMS/CODES/login.php?error=bot_detected");
    exit();
}


/* =========================================================
   RESIDENT LOGIN
   ========================================================= */

if ($account_type === 'resident') {

    $stmt = $conn->prepare("
        SELECT *
        FROM residents
        WHERE username = ?
        LIMIT 1
    ");

    if (!$stmt) {
        header("Location: /BMS/CODES/login.php?error=invalid");
        exit();
    }

    $stmt->bind_param("s", $username);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows !== 1) {
        $stmt->close();

        header("Location: /BMS/CODES/login.php?error=invalid");
        exit();
    }

    $user = $result->fetch_assoc();

    $stmt->close();


    /* =====================================================
       CHECK ACCOUNT LOCK
       ===================================================== */

    $lockUntil = $user['lock_until'] ?? null;

    if (
        !empty($lockUntil) &&
        strtotime($lockUntil) !== false &&
        strtotime($lockUntil) > time()
    ) {
        header("Location: /BMS/CODES/login.php?error=locked");
        exit();
    }


    /* =====================================================
       PASSWORD CHECK
       ===================================================== */

    if (
        !isset($user['password']) ||
        !password_verify($password, $user['password'])
    ) {

        $failedAttempts =
            (int)($user['failed_attempts'] ?? 0);

        $failedAttempts++;

        if ($failedAttempts >= 3) {

            $newLockUntil =
                date(
                    'Y-m-d H:i:s',
                    time() + (20 * 60)
                );

            $update = $conn->prepare("
                UPDATE residents
                SET failed_attempts = 0,
                    lock_until = ?
                WHERE resident_id = ?
            ");

            if ($update) {
                $update->bind_param(
                    "si",
                    $newLockUntil,
                    $user['resident_id']
                );

                $update->execute();
                $update->close();
            }

            header(
                "Location: /BMS/CODES/login.php?error=locked"
            );
            exit();

        } else {

            $update = $conn->prepare("
                UPDATE residents
                SET failed_attempts = ?
                WHERE resident_id = ?
            ");

            if ($update) {
                $update->bind_param(
                    "ii",
                    $failedAttempts,
                    $user['resident_id']
                );

                $update->execute();
                $update->close();
            }
        }

        header(
            "Location: /BMS/CODES/login.php?error=invalid"
        );
        exit();
    }


    /* =====================================================
       PASSWORD CORRECT
       Reset failed login counter
       ===================================================== */

    $update = $conn->prepare("
        UPDATE residents
        SET failed_attempts = 0,
            lock_until = NULL
        WHERE resident_id = ?
    ");

    if ($update) {
        $update->bind_param(
            "i",
            $user['resident_id']
        );

        $update->execute();
        $update->close();
    }


    /* =====================================================
       ACCOUNT STATUS
       ===================================================== */

    $status = strtolower(
        trim($user['status'] ?? '')
    );


    /* -----------------------------------------------------
       PENDING ACCOUNT
       Database currently uses lowercase: pending
       ----------------------------------------------------- */

    if ($status === 'pending') {

        header(
            "Location: /BMS/CODES/login.php?error=pending"
        );

        exit();
    }


    /* -----------------------------------------------------
       APPROVED ACCOUNT
       ----------------------------------------------------- */

    if ($status !== 'approved') {

        header(
            "Location: /BMS/CODES/login.php?error=decline"
        );

        exit();
    }


    /* =====================================================
       SUCCESSFUL RESIDENT LOGIN
       ===================================================== */

    session_regenerate_id(true);

    $_SESSION['csrf_token'] =
        bin2hex(random_bytes(32));

    $_SESSION['resident_id'] =
        $user['resident_id'];

    $_SESSION['username'] =
        $user['username'];

    $_SESSION['account_type'] =
        'resident';

    $_SESSION['department'] =
        'RESIDENT';

    $_SESSION['full_name'] =
        $user['name'] ?? '';

    /* Remove official-only session values */
    unset(
        $_SESSION['official_id'],
        $_SESSION['is_admin']
    );


    header(
        "Location: /BMS/Barangay_user/Frontend/Userdashboard.php"
    );

    exit();
}


/* =========================================================
   OFFICIAL LOGIN
   ========================================================= */

if ($account_type === 'official') {

    $stmt = $conn->prepare("
        SELECT *
        FROM officials
        WHERE username = ?
        LIMIT 1
    ");

    if (!$stmt) {
        header("Location: /BMS/CODES/login.php?error=invalid");
        exit();
    }

    $stmt->bind_param("s", $username);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows !== 1) {
        $stmt->close();

        header("Location: /BMS/CODES/login.php?error=invalid");
        exit();
    }

    $official = $result->fetch_assoc();

    $stmt->close();


    /* =====================================================
       CHECK ADMIN/OFFICIAL LOCK
       ===================================================== */

    if (
        isset($official['is_locked']) &&
        (int)$official['is_locked'] === 1
    ) {
        header(
            "Location: /BMS/CODES/login.php?error=locked"
        );
        exit();
    }


    /* =====================================================
       PASSWORD CHECK
       ===================================================== */

    if (
        !isset($official['password']) ||
        !password_verify(
            $password,
            $official['password']
        )
    ) {
        header(
            "Location: /BMS/CODES/login.php?error=invalid"
        );
        exit();
    }


    /* =====================================================
       ACCOUNT STATUS
       ===================================================== */

    $status = strtolower(
        trim($official['status'] ?? '')
    );


    /* -----------------------------------------------------
       PENDING OFFICIAL
       Database currently uses lowercase: pending
       ----------------------------------------------------- */

    if ($status === 'pending') {

        header(
            "Location: /BMS/CODES/login.php?error=pending"
        );

        exit();
    }


    /* -----------------------------------------------------
       ACTIVE / APPROVED OFFICIAL
       ----------------------------------------------------- */

    if (
        $status !== 'active' &&
        $status !== 'approved'
    ) {

        header(
            "Location: /BMS/CODES/login.php?error=decline"
        );

        exit();
    }


    /* =====================================================
       CHECK DEPARTMENT
       ===================================================== */

    $department = strtoupper(
        trim($official['department'] ?? '')
    );

    $allowedDepartments = [
        'ADMIN',
        'BPSO',
        'CLEARANCE',
        'LUPON'
    ];

    if (
        !in_array(
            $department,
            $allowedDepartments,
            true
        )
    ) {
        header(
            "Location: /BMS/CODES/login.php?error=invalid"
        );
        exit();
    }


    /* =====================================================
       SUCCESSFUL OFFICIAL LOGIN
       ===================================================== */

    session_regenerate_id(true);

    $_SESSION['csrf_token'] =
        bin2hex(random_bytes(32));

    $_SESSION['official_id'] =
        $official['official_id'];

    $_SESSION['username'] =
        $official['username'];

    $_SESSION['department'] =
        $department;

    $_SESSION['full_name'] =
        $official['name'] ?? '';

    $_SESSION['account_type'] =
        'official';

    /* Remove resident-only session value */
    unset(
        $_SESSION['resident_id']
    );


    /* =====================================================
       DEPARTMENT REDIRECT
       ===================================================== */

    switch ($department) {

        case 'ADMIN':

            header(
                "Location: /BMS/barangay_admin/admin_dashboard.php"
            );

            exit();


        case 'BPSO':

            header(
                "Location: /BMS/BPSO/index.php"
            );

            exit();


        case 'CLEARANCE':

            header(
                "Location: /BMS/Clearance/dashboard.php"
            );

            exit();


        case 'LUPON':

            header(
                "Location: /BMS/Lupon_Office/dashboard/dashboard.php"
            );

            exit();
    }


    /* Fallback */
    header(
        "Location: /BMS/CODES/login.php?error=invalid"
    );

    exit();
}


/* =========================================================
   FINAL FALLBACK
   ========================================================= */

header(
    "Location: /BMS/CODES/login.php?error=invalid"
);

exit();
?>
