<?php
require_once __DIR__ . '/../BACKEND/security_helpers.php';
bms_start_secure_session();
bms_send_security_headers();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay San Isidro</title>
    <link rel="stylesheet" href="/BMS/STYLE/forgot.css">
</head>
<body>
    <header class="topnav">
        <div class="logo-area">
            <img src="/BMS/IMAGES/silogo.png" alt="San Isidro Logo" class="logo1">
            <img src="/BMS/IMAGES/Home.png" alt="One Cainta Logo" class="logo2">
        </div>
    </header>

    <div class="fullbg">
        <section class="topp">
            <div class="top-logo">
                <img src="/BMS/IMAGES/silogo.png" alt="silogo">
            </div>

            <div class="login-form">
                <a href="/BMS/CODES/login.php" class="back-btn">← Back</a>
                <h2>Forgot Password</h2>

                <form action="/BMS/BACKEND/send_reset.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" placeholder="Enter your email" required>
                    </div>

                    <button type="submit">Send Reset Link</button>
                </form>
            </div>
        </section>
    </div>
</body>
</html>