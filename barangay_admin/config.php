<?php

$conn = new mysqli(
    "localhost",
    "root",
    "",
    "barangay_db"
);

if ($conn->connect_error) {
    die("Database connection failed.");
}

$conn->set_charset("utf8mb4");
?>