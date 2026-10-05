<?php
require_once(__DIR__ . '/calendar_api_guard.php');
require_once(__DIR__ . '/../includes/db_connect.php');

/**
 * Always fetches a RANGE, never the whole table. The frontend
 * asks for exactly the span of days visible in the current
 * calendar view (usually ~35-42 days), so this stays fast no
 * matter how many years of hearings accumulate.
 */
$start = $_GET['start'] ?? '';
$end   = $_GET['end'] ?? '';

$date_pattern = '/^\d{4}-\d{2}-\d{2}$/';
if (!preg_match($date_pattern, $start) || !preg_match($date_pattern, $end)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'A valid start and end date (YYYY-MM-DD) are required.']);
    exit();
}

$sql = "SELECT
            e.id, e.title, e.event_date, e.start_time, e.end_time,
            e.location, e.description, e.status, e.case_id,
            et.id AS event_type_id, et.name AS event_type, et.color_hex,
            c.case_no, c.complainant_name, c.respondent_name
        FROM calendar_events e
        INNER JOIN event_types et ON et.id = e.event_type_id
        LEFT JOIN cases c ON c.id = e.case_id
        WHERE e.event_date BETWEEN ? AND ?
        ORDER BY e.event_date ASC, e.start_time ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $start, $end);
$stmt->execute();
$result = $stmt->get_result();

$events = [];
while ($row = $result->fetch_assoc()) {
    $events[] = [
        'id'          => (int) $row['id'],
        'title'       => $row['title'],
        'date'        => $row['event_date'],
        'start_time'  => substr($row['start_time'], 0, 5),
        'end_time'    => $row['end_time'] ? substr($row['end_time'], 0, 5) : null,
        'location'    => $row['location'],
        'description' => $row['description'],
        'status'      => $row['status'],
        'case_id'     => $row['case_id'] ? (int) $row['case_id'] : null,
        'case_no'     => $row['case_no'],
        'complainant' => $row['complainant_name'],
        'respondent'  => $row['respondent_name'],
        'type_id'     => (int) $row['event_type_id'],
        'type'        => $row['event_type'],
        'color'       => $row['color_hex'],
    ];
}
$stmt->close();

echo json_encode(['success' => true, 'events' => $events]);
$conn->close();
