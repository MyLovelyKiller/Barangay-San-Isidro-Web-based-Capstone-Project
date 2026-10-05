<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require __DIR__ . '/BACKEND/db_connect.php';

try {
    $conn->query('SELECT 1');
    $conn->close();
} catch (mysqli_sql_exception $exception) {
    error_log('BMS readiness database check failed.');
    http_response_code(503);
    echo json_encode(['status' => 'unavailable']);
    exit();
}

http_response_code(200);
echo json_encode(['status' => 'ok']);
