<?php
require_once __DIR__ . '/../BACKEND/security_helpers.php';
bms_start_secure_session();
bms_send_security_headers();
header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(
        random_bytes(32)
    );
}

$csrf_token = $_SESSION['csrf_token'];

$token = $_GET['token'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay San Isidro - Password Update</title>
    <link rel="stylesheet" href="/BMS/STYLE/forgot.css">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
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
                <h2>Reset Password</h2>

                <form action="/BMS/BACKEND/update_pass.php" method="POST">

                    <!-- CSRF PROTECTION -->
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?php echo htmlspecialchars(
                            $csrf_token,
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>"
                    >

                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">

                    <div class="form-group">
							<label for="password">Password</label>
							<div class="input-wrapper">
								<input type="password" id="password" name="password" required>
								<i class="fas fa-eye toggle-password" id="eye-password"></i>
							</div>
    
							<div class="strength-container">
								<div class="strength-bar">
									<div id="strength-fill"></div>
								</div>
								<small id="strength-text">Password Strength</small>
							</div>

							<ul class="password-rules">
								<li id="rule-length">At least 8 characters</li>
								<li id="rule-uppercase">Uppercase letter</li>
								<li id="rule-lowercase">	Lowercase letter</li>
								<li id="rule-number">Number</li>
								<li id="rule-special">Special character</li>
							</ul>
						</div>

						<div class="form-group">
							<label for="confirm_password">Confirm Password</label>
							<div class="input-wrapper">
								<input type="password" id="confirm_password" name="confirm_password" required>
								<i class="fas fa-eye toggle-password" id="eye-confirm"></i>
							</div>
							<small id="match-message"></small>
						</div>

                    <button type="submit">Confirm</button>
                </form>
            </div>
        </section>
    </div>
    <script src="/BMS/STYLE/reset.js"></script>
</body>
</html>