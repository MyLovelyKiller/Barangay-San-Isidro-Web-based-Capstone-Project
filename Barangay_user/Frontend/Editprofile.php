<?php
session_start();
include "../../BACKEND/db_connect.php";

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!isset($_SESSION['resident_id']) && !isset($_SESSION['username'])) {
    header("Location: /BMS/CODES/login.php");
    exit();
}

/* Get resident_id */
if (isset($_SESSION['resident_id'])) {
    $resident_id = $_SESSION['resident_id'];
} else {
    $username = $_SESSION['username'];
    $getResident = "SELECT resident_id FROM residents WHERE username='$username'";
    $residentResult = $conn->query($getResident);

    if ($residentResult && $residentResult->num_rows > 0) {
        $residentData = $residentResult->fetch_assoc();
        $resident_id = $residentData['resident_id'];
        $_SESSION['resident_id'] = $resident_id;
    } else {
        session_destroy();
        header("Location: /BMS/CODES/login.php");
        exit();
    }
}

$sqlUser = "SELECT * FROM residents WHERE resident_id = '$resident_id'";
$userResult = $conn->query($sqlUser);

if ($userResult && $userResult->num_rows == 1) {
    $userData = $userResult->fetch_assoc();
} else {
    die("User not found.");
}
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Edit Profile</title>
    <link rel="stylesheet" href="../css/Profile.css">
    <link rel="stylesheet" href="../css/Sidenav.css">
    <link rel="stylesheet" href="../css/Global.css">
    <link rel="stylesheet" href="../css/Footer.css">
    <script src="../js/js.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
<div class="container">
    <aside class="sidebar" id="sidebar">
        <div class="sidebarcontent">
            <div class="sidebar-header">
                <div class="burgertab" onclick="toggleSidebar()">
                    <i class="fa-solid fa-bars"></i>
                </div>
                <div class="logosidebar">
                    <img src="../picture/Logo.png" alt="Barangay Logo" id="sidebarLogo">
                </div>
            </div>

            <nav class="navlinks">
                <a href="Userdashboard.php"><i class="fa-solid fa-house"></i><span>Home</span></a>
                <a href="Profile.php" class="active"><i class="fa-solid fa-user"></i><span>My Profile</span></a>
                <a href="Request.php"><i class="fa-solid fa-file-circle-plus"></i><span>Request a Document</span></a>
                <a href="History.php"><i class="fa-solid fa-clock-rotate-left"></i><span>History</span></a>
                <div class="nav-divider"></div> 
                <form action="/BMS/BACKEND/logout.php" method="POST" class="logout-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" class="logout-link"><i class="fa-solid fa-right-from-bracket"></i><span>Log out</span></button>
                </form>
            </nav>
        </div>
    </aside>

    <main class="maincontent profilebg">
        <div class="contentwrapper">
            <h1 class="profiletitle">Edit My Profile</h1>

            <div class="profilecard">
                <form class="profileform" action="../Backend/update_profile.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                    
                    <div class="profilepicsection">
                        <div class="avatar-wrapper">
                            <?php
                                $profilePicPath = !empty($userData['profile_picture']) ? "../picture/" . $userData['profile_picture'] : "";
                                $profilePicFile = !empty($userData['profile_picture']) ? __DIR__ . "/../picture/" . $userData['profile_picture'] : "";
                            ?>

                            <?php if (!empty($userData['profile_picture']) && file_exists($profilePicFile)): ?>
                                <img src="<?= htmlspecialchars($profilePicPath) ?>" alt="Profile" class="profilepic" id="profilePreview">
                                <div class="default-avatar" id="defaultAvatar" style="display:none;">
                                    <i class="fa-solid fa-user"></i>
                                </div>
                            <?php else: ?>
                                <div class="default-avatar" id="defaultAvatar">
                                    <i class="fa-solid fa-user"></i>
                                </div>
                                <img src="" alt="Profile" class="profilepic" id="profilePreview" style="display:none;">
                            <?php endif; ?>
                        </div>

                        <div class="upload-controls">
                            <label for="file-upload" class="avabtnsubmit" style="display: inline-block; cursor: pointer; padding: 8px 20px; font-size: 13px;">
                                Choose Photo
                            </label>
                            <input id="file-upload" type="file" name="profile_picture" accept="image/*" style="display: none;" onchange="updateFileName()" />
                            <p id="file-name" style="font-size: 12px; color: #64748b; margin-top: 5px;">No file chosen</p>
                        </div>
                    </div>

                    <div class="formsection">
                        <div class="formgroup">
                            <label>Full Name</label>
                            <input type="text" name="full_name" value="<?= htmlspecialchars($userData['name']) ?>" required />

                            <label>Username</label>
                            <input type="text" name="username" value="<?= htmlspecialchars($userData['username']) ?>" required />

                            <label>Birthdate</label>
                            <input type="date" name="birthdate" value="<?= htmlspecialchars($userData['birthdate']) ?>" required />

                            <label>Sex</label>
                            <select name="sex" required>
                                <option value="Male" <?= $userData['sex']=='Male' ? 'selected' : '' ?>>Male</option>
                                <option value="Female" <?= $userData['sex']=='Female' ? 'selected' : '' ?>>Female</option>
                            </select>
                        </div>

                        <div class="formgroup">
                            <label>Email Address</label>
                            <input type="email" name="email" value="<?= htmlspecialchars($userData['email']) ?>" required />

                            <label>Contact Number</label>
                            <input type="text" name="contact_number" value="<?= htmlspecialchars($userData['contact_number']) ?>" required />

                            <label>Current Address</label>
                            <textarea name="address" rows="2" required><?= htmlspecialchars($userData['address']) ?></textarea>
                        </div>
                    </div>

                    <div class="passwordsection" style="margin-top: 30px; border-top: 1px solid #f1f5f9; padding-top: 20px;">
                        <h3 style="color: #3b62d1; margin-bottom: 20px;">Change Password</h3>
                        <div class="formsection">
                            <div class="formgroup">
                                <label>Current Password</label>
                                <input type="password" name="current_password" placeholder="Enter current password" />
                            </div>
                            <div class="formgroup">
                                <label>New Password</label>
                                <input type="password" name="new_password" placeholder="Enter new password" />
                            </div>
                        </div>
                    </div>

                    <!-- Hidden input to store resident_id -->
                    <input type="hidden" name="resident_id" value="<?= htmlspecialchars($resident_id) ?>" />

                    <div class="profileactions">
                        <button type="submit" class="btnsubmit">Save Changes</button>
                        <a href="Profile.php" class="btncancel">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
        
        <footer class="footer">
            <div class="footerleft"><div class="footertext">A Centralized Web-based Management System Service</div></div>
            <div class="footercenter">All Rights Reserved</div>
            <div class="footerright">Contact Info Part</div>
        </footer>
    </main>
</div>

<script>
function updateFileName() {
    const fileInput = document.getElementById('file-upload');
    const fileNameDisplay = document.getElementById('file-name');
    const preview = document.getElementById('profilePreview');
    const defaultAvatar = document.getElementById('defaultAvatar');

    if (fileInput.files.length > 0) {
        fileNameDisplay.textContent = fileInput.files[0].name;

        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = "block";

            if (defaultAvatar) {
                defaultAvatar.style.display = "none";
            }
        }
        reader.readAsDataURL(fileInput.files[0]);
    } else {
        fileNameDisplay.textContent = "No file chosen";
    }
}
</script>

</body>
</html>