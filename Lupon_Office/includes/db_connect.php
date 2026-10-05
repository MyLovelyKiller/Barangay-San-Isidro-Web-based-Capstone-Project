<?php
// Database Configuration
$host = "localhost";     // Standard for XAMPP
$user = "root";          // Default XAMPP username
$pass = "";              // Default XAMPP password (empty)
$db_name = "barangay_db";

// Create Connection
$conn = new mysqli($host, $user, $pass, $db_name);

// Check Connection
if ($conn->connect_error) {
    // If it fails, stop everything and show the error
    die("Database Connection Failed: " . $conn->connect_error);
}

?>