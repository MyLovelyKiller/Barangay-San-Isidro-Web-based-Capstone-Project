<?php
if (session_status() === PHP_SESSION_ACTIVE && empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<link rel="stylesheet" href="/BMS/Lupon_Office/assets/css/sidebar.css">

<aside class="sidebar" id="sidebar">
    <div class="burgertab" id="burgertab">
        <i class="fa-solid fa-bars"></i>
    </div>

    <div class="sidebarcontent">
        <div class="sidebar-header">
            <div class="logosidebar">
                <img src="/BMS/IMAGES/silogo.png" alt="Barangay Logo" id="sidebarLogo">
            </div>
        </div>

        <nav class="navlinks">
            <a href="/BMS/Lupon_Office/dashboard/dashboard.php" class="<?= ($page == 'dashboard.php') ? 'active' : ''; ?>">
                <i class="fa fa-home"></i> <span> Home</span>
            </a>
            <a href="/BMS/Lupon_Office/profile/profile.php" class="<?= ($page == 'profile.php') ? 'active' : ''; ?>">
                <i class="fa fa-user"></i> <span> Profile</span>
            </a>
            <a href="/BMS/Lupon_Office/cases/cases.php" class="<?= ($page == 'cases.php') ? 'active' : ''; ?>">
                <i class="fa fa-book"></i> <span> Case Management</span>
            </a>
            <a href="/BMS/Lupon_Office/calendar/calendar.php" class="<?= ($page == 'calendar.php') ? 'active' : ''; ?>">
                <i class="fa fa-calendar-alt"></i> <span> Calendar</span>
            </a>
            <a href="/BMS/Lupon_Office/audit/audit_trail.php" class="<?= ($page == 'audit_trail.php') ? 'active' : ''; ?>">
                <i class="fa fa-clock-rotate-left"></i> <span> Audit Trail</span>
            </a>

            <form action="/BMS/BACKEND/logout.php" method="POST" class="logout-form" onsubmit="return confirmLogout(event)">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="logout-link">
                    <i class="fa fa-sign-out-alt"></i> <span> Log out</span>
                </button>
            </form>
        </nav>
    </div>
</aside>
<script src="/BMS/Lupon_Office/assets/js/sidebar.js"></script>
