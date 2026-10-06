<?php

function bms_start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');

    $isHttps = (isset($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
        || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function bms_send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=()');
}

function bms_require_official_department(mysqli $conn, string $department): void
{
    $officialId = filter_var($_SESSION['official_id'] ?? null, FILTER_VALIDATE_INT);
    $sessionDepartment = strtoupper(trim((string)($_SESSION['department'] ?? '')));

    if (
        ($_SESSION['account_type'] ?? '') !== 'official' ||
        $officialId === false ||
        $officialId === null ||
        $officialId < 1 ||
        $sessionDepartment !== strtoupper($department)
    ) {
        http_response_code(403);
        exit('Access denied.');
    }

    $stmt = $conn->prepare(
        'SELECT department, status FROM officials WHERE official_id = ? LIMIT 1'
    );
    if (!$stmt) {
        error_log('BMS official authorization query could not be prepared.');
        http_response_code(500);
        exit('Authorization could not be verified.');
    }

    $stmt->bind_param('i', $officialId);
    if (!$stmt->execute()) {
        $stmt->close();
        error_log('BMS official authorization query failed.');
        http_response_code(500);
        exit('Authorization could not be verified.');
    }

    $official = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (
        !$official ||
        strtoupper(trim((string)$official['department'])) !== strtoupper($department) ||
        $official['status'] !== 'Active'
    ) {
        http_response_code(403);
        exit('Access denied.');
    }
}

function bms_password_is_strong(string $password): bool
{
    return strlen($password) >= 8
        && strlen($password) <= 128
        && preg_match('/[a-z]/', $password) === 1
        && preg_match('/[A-Z]/', $password) === 1
        && preg_match('/[0-9]/', $password) === 1
        && preg_match('/[^A-Za-z0-9]/', $password) === 1;
}

function bms_rate_limit(string $action, int $limit, int $windowSeconds): bool
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if ($ip === '') {
        return false;
    }

    $directory = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR . 'bms-rate-limits';
    if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
        error_log('BMS rate-limit storage could not be created.');
        return false;
    }

    $file = $directory . DIRECTORY_SEPARATOR
        . hash('sha256', $action . "\0" . $ip) . '.json';
    $handle = @fopen($file, 'c+');
    if ($handle === false) {
        error_log('BMS rate-limit storage could not be opened.');
        return false;
    }

    try {
        if (!flock($handle, LOCK_EX)) {
            error_log('BMS rate-limit storage could not be locked.');
            return false;
        }

        $contents = stream_get_contents($handle);
        $attempts = json_decode($contents === false ? '' : $contents, true);
        if (!is_array($attempts)) {
            $attempts = [];
        }

        $cutoff = time() - $windowSeconds;
        $attempts = array_values(array_filter($attempts, static function ($timestamp) use ($cutoff): bool {
            return is_int($timestamp) && $timestamp > $cutoff;
        }));

        if (count($attempts) >= $limit) {
            return false;
        }

        $attempts[] = time();
        rewind($handle);
        if (!ftruncate($handle, 0) || fwrite($handle, json_encode($attempts)) === false) {
            error_log('BMS rate-limit storage could not be updated.');
            return false;
        }

        return true;
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function bms_mail_config(): array
{
    $config = require __DIR__ . '/mail_config.php';
    foreach (['api_key', 'from_email', 'from_name'] as $key) {
        if (!isset($config[$key]) || $config[$key] === '') {
            error_log('BMS mail configuration is incomplete.');
            throw new RuntimeException('Email service is not configured.');
        }
    }
    return $config;
}

function bms_send_email(string $to, string $subject, string $html): void
{
    if (!function_exists('curl_init')) {
        error_log('BMS Resend email could not be sent because the PHP cURL extension is unavailable.');
        throw new RuntimeException('Email service is unavailable.');
    }

    $config = bms_mail_config();
    $payload = json_encode([
        'from' => $config['from_name'] . ' <' . $config['from_email'] . '>',
        'to' => [$to],
        'subject' => $subject,
        'html' => $html,
    ]);
    if ($payload === false) {
        error_log('BMS Resend email payload could not be encoded.');
        throw new RuntimeException('Email could not be sent.');
    }

    $curl = curl_init('https://api.resend.com/emails');
    if ($curl === false) {
        error_log('BMS Resend email request could not be initialized.');
        throw new RuntimeException('Email service is unavailable.');
    }

    if (!curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $config['api_key'],
            'Content-Type: application/json',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ])) {
        curl_close($curl);
        error_log('BMS Resend email request options could not be configured.');
        throw new RuntimeException('Email service is unavailable.');
    }

    $response = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    if ($response === false) {
        $error = curl_error($curl);
        curl_close($curl);
        error_log('BMS Resend email request failed: ' . $error);
        throw new RuntimeException('Email service request failed.');
    }
    curl_close($curl);

    if ($status < 200 || $status >= 300) {
        error_log('BMS Resend email request returned HTTP ' . $status . '.');
        throw new RuntimeException('Email service rejected the request.');
    }
}

