<?php
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include "../../BACKEND/db_connect.php";

/* Check if user is logged in */
if (!isset($_SESSION['resident_id']) && !isset($_SESSION['username'])){
  header("Location: /BMS/CODES/login.php");
  exit();
}

/* Get user info using prepared statements */
if (isset($_SESSION['resident_id'])) {
  $resident_id = $_SESSION['resident_id'];
  $sqlUser = "SELECT * FROM residents WHERE resident_id = ?";
  $stmtUser = $conn->prepare($sqlUser);
  $stmtUser->bind_param("i", $resident_id);
} else {
  $username = $_SESSION['username'];
  $sqlUser = "SELECT * FROM residents WHERE username = ?";
  $stmtUser = $conn->prepare($sqlUser);
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

/* Unified history query with category tags */
$query = "
SELECT * FROM (

    -- Resident Requests
    SELECT 
        rr.request_id AS record_id,
        rr.status AS status,
        rr.submitted_at AS submitted_at,
        rr.updated_at AS updated_at,
        rr.reason_message AS reason_message,
        rr.payment_method AS payment_method,
        rr.ref_number AS ref_number,
        rr.fullname AS input_fullname,
        rr.birthdate AS input_birthdate,
        rr.phone AS input_phone,
        rr.email AS input_email,
        rr.address AS input_address,
        rr.purpose AS input_purpose,
        dt.name AS document,
        dt.price AS price,
        'document' AS category_type,
        (
            SELECT GROUP_CONCAT(ra.file_path SEPARATOR ',') 
            FROM record_attachments ra 
            WHERE ra.record_type = 'request' AND ra.record_id = rr.request_id
        ) AS attachment
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
        b.response AS reason_message,
        'N/A' AS payment_method,
        'N/A' AS ref_number,
        b.complainants AS input_fullname,
        NULL AS input_birthdate,
        NULL AS input_phone,
        NULL AS input_email,
        'N/A' AS input_address,
        b.summary_remarks AS input_purpose,
        CONCAT('Blotter: ', b.complaint) AS document,
        NULL AS price,
        'blotter' AS category_type,
        (
            SELECT GROUP_CONCAT(ra.file_path SEPARATOR ',') 
            FROM record_attachments ra 
            WHERE ra.record_type = 'blotter' AND ra.record_id = b.id
        ) AS attachment
    FROM blotter b
    WHERE b.resident_id = ?

    UNION ALL

    -- Medication Assistance Requests
    SELECT 
        rm.id AS record_id,
        rm.status AS status,
        rm.submitted_at AS submitted_at,
        NULL AS updated_at,
        NULL AS reason_message,
        'N/A' AS payment_method,
        'N/A' AS ref_number,
        r.name AS input_fullname,
        r.birthdate AS input_birthdate,
        r.contact_number AS input_phone,
        r.email AS input_email,
        r.address AS input_address,
        CONCAT('Requested Medicine: ', rm.medicine_name, ' | Dosage: ', rm.dosage) AS input_purpose,
        CONCAT('Medical Assistance: ', rm.medicine_name) AS document,
        NULL AS price,
        'medical' AS category_type,
        (
            SELECT GROUP_CONCAT(ra.file_path SEPARATOR ',') 
            FROM record_attachments ra 
            WHERE ra.record_type = 'medicine' AND ra.record_id = rm.id
        ) AS attachment
    FROM resident_medicine rm
    JOIN residents r ON rm.resident_id = r.resident_id
    WHERE rm.resident_id = ?

) AS history_data
ORDER BY submitted_at DESC
";

$stmt = $conn->prepare($query);
if (!$stmt) {
    die("Query Error: " . $conn->error);
}

$stmt->bind_param("iii", $resident_id, $resident_id, $resident_id);
$stmt->execute();
$result = $stmt->get_result();

if (!$result) {
    die("Query Error: " . $conn->error);
}
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>History</title>
<link rel="stylesheet" href="../css/History.css" />
<link rel="stylesheet" href="../css/Sidenav.css" />
<link rel="stylesheet" href="../css/Global.css" />
<link rel="stylesheet" href="../css/Footer.css" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
  .category-filter-dropdown {
    padding: 8px 12px;
    border-radius: 6px;
    border: 1px solid #cbd5e1;
    background: #fff;
    font-size: 0.9rem;
    cursor: pointer;
    margin-bottom: 10px;
  }
  .filter-wrapper {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
    margin-bottom: 15px;
  }
</style>
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
      <a href="Userdashboard.php"><i class="fa-solid fa-house"></i><span>Home</span></a>
      <a href="Profile.php"><i class="fa-solid fa-user"></i><span>My Profile</span></a>
      <a href="Request.php"><i class="fa-solid fa-file-circle-plus"></i><span>Request a Document</span></a>
      <a href="History.php" class="active"><i class="fa-solid fa-clock-rotate-left"></i><span>History</span></a>
      <div class="nav-divider"></div> 
      <form action="/BMS/BACKEND/logout.php" method="POST" class="logout-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <button type="submit" class="logout-link"><i class="fa-solid fa-right-from-bracket"></i><span>Log out</span></button>
      </form>
    </nav>
  </div>
</aside>

<main class="maincontent historybg">
<div class="contentwrapper">
<h1 class="historytitle">My Request History</h1>

<div class="searchcontainer">
  <input type="text" id="searchInput" placeholder="Search by document name, date, or month...">
</div>

<!-- Filter Section with Status Buttons & Category Dropdown -->
<div class="filter-wrapper">
  <div class="filtercontainer" style="margin-bottom: 0;">
    <button class="filterbtn active" data-filter="all">All Status</button>
    <button class="filterbtn" data-filter="pending">Pending</button>
    <button class="filterbtn" data-filter="complete">Approved</button>
    <button class="filterbtn" data-filter="cancelled">Rejected</button>
  </div>

  <select id="categoryFilter" class="category-filter-dropdown" onchange="filterByCategory()">
    <option value="all">All Categories</option>
    <option value="document">Documents Only</option>
    <option value="blotter">Blotters Only</option>
    <option value="medical">Medical Assistance Only</option>
  </select>
</div>

<div class="historygrid" id="historyGrid">
<?php
if($result->num_rows > 0){
  while($row = $result->fetch_assoc()){
    $status = $row['status'] ?? 'Pending';
    $documentName = !empty($row['document']) ? $row['document'] : 'Unknown Document';
    $paymentMethod = $row['payment_method'];
    $refNumber = $row['ref_number'];
    $categoryType = $row['category_type']; // document, blotter, or medical
    $statusClass = "";

    if(in_array($status, ["Approved", "Complete", "Resolved", "Ready for Pickup", "Transferred to Lupon"])) {
      $statusClass = "complete";
    } elseif(strpos($status, 'Pending') !== false) {
      $statusClass = "pending";
    } else {
      $statusClass = "cancelled";
    }

    $priceText = isset($row['price']) && $row['price'] !== NULL ? "₱" . number_format($row['price'], 2) : "Free / Sponsored";
?>
<div class="historycard" data-status="<?php echo $statusClass; ?>" data-category="<?php echo $categoryType; ?>"
     onclick="openPopup(
         '<?php echo htmlspecialchars(addslashes($documentName)); ?>',
         '<?php echo htmlspecialchars(addslashes($status)); ?>',
         '<?php echo date('M d, Y h:i A', strtotime($row['submitted_at'])); ?>',
         '<?php echo htmlspecialchars(addslashes($row['input_fullname'] ?? 'N/A')); ?>',
         '<?php echo htmlspecialchars(addslashes($row['input_birthdate'] ?? 'N/A')); ?>',
         '<?php echo htmlspecialchars(addslashes($row['input_phone'] ?? 'N/A')); ?>',
         '<?php echo htmlspecialchars(addslashes($row['input_email'] ?? 'N/A')); ?>',
         '<?php echo htmlspecialchars(addslashes($row['input_address'] ?? 'N/A')); ?>',
         '<?php echo htmlspecialchars(addslashes($row['input_purpose'] ?? 'N/A')); ?>',
         '<?php echo htmlspecialchars(addslashes($priceText)); ?>',
         '<?php echo htmlspecialchars(addslashes($paymentMethod)); ?>',
         '<?php echo htmlspecialchars(addslashes($refNumber)); ?>',
         '<?php echo htmlspecialchars(addslashes($row['reason_message'] ?? '')); ?>',
         '<?php echo htmlspecialchars(addslashes($row['attachment'] ?? '')); ?>'
     )">
  <div class="cardheader">
    <h3><?php echo htmlspecialchars(ucfirst($documentName)); ?></h3>
    <span class="<?php echo $statusClass; ?>"><?php echo htmlspecialchars($status); ?></span>
  </div>

  <div class="cardbody">
    <p><i class="fa-regular fa-calendar"></i> Submitted: <?php echo date("M d, Y h:i A", strtotime($row['submitted_at'])); ?></p>

    <?php if (!empty($row['updated_at'])) { ?>
      <p><i class="fa-regular fa-clock"></i> Updated: <?php echo date("M d, Y h:i A", strtotime($row['updated_at'])); ?></p>
    <?php } ?>

    <?php if ($paymentMethod !== 'N/A') { ?>
      <div class="payment-info">
        <p><strong><i class="fa-solid fa-money-bill-wave"></i> Fee:</strong> <?php echo $priceText; ?></p>
        <p><strong><i class="fa-solid fa-credit-card"></i> Method:</strong> <?php echo htmlspecialchars($paymentMethod); ?></p>
        <?php if($paymentMethod === 'GCash' && !empty($refNumber)): ?>
          <p><strong><i class="fa-solid fa-hashtag"></i> Ref No:</strong> <?php echo htmlspecialchars($refNumber); ?></p>
        <?php endif; ?>
      </div>
    <?php } ?>

    <?php if (!empty($row['reason_message'])): ?>
      <div class="reason-message-box <?php echo $statusClass; ?>">
        <div class="reason-label">
          <?php
            $icon = 'ℹ'; $label = 'Status Note:';
            if ($status === 'Rejected' || $status === 'Cancelled') { $icon = '⛔'; $label = 'Rejection Reason:'; }
            elseif ($statusClass === 'complete') { $icon = '✓'; $label = 'Officer Note:'; }
            echo $icon . ' ' . $label;
          ?>
        </div>
        <div class="reason-text"><?php echo htmlspecialchars($row['reason_message']); ?></div>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php
  }
} else {
  echo "<p>No request history found.</p>";
}
?>
</div>
</div>

