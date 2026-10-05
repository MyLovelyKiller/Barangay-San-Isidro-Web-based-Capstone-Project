<?php
/*
|--------------------------------------------------------------------------
| Ensure session is started
|--------------------------------------------------------------------------
*/
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| Create CSRF token if it does not already exist
|--------------------------------------------------------------------------
*/
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<link rel="stylesheet" href="style/sidebar.css">

<div class="sidebar" id="sidebar">

    <div class="burgertab" id="burgertab">
        <i class="fa-solid fa-bars"></i>
    </div>

    <div class="sidebar-header">
        <div class="logosidebar">
            <img
                src="/BMS/IMAGES/silogo.png"
                alt="Logo"
                id="sidebarLogo"
            >
        </div>
    </div>

    <nav class="navlinks">

        <?php
        /* Get current page */
        $current_page = basename($_SERVER['PHP_SELF']);
        ?>

        <!-- Dashboard -->
        <a
            href="dashboard.php"
            class="<?= ($current_page == 'dashboard.php') ? 'active' : ''; ?>"
        >
            <i class="fa fa-home"></i>
            <span>Home</span>
        </a>

        <!-- Requests -->
        <a
            href="requests.php"
            class="<?= ($current_page == 'requests.php') ? 'active' : ''; ?>"
        >
            <i class="fas fa-file-alt"></i>
            <span>Requests</span>
        </a>

        <!-- Profile -->
        <a
            href="profile.php"
            class="<?= ($current_page == 'profile.php') ? 'active' : ''; ?>"
        >
            <i class="fa fa-user"></i>
            <span>Profile</span>
        </a>

        <!-- Sidebar Footer -->
        <div class="sidebar-footer">

            <!-- Secure Logout -->
            <form
                action="/BMS/BACKEND/logout.php"
                method="POST"
                class="logout-form"
            >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(
                        $_SESSION['csrf_token'],
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>"
                >

                <button
                    type="submit"
                    class="logout-button"
                >
                    <i class="fa fa-sign-out-alt"></i>
                    <span>Log out</span>
                </button>

            </form>

        </div>

    </nav>
</div>

<script>
const sidebar = document.getElementById("sidebar");
const burgertab = document.getElementById("burgertab");

burgertab.addEventListener("click", function () {
    sidebar.classList.toggle("collapsed");
});
</script>
