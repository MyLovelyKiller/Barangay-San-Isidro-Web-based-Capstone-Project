<?php

/* Check if user is logged in */
if (!isset($_SESSION['username'])) {
    header("Location: /BMS/CODES/login.php");
    exit();
}

$currentPage = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
?>

<div class="sidebar">

<button id="toggleSidebar" class="toggle-btn">
<i class="fa fa-bars"></i>
</button>

<div class="logo-section">
<img src="/BMS/IMAGES/silogo.png" alt="San Isidro Logo" class="logo1">
</div>

<ul class="nav">

<li>
<a class="nav-link <?php if($currentPage=='admin_dashboard.php') echo 'active'; ?>" href="admin_dashboard.php">
<i class="fa fa-chart-line"></i>
<span>Home</span>
</a>
</li>

<li>
<a class="nav-link <?php if($currentPage=='residents.php') echo 'active'; ?>" href="residents.php">
<i class="fa fa-users"></i>
<span>Residents</span>
</a>
</li>

<li>
<a class="nav-link <?php if($currentPage=='admin_requests.php') echo 'active'; ?>" href="admin_requests.php">
<i class="fa fa-file-alt"></i>
<span>Documents</span>
</a>
</li>

<li>
<a class="nav-link <?php if($currentPage=='barangay_official.php') echo 'active'; ?>" href="barangay_official.php">
<i class="fa fa-user-tie"></i>
<span>Officials</span>
</a>
</li>

<li>
<a class="nav-link <?php if($currentPage=='attendance.php') echo 'active'; ?>" href="attendance.php">
<i class="fa fa-clock"></i>
<span>Attendance</span>
</a>
</li>

<li>
<a class="nav-link <?php if($currentPage=='message_us.php') echo 'active'; ?>" href="message_us.php">
<i class="fa fa-envelope"></i>
<span>Message Us</span>
</a>
</li>

<li>
<a class="nav-link <?php if($currentPage=='history_data.php') echo 'active'; ?>" href="history_data.php">
<i class="fa fa-history"></i>
<span>Data History</span>
</a>
</li>

<li>
<a class="nav-link <?php if($currentPage=='chatbot_analytics.php') echo 'active'; ?>" href="chatbot_analytics.php">
<i class="fa fa-robot"></i>
<span>Chatbot Analytics</span>
</a>
</li>

<li>
<a class="nav-link <?php if($currentPage=='profile.php') echo 'active'; ?>" href="profile.php">
<i class="fa fa-user-circle"></i>
<span>My Profile</span>
</a>
</li>

<li>
<form action="/BMS/BACKEND/logout.php" method="POST" style="margin:0;">

<input type="hidden"
       name="csrf_token"
       value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

<button type="submit"
        class="nav-link logout-btn"
        style="border:0; background:none; width:100%; text-align:left; cursor:pointer;">
<i class="fa fa-sign-out-alt"></i>
<span>Logout</span>
</button>

</form>
</li>

</ul>

</div>

<script>
document.getElementById("toggleSidebar").onclick = function () {
    document.body.classList.toggle("sidebar-collapsed");
};
</script>