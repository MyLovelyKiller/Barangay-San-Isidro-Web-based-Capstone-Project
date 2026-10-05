<?php

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "barangay_db";

$conn = mysqli_connect($servername, $username, $password, $dbname);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
// --- ENCRYPTION CONFIGURATION ---
//define('ENCRYPTION_KEY', 'your-super-secret-32-char-key-here!!'); 
//define('ENCRYPTION_IV', '1234567890123456'); 

$encryption_key = 'your-super-secret-32-char-key-here!!'; 
$encryption_iv = '1234567890123456';
$ciphering = "AES-256-CBC";

?>