function bms_verify_recaptcha(string $token, string $expectedAction): bool
{
    $config = require __DIR__ . '/recaptcha_config.php';
    $projectId = $config['project_id'] ?? '';
    $apiKey = $config['api_key'] ?? '';
    $siteKey = $config['site_key'] ?? '';

    if ($token === '' || $projectId === '' || $apiKey === '' || $siteKey === '') {
        error_log('BMS reCAPTCHA configuration or token is missing.');
        return false;
    }

    $url = 'https://recaptchaenterprise.googleapis.com/v1/projects/'
        . rawurlencode($projectId) . '/assessments?key=' . rawurlencode($apiKey);
    $payload = json_encode([
        'event' => [
            'token' => $token,
            'siteKey' => $siteKey,
            'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'userIpAddress' => $_SERVER['REMOTE_ADDR'] ?? '',
            'expectedAction' => $expectedAction,
        ],
    ]);

    if ($payload === false) {
        error_log('BMS reCAPTCHA request could not be encoded.');
        return false;
    }

    $curl = curl_init($url);
    if ($curl === false) {
        error_log('BMS reCAPTCHA cURL initialization failed.');
        return false;
    }

    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $response = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);

    if ($response === false || $error !== '' || $status < 200 || $status >= 300) {
        error_log('BMS reCAPTCHA service request failed.');
        return false;
    }

    $assessment = json_decode($response, true);
    $properties = is_array($assessment) ? ($assessment['tokenProperties'] ?? []) : [];
    $risk = is_array($assessment) ? ($assessment['riskAnalysis'] ?? []) : [];
    $threshold = (float)($config['minimum_score'] ?? 0.3);

    return ($properties['valid'] ?? false) === true
        && ($properties['action'] ?? '') === $expectedAction
        && (float)($risk['score'] ?? 0) >= $threshold;
}

function bms_encrypt_profile_id(string $plainText)
{
    $key = base64_decode(getenv('BMS_PROFILE_ENCRYPTION_KEY_B64') ?: '', true);
    if ($key === false || strlen($key) !== 32) {
        error_log('BMS profile encryption key is missing or invalid.');
        return false;
    }

    $nonce = random_bytes(12);
    $tag = '';
    $cipherText = openssl_encrypt($plainText, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag);
    if ($cipherText === false || strlen($tag) !== 16) {
        error_log('BMS profile ID encryption failed.');
        return false;
    }

    return 'v2:' . base64_encode($nonce . $tag . $cipherText);
}

function bms_decrypt_profile_id(string $storedValue)
{
    if (ctype_digit($storedValue)) {
        return $storedValue;
    }

    if (strncmp($storedValue, 'v2:', 3) === 0) {
        $key = base64_decode(getenv('BMS_PROFILE_ENCRYPTION_KEY_B64') ?: '', true);
        $payload = base64_decode(substr($storedValue, 3), true);
        if ($key === false || strlen($key) !== 32 || $payload === false || strlen($payload) < 28) {
            return false;
        }

        return openssl_decrypt(
            substr($payload, 28),
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            substr($payload, 0, 12),
            substr($payload, 12, 16)
        );
    }

    $legacyKey = base64_decode(getenv('BMS_PROFILE_LEGACY_KEY_B64') ?: '', true);
    $legacyIv = base64_decode(getenv('BMS_PROFILE_LEGACY_IV_B64') ?: '', true);
    if ($legacyKey !== false && strlen($legacyKey) >= 32 && $legacyIv !== false && strlen($legacyIv) === 16) {
        $decoded = openssl_decrypt($storedValue, 'AES-256-CBC', substr($legacyKey, 0, 32), 0, $legacyIv);
        if (is_string($decoded) && ctype_digit($decoded)) {
            return $decoded;
        }
    }

    $registrationKey = base64_decode(getenv('BMS_REGISTRATION_LEGACY_KEY_B64') ?: '', true);
    $registrationIv = base64_decode(getenv('BMS_REGISTRATION_LEGACY_IV_B64') ?: '', true);
    if ($registrationKey !== false && strlen($registrationKey) >= 16 && $registrationIv !== false && strlen($registrationIv) === 16) {
        $decoded = openssl_decrypt(
            $storedValue,
            'AES-128-CTR',
            substr($registrationKey, 0, 16),
            0,
            $registrationIv
        );
        if (is_string($decoded) && ctype_digit($decoded)) {
            return $decoded;
        }
    }

    return false;
}

function bms_scan_file_with_clamav(string $filePath, ?string &$message = null): bool
{
    $scanner = getenv('BMS_CLAMSCAN_PATH') ?: '';
    if ($scanner === '' || !is_file($scanner)) {
        $message = 'File security scanner is unavailable.';
        error_log('BMS ClamAV scanner is not configured or not found.');
        return false;
    }

    if (!is_file($filePath) || !function_exists('exec')) {
        $message = 'File security scan could not be started.';
        error_log('BMS ClamAV cannot scan the supplied file or exec is disabled.');
        return false;
    }

    $command = escapeshellarg($scanner)
        . ' --no-summary --max-filesize=20M --max-scansize=100M '
        . escapeshellarg($filePath) . ' 2>&1';
    $output = [];
    $exitCode = -1;
    exec($command, $output, $exitCode);

    if ($exitCode === 0) {
        $message = 'Clean';
        return true;
    }

    $message = $exitCode === 1 ? 'File was detected as infected.' : 'File security scan failed.';
    error_log('BMS ClamAV scan failed with exit code ' . $exitCode . '.');
    return false;
}