<!-- Details Modal -->
<div id="detailsModal" class="history-modal-overlay" onclick="closePopup(event)">
  <div class="history-modal-content" onclick="event.stopPropagation()">
    <div class="history-modal-header">
      <h2 id="modalDocTitle">Request Input Details</h2>
      <button type="button" class="btn-close-modal" onclick="document.getElementById('detailsModal').style.display='none'">&times;</button>
    </div>
    
    <div class="history-modal-body form-layout-container">
      
      <!-- Section: Status & Meta -->
      <div class="form-row">
        <div class="form-group half">
          <label>Status</label>
          <div class="form-control-static" id="modalStatus"></div>
        </div>
        <div class="form-group half">
          <label>Date Submitted</label>
          <div class="form-control-static" id="modalSubmitted"></div>
        </div>
      </div>

      <!-- Section: Personal Information -->
      <div class="form-section-title">Personal Information</div>
      
      <div class="form-group">
        <label>Full Name Inputted</label>
        <div class="form-control-static" id="modalFullname"></div>
      </div>

      <div class="form-row">
        <div class="form-group half">
          <label>Birthdate</label>
          <div class="form-control-static" id="modalBirthdate"></div>
        </div>
        <div class="form-group half">
          <label>Contact Number</label>
          <div class="form-control-static" id="modalPhone"></div>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group half">
          <label>Email Address</label>
          <div class="form-control-static" id="modalEmail"></div>
        </div>
        <div class="form-group half">
          <label>Address</label>
          <div class="form-control-static" id="modalAddress"></div>
        </div>
      </div>

      <!-- Section: Request / Incident Particulars -->
      <div class="form-section-title">Request / Incident Particulars</div>

      <div class="form-group">
        <label>Purpose / Specific Details</label>
        <div class="form-control-static textarea-box" id="modalPurpose"></div>
      </div>

      <div class="form-group">
        <label>Payment / Service Cost</label>
        <div class="form-control-static" id="modalPayment"></div>
      </div>

      <!-- Section: Notes & Attachments (Conditional) -->
      <div class="form-group" id="modalNoteGroup" style="display: none;">
        <label id="modalNoteLabel">Status Note</label>
        <div class="form-control-static error-box" id="modalNoteText"></div>
      </div>

      <div class="form-group" id="modalAttachmentGroup" style="display: none;">
        <label>Attachment Proof</label>
        <div class="form-control-static" id="modalAttachmentContent"></div>
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

<!-- Simple JavaScript for Category Dropdown Filtering -->
<script>
function filterByCategory() {
    let selectedCategory = document.getElementById('categoryFilter').value;
    let cards = document.querySelectorAll('.historycard');

    cards.forEach(card => {
        let cardCategory = card.getAttribute('data-category');
        if (selectedCategory === 'all' || cardCategory === selectedCategory) {
            card.style.display = '';
        } else {
            card.style.display = 'none';
        }
    });
}
</script>

<!-- External JS Scripts -->
<script src="../js/js.js"></script>
<script src="../js/view.js"></script>
</body>
</html>