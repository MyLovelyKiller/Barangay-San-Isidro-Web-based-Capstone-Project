<?php

$dbHost = getenv('BMS_DB_HOST');
$dbUser = getenv('BMS_DB_USER');
$dbPassword = getenv('BMS_DB_PASSWORD');
$dbName = getenv('BMS_DB_NAME');

if (
    $dbHost === false || $dbUser === false || $dbPassword === false ||
    $dbName === false || $dbHost === '' || $dbUser === '' ||
    $dbPassword === '' || $dbName === ''
) {
    error_log('BMS database configuration is incomplete.');
    http_response_code(500);
    exit('Database configuration is unavailable.');
}

try {
    $conn = new mysqli($dbHost, $dbUser, $dbPassword, $dbName);
    if (!$conn->set_charset('utf8mb4')) {
        throw new mysqli_sql_exception('Character set setup failed.');
    }
} catch (mysqli_sql_exception $exception) {
    error_log('BMS database connection or charset setup failed.');
    http_response_code(500);
    exit('Database service is unavailable.');
}
require_once __DIR__ . '/security_helpers.php';