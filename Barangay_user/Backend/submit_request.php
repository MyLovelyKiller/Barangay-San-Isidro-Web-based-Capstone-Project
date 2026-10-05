<?php

ob_start();
require_once __DIR__ . '/../../BACKEND/security_helpers.php';
bms_start_secure_session();
bms_send_security_headers();

include "../../BACKEND/db_connect.php";

/* ============================================================
   CSRF TOKEN
   ============================================================ */

/*
 * Generate a secure CSRF token if one does not already exist.
 *
 * The token is stored in the user's PHP session and must also
 * be submitted by the form. The backend compares both values
 * before processing any POST request.
 */
if (empty($_SESSION['csrf_token'])) {

    $_SESSION['csrf_token'] = bin2hex(
        random_bytes(32)
    );

}


/* ============================================================
   CHECK IF USER IS LOGGED IN
   ============================================================ */

if (!isset($_SESSION['resident_id']) && !isset($_SESSION['username'])) {

    header("Location: /BMS/CODES/login.php");
    exit();

}


/* ============================================================
   GET USER INFO
   ============================================================ */

if (isset($_SESSION['resident_id'])) {

    $resident_id = (int)$_SESSION['resident_id'];

    $sqlUser = "SELECT * FROM residents WHERE resident_id = ?";

    $stmt = $conn->prepare($sqlUser);
    $stmt->bind_param("i", $resident_id);

} else {

    $username = $_SESSION['username'];

    $sqlUser = "SELECT * FROM residents WHERE username = ?";

    $stmt = $conn->prepare($sqlUser);
    $stmt->bind_param("s", $username);

}

$stmt->execute();

$userResult = $stmt->get_result();

if ($userResult && $userResult->num_rows > 0) {

    $userData = $userResult->fetch_assoc();

    $resident_id = (int)$userData['resident_id'];

    /*
     * Get the satellite assigned to this resident.
     */
    $satellite_id = (int)($userData['satellite_id'] ?? 0);

} else {

    session_destroy();

    header("Location: /BMS/CODES/login.php");
    exit();

}

$stmt->close();


/* ============================================================
   CHECK SATELLITE ASSIGNMENT
   ============================================================ */

if ($satellite_id <= 0) {

    die("Your account is not assigned to a satellite.");

}


/* ============================================================
   HELPER FUNCTIONS FOR FILE VALIDATION & PROCESSING
   ============================================================ */

/**
 * Validates individual file parameters
 */
function validateUploadedFile(
    $file,
    $allowedExts = ['png', 'jpeg', 'jpg', 'doc', 'docx', 'pdf'],
    $maxMb = 50
) {

    if (
        !isset($file['error']) ||
        $file['error'] === UPLOAD_ERR_NO_FILE
    ) {

        return true;

    }

    if ($file['error'] !== UPLOAD_ERR_OK) {

        if (
            $file['error'] === UPLOAD_ERR_INI_SIZE ||
            $file['error'] === UPLOAD_ERR_FORM_SIZE
        ) {

            return "File '{$file['name']}' exceeds the maximum upload limit.";

        }

        return "An error occurred while uploading '{$file['name']}'.";

    }

    $fileName = $file['name'];
    $fileSize = $file['size'];

    $maxBytes = $maxMb * 1024 * 1024;

    $ext = strtolower(
        pathinfo($fileName, PATHINFO_EXTENSION)
    );

    if (!in_array($ext, $allowedExts, true)) {

        return "Invalid file type for '{$fileName}'. Allowed formats: " .
            strtoupper(implode(', ', $allowedExts));

    }

    if ($fileSize > $maxBytes) {

        return "File '{$fileName}' exceeds the maximum size limit of {$maxMb}MB.";

    }

    return true;
}


/**
 * Pre-validates single or multi-file upload fields
 */
function preValidateFileGroup(
    $fileField,
    $allowedExts = ['png', 'jpeg', 'jpg', 'doc', 'docx', 'pdf'],
    $maxMb = 50
) {

    if (empty($fileField['name'])) {

        return true;

    }

    if (is_array($fileField['name'])) {

        foreach ($fileField['name'] as $key => $name) {

            if (
                $fileField['error'][$key] === UPLOAD_ERR_NO_FILE
            ) {

                continue;

            }

            $single = [

                'name'     => $fileField['name'][$key],
                'type'     => $fileField['type'][$key],
                'tmp_name' => $fileField['tmp_name'][$key],
                'error'    => $fileField['error'][$key],
                'size'     => $fileField['size'][$key]

            ];

            $check = validateUploadedFile(
                $single,
                $allowedExts,
                $maxMb
            );

            if ($check !== true) {

                return $check;

            }

        }

    } else {

        if (
            $fileField['error'] !== UPLOAD_ERR_NO_FILE
        ) {

            $check = validateUploadedFile(
                $fileField,
                $allowedExts,
                $maxMb
            );

            if ($check !== true) {

                return $check;

            }

        }

    }

    return true;
}


/**
 * Scan a file using ClamAV.
 *
 * ClamAV exit codes:
 * 0 = Clean
 * 1 = Infected
 * >1 = Scanner error
 */
