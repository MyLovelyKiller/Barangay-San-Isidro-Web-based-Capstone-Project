<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'config.php'; // Make sure $conn is defined

/* ===== CSRF TOKEN ===== */
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];

/* Fetch unread messages for notifications */
$notif_messages = [];
$notif_count = 0;

if ($conn) {

    $notif_stmt = $conn->prepare("
        SELECT *
        FROM messages
        WHERE is_read = 0
        ORDER BY created_at DESC
        LIMIT 5
    ");

    if ($notif_stmt) {
        $notif_stmt->execute();
        $notif_result = $notif_stmt->get_result();

        if ($notif_result) {
            $notif_messages = $notif_result->fetch_all(MYSQLI_ASSOC);
            $notif_count = count($notif_messages);
        }
    }
}
?>

<link rel="stylesheet" href="style/header.css">
<link rel="stylesheet" href="style/main.css">

<div class="topbar">

    <div class="logo-area">
        <img src="/BMS/IMAGES/silogo.png" alt="San Isidro Logo" class="logo1">

        <div class="user-title">
            <?php
            // Displays the name set in Profile.php or during Login
            if(isset($_SESSION['name']) && !empty($_SESSION['name'])){
                echo "Welcome, " . htmlspecialchars($_SESSION['name'], ENT_QUOTES, 'UTF-8');
            } else {
                echo "Welcome, Admin";
            }
            ?>
        </div>
    </div>

    <div class="topbar-right">

        <div class="clock" id="liveClock"></div>

        <div class="notif-icon" onclick="toggleNotif()">
            <i class="fa fa-bell"></i>

            <?php if($notif_count > 0): ?>
                <span class="notif-count">
                    <?= (int)$notif_count ?>
                </span>
            <?php endif; ?>

        </div>

        <div class="notif-panel" id="notifPanel">

            <?php if(!empty($notif_messages)): ?>

                <?php foreach($notif_messages as $msg): ?>

                    <div class="notif-item">

                        <strong>
                            <?= htmlspecialchars($msg['name'], ENT_QUOTES, 'UTF-8') ?>
                        </strong>

                        <p>
                            <?php
                            $shortMsg = substr($msg['message'], 0, 50);
                            echo htmlspecialchars($shortMsg, ENT_QUOTES, 'UTF-8');

                            if(strlen($msg['message']) > 50) {
                                echo "...";
                            }
                            ?>
                        </p>

                        <small>
                            <?= htmlspecialchars(
                                date("M d, Y h:i A", strtotime($msg['created_at'])),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </small>

                    </div>

                <?php endforeach; ?>

                <div class="notif-item" style="text-align:center;">
                    <a href="message_us.php"
                       style="text-decoration:none; color:#3f51b5; font-weight:bold;">
                        View All Messages
                    </a>
                </div>

            <?php else: ?>

                <div class="notif-item no-message">
                    No new messages
                </div>

            <?php endif; ?>

        </div>

    </div>

</div>

<script>
// Live Clock
function updateClock() {
    const now = new Date();
    document.getElementById("liveClock").innerHTML =
        now.toLocaleDateString() + " | " + now.toLocaleTimeString();
}

setInterval(updateClock, 1000);
updateClock();

// Toggle Notification Panel
function toggleNotif() {
    const panel = document.getElementById("notifPanel");
    panel.style.display =
        (panel.style.display === "block") ? "none" : "block";
}

// Close notification panel if clicked outside
window.onclick = function(e) {

    if (!e.target.closest('.notif-icon') &&
        !e.target.closest('.notif-panel')) {

        const panel = document.getElementById("notifPanel");

        if(panel) {
            panel.style.display = "none";
        }
    }
}
</script>