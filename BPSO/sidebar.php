```php
<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet"
>

<link rel="stylesheet" href="sidebar.css">

<?php

$currentPage = basename($_SERVER['PHP_SELF']);

/*
 * Create CSRF token if one does not already exist.
 * The parent BPSO pages normally create this token,
 * but this makes the sidebar safe to use independently.
 */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];

?>

<div class="sidebar">

<button
    type="button"
    id="toggleSidebar"
    class="toggle-btn"
    aria-label="Toggle Sidebar"
>
    <i class="fa fa-bars"></i>
</button>

<div class="logo-section">

<img
    src="/BMS/IMAGES/silogo.png"
    alt="San Isidro Logo"
    class="logo1"
>

</div>

<div class="nav">

<li>
<a
    class="nav-link <?= $currentPage === 'index.php' ? 'active' : ''; ?>"
    href="index.php"
>
    <i class="fa fa-chart-line"></i>
    <span>Home</span>
</a>
</li>

<li>
<a
    class="nav-link <?= $currentPage === 'blotter.php' ? 'active' : ''; ?>"
    href="blotter.php"
>
    <i class="fa fa-book"></i>
    <span>Blotter</span>
</a>
</li>

<li>
<a
    class="nav-link <?= $currentPage === 'borrowing.php' ? 'active' : ''; ?>"
    href="borrowing.php"
>
    <i class="fa fa-box"></i>
    <span>Borrowing</span>
</a>
</li>

<li>
<a
    class="nav-link <?= $currentPage === 'vehicle_logs.php' ? 'active' : ''; ?>"
    href="vehicle_logs.php"
>
    <i class="fa fa-car"></i>
    <span>Vehicle Logs</span>
</a>
</li>

<li>
<a
    class="nav-link <?= $currentPage === 'profile.php' ? 'active' : ''; ?>"
    href="profile.php"
>
    <i class="fa fa-user-circle"></i>
    <span>My Profile</span>
</a>
</li>

<li>

<form
    action="/BMS/BACKEND/logout.php"
    method="POST"
    style="margin:0;"
>

<input
    type="hidden"
    name="csrf_token"
    value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>"
>

<button
    type="submit"
    class="nav-link logout-btn"
    style="border:0;background:none;width:100%;text-align:left;"
>
    <i class="fa fa-sign-out-alt"></i>
    <span>Logout</span>
</button>

</form>

</li>

</div>

</div>

<script>
document.getElementById("toggleSidebar").onclick = function () {
    document.body.classList.toggle("sidebar-collapsed");
};
</script>
