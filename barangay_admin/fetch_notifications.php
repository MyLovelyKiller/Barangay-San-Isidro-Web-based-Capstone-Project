<?php
session_start();
include 'config.php';

header('Content-Type: application/json; charset=UTF-8');

/* Check if user is logged in */
if (!isset($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode([
        'count' => 0,
        'reports' => [],
        'error' => 'Unauthorized'
    ]);
    exit();
}

/* Get latest 5 satellite reports */
$stmt = $conn->prepare("
    SELECT r.*, s.satellite_name
    FROM satellite_reports r
    JOIN satellites s ON r.satellite_id = s.id
    ORDER BY r.report_date DESC
    LIMIT 5
");

if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        'count' => 0,
        'reports' => [],
        'error' => 'Database error'
    ]);
    exit();
}

$stmt->execute();
$result = $stmt->get_result();

/* Get unread report count */
$countStmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM satellite_reports
    WHERE report_status = 'Unread'
");

if (!$countStmt) {
    http_response_code(500);
    echo json_encode([
        'count' => 0,
        'reports' => [],
        'error' => 'Database error'
    ]);
    exit();
}

$countStmt->execute();
$countResult = $countStmt->get_result()->fetch_assoc();

$count = (int)$countResult['total'];

$data = [];

while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

$stmt->close();
$countStmt->close();

echo json_encode([
    'count' => $count,
    'reports' => $data
], JSON_UNESCAPED_UNICODE);
?>