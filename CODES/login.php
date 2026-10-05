<?php
require_once __DIR__ . '/../BACKEND/security_helpers.php';
bms_start_secure_session();
bms_send_security_headers();

/* =========================================================
   CSRF TOKEN
   ========================================================= */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(
        random_bytes(32)
    );
}

$csrf_token = $_SESSION['csrf_token'];

// --- THE STRICT REDIRECT RULE remains the same ---
if (isset($_SESSION['username'])) {
    if (isset($_SESSION['department'])) {
        $dept = strtoupper($_SESSION['department']); 
        switch ($dept) {
            case 'ADMIN': header("Location: /BMS/barangay_admin/admin_dashboard.php"); exit();
            case 'BPSO': header("Location: /BMS/BPSO/index.php"); exit();
            case 'CLEARANCE': header("Location: /BMS/Clearance/dashboard.php"); exit();
            case 'LUPON': header("Location: /BMS/Lupon_Office/dashboard/dashboard.php"); exit();
            case 'RESIDENT': header("Location: /BMS/Barangay_user/Frontend/Userdashboard.php"); exit();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay San Isidro - Login</title>
    
    <script src="https://www.google.com/recaptcha/enterprise.js?render=6LeDTbosAAAAAEKRg2OKrhBv620cRvwTPw8Fbsv4"></script>
    
    <link rel="stylesheet" href="/BMS/STYLE/login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <style>
        .v-check { display: none !important; }

        /* --- TOP POP-UP NOTIFICATION STYLE --- */
        .top-notification {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            background-color: #d1ecf1;
            color: #0c5460;
            padding: 15px 30px;
            border-radius: 8px;
            border: 1px solid #bee5eb;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            z-index: 10001; /* Higher than sticky topnav */
            font-size: 15px;
            font-weight: bold;
            text-align: center;
            min-width: 320px;
            animation: slideDown 0.4s ease-out;
            font-family: Arial, sans-serif;
        }

        @keyframes slideDown {
            from { top: -100px; opacity: 0; }
            to { top: 20px; opacity: 1; }
        }
    </style>
</head>
<body>

    <?php if (isset($_GET['reset']) && $_GET['reset'] == 'sent'): ?>
        <div id="topAlert" class="top-notification">
            A password reset link has been sent to your email. Please check your inbox or spam folder.
        </div>

        <script>
            // Automatically hide the message after 6 seconds
            setTimeout(function() {
                var alert = document.getElementById('topAlert');
                if(alert) {
                    alert.style.transition = "opacity 0.5s ease";
                    alert.style.opacity = "0";
                    setTimeout(() => alert.remove(), 500);
                }
            }, 6000);
        </script>
    <?php endif; ?>

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
                <a href="/BMS/index.php" class="back-btn">← Back</a>
                <h2>LOGIN</h2>
                
                <form id="loginForm" action="/BMS/BACKEND/login1.php" method="POST">
                    
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

                    <div class="v-check">
                        <input type="text" name="bms_v_field" value="">
                    </div>

                    <input type="hidden" name="g-recaptcha-response" id="g-recaptcha-response">

                    <div class="form-group">
                        <label for="role">Who is the owner of the account?</label>
                        <select id="role" name="account_type" required>
                            <option value="" disabled selected>Select account type</option>
                            <option value="resident">Brgy. San Isidro Resident</option>
                            <option value="official">Brgy. Official</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" placeholder="Enter your username" required>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="input-wrapper">
                            <input type="password" id="password" name="password" required>
                            <i class="fas fa-eye toggle-password" id="eye-password"></i>
                        </div>  
                    </div>

                    <div class="form-links">
                        <a href="/BMS/CODES/forgot_pass.php">Forget password? Click here</a>
                        <a href="/BMS/CODES/register.php">No account yet? Register here!</a>
                    </div>

                    <button type="submit" id="loginButton" style="width:100%; padding: 12px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">
    Confirm
</button>
                </form>
            </div>
        </section>
    </div>

        <script>
            const form = document.getElementById('loginForm');
            const loginButton = document.getElementById('loginButton');

            form.addEventListener('submit', function(event) {
                event.preventDefault();

                // Check required fields first
                if (!form.checkValidity()) {
                    form.reportValidity();
                    return;
                }

                // Prevent multiple submissions
                if (form.dataset.submitting === "true") {
                    return;
                }

                form.dataset.submitting = "true";
                loginButton.disabled = true;
                loginButton.innerText = "Logging in...";

                grecaptcha.enterprise.ready(function() {
                    grecaptcha.enterprise.execute(
                        '6LeDTbosAAAAAEKRg2OKrhBv620cRvwTPw8Fbsv4',
                        { action: 'login' }
                    ).then(function(token) {

                        document.getElementById('g-recaptcha-response').value = token;

                        // Submit after reCAPTCHA is completed
                        form.submit();

                    }).catch(function(error) {

                        console.error("reCAPTCHA error:", error);

                        form.dataset.submitting = "false";
                        loginButton.disabled = false;
                        loginButton.innerText = "Confirm";

                        alert("Unable to verify reCAPTCHA. Please try again.");
                    });
                });
            });
        </script>

    <script src="/BMS/STYLE/login.js"></script>
<?php
$message = "";
$title = "";

if(isset($_GET['error'])) {

    switch($_GET['error']) {

        case "invalid":
            $title = "Login Failed";
            $message = "Invalid username or password.";
            break;

        case "locked":
            $title = "🚫 Account Locked";
            $message = "Too many failed login attempts. Please try again after 20 minutes.";
            break;

        case "pending":
            $title = "Pending Account";
            $message = "Your account is still pending approval.";
            break;

        case "decline":
            $title = "Account Declined";
            $message = "Your account has been declined.";
            break;

        case "captcha_failed":
            $title = "Captcha Failed";
            $message = "Captcha verification failed.";
            break;

        case "bot_detected":
            $title = "Bot Detected";
            $message = "Suspicious activity detected.";
            break;
    }
}

if(!empty($message)) {
?>

<div id="popupMessage" class="popup">
    <div class="popup-content">
        <h2><?php echo $title; ?></h2>
        <p><?php echo $message; ?></p>
        <button onclick="closePopup()">OK</button>
    </div>
</div>

<style>
.popup{
    position:fixed;
    top:0;
    left:0;
    width:100%;
    height:100%;
    background:rgba(0,0,0,0.5);
    display:flex;
    justify-content:center;
    align-items:center;
    z-index:9999;
}

.popup-content{
    background:#fff;
    padding:25px;
    border-radius:10px;
    text-align:center;
    width:320px;
    box-shadow:0 10px 30px rgba(0,0,0,0.3);
    animation: popupFade 0.3s ease;
}

.popup-content h2{
    margin-bottom:10px;
    color:#d9534f;
}

.popup-content p{
    font-size:15px;
    color:#333;
}

.popup-content button{
    margin-top:15px;
    padding:10px 18px;
    border:none;
    background:#d9534f;
    color:#fff;
    border-radius:5px;
    cursor:pointer;
    font-weight:bold;
}

@keyframes popupFade{
    from{
        transform:scale(0.8);
        opacity:0;
    }
    to{
        transform:scale(1);
        opacity:1;
    }
}
</style>

<script>
function closePopup(){
    document.getElementById("popupMessage").style.display = "none";
}
</script>

<?php } ?>
</body>

</html>