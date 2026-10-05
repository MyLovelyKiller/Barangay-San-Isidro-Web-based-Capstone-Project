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

$title       = trim($input['title'] ?? '');
$type_id     = (int) ($input['event_type_id'] ?? 0);
$case_id     = !empty($input['case_id']) ? (int) $input['case_id'] : null;
$event_date  = $input['event_date'] ?? '';
$start_time  = $input['start_time'] ?? '';
$end_time    = !empty($input['end_time']) ? $input['end_time'] : null;
$location    = trim($input['location'] ?? '') ?: 'Barangay Hall - Lupon Office';
$description = trim($input['description'] ?? '');

/* ---- VALIDATION ---- */
$errors = [];

if ($title === '' || mb_strlen($title) > 150) {
    $errors[] = 'Title is required and must be under 150 characters.';
}

if ($type_id <= 0) {
    $errors[] = 'Please select a valid event type.';
} else {
    $check = $conn->prepare("SELECT id FROM event_types WHERE id = ?");
    $check->bind_param("i", $type_id);
    $check->execute();
    if ($check->get_result()->num_rows === 0) {
        $errors[] = 'Please select a valid event type.';
    }
    $check->close();
}

$date_obj = DateTime::createFromFormat('Y-m-d', $event_date);
if (!$date_obj || $date_obj->format('Y-m-d') !== $event_date) {
    $errors[] = 'A valid event date is required.';
}

if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $start_time)) {
    $errors[] = 'A valid start time is required.';
}

if ($end_time !== null && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $end_time)) {
    $errors[] = 'End time is not in a valid format.';
}

if ($case_id !== null) {
    $check = $conn->prepare("SELECT id FROM cases WHERE id = ?");
    $check->bind_param("i", $case_id);
    $check->execute();
    if ($check->get_result()->num_rows === 0) {
        $errors[] = 'The selected case reference does not exist.';
    }
    $check->close();
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit();
}

/* ---- DUPLICATE-SUBMISSION GUARD ----
   The client already disables the Save button while a request is in
   flight, but that alone doesn't protect against a network retry or
   the same form being submitted from two tabs. If an identical event
   (same title/date/time/type) was created in the last 10 seconds,
   treat this as a duplicate rather than inserting a second copy. */
$dupe_check = $conn->prepare(
    "SELECT id FROM calendar_events
     WHERE title = ? AND event_type_id = ? AND event_date = ? AND start_time = ?
       AND created_at >= (NOW() - INTERVAL 10 SECOND)
     LIMIT 1"
);
$dupe_check->bind_param("siss", $title, $type_id, $event_date, $start_time);
$dupe_check->execute();
$dupe = $dupe_check->get_result()->fetch_assoc();
$dupe_check->close();

if ($dupe) {
    echo json_encode(['success' => true, 'message' => 'Event scheduled successfully.', 'id' => (int) $dupe['id']]);
    exit();
}

/* ---- INSERT (prepared statement — no user input touches the SQL string) ---- */
$stmt = $conn->prepare(
    "INSERT INTO calendar_events
        (title, event_type_id, case_id, event_date, start_time, end_time, location, description, status, created_by)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Scheduled', ?)"
);

$official_id = (int) $_SESSION['official_id'];
$stmt->bind_param(
    "siisssssi",
    $title, $type_id, $case_id, $event_date, $start_time, $end_time, $location, $description, $official_id
);

if ($stmt->execute()) {
    $new_id = $stmt->insert_id;
    $stmt->close();

    if ($case_id !== null) {
        calendar_sync_case_schedule($conn, $case_id);
    }

    $username = $_SESSION['username'] ?? 'Unknown';
    $desc_log = "Scheduled new event \"{$title}\" on {$event_date}" . ($case_id ? " for case #{$case_id}" : "");
    $log = $conn->prepare("INSERT INTO audit_trail (action, description, user) VALUES ('CREATE', ?, ?)");
    $log->bind_param("ss", $desc_log, $username);
    $log->execute();
    $log->close();

    echo json_encode(['success' => true, 'message' => 'Event scheduled successfully.', 'id' => $new_id]);
} else {
    error_log("Calendar insert error: " . $stmt->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not save the event. Please try again.']);
}

$conn->close();
