<?php
include "../../BACKEND/db_connect.php";

header('Content-Type: application/json');

// Check if ID is passed
if (!isset($_GET['id'])) {
    echo json_encode(['price' => 0]);
    exit;
}

$doc_id = intval($_GET['id']);

// Fetch the price from database
$stmt = $conn->prepare("SELECT price FROM document_types WHERE document_type_id = ?");
$stmt->bind_param("i", $doc_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $row = $result->fetch_assoc()) {
    echo json_encode(['price' => floatval($row['price'])]);
} else {
    echo json_encode(['price' => 0]);
}