<?php
session_start();
include 'config.php'; // Adjust path if needed

/* ===== CSRF TOKEN ===== */
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* ===== SEND MESSAGE ===== */
if(isset($_POST['send_message'])) {

    /* CHECK CSRF */
    $submitted_token = $_POST['csrf_token'] ?? '';

    if (
        empty($submitted_token) ||
        !isset($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $submitted_token)
    ) {
        http_response_code(403);
        die("Invalid CSRF token.");
    }

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $message = trim($_POST['message'] ?? '');

    /* REQUIRED FIELDS */
    if ($name === '' || $email === '' || $contact === '' || $message === '') {
        $_SESSION['msg_error'] = "Please complete all required fields.";
        header("Location: /BMS/index.php");
        exit();
    }

    /* VALIDATE EMAIL */
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['msg_error'] = "Please enter a valid email address.";
        header("Location: /BMS/index.php");
        exit();
    }

    /* INSERT MESSAGE */
    $stmt = $conn->prepare("
        INSERT INTO messages (name, email, contact, message)
        VALUES (?, ?, ?, ?)
    ");

    $stmt->bind_param("ssss", $name, $email, $contact, $message);

    if($stmt->execute()){
        $_SESSION['msg_success'] = "Thank you for reaching us today. You will be receiving an email or SMS with regards to your concern.";
        header("Location: /BMS/index.php");
        exit();
    } else {
        $_SESSION['msg_error'] = "Error: " . $stmt->error;
        header("Location: /BMS/index.php");
        exit();
    }
}
?>