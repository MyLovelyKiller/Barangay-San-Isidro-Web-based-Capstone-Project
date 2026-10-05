<?php
session_start();
require_once '../BACKEND/db_connect.php';


/* =========================================================
   CHECK IF USER IS LOGGED IN AS CLEARANCE OFFICER
   ========================================================= */

if (
    !isset($_SESSION['official_id']) ||
    !isset($_SESSION['department']) ||
    $_SESSION['department'] !== "CLEARANCE"
) {
    header("HTTP/1.0 403 Forbidden");
    exit("Access denied.");
}


/* =========================================================
   CSRF TOKEN VALIDATION
   ========================================================= */

$submitted_token = $_GET['csrf_token'] ?? '';

if (
    empty($submitted_token) ||
    !isset($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $submitted_token)
) {
    http_response_code(403);
    exit("Invalid CSRF token.");
}


/* =========================================================
   VALIDATE REQUEST ID
   ========================================================= */

if (!isset($_GET['id']) || !ctype_digit((string)$_GET['id'])) {
    http_response_code(400);
    exit("Invalid request.");
}

$request_id = (int) $_GET['id'];

if ($request_id <= 0) {
    http_response_code(400);
    exit("Invalid request.");
}


/* =========================================================
   FETCH ATTACHMENT FROM DATABASE
   ========================================================= */

$stmt = $conn->prepare("
    SELECT attachment
    FROM resident_request
    WHERE request_id = ?
    LIMIT 1
");

if (!$stmt) {
    http_response_code(500);
    exit("Database error.");
}

$stmt->bind_param("i", $request_id);
$stmt->execute();

$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {
    http_response_code(404);
    exit("Request not found.");
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

$attachment = basename($row['attachment']);


/*
|--------------------------------------------------------------------------
| Prevent empty or suspicious filenames
|--------------------------------------------------------------------------
*/

if (
    $attachment === '' ||
    $attachment === '.' ||
    $attachment === '..'
) {
    http_response_code(400);
    exit("Invalid attachment.");
}


/* =========================================================
   BUILD ABSOLUTE FILE PATH
   ========================================================= */

/*
|--------------------------------------------------------------------------
| Expected upload directory:
|
| C:/xampp/htdocs/BMS/Barangay_user/uploads/
|--------------------------------------------------------------------------
*/

$uploadDirectory = realpath(
    $_SERVER['DOCUMENT_ROOT'] . "/BMS/Barangay_user/uploads"
);

if ($uploadDirectory === false || !is_dir($uploadDirectory)) {
    http_response_code(500);
    exit("Upload directory not found.");
}


/* =========================================================
   BUILD FILE PATH
   ========================================================= */

$filePath = $uploadDirectory . DIRECTORY_SEPARATOR . $attachment;


/* =========================================================
   VERIFY FILE EXISTS
   ========================================================= */

if (!is_file($filePath)) {
    http_response_code(404);
    exit("File does not exist on server.");
}


/* =========================================================
   VERIFY FILE IS INSIDE UPLOAD DIRECTORY
   ========================================================= */

$realFilePath = realpath($filePath);

if ($realFilePath === false) {
    http_response_code(404);
    exit("File does not exist on server.");
}


/*
|--------------------------------------------------------------------------
| Ensure the resolved file remains inside the intended upload directory.
|--------------------------------------------------------------------------
*/

$uploadDirectoryNormalized = rtrim(
    str_replace('\\', '/', $uploadDirectory),
    '/'
);

$realFilePathNormalized = str_replace('\\', '/', $realFilePath);

if (
    strpos(
        $realFilePathNormalized,
        $uploadDirectoryNormalized . '/'
    ) !== 0
) {
    http_response_code(403);
    exit("Invalid file location.");
}


/* =========================================================
   GET FILE MIME TYPE
   ========================================================= */

$mimeType = 'application/octet-stream';

if (function_exists('finfo_open')) {

    $finfo = finfo_open(FILEINFO_MIME_TYPE);

    if ($finfo !== false) {

        $detectedMime = finfo_file($finfo, $realFilePath);

        if ($detectedMime !== false && !empty($detectedMime)) {
            $mimeType = $detectedMime;
        }

        finfo_close($finfo);
    }

} elseif (function_exists('mime_content_type')) {

    $detectedMime = mime_content_type($realFilePath);

    if ($detectedMime !== false && !empty($detectedMime)) {
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
   CLEAR OUTPUT BUFFER
   ========================================================= */

while (ob_get_level() > 0) {
    ob_end_clean();
}


/* =========================================================
   FORCE FILE DOWNLOAD
   ========================================================= */

header("Content-Description: File Transfer");
header("Content-Type: " . $mimeType);
header(
    'Content-Disposition: attachment; filename="' .
    str_replace('"', '', $attachment) .
    '"'
);
header("Content-Length: " . $fileSize);
header("Cache-Control: private, no-store, no-cache, must-revalidate");
header("Pragma: public");
header("Expires: 0");


/* =========================================================
   SEND FILE
   ========================================================= */

readfile($realFilePath);

exit;
?>