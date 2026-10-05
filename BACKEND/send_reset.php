<?php
session_start();

include 'db_connect.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../PHPMailer/src/Exception.php';
require __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require __DIR__ . '/../PHPMailer/src/SMTP.php';

/* =========================================================
   CSRF PROTECTION
   ========================================================= */

$csrfToken = $_POST['csrf_token'] ?? '';

if (
    empty($csrfToken) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals(
        $_SESSION['csrf_token'],
        $csrfToken
    )
) {
    http_response_code(403);
    exit("Invalid CSRF token.");
}

$email = trim($_POST['email'] ?? '');

if (empty($email)) {
    die("Email required.");
}

$account_type = "";

/* 1. Check officials */
$sql = "SELECT * FROM officials WHERE email = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $account_type = "official";
} else {
    /* 2. Check residents */
    $sql = "SELECT * FROM residents WHERE email = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $account_type = "resident";
    }
}

if ($account_type == "") {
    die("Email not found.");
}

/* 3. Delete old reset requests */
$sql = "DELETE FROM password_resets WHERE email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();

/* 4. Create token */
$token = bin2hex(random_bytes(32));

/* 5. Save token */
$sql = "INSERT INTO password_resets (email, token, account_type) VALUES (?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sss", $email, $token, $account_type);

if (!$stmt->execute()) {
    die("Failed to save token.");
}

$reset_link = "http://localhost/BMS/CODES/reset_pass.php?token=" . urlencode($token);

/* 6. PHPMailer Configuration (Matching with send_otp.php) */
$mail = new PHPMailer(true);

try {
    // SMTP settings
    $mail->isSMTP();
    $mail->SMTPDebug  = 0; 
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    
    // GAMITIN ANG EMAIL AT APP PASSWORD NA NAG-WORK SA OTP
    $mail->Username   = 'christianmorales602@gmail.com'; 
    $mail->Password   = 'flfnjwrbavgelzgu'; 

    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    // SSL BYPASS (Mahalaga para sa XAMPP)
    $mail->SMTPOptions = array(
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        )
    );

    // Sender and receiver
    $mail->setFrom('christianmorales602@gmail.com', 'Barangay San Isidro');
    $mail->addAddress($email);

    // Email content
    $mail->isHTML(true);
    $mail->Subject = 'Password Reset Request - Barangay San Isidro';
    $mail->Body = "
        <div style='font-family: Arial, sans-serif; line-height: 1.6;'>
            <h3>Password Reset Request</h3>
            <p>Hello,</p>
            <p>We received a request to reset your password for your Barangay San Isidro account.</p>
            <p>Click the button below to reset it:</p>
            <p><a href='$reset_link' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Reset Password</a></p>
            <p>Or copy and paste this link in your browser:</p>
            <p><a href='$reset_link'>$reset_link</a></p>
            <p>If you did not request this, you can safely ignore this email.</p>
        </div>
    ";

    $mail->send();

    header("Location: /BMS/CODES/login.php?reset=sent");
    exit();

} catch (Exception $e) {
    die("Email could not be sent. Mailer Error: " . $mail->ErrorInfo);
}
?>