function scanFileWithClamAV($filePath, &$scanMessage = null)
{
    // LOCAL DEVELOPMENT PATH
    $clamScanPath = getenv('BMS_CLAMSCAN_PATH') ?: '';

    // Check if ClamAV exists
    if (!is_file($clamScanPath)) {
        $scanMessage = 'ClamAV scanner was not found.';
        return false;
    }

    // Check if file exists
    if (!is_file($filePath)) {
        $scanMessage = 'File to scan was not found.';
        return false;
    }

    // Check whether PHP can execute external commands
    if (!function_exists('exec')) {
        $scanMessage = 'PHP exec() function is disabled.';
        return false;
    }

    // Build ClamAV command
    $command =
        escapeshellarg($clamScanPath) .
        ' --no-summary --max-filesize=20M --max-scansize=100M ' .
        escapeshellarg($filePath);

    $output = [];
    $exitCode = -1;

    // Run ClamAV
    exec($command, $output, $exitCode);

    // Clean
    if ($exitCode === 0) {
        $scanMessage = 'Clean';
        return true;
    }

    // Virus detected
    if ($exitCode === 1) {
        $scanMessage = 'File was detected as infected.';
        return false;
    }

    // Scanner error
    $scanMessage = 'ClamAV scan failed. Exit code: ' . $exitCode;

    return false;
}


/* ============================================================
   reCAPTCHA ENTERPRISE SERVER-SIDE VERIFICATION
   ============================================================ */

/**
 * Verifies the reCAPTCHA Enterprise token generated by steps.js.
 *
 * The frontend currently uses:
 *
 * grecaptcha.enterprise.execute(
 *     SITE_KEY,
 *     { action: "REQUEST_DOCUMENT" }
 * );
 *
 * This function sends the token to Google and verifies:
 *
 * 1. Token is valid
 * 2. Action is REQUEST_DOCUMENT
 * 3. Risk score meets the configured minimum
 */
function verifyRecaptchaEnterprise($token)
{
    /* --------------------------------------------------------
       Validate token
       -------------------------------------------------------- */

    if (
        !is_string($token) ||
        trim($token) === ''
    ) {

        return [
            'success' => false,
            'error' => 'Missing reCAPTCHA token.'
        ];

    }


    /* --------------------------------------------------------
       Load protected reCAPTCHA configuration
       -------------------------------------------------------- */

    $configPath =
        __DIR__ . "/../../BACKEND/recaptcha_config.php";

    if (!is_file($configPath)) {

        return [
            'success' => false,
            'error' => 'reCAPTCHA configuration was not found.'
        ];

    }


    $config = require $configPath;


    $projectId =
        $config['project_id'] ?? '';

    $apiKey =
        $config['api_key'] ?? '';

    $siteKey =
        $config['site_key'] ?? '';


    /*
     * The document request frontend uses this action.
     *
     * DO NOT change this unless steps.js is also changed.
     */
    $expectedAction =
        'REQUEST_DOCUMENT';


    /*
     * Use your existing minimum score from the config.
     *
     * If it is not present, use 0.3.
     */
    $minimumScore =
        isset($config['minimum_score'])
            ? (float)$config['minimum_score']
            : 0.3;


    /* --------------------------------------------------------
       Make sure configuration exists
       -------------------------------------------------------- */

    if (
        $projectId === '' ||
        $apiKey === '' ||
        $siteKey === ''
    ) {

        return [
            'success' => false,
            'error' => 'Incomplete reCAPTCHA configuration.'
        ];

    }


    /* --------------------------------------------------------
       Google Enterprise assessment endpoint
       -------------------------------------------------------- */

    $endpoint =
        'https://recaptchaenterprise.googleapis.com/v1/projects/' .
        rawurlencode($projectId) .
        '/assessments?key=' .
        rawurlencode($apiKey);


    /* --------------------------------------------------------
       Request payload
       -------------------------------------------------------- */

    $payload = [

        'event' => [

            'token' =>
                trim($token),

            'siteKey' =>
                $siteKey,

            'userAgent' =>
                $_SERVER['HTTP_USER_AGENT'] ?? '',

            'userIpAddress' =>
                $_SERVER['REMOTE_ADDR'] ?? '',

            'expectedAction' =>
                $expectedAction

        ]

    ];


    $jsonPayload =
        json_encode($payload);


    if ($jsonPayload === false) {

        return [
            'success' => false,
            'error' => 'Unable to prepare reCAPTCHA request.'
        ];

    }


    /* --------------------------------------------------------
       Send request to Google
       -------------------------------------------------------- */

    $ch =
        curl_init($endpoint);


    curl_setopt_array(
        $ch,
        [

            CURLOPT_POST =>
                true,

            CURLOPT_RETURNTRANSFER =>
                true,

            CURLOPT_HTTPHEADER =>
                [

                    'Content-Type: application/json'

                ],

            CURLOPT_POSTFIELDS =>
                $jsonPayload,

            CURLOPT_CONNECTTIMEOUT =>
                10,

            CURLOPT_TIMEOUT =>
                15,

            CURLOPT_SSL_VERIFYPEER =>
                true,

            CURLOPT_SSL_VERIFYHOST =>
                2

        ]
    );


    $response =
        curl_exec($ch);


    $curlError =
        curl_error($ch);


    $httpCode =
        (int)curl_getinfo(
            $ch,
            CURLINFO_HTTP_CODE
        );


    curl_close($ch);


    /* --------------------------------------------------------
       Check cURL failure
       -------------------------------------------------------- */

    if (
        $response === false ||
        $curlError !== ''
    ) {

        return [
            'success' => false,
            'error' => 'Unable to contact reCAPTCHA service.'
        ];

    }


    /* --------------------------------------------------------
       Check HTTP response
       -------------------------------------------------------- */

    if (
        $httpCode < 200 ||
        $httpCode >= 300
    ) {

        return [
            'success' => false,
            'error' => 'reCAPTCHA service returned an invalid response.'
        ];

    }


    /* --------------------------------------------------------
       Decode Google response
       -------------------------------------------------------- */

    $result =
        json_decode(
            $response,
            true
        );


    if (!is_array($result)) {

        return [
            'success' => false,
            'error' => 'Invalid reCAPTCHA response.'
        ];

    }


    /* --------------------------------------------------------
       Token properties
       -------------------------------------------------------- */

    $tokenProperties =
        $result['tokenProperties'] ?? [];


    $tokenValid =
        $tokenProperties['valid'] ?? false;


    $returnedAction =
        $tokenProperties['action'] ?? '';


    /* --------------------------------------------------------
       Check whether Google considers token valid
       -------------------------------------------------------- */

    if ($tokenValid !== true) {

        return [
            'success' => false,
            'error' => 'reCAPTCHA token is invalid.'
        ];

    }


    /* --------------------------------------------------------
       Check expected action
       -------------------------------------------------------- */

    if ($returnedAction !== $expectedAction) {

        return [
            'success' => false,
            'error' => 'reCAPTCHA action mismatch.'
        ];

    }


    /* --------------------------------------------------------
       Get risk score
       -------------------------------------------------------- */

    $score =
        isset($result['riskAnalysis']['score'])
            ? (float)$result['riskAnalysis']['score']
            : 0.0;


    /* --------------------------------------------------------
       Check minimum score
       -------------------------------------------------------- */

    if ($score < $minimumScore) {

        return [
            'success' => false,
            'error' => 'reCAPTCHA risk score is too low.'
        ];

    }


    /* --------------------------------------------------------
       Verification successful
       -------------------------------------------------------- */

    return [

        'success' =>
            true,

        'score' =>
            $score,

        'action' =>
            $returnedAction

    ];
}


