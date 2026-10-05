<?php
require_once(__DIR__ . '/calendar_api_guard.php');
require_once(__DIR__ . '/../includes/db_connect.php');

$result = $conn->query("SELECT id, name, color_hex FROM event_types ORDER BY sort_order ASC, name ASC");

$types = [];
while ($row = $result->fetch_assoc()) {
    $types[] = [
        'id'    => (int) $row['id'],
        'name'  => $row['name'],
        'color' => $row['color_hex'],
    ];
}

echo json_encode(['success' => true, 'types' => $types]);
$conn->close();
