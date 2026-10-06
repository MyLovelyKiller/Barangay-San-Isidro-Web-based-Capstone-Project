<?php
session_start();
include "config.php";

bms_require_official_department($conn, 'ADMIN');

/* Validate request ID */
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($id === false || $id === null || $id <= 0) {
    http_response_code(400);
    exit("Invalid request ID.");
}

/* Get resident request */
$stmt = $conn->prepare("
    SELECT rr.*, dt.name AS document_name
    FROM resident_request rr
    LEFT JOIN document_types dt
        ON rr.document_type_id = dt.document_type_id
    WHERE rr.request_id = ?
");

if (!$stmt) {
    http_response_code(500);
    exit("Database error.");
}

$stmt->bind_param("i", $id);

if (!$stmt->execute()) {
    $stmt->close();
    http_response_code(500);
    exit("Database error.");
}

$result = $stmt->get_result();
$row = $result->fetch_assoc();

$stmt->close();

/* Request not found */
if (!$row) {
    http_response_code(404);
    exit("Request not found.");
}
?>

<!DOCTYPE html>
<html>

<head>

<title>View Resident Request</title>

<link rel="stylesheet" href="style/view_document.css">

</head>

<body>

<div class="paper">

<div class="header">

<img src="/BMS/IMAGES/silogo.png" class="logo">

<div class="header-text">

<h3>OFFICE OF THE BARANGAY</h3>
<p>Barangay San Isidro Cainta, Rizal</p>
<h2>Request Form</h2>

</div>

</div>

<div class="section">

<b>Requested Document:</b>
<?= htmlspecialchars($row['document_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>

</div>

<div class="form-grid">

<p><b>Name:</b> <?= htmlspecialchars($row['fullname'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>

<p><b>Birthday:</b> <?= htmlspecialchars($row['birthdate'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>

<p><b>Contact No:</b> <?= htmlspecialchars($row['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>

<p><b>Email:</b> <?= htmlspecialchars($row['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>

<p><b>Address:</b> <?= htmlspecialchars($row['address'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>

<p><b>Purpose:</b> <?= htmlspecialchars($row['purpose'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>

</div>

<div class="emergency">

<h3>In case of Emergency</h3>

<p>Name: ___________________________</p>
<p>Address: _________________________</p>
<p>Contact No: ______________________</p>

</div>

<div class="footer">

<p>Date Submitted: <?= htmlspecialchars($row['submitted_at'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>

</div>

</div>

</body>

</html>