/**
 * Saves base64 scanned document captures after ClamAV scanning.
 */
function processScannedDocument(
    $conn,
    $recordType,
    $recordId,
    $base64Data,
    $resident_id
) {

    if (empty($base64Data)) {
        return;
    }

    $upload_dir = "../../uploads/attachments/";
    $quarantine_dir = "../../uploads/quarantine/";

    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    if (!is_dir($quarantine_dir)) {
        mkdir($quarantine_dir, 0777, true);
    }

    $ext = 'png';

    if (
        preg_match(
            '/^data:image\/(\w+);base64,/',
            $base64Data,
            $type
        )
    ) {

        $base64Data = substr(
            $base64Data,
            strpos($base64Data, ',') + 1
        );

        $typeStr = strtolower($type[1]);

        if (in_array($typeStr, ['png', 'jpeg', 'jpg'], true)) {
            $ext = $typeStr;
        }

    }

    $base64Data = str_replace(' ', '+', $base64Data);

    $decodedData = base64_decode(
        $base64Data,
        true
    );

    if ($decodedData === false || $decodedData === '') {
        return;
    }

    $uniqueId = bin2hex(
        random_bytes(8)
    );

    $file_unique =
        "SCAN_" .
        date('Ymd_His') . "_" .
        $resident_id . "_" .
        $uniqueId .
        "." .
        $ext;

    /* ============================================================
       STEP 1: Save the camera capture into quarantine.
       ============================================================ */

    $quarantinePath =
        $quarantine_dir . $file_unique;

    if (
        file_put_contents(
            $quarantinePath,
            $decodedData
        ) === false
    ) {
        return;
    }

    /* ============================================================
       STEP 2: Scan the quarantined camera capture with ClamAV.
       ============================================================ */

    $scanMessage = '';

    $scanResult = scanFileWithClamAV(
        $quarantinePath,
        $scanMessage
    );

    /* ============================================================
       STEP 3: Reject infected files or scanner errors.
       ============================================================ */

    if (!$scanResult) {

        if (file_exists($quarantinePath)) {
            unlink($quarantinePath);
        }

        return;
    }

    /* ============================================================
       STEP 4: Move the clean capture to permanent storage.
       ============================================================ */

    $targetPath =
        $upload_dir . $file_unique;

    if (
        !rename(
            $quarantinePath,
            $targetPath
        )
    ) {

        if (file_exists($quarantinePath)) {
            unlink($quarantinePath);
        }

        return;
    }

    /* ============================================================
       STEP 5: Save only the clean file to the database.
       ============================================================ */

    $stmtAtt = $conn->prepare("
        INSERT INTO record_attachments
        (
            record_type,
            record_id,
            file_path
        )
        VALUES (?, ?, ?)
    ");

    if ($stmtAtt) {

        $stmtAtt->bind_param(
            "sis",
            $recordType,
            $recordId,
            $targetPath
        );

        if (!$stmtAtt->execute()) {

            // Database failure: remove the clean file so it is not orphaned.
            if (file_exists($targetPath)) {
                unlink($targetPath);
            }

        }

        $stmtAtt->close();

    } else {

        // Database prepare failure: remove the clean file.
        if (file_exists($targetPath)) {
            unlink($targetPath);
        }

    }
}


/**
 * Processes, scans, and saves uploaded attachment files
 */
function handleMultipleUploads(
    $conn,
    $recordType,
    $recordId,
    $fileField,
    $resident_id
) {

    // Permanent attachment directory
    $upload_dir = "../../uploads/attachments/";

    // Temporary quarantine directory
    $quarantine_dir = "../../uploads/quarantine/";

    // Create directories if they do not exist
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    if (!is_dir($quarantine_dir)) {
        mkdir($quarantine_dir, 0777, true);
    }

    // No file uploaded
    if (empty($fileField['name'])) {
        return;
    }


    /* ============================================================
       MULTIPLE FILE UPLOADS
       ============================================================ */

    if (is_array($fileField['name'])) {

        foreach ($fileField['name'] as $key => $name) {

            if (
                !isset($fileField['error'][$key]) ||
                $fileField['error'][$key] !== UPLOAD_ERR_OK
            ) {
                continue;
            }

            $ext = strtolower(
                pathinfo(
                    $name,
                    PATHINFO_EXTENSION
                )
            );

            $file_unique =
                "DOC_" .
                time() .
                "_" .
                $key .
                "_" .
                $resident_id .
                "." .
                $ext;

            /*
             * STEP 1: Move uploaded file into quarantine.
             */
            $quarantinePath =
                $quarantine_dir . $file_unique;

            if (
                !move_uploaded_file(
                    $fileField['tmp_name'][$key],
                    $quarantinePath
                )
            ) {
                continue;
            }

            /*
             * STEP 2: Scan quarantined file with ClamAV.
             */
            $scanMessage = '';

            $scanResult = scanFileWithClamAV(
                $quarantinePath,
                $scanMessage
            );

            /*
             * STEP 3: Reject infected files or scanner errors.
             */
            if (!$scanResult) {

                if (file_exists($quarantinePath)) {
                    unlink($quarantinePath);
                }

                continue;
            }

            /*
             * STEP 4: Move clean file to permanent storage.
             */
            $targetPath =
                $upload_dir . $file_unique;

            if (
                !rename(
                    $quarantinePath,
                    $targetPath
                )
            ) {

                if (file_exists($quarantinePath)) {
                    unlink($quarantinePath);
                }

                continue;
            }

            /*
             * STEP 5: Save clean file to database.
             */
            $stmtAtt = $conn->prepare("
                INSERT INTO record_attachments
                (
                    record_type,
                    record_id,
                    file_path
                )
                VALUES (?, ?, ?)
            ");

            if ($stmtAtt) {

                $stmtAtt->bind_param(
                    "sis",
                    $recordType,
                    $recordId,
                    $targetPath
                );

                $stmtAtt->execute();

                $stmtAtt->close();
            }
        }


    /* ============================================================
       SINGLE FILE UPLOAD
       ============================================================ */

    } else {

        if (
            !isset($fileField['error']) ||
            $fileField['error'] !== UPLOAD_ERR_OK
        ) {
            return;
        }

        $ext = strtolower(
            pathinfo(
                $fileField['name'],
                PATHINFO_EXTENSION
            )
        );

        $file_unique =
            "DOC_" .
            time() .
            "_" .
            $resident_id .
            "." .
            $ext;

        /*
         * STEP 1: Move uploaded file into quarantine.
         */
        $quarantinePath =
            $quarantine_dir . $file_unique;

        if (
            !move_uploaded_file(
                $fileField['tmp_name'],
                $quarantinePath
            )
        ) {
            return;
        }

        /*
         * STEP 2: Scan quarantined file with ClamAV.
         */
        $scanMessage = '';

        $scanResult = scanFileWithClamAV(
            $quarantinePath,
            $scanMessage
        );

        /*
         * STEP 3: Reject infected files or scanner errors.
         */
        if (!$scanResult) {

            if (file_exists($quarantinePath)) {
                unlink($quarantinePath);
            }

            return;
        }

        /*
         * STEP 4: Move clean file to permanent storage.
         */
        $targetPath =
            $upload_dir . $file_unique;

        if (
            !rename(
                $quarantinePath,
                $targetPath
            )
        ) {

            if (file_exists($quarantinePath)) {
                unlink($quarantinePath);
            }

            return;
        }

        /*
         * STEP 5: Save clean file to database.
         */
        $stmtAtt = $conn->prepare("
            INSERT INTO record_attachments
            (
                record_type,
                record_id,
                file_path
            )
            VALUES (?, ?, ?)
        ");

        if ($stmtAtt) {

            $stmtAtt->bind_param(
                "sis",
                $recordType,
                $recordId,
                $targetPath
            );

            $stmtAtt->execute();

            $stmtAtt->close();
        }
    }
}


/* ============================================================
   FORM SUBMISSION HANDLING
   ============================================================ */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {


    /* ========================================================
       CSRF TOKEN VALIDATION
       ======================================================== */

    $csrfToken =
        $_POST['csrf_token'] ?? '';

    if (
        empty($csrfToken) ||
        !is_string($csrfToken) ||
        empty($_SESSION['csrf_token']) ||
        !hash_equals(
            $_SESSION['csrf_token'],
            $csrfToken
        )
    ) {

        http_response_code(403);

        exit('Invalid CSRF token.');

    }


    $formFile = trim(
        $_POST['form_file'] ?? ''
    );

    $goBack = !empty($formFile)
        ? $formFile
        : "Certifications.php";


    /* ========================================================
       MULTI-FIELD HONEYPOT VALIDATION
       ======================================================== */

    $honeypots = [

        'middle_initial_verification',
        'system_verification_code',
        'subject_verification_id',
        'id_verification_token',
        'med_verification_token'

    ];

    foreach ($honeypots as $pot) {

        if (!empty($_POST[$pot])) {

            header(
                "Location: /BMS/Barangay_user/Forms/{$goBack}" .
                "?status=error&msg=Security breach detected"
            );

            exit();

        }

    }


    /* ========================================================
       reCAPTCHA ENTERPRISE VERIFICATION
       ======================================================== */

    $captchaToken =
        $_POST['g-recaptcha-response'] ?? '';


    /*
     * First make sure the browser actually sent a token.
     */
    if (
        !is_string($captchaToken) ||
        trim($captchaToken) === ''
    ) {

        header(
            "Location: /BMS/Barangay_user/Forms/{$goBack}" .
            "?status=error&msg=" .
            urlencode("Please complete the security check.")
        );

        exit();

    }


    /*
     * Now verify the token with Google reCAPTCHA Enterprise.
     *
     * IMPORTANT:
     * We do NOT allow the request to continue if verification
     * fails. There is no fallback form.submit() bypass.
     */
    $captchaResult =
        verifyRecaptchaEnterprise(
            trim($captchaToken)
        );


    if (
        empty($captchaResult['success'])
    ) {

        header(
            "Location: /BMS/Barangay_user/Forms/{$goBack}" .
            "?status=error&msg=" .
            urlencode("Security verification failed. Please try again.")
        );

        exit();

    }


    /* ========================================================
       GET FORM DATA
       ======================================================== */

    $fullname =
        trim($_POST['fullname'] ?? '');

    $birthdate =
        trim($_POST['birthdate'] ?? '');

    $incident_date =
        trim($_POST['incident_date'] ?? '');

    $document_type_id =
        (int)($_POST['document_type_id'] ?? 0);

    $purpose =
        trim($_POST['purpose'] ?? '');

    $phone =
        trim($_POST['phone'] ?? '');

    $email =
        trim($_POST['email'] ?? '');

    $address =
        trim($_POST['address'] ?? '');

    $scanned_document_data =
        $_POST['scanned_document_data'] ?? '';


    /* ========================================================
       BASIC FIELD VALIDATION
       ======================================================== */

    if (
        empty($fullname) ||
        empty($purpose) ||
        empty($phone) ||
        empty($email) ||
        empty($address) ||
        empty($formFile)
    ) {

        header(
            "Location: /BMS/Barangay_user/Forms/{$goBack}" .
            "?status=error&msg=Missing required fields"
        );

        exit();

    }


    /* ========================================================
       PRE-FLIGHT SIGNATURE FILE VALIDATION
       ======================================================== */

    if (
        isset($_FILES['signature_file']) &&
        $_FILES['signature_file']['error'] !== UPLOAD_ERR_NO_FILE
    ) {

        $sigVal = validateUploadedFile(
            $_FILES['signature_file'],
            ['png', 'jpg', 'jpeg'],
            50
        );

        if ($sigVal !== true) {

            header(
                "Location: /BMS/Barangay_user/Forms/{$goBack}" .
                "?status=error&msg=" .
                urlencode($sigVal)
            );

            exit();

        }

    }


    /* ========================================================
       VALIDATE ATTACHMENTS
       ======================================================== */

    if (isset($_FILES['attachment'])) {

        $attVal = preValidateFileGroup(
            $_FILES['attachment'],
            ['png', 'jpeg', 'jpg', 'doc', 'docx', 'pdf'],
            50
        );

        if ($attVal !== true) {

            header(
                "Location: /BMS/Barangay_user/Forms/{$goBack}" .
                "?status=error&msg=" .
                urlencode($attVal)
            );

            exit();

        }

    }


    /* ========================================================
       VALIDATE PROOF OF MEDICAL RECORD
       ======================================================== */

    if (
        isset($_FILES['proof_of_medical_record'])
    ) {

        $medVal = preValidateFileGroup(
            $_FILES['proof_of_medical_record'],
            ['png', 'jpeg', 'jpg', 'doc', 'docx', 'pdf'],
            50
        );

        if ($medVal !== true) {

            header(
                "Location: /BMS/Barangay_user/Forms/{$goBack}" .
                "?status=error&msg=" .
                urlencode($medVal)
            );

            exit();

        }

    }


    /* ========================================================
       PAYMENT VALIDATION
       ======================================================== */

    $payment_method =
        $_POST['payment_method'] ?? 'Walk-in';

    if (
        $payment_method === 'GCash' &&
        isset($_FILES['payment_receipt'])
    ) {

        $rcpVal = validateUploadedFile(
            $_FILES['payment_receipt'],
            ['png', 'jpeg', 'jpg', 'pdf'],
            50
        );

        if ($rcpVal !== true) {

            header(
                "Location: /BMS/Barangay_user/Forms/{$goBack}" .
                "?status=error&msg=" .
                urlencode($rcpVal)
            );

            exit();

        }

    }


    /* ========================================================
       DIGITAL SIGNATURE PROCESSING
       ======================================================== */

    $digital_signature = NULL;

    $sig_dir =
        "../../uploads/signatures/";

    $sig_quarantine_dir =
        "../../uploads/quarantine/";

    if (!is_dir($sig_dir)) {
        mkdir($sig_dir, 0777, true);
    }

    if (!is_dir($sig_quarantine_dir)) {
        mkdir($sig_quarantine_dir, 0777, true);
    }

    /* ------------------------------------------------------------
       CANVAS SIGNATURE
       ------------------------------------------------------------ */

    if (!empty($_POST['digital_signature'])) {

        $img_data = $_POST['digital_signature'];

        $img_data = str_replace(
            'data:image/png;base64,',
            '',
            $img_data
        );

        $img_data = str_replace(
            ' ',
            '+',
            $img_data
        );

        $decoded_data = base64_decode(
            $img_data,
            true
        );

        if ($decoded_data !== false && $decoded_data !== '') {

            $signatureId = bin2hex(
                random_bytes(8)
            );

            $sig_filename =
                "SIG_CANVAS_" .
                date('Ymd_His') . "_" .
                $resident_id . "_" .
                $signatureId .
                ".png";

            $quarantineSigPath =
                $sig_quarantine_dir . $sig_filename;

            if (
                file_put_contents(
                    $quarantineSigPath,
                    $decoded_data
                ) !== false
            ) {

                $scanMessage = '';

                $scanResult = scanFileWithClamAV(
                    $quarantineSigPath,
                    $scanMessage
                );

                if ($scanResult) {

                    $targetSigPath =
                        $sig_dir . $sig_filename;

                    if (
                        rename(
                            $quarantineSigPath,
                            $targetSigPath
                        )
                    ) {

                        $digital_signature =
                            $targetSigPath;

                    } else {

                        if (
                            file_exists(
                                $quarantineSigPath
                            )
                        ) {

                            unlink(
                                $quarantineSigPath
                            );

                        }

                    }

                } else {

                    if (
                        file_exists(
                            $quarantineSigPath
                        )
                    ) {

                        unlink(
                            $quarantineSigPath
                        );

                    }

                }

            }

        }

    } elseif (
        isset($_FILES['signature_file']) &&
        $_FILES['signature_file']['error'] === UPLOAD_ERR_OK
    ) {

        /* ------------------------------------------------------------
           UPLOADED SIGNATURE FILE
           ------------------------------------------------------------ */

        $file_ext = strtolower(
            pathinfo(
                $_FILES['signature_file']['name'],
                PATHINFO_EXTENSION
            )
        );

        $signatureId = bin2hex(
            random_bytes(8)
        );

        $sig_filename =
            "SIG_FILE_" .
            date('Ymd_His') . "_" .
            $resident_id . "_" .
            $signatureId .
            "." .
            $file_ext;

        $quarantineSigPath =
            $sig_quarantine_dir . $sig_filename;

        if (
            move_uploaded_file(
                $_FILES['signature_file']['tmp_name'],
                $quarantineSigPath
            )
        ) {

            $scanMessage = '';

            $scanResult = scanFileWithClamAV(
                $quarantineSigPath,
                $scanMessage
            );

            if ($scanResult) {

                $targetSigPath =
                    $sig_dir . $sig_filename;

                if (
                    rename(
                        $quarantineSigPath,
                        $targetSigPath
                    )
                ) {

                    $digital_signature =
                        $targetSigPath;

                } else {

                    if (
                        file_exists(
                            $quarantineSigPath
                        )
                    ) {

                        unlink(
                            $quarantineSigPath
                        );

                    }

                }

            } else {

                if (
                    file_exists(
                        $quarantineSigPath
                    )
                ) {

                    unlink(
                        $quarantineSigPath
                    );

                }

            }

        }

    }


    /* ============================================================
       CASE 1: INCIDENT REPORT
       TABLE: blotter
       ============================================================ */

    if ($formFile === "Incident_report.php") {

        if (empty($incident_date)) {

            header(
                "Location: /BMS/Barangay_user/Forms/{$goBack}" .
                "?status=error&msg=Incident date is required"
            );

            exit();

        }


        /*
         * SATELLITE ID ADDED
         */

        $stmtInsert = $conn->prepare("

            INSERT INTO blotter

            (
                complaint,
                complainants,
                date,
                officer,
                summary_remarks,
                status,
                resident_id,
                satellite_id,
                digital_signature
            )

            VALUES
            (
                ?,
                ?,
                ?,
                'Pending Assignment',
                ?,
                'Pending',
                ?,
                ?,
                ?
            )

        ");


        $stmtInsert->bind_param(
            "sssssis",
            $purpose,
            $fullname,
            $incident_date,
            $purpose,
            $resident_id,
            $satellite_id,
            $digital_signature
        );


        if ($stmtInsert->execute()) {

            $blotter_id =
                $stmtInsert->insert_id;

            $stmtInsert->close();


            /* Handle Camera Capture Scan */

            if (!empty($scanned_document_data)) {

                processScannedDocument(
                    $conn,
                    'blotter',
                    $blotter_id,
                    $scanned_document_data,
                    $resident_id
                );

            }


            /* Handle Multiple File Attachments */

            if (isset($_FILES['attachment'])) {

                handleMultipleUploads(
                    $conn,
                    'blotter',
                    $blotter_id,
                    $_FILES['attachment'],
                    $resident_id
                );

            }


            /* Handle Proof of Medical Record */

            if (
                isset($_FILES['proof_of_medical_record'])
            ) {

                handleMultipleUploads(
                    $conn,
                    'blotter',
                    $blotter_id,
                    $_FILES['proof_of_medical_record'],
                    $resident_id
                );

            }


            header(
                "Location: /BMS/Barangay_user/Forms/{$formFile}?status=success"
            );

        } else {

            $errorMsg =
                $stmtInsert->error;

            $stmtInsert->close();

            header(
                "Location: /BMS/Barangay_user/Forms/{$formFile}" .
                "?status=error&msg=" .
                urlencode($errorMsg)
            );

        }

        exit();

    }


    /* ============================================================
       CASE 2: MEDICATION ASSISTANCE
       TABLE: resident_medicine
       ============================================================ */

    if ($formFile === "Medical_Assistance.php") {

        $medicine_name =
            trim($_POST['medicine_name'] ?? '');

        $dosage =
            trim($_POST['dosage'] ?? '');


        if (
            empty($medicine_name) ||
            empty($dosage)
        ) {

            header(
                "Location: /BMS/Barangay_user/Forms/{$goBack}" .
                "?status=error&msg=Medicine name and dosage are required"
            );

            exit();

        }


        /*
         * SATELLITE ID ADDED
         */

        $stmtInsert = $conn->prepare("

            INSERT INTO resident_medicine

            (
                resident_id,
                satellite_id,
                medicine_name,
                dosage,
                purpose,
                digital_signature,
                status,
                submitted_at
            )

            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                'Pending',
                NOW()
            )

        ");


        $stmtInsert->bind_param(
            "iissss",
            $resident_id,
            $satellite_id,
            $medicine_name,
            $dosage,
            $purpose,
            $digital_signature
        );


        if ($stmtInsert->execute()) {

            $medicine_request_id =
                $stmtInsert->insert_id;

            $stmtInsert->close();


            /* Handle Camera Capture Scan */

            if (!empty($scanned_document_data)) {

                processScannedDocument(
                    $conn,
                    'medicine',
                    $medicine_request_id,
                    $scanned_document_data,
                    $resident_id
                );

            }


            /* Handle Prescription Attachments */

            if (isset($_FILES['attachment'])) {

                handleMultipleUploads(
                    $conn,
                    'medicine',
                    $medicine_request_id,
                    $_FILES['attachment'],
                    $resident_id
                );

            }


            header(
                "Location: /BMS/Barangay_user/Forms/{$formFile}?status=success"
            );

        } else {

            $errorMsg =
                $stmtInsert->error;

            $stmtInsert->close();

            header(
                "Location: /BMS/Barangay_user/Forms/{$formFile}" .
                "?status=error&msg=" .
                urlencode($errorMsg)
            );

        }

        exit();

    }


    /* ============================================================
       CASE 3: REGULAR DOCUMENT REQUESTS
       TABLE: resident_request
       ============================================================ */

    if (empty($birthdate)) {

        header(
            "Location: /BMS/Barangay_user/Forms/{$goBack}" .
            "?status=error&msg=Birthdate is required"
        );

        exit();

    }


    if (empty($document_type_id)) {

        header(
            "Location: /BMS/Barangay_user/Forms/{$goBack}" .
            "?status=error&msg=Document type is required"
        );

        exit();

    }


    /* ========================================================
       PAYMENT LOGIC
       ======================================================== */

    $ref_number =
        trim($_POST['ref_number'] ?? '');

    $payment_receipt = NULL;

    if (
        $payment_method === 'GCash' &&
        isset($_FILES['payment_receipt']) &&
        $_FILES['payment_receipt']['error'] === UPLOAD_ERR_OK
    ) {

        $receipt_dir =
            "../../uploads/receipts/";

        $receipt_quarantine_dir =
            "../../uploads/quarantine/";

        if (!is_dir($receipt_dir)) {
            mkdir($receipt_dir, 0777, true);
        }

        if (!is_dir($receipt_quarantine_dir)) {
            mkdir($receipt_quarantine_dir, 0777, true);
        }

        $receipt_ext = strtolower(
            pathinfo(
                $_FILES['payment_receipt']['name'],
                PATHINFO_EXTENSION
            )
        );

        $receiptId = bin2hex(
            random_bytes(8)
        );

        $receipt_unique =
            "PAY_" .
            date('Ymd_His') . "_" .
            $resident_id . "_" .
            $receiptId .
            "." .
            $receipt_ext;

        $quarantineReceiptPath =
            $receipt_quarantine_dir . $receipt_unique;

        /* Move receipt into quarantine first. */
        if (
            move_uploaded_file(
                $_FILES['payment_receipt']['tmp_name'],
                $quarantineReceiptPath
            )
        ) {

            /* Scan receipt before permanent storage. */
            $scanMessage = '';

            $scanResult = scanFileWithClamAV(
                $quarantineReceiptPath,
                $scanMessage
            );

            if ($scanResult) {

                $targetReceiptPath =
                    $receipt_dir . $receipt_unique;

                if (
                    rename(
                        $quarantineReceiptPath,
                        $targetReceiptPath
                    )
                ) {

                    $payment_receipt =
                        $targetReceiptPath;

                } else {

                    if (
                        file_exists(
                            $quarantineReceiptPath
                        )
                    ) {

                        unlink(
                            $quarantineReceiptPath
                        );

                    }

                }

            } else {

                /* Infected file or scanner error: delete it. */
                if (
                    file_exists(
                        $quarantineReceiptPath
                    )
                ) {

                    unlink(
                        $quarantineReceiptPath
                    );

                }

            }

        }

    }


    /* ========================================================
       LIMIT CHECK
       MAX 3 REQUESTS PER DAY
       ======================================================== */

    $stmtCheck = $conn->prepare("

        SELECT COUNT(*) AS total
        FROM resident_request
        WHERE resident_id = ?
        AND document_type_id = ?
        AND DATE(submitted_at) = CURDATE()

    ");

    $stmtCheck->bind_param(
        "ii",
        $resident_id,
        $document_type_id
    );

    $stmtCheck->execute();

    $rowCheck =
        $stmtCheck->get_result()->fetch_assoc();

    $stmtCheck->close();


    if (($rowCheck['total'] ?? 0) >= 3) {

        header(
            "Location: /BMS/Barangay_user/Forms/{$formFile}?status=limit"
        );

        exit();

    }


    /* ========================================================
       GET PRICE
       ======================================================== */

    $stmtPrice = $conn->prepare("

        SELECT price
        FROM document_types
        WHERE document_type_id = ?

    ");

    $stmtPrice->bind_param(
        "i",
        $document_type_id
    );

    $stmtPrice->execute();

    $rowPrice =
        $stmtPrice->get_result()->fetch_assoc();

    $stmtPrice->close();


    $price =
        $rowPrice['price'] ?? 0;


    /* ========================================================
       INITIAL STATUS
       ======================================================== */

    $initial_status =
        ($payment_method === 'GCash')
        ? 'Pending Verification'
        : 'Pending (Pay at Hall)';


    /* ========================================================
       INSERT REGULAR DOCUMENT REQUEST
       ======================================================== */

    /*
     * SATELLITE ID ADDED HERE
     */

    $stmtInsert = $conn->prepare("

        INSERT INTO resident_request

        (
            resident_id,
            satellite_id,
            document_type_id,
            fullname,
            birthdate,
            purpose,
            phone,
            email,
            address,
            digital_signature,
            price,
            payment_method,
            ref_number,
            payment_receipt,
            status,
            submitted_at
        )

        VALUES
        (
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            NOW()
        )

    ");


    $stmtInsert->bind_param(

        "iiisssssssdssss",

        $resident_id,
        $satellite_id,
        $document_type_id,
        $fullname,
        $birthdate,
        $purpose,
        $phone,
        $email,
        $address,
        $digital_signature,
        $price,
        $payment_method,
        $ref_number,
        $payment_receipt,
        $initial_status

    );


    if ($stmtInsert->execute()) {

        $request_id =
            $stmtInsert->insert_id;

        $stmtInsert->close();


        /* Handle Camera Capture Scan */

        if (!empty($scanned_document_data)) {

            processScannedDocument(
                $conn,
                'request',
                $request_id,
                $scanned_document_data,
                $resident_id
            );

        }


        /* Handle Multiple File Attachments */

        if (isset($_FILES['attachment'])) {

            handleMultipleUploads(
                $conn,
                'request',
                $request_id,
                $_FILES['attachment'],
                $resident_id
            );

        }


        header(
            "Location: /BMS/Barangay_user/Forms/{$formFile}?status=success"
        );

    } else {

        $errorMsg =
            $stmtInsert->error;

        $stmtInsert->close();

        header(
            "Location: /BMS/Barangay_user/Forms/{$formFile}" .
            "?status=error&msg=" .
            urlencode($errorMsg)
        );

    }

    exit();

}

?>
