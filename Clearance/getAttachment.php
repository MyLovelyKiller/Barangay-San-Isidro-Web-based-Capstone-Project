<?php
session_start();

require_once '../BACKEND/db_connect.php';


/* =========================================================
   CHECK IF USER IS LOGGED IN
   ========================================================= */

if (!isset($_SESSION['official_id'])) {
    http_response_code(403);
    exit("Access denied.");
}


/* =========================================================
   VERIFY CLEARANCE OFFICER DIRECTLY FROM DATABASE
   ========================================================= */

$official_id = (int)$_SESSION['official_id'];

$officerStmt = $conn->prepare("
    SELECT
        official_id,
        department,
        satellite_id
    FROM officials
    WHERE official_id = ?
      AND department = 'CLEARANCE'
    LIMIT 1
");

if (!$officerStmt) {
    error_log(
        "Clearance getAttachment officer query prepare failed: "
        . $conn->error
    );

    http_response_code(500);
    exit("Unable to verify account.");
}

$officerStmt->bind_param("i", $official_id);

if (!$officerStmt->execute()) {
    error_log(
        "Clearance getAttachment officer query execute failed: "
        . $officerStmt->error
    );

    $officerStmt->close();

    http_response_code(500);
    exit("Unable to verify account.");
}

$officerResult = $officerStmt->get_result();

if (
    !$officerResult ||
    !$officerRow = $officerResult->fetch_assoc()
) {
    $officerStmt->close();

    http_response_code(403);
    exit("Access denied.");
}

$satellite_id = (int)($officerRow['satellite_id'] ?? 0);

$officerStmt->close();


/* =========================================================
   VERIFY SATELLITE
   ========================================================= */

if ($satellite_id <= 0) {
    http_response_code(403);
    exit("Your Clearance account does not have an assigned satellite.");
}


/* =========================================================
   CSRF TOKEN VALIDATION
   ========================================================= */

$submitted_token = $_GET['csrf_token'] ?? '';

if (
    empty($submitted_token) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals(
        $_SESSION['csrf_token'],
        $submitted_token
    )
) {
    http_response_code(403);
    exit("Invalid CSRF token.");
}


/* =========================================================
   VALIDATE REQUEST ID
   ========================================================= */

if (
    !isset($_GET['id']) ||
    !ctype_digit((string)$_GET['id'])
) {
    http_response_code(400);
    exit("Invalid request.");
}

$request_id = (int)$_GET['id'];

if ($request_id <= 0) {
    http_response_code(400);
    exit("Invalid request.");
}


/* =========================================================
   FETCH REQUEST
   MUST BELONG TO OFFICER'S SATELLITE
   ========================================================= */

$stmt = $conn->prepare("
    SELECT
        request_id,
        satellite_id,
        attachment
    FROM resident_request
    WHERE request_id = ?
      AND satellite_id = ?
    LIMIT 1
");

if (!$stmt) {
    error_log(
        "Clearance getAttachment request query prepare failed: "
        . $conn->error
    );

    http_response_code(500);
    exit("Unable to load request.");
}

$stmt->bind_param(
    "ii",
    $request_id,
    $satellite_id
);

if (!$stmt->execute()) {
    error_log(
        "Clearance getAttachment request query execute failed: "
        . $stmt->error
    );

    $stmt->close();

    http_response_code(500);
    exit("Unable to load request.");
}

$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {
    $stmt->close();

    /*
     * Do not reveal whether the request exists in another
     * satellite. Return a generic authorization response.
     */
    http_response_code(403);
    exit("You are not authorized to access this request.");
}

$row = $result->fetch_assoc();

$stmt->close();


/* =========================================================
   CHECK ATTACHMENT
   ========================================================= */

if (empty($row['attachment'])) {
    http_response_code(404);
    exit("No attachment found.");
}


/* =========================================================
   SANITIZE FILENAME
   ========================================================= */

/*
 * basename() prevents directory traversal such as:
 *
 * ../../private/file.pdf
 * ../../../etc/passwd
 *
 * Only the filename itself is allowed.
 */

$attachment = basename(trim($row['attachment']));

if (
    $attachment === '' ||
    $attachment === '.' ||
    $attachment === '..'
) {
    http_response_code(400);
    exit("Invalid attachment.");
}


/* =========================================================
   BUILD UPLOAD DIRECTORY
   ========================================================= */

$uploadDirectory = realpath(
    $_SERVER['DOCUMENT_ROOT'] .
    "/BMS/Barangay_user/uploads"
);

if (
    $uploadDirectory === false ||
    !is_dir($uploadDirectory)
) {
    error_log(
        "Clearance getAttachment upload directory not found."
    );

    http_response_code(500);
    exit("Upload directory not found.");
}


/* =========================================================
   BUILD FILE PATH
   ========================================================= */

$filePath = $uploadDirectory .
    DIRECTORY_SEPARATOR .
    $attachment;


/* =========================================================
   VERIFY FILE EXISTS
   ========================================================= */

if (!is_file($filePath)) {
    http_response_code(404);
    exit("File does not exist on server.");
}


/* =========================================================
   RESOLVE REAL FILE PATH
   ========================================================= */

$realFilePath = realpath($filePath);

if ($realFilePath === false) {
    http_response_code(404);
    exit("File does not exist on server.");
}


/* =========================================================
   PATH TRAVERSAL PROTECTION
   ========================================================= */

$uploadDirectoryNormalized = rtrim(
    str_replace('\\', '/', $uploadDirectory),
    '/'
);

$realFilePathNormalized = str_replace(
    '\\',
    '/',
    $realFilePath
);

if (
    strpos(
        $realFilePathNormalized,
        $uploadDirectoryNormalized . '/'
    ) !== 0
) {
    error_log(
        "Clearance getAttachment blocked invalid file path " .
        "for request ID: " . $request_id
    );

    http_response_code(403);
    exit("Invalid file location.");
}


/* =========================================================
   VERIFY REGULAR FILE
   ========================================================= */

if (!is_file($realFilePath)) {
    http_response_code(404);
    exit("Invalid file.");
}


/* =========================================================
   GET FILE MIME TYPE
   ========================================================= */

$mimeType = 'application/octet-stream';

if (function_exists('finfo_open')) {

    $finfo = finfo_open(FILEINFO_MIME_TYPE);

    if ($finfo !== false) {

        $detectedMime = finfo_file(
            $finfo,
            $realFilePath
        );

        if (
            $detectedMime !== false &&
            !empty($detectedMime)
        ) {
            $mimeType = $detectedMime;
        }

        finfo_close($finfo);
    }

} elseif (function_exists('mime_content_type')) {

    $detectedMime = mime_content_type(
        $realFilePath
    );

    if (
        $detectedMime !== false &&
        !empty($detectedMime)
    ) {
        $mimeType = $detectedMime;
    }
}


/* =========================================================
   FILE SIZE
   ========================================================= */

$fileSize = filesize($realFilePath);

if ($fileSize === false) {
    http_response_code(500);
    exit("Unable to determine file size.");
}


/* =========================================================
   SAFE DOWNLOAD FILENAME
   ========================================================= */

$downloadName = preg_replace(
    '/[^A-Za-z0-9._-]/',
    '_',
    $attachment
);

if (
    $downloadName === null ||
    $downloadName === ''
) {
    $downloadName = 'attachment';
}


/* =========================================================
   CLEAR OUTPUT BUFFER
   ========================================================= */

while (ob_get_level() > 0) {
    ob_end_clean();
}


/* =========================================================
   RESPONSE HEADERS
   ========================================================= */

header("Content-Description: File Transfer");
header("Content-Type: " . $mimeType);

header(
    'Content-Disposition: attachment; filename="' .
    $downloadName .
    '"'
);

header("Content-Length: " . $fileSize);

header(
    "Cache-Control: private, no-store, " .
    "no-cache, must-revalidate"
);

header("Pragma: no-cache");
header("Expires: 0");
header("X-Content-Type-Options: nosniff");


/* =========================================================
   SEND FILE
   ========================================================= */

if (readfile($realFilePath) === false) {
    error_log(
        "Clearance getAttachment failed to read file: " .
        $realFilePath
    );
}

exit;
?>
