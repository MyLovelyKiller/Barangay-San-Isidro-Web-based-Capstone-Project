<?php
session_start();
include "../../BACKEND/db_connect.php";

/* 1. SECURE LOGIN CHECK */
if (!isset($_SESSION['resident_id']) && !isset($_SESSION['username'])) {
    header("Location: /BMS/CODES/login.php");
    exit();
}

/* 2. GET RESIDENT ID (SQL Injection Defense) */
if (isset($_SESSION['resident_id'])) {
    $resident_id = $_SESSION['resident_id'];
} else {
    $username = $_SESSION['username'];
    
    // Convert to Prepared Statement
    $stmt = $conn->prepare("SELECT resident_id FROM residents WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $residentResult = $stmt->get_result();

    if ($residentResult && $residentResult->num_rows > 0) {
        $residentData = $residentResult->fetch_assoc();
        $resident_id = $residentData['resident_id'];
        $_SESSION['resident_id'] = $resident_id; 
    } else {
        session_destroy();
        header("Location: /BMS/CODES/login.php");
        exit();
    }
    $stmt->close();
}

/* 3. GET FULL RESIDENT INFORMATION (SQL Injection Defense) */
$sql = "SELECT * FROM residents WHERE resident_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $resident_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

/* 4. DECRYPTION LOGIC */
$decrypted_id = "N/A"; 

// Ensure $user exists and keys are defined before decrypting
if ($user && !empty($user['id_number'])) {
    if (isset($ciphering, $encryption_key, $encryption_iv)) {
        // Fixed: changed $officer to $user to match your resident query
        $decrypted_id = openssl_decrypt($user['id_number'], $ciphering, $encryption_key, 0, $encryption_iv);
        
        if ($decrypted_id === false) {
            $decrypted_id = "Encryption Error";
        }
    } else {
        $decrypted_id = "Config Missing";
    }
}
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Profile</title>

<link rel="stylesheet" href="../css/Profile.css">
<link rel="stylesheet" href="../css/Global.css">
<link rel="stylesheet" href="../css/Sidenav.css">
<link rel="stylesheet" href="../css/Footer.css">
<script src="../js/js.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

</head>

<body>

<div class="container">

<aside class="sidebar" id="sidebar">
       <div class="burgertab" onclick="toggleSidebar()">
            <i class="fa-solid fa-bars"></i>
          </div>
      <div class="sidebarcontent">
        <div class="sidebar-header">
          <div class="logosidebar">
            <img src="../picture/Logo.png" alt="Barangay Logo" id="sidebarLogo">
          </div>
        </div>

        <nav class="navlinks">
          <a href="Userdashboard.php">
            <i class="fa-solid fa-house"></i>
            <span>Home</span>
          </a>
          <a href="Profile.php" class="active">
            <i class="fa-solid fa-user"></i>
            <span>My Profile</span>
          </a>
          <a href="Request.php">
            <i class="fa-solid fa-file-circle-plus"></i>
            <span>Request a Document</span>
          </a>
          <a href="History.php">
            <i class="fa-solid fa-clock-rotate-left"></i>
            <span>History</span>
          </a>
          <div class="nav-divider"></div> 
          <a href="/BMS/BACKEND/logout.php">
            <i class="fa-solid fa-right-from-bracket"></i>
            <span>Log out</span>
          </a>
        </nav>
      </div>
    </aside>

<main class="maincontent profilebg">

<div class="contentwrapper">

<h1 class="profiletitle">My Profile</h1>

<div class="profilesection">

<div class="profilecard">

<div class="profileheader">

<div class="avatarcontainer">
    <div class="avatar-wrapper">
        <?php
            $profilePicPath = !empty($user['profile_picture']) ? "../picture/" . $user['profile_picture'] : "";
            $profilePicFile = !empty($user['profile_picture']) ? __DIR__ . "/../picture/" . $user['profile_picture'] : "";
        ?>

        <?php if (!empty($user['profile_picture']) && file_exists($profilePicFile)): ?>
            <img src="<?= htmlspecialchars($profilePicPath) ?>" alt="Profile Picture" class="profilepic"/>
        <?php else: ?>
            <div class="default-avatar">
                <i class="fa-solid fa-user"></i>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="userheadline">
<h2><?php echo $user['name']; ?></h2>
<p>Resident of Barangay San Isidro</p>
</div>

<a href="Editprofile.php" class="btneditprofile">Edit Profile</a>

</div>
</div>

<div class="detailssection">
<h3>Personal Information</h3>
<div class="formsection">
<div class="formgroup">

<label>Full Name</label>
<p class="infotext"><?php echo $user['name']; ?></p>

<label>Sex</label>
<p class="infotext"><?php echo $user['sex']; ?></p>

<label>Birthdate</label>
<p class="infotext"><?php echo $user['birthdate']; ?></p>

<label>ID Number</label>
<span class="detail-value"><?php echo htmlspecialchars($decrypted_id); ?></span>

</div>

<div class="formgroup">

<label>Username</label>
<p class="infotext"><?php echo $user['username']; ?></p>

<label>Email Address</label>
<p class="infotext"><?php echo $user['email']; ?></p>

<label>Contact Number</label>
<p class="infotext"><?php echo $user['contact_number']; ?></p>

<label>Current Address</label>
<p class="infotext"><?php echo $user['address']; ?></p>

</div>

</div>
</div>

</div>
</div>
<footer class="footer">
    <div class="footerleft"><div class="footertext">A Centralized Web-based Management System Service</div></div>
    <div class="footercenter">All Rights Reserved</div>
    <div class="footerright">Contact Info Part</div>
  </footer>
</main>
</div>
</body>
</html>