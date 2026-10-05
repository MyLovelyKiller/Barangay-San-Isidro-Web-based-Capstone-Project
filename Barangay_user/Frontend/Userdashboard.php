<?php
session_start();

include "../../BACKEND/db_connect.php";

/* 1. SECURE SESSION CHECK */
if (!isset($_SESSION['resident_id']) && !isset($_SESSION['username'])){
  header("Location: /BMS/CODES/login.php");
  exit();
}

/* 2. FETCH USER DATA (Fortified) */
if (isset($_SESSION['resident_id'])) {
  $resident_id = $_SESSION['resident_id'];
  $stmtUser = $conn->prepare("SELECT * FROM residents WHERE resident_id = ?");
  $stmtUser->bind_param("i", $resident_id);
} else {
  $username = $_SESSION['username'];
  $stmtUser = $conn->prepare("SELECT * FROM residents WHERE username = ?");
  $stmtUser->bind_param("s", $username);
}

$stmtUser->execute();
$userResult = $stmtUser->get_result();

if ($userResult && $userResult->num_rows > 0) {
  $userData = $userResult->fetch_assoc();
  $resident_id = $userData['resident_id'];
  $resident_name = $userData['name'];
} else {
  session_destroy();
  header("Location: /BMS/CODES/login.php");
  exit();
}
$stmtUser->close();

/* 3. UNIFIED RECENT ACTIVITY QUERY (Matching History Page) */
$queryRecent = "
SELECT * FROM (

    -- Resident Requests
    SELECT 
        rr.request_id AS record_id,
        rr.status AS status,
        rr.submitted_at AS submitted_at,
        rr.updated_at AS updated_at,
        dt.name AS document,
        dt.price AS price,
        'document' AS category_type
    FROM resident_request rr
    LEFT JOIN document_types dt 
        ON rr.document_type_id = dt.document_type_id
    WHERE rr.resident_id = ?

    UNION ALL

    -- Blotter / Incident Reports
    SELECT 
        b.id AS record_id,
        b.status AS status,
        CONCAT(b.date, ' 00:00:00') AS submitted_at,
        NULL AS updated_at,
        CONCAT('Blotter: ', b.complaint) AS document,
        NULL AS price,
        'blotter' AS category_type
    FROM blotter b
    WHERE b.resident_id = ?

    UNION ALL

    -- Medication Assistance Requests
    SELECT 
        rm.id AS record_id,
        rm.status AS status,
        rm.submitted_at AS submitted_at,
        NULL AS updated_at,
        CONCAT('Medical Assistance: ', rm.medicine_name) AS document,
        NULL AS price,
        'medical' AS category_type
    FROM resident_medicine rm
    WHERE rm.resident_id = ?

) AS recent_data
ORDER BY submitted_at DESC
LIMIT 10
";

$stmtRecent = $conn->prepare($queryRecent);

// Bind resident_id thrice for the 3 parts of the UNION ALL query
$stmtRecent->bind_param("iii", $resident_id, $resident_id, $resident_id);
$stmtRecent->execute();
$result = $stmtRecent->get_result();
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Barangay Portal</title>
  <link rel="stylesheet" href="../css/Dashboard_Recentrequest.css">
  <link rel="stylesheet" href="../css/Global.css">
  <link rel="stylesheet" href="../css/Sidenav.css">
  <link rel="stylesheet" href="../css/Footer.css">
  <script src="../js/js.js"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
  <div id="successPopup" class="statuspopup">
    <i class="fa-solid fa-circle-check"></i>
    <span>Document successfully submitted! Wait for further updates. Thank you.</span>
  </div>

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
          <a href="Userdashboard.php" class="active">
            <i class="fa-solid fa-house"></i>
            <span>Home</span>
          </a>
          <a href="Profile.php">
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

    <main class="maincontent" id="main-content">
      <div class="contentwrapper">
        <header>
          <h1>Good Day, <?= htmlspecialchars($userData['name']) ?></h1>
          <h2>What can we help you today?</h2>
        </header>

        <section class="boxcontainer">
          <a href="Request.php" class="usercardcontainer">
            <div class="document">
              <div class="icon"><img src="../picture/Clearance.png" alt="Request" class="img"></div>
              <div class="requestboxtext">
                <h3>Request a Document</h3>
                <p>Apply for clearance and permits</p>
              </div>
            </div>
          </a>
          
          <a href="History.php" class="usercardcontainer">
            <div class="tracking">
              <div class="icon">
                <img src="../picture/Tracking.png" alt="Track" class="img">
              </div>
              <div class="requestboxtext">
                <h3>Track Request</h3>
                <p>View real-time status updates</p>
              </div>
            </div>
          </a>

          <a href="Profile.php" class="usercardcontainer">
            <div class="Profile">
              <div class="icon">
                <img src="../picture/Person1.png" alt="Alerts" class="img">
              </div>
              <div class="requestboxtext">
                <h3>Profile</h3>
                <p>Update your profile</p>
              </div>
            </div>
          </a>
        </section>

        <section class="recentrequests">
          <h3>Recent Requests</h3>
          <div class="requestcard"> 
            <?php
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $status = $row['status'] ?? 'Pending';
                    $documentName = !empty($row['document']) ? $row['document'] : 'Unknown Document';
                    $categoryType = $row['category_type'];

                    if (in_array($status, ['Approved', 'Complete', 'Resolved', 'Ready for Pickup', 'Transferred to Lupon'])) {
                        $statusClass = 'complete';
                    } elseif (strpos($status, 'Pending') !== false) {
                        $statusClass = 'pending';
                    } else {
                        $statusClass = 'cancelled';
                    }

                    $price = isset($row['price']) ? floatval($row['price']) : 0;
                    $priceText = ($price > 0) ? '₱' . number_format($price, 2) : 'FREE';
            ?>
                    <div class="requestrow">
                      <div class="request-info" style="width: 100%;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; margin-bottom: 8px;">
                          <h4><?php echo htmlspecialchars($documentName); ?></h4>
                          <span class="status <?php echo $statusClass; ?>"><?php echo htmlspecialchars($status); ?></span>
                        </div>

                        <?php if (!empty($row['submitted_at'])) { ?>
                          <p><i class="fa-regular fa-calendar"></i> Submitted: <?php echo date("M d, Y h:i A", strtotime($row['submitted_at'])); ?></p>
                        <?php } ?>

                        <?php if (!empty($row['updated_at'])) { ?>
                          <p><i class="fa-regular fa-clock"></i> Updated: <?php echo date("M d, Y h:i A", strtotime($row['updated_at'])); ?></p>
                        <?php } ?>

                        <?php if ($categoryType === 'document') { ?>
                          <p><strong>Price: </strong><?php echo htmlspecialchars($priceText); ?></p>
                        <?php } ?>
                      </div>
                    </div>
            <?php 
                }
            } else {
                echo '<p style="grid-column: span 3; text-align: center; padding: 40px; color: #64748b;">No recent requests found.</p>';
            }
            ?>
          </div>
        </section>
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