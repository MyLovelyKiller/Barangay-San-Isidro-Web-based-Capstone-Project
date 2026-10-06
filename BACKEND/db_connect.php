<?php

$dbHost = getenv('BMS_DB_HOST');
$dbUser = getenv('BMS_DB_USER');
$dbPassword = getenv('BMS_DB_PASSWORD');
$dbName = getenv('BMS_DB_NAME');
$dbPort = getenv('BMS_DB_PORT');

if ($dbHost === false || $dbHost === '') {
    $dbHost = getenv('MYSQLHOST');
}
if ($dbUser === false || $dbUser === '') {
    $dbUser = getenv('MYSQLUSER');
}
if ($dbPassword === false || $dbPassword === '') {
    $dbPassword = getenv('MYSQLPASSWORD');
}
if ($dbName === false || $dbName === '') {
    $dbName = getenv('MYSQLDATABASE');
}
if ($dbPort === false || $dbPort === '') {
    $dbPort = getenv('MYSQLPORT');
}
if ($dbPort === false || $dbPort === '') {
    $dbPort = '3306';
}

if (
    $dbHost === false || $dbUser === false || $dbPassword === false ||
    $dbName === false || $dbHost === '' || $dbUser === '' ||
    $dbPassword === '' || $dbName === ''
) {
    error_log('BMS database configuration is incomplete.');
    http_response_code(500);
    exit('Database configuration is unavailable.');
}

if (!ctype_digit($dbPort) || (int) $dbPort < 1 || (int) $dbPort > 65535) {
    error_log('BMS database port configuration is invalid.');
    http_response_code(500);
    exit('Database configuration is unavailable.');
}

try {
    $conn = new mysqli($dbHost, $dbUser, $dbPassword, $dbName, (int) $dbPort);
    if (!$conn->set_charset('utf8mb4')) {
        throw new mysqli_sql_exception('Character set setup failed.');
    }
} catch (mysqli_sql_exception $exception) {
    error_log('BMS database connection or charset setup failed.');
    http_response_code(500);
    exit('Database service is unavailable.');
}
require_once __DIR__ . '/security_helpers.php';