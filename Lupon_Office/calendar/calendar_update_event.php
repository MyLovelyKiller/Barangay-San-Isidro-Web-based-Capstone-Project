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

/* Fetch the OLD row first — we need case_id to re-sync schedule_date
   for both the old and new linked case, and we keep the rest (title,
   date, time, status, etc.) so the audit log below can describe what
   actually changed instead of a generic "Updated event" message. */
$existing_stmt = $conn->prepare("SELECT title, event_type_id, case_id, event_date, start_time, end_time, location, status FROM calendar_events WHERE id = ?");
$existing_stmt->bind_param("i", $id);
$existing_stmt->execute();
$existing = $existing_stmt->get_result()->fetch_assoc();
$existing_stmt->close();

if (!$existing) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Event not found.']);
    exit();
}
$old_case_id = $existing['case_id'] !== null ? (int) $existing['case_id'] : null;

$title       = trim($input['title'] ?? '');
$type_id     = (int) ($input['event_type_id'] ?? 0);
$case_id     = !empty($input['case_id']) ? (int) $input['case_id'] : null;
$event_date  = $input['event_date'] ?? '';
$start_time  = $input['start_time'] ?? '';
$end_time    = !empty($input['end_time']) ? $input['end_time'] : null;
$location    = trim($input['location'] ?? '') ?: 'Barangay Hall - Lupon Office';
$description = trim($input['description'] ?? '');
$status      = $input['status'] ?? 'Scheduled';

$allowed_status = ['Scheduled', 'Completed', 'Cancelled', 'Postponed'];

/* ---- VALIDATION (mirrors calendar_save_event.php) ---- */
$errors = [];

if ($title === '' || mb_strlen($title) > 150) {
    $errors[] = 'Title is required and must be under 150 characters.';
}

if ($type_id <= 0) {
    $errors[] = 'Please select a valid event type.';
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

if (!in_array($status, $allowed_status, true)) {
    $errors[] = 'Invalid status value.';
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

/* ---- UPDATE ---- */
$stmt = $conn->prepare(
    "UPDATE calendar_events SET
        title = ?, event_type_id = ?, case_id = ?, event_date = ?,
        start_time = ?, end_time = ?, location = ?, description = ?, status = ?
     WHERE id = ?"
);
$stmt->bind_param(
    "siissssssi",
    $title, $type_id, $case_id, $event_date, $start_time, $end_time, $location, $description, $status, $id
);

if ($stmt->execute()) {
    $stmt->close();

    // Re-sync schedule_date for both the old and new linked case
    if ($old_case_id !== null) {
        calendar_sync_case_schedule($conn, $old_case_id);
    }
    if ($case_id !== null && $case_id !== $old_case_id) {
        calendar_sync_case_schedule($conn, $case_id);
    }

    $username = $_SESSION['username'] ?? 'Unknown';

    /* ---- Build a diff-aware description ----
       A generic "Updated event" tells you nothing useful in the trail.
       Call out the changes that actually matter: status transitions
       (especially Cancelled/Postponed), reschedules, and retitles.
       Minor edits (location/description tweaks only) fall back to a
       short generic note rather than an empty change list. */
    $changes = [];

    if ($existing['status'] !== $status) {
        $changes[] = "status changed from {$existing['status']} to {$status}";
    }
    if ($existing['event_date'] !== $event_date || substr($existing['start_time'], 0, 5) !== substr($start_time, 0, 5)) {
        $changes[] = "rescheduled from " . formatDisplayDateTime($existing['event_date'], $existing['start_time'])
            . " to " . formatDisplayDateTime($event_date, $start_time);
    }
    if ($existing['title'] !== $title) {
        $changes[] = "retitled from \"{$existing['title']}\" to \"{$title}\"";
    }
    if ($old_case_id !== $case_id) {
        $changes[] = $case_id === null
            ? "unlinked from case #{$old_case_id}"
            : ($old_case_id === null ? "linked to case #{$case_id}" : "relinked from case #{$old_case_id} to case #{$case_id}");
    }

    $desc_log = $changes
        ? "Updated event \"{$title}\" (ID {$id}): " . implode('; ', $changes)
        : "Updated event \"{$title}\" (ID {$id}) — minor details edited";

    $log = $conn->prepare("INSERT INTO audit_trail (action, description, user) VALUES ('UPDATE', ?, ?)");
    $log->bind_param("ss", $desc_log, $username);
    $log->execute();
    $log->close();

    echo json_encode(['success' => true, 'message' => 'Event updated successfully.']);
} else {
    error_log("Calendar update error: " . $stmt->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not update the event. Please try again.']);
}

$conn->close();
