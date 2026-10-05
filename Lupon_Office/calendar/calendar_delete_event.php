<?php
require_once(__DIR__ . '/calendar_api_guard.php');
require_once(__DIR__ . '/../includes/db_connect.php');
require_once(__DIR__ . '/../includes/calendar_helpers.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$id = (int) ($input['id'] ?? 0);

if ($id <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'A valid event id is required.']);
    exit();
}

$fetch = $conn->prepare("SELECT title, case_id FROM calendar_events WHERE id = ?");
$fetch->bind_param("i", $id);
$fetch->execute();
$event = $fetch->get_result()->fetch_assoc();
$fetch->close();

if (!$event) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Event not found.']);
    exit();
}

$stmt = $conn->prepare("DELETE FROM calendar_events WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    $stmt->close();

    if (!empty($event['case_id'])) {
        calendar_sync_case_schedule($conn, (int) $event['case_id']);
    }

    $username = $_SESSION['username'] ?? 'Unknown';
    $desc_log = "Deleted event \"{$event['title']}\" (ID {$id})";
    $log = $conn->prepare("INSERT INTO audit_trail (action, description, user) VALUES ('DELETE', ?, ?)");
    $log->bind_param("ss", $desc_log, $username);
    $log->execute();
    $log->close();

    echo json_encode(['success' => true, 'message' => 'Event deleted.']);
} else {
    error_log("Calendar delete error: " . $stmt->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not delete the event. Please try again.']);
}

$conn->close();
