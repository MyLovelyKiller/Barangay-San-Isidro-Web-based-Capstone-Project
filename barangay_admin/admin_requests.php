<?php
session_start();
include 'config.php';
include 'session_time-out.php';
date_default_timezone_set('Asia/Manila');

/* ===== CSRF TOKEN ===== */
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];

/* ===== GET LOGGED-IN FULL NAME ===== */
$username = $_SESSION['username'] ?? '';
$name = 'Unknown';

if($username){
    $stmtUser = $conn->prepare("SELECT name FROM officials WHERE username = ?");
    $stmtUser->bind_param("s", $username);
    $stmtUser->execute();
    $resultUser = $stmtUser->get_result();

    if($rowUser = $resultUser->fetch_assoc()){
        $name = $rowUser['name'];
    }
}

/* ================= APPROVE REQUEST ================= */
if(isset($_GET['approve_request'])){

    /* CHECK CSRF */
    $submitted_token = $_GET['csrf_token'] ?? '';

    if (
        empty($submitted_token) ||
        !isset($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $submitted_token)
    ) {
        http_response_code(403);
        die("Invalid CSRF token.");
    }

    $id = filter_input(INPUT_GET, 'approve_request', FILTER_VALIDATE_INT);

    if (!$id) {
        http_response_code(400);
        die("Invalid request ID.");
    }

    /* GET REQUEST DATA */
    $get = $conn->prepare("
        SELECT resident_request.*, document_types.name AS document_name
        FROM resident_request
        LEFT JOIN document_types
        ON resident_request.document_type_id = document_types.document_type_id
        WHERE resident_request.request_id = ?
        LIMIT 1
    ");

    $get->bind_param("i", $id);
    $get->execute();
    $result = $get->get_result();
    $data = $result->fetch_assoc();

    if (!$data) {
        header("Location: admin_requests.php");
        exit();
    }

    /* IF DOCUMENT IS BLOTTER */
    if($data['document_name'] == 'Blotter'){

        $complainant = $data['fullname'];
        $purpose = $data['purpose'];

        /* INSERT INTO BLOTTER */
        $blotter = $conn->prepare("
            INSERT INTO blotter
            (complaint, complainants, date, officer, summary_remarks, status)
            VALUES
            (?, ?, CURDATE(), 'System Auto', 'Filed via Resident Request', 'Pending')
        ");

        $blotter->bind_param("ss", $purpose, $complainant);
        $blotter->execute();

        /* AUDIT: BLOTTER CREATED */
        $desc = "Created blotter for {$data['fullname']} (Purpose: {$data['purpose']})";
        $log = $conn->prepare("INSERT INTO audit_trail (action, description, user) VALUES ('BLOTTER', ?, ?)");
        $log->bind_param("ss", $desc, $name);
        $log->execute();

    }

    /* APPROVE REQUEST */
    $update = $conn->prepare("
        UPDATE resident_request
        SET status = 'Approved', approved_at = NOW()
        WHERE request_id = ?
    ");

    $update->bind_param("i", $id);
    $update->execute();

    /* AUDIT: APPROVE */
    $desc = "Approved document request ({$data['document_name']}) for {$data['fullname']}";
    $log = $conn->prepare("INSERT INTO audit_trail (action, description, user) VALUES ('APPROVE REQUEST', ?, ?)");
    $log->bind_param("ss", $desc, $name);
    $log->execute();

    header("Location: admin_requests.php");
    exit();

}

/* ================= DECLINE REQUEST ================= */
if(isset($_GET['decline_request'])){

    /* CHECK CSRF */
    $submitted_token = $_GET['csrf_token'] ?? '';

    if (
        empty($submitted_token) ||
        !isset($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $submitted_token)
    ) {
        http_response_code(403);
        die("Invalid CSRF token.");
    }

    $id = filter_input(INPUT_GET, 'decline_request', FILTER_VALIDATE_INT);

    if (!$id) {
        http_response_code(400);
        die("Invalid request ID.");
    }

    /* GET REQUEST DATA */
    $get = $conn->prepare("
        SELECT resident_request.*, document_types.name AS document_name
        FROM resident_request
        LEFT JOIN document_types
        ON resident_request.document_type_id = document_types.document_type_id
        WHERE resident_request.request_id = ?
        LIMIT 1
    ");

    $get->bind_param("i", $id);
    $get->execute();
    $result = $get->get_result();
    $data = $result->fetch_assoc();

    if (!$data) {
        header("Location: admin_requests.php");
        exit();
    }

    /* UPDATE */
    $update = $conn->prepare("
        UPDATE resident_request
        SET status = 'Rejected'
        WHERE request_id = ?
    ");

    $update->bind_param("i", $id);
    $update->execute();

    /* AUDIT: DECLINE */
    $desc = "Declined document request ({$data['document_name']}) for {$data['fullname']}";
    $log = $conn->prepare("INSERT INTO audit_trail (action, description, user) VALUES ('DECLINE REQUEST', ?, ?)");
    $log->bind_param("ss", $desc, $name);
    $log->execute();

    header("Location: admin_requests.php");
    exit();

}

/* ================= GET REQUESTS ================= */
$requests = $conn->query("
    SELECT resident_request.*, document_types.name AS document_name
    FROM resident_request
    LEFT JOIN document_types
    ON resident_request.document_type_id = document_types.document_type_id
    ORDER BY resident_request.request_id DESC
");
?>

<!DOCTYPE html>
<html>

<head>

<title>Resident Requests</title>

<link rel="stylesheet" href="style/main.css">
<link rel="stylesheet" href="style/sidebar.css">
<link rel="stylesheet" href="style/header.css">
<link rel="stylesheet" href="style/admin_request.css">

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

</head>

<body>

<?php include 'sidebar.php'; ?>

<div class="main-wrapper">

<?php include 'header.php'; ?>

<div class="content">

<h2>Resident Document Requests</h2>

<table>

<tr>
<th>ID</th>
<th>Name</th>
<th>Document</th>
<th>Purpose</th>
<th>Phone</th>
<th>Status</th>
<th>Action</th>
</tr>

<?php while($row = $requests->fetch_assoc()){ ?>

<tr>

<td><?= (int)$row['request_id'] ?></td>

<td><?= htmlspecialchars($row['fullname'], ENT_QUOTES, 'UTF-8') ?></td>

<td><?= htmlspecialchars($row['document_name'], ENT_QUOTES, 'UTF-8') ?></td>

<td><?= htmlspecialchars($row['purpose'], ENT_QUOTES, 'UTF-8') ?></td>

<td><?= htmlspecialchars($row['phone'], ENT_QUOTES, 'UTF-8') ?></td>

<td>
<?php
$status = $row['status'];

if($status=="Pending")
echo "<span class='status-pending'>Pending</span>";

if($status=="Approved")
echo "<span class='status-approved'>Approved</span>";

if($status=="Rejected")
echo "<span class='status-declined'>Rejected</span>";
?>
</td>

<td>

<a href="view_document.php?id=<?= (int)$row['request_id'] ?>" target="_blank">
<button class="btn btn-view">
<i class="fa fa-file"></i> View
</button>
</a>

<?php if($status=="Pending"){ ?>

<a href="?approve_request=<?= (int)$row['request_id'] ?>&csrf_token=<?= urlencode($csrf_token) ?>">
<button class="btn btn-approve">
<i class="fa fa-check"></i>
</button>
</a>

<a href="?decline_request=<?= (int)$row['request_id'] ?>&csrf_token=<?= urlencode($csrf_token) ?>">
<button class="btn btn-decline">
<i class="fa fa-times"></i>
</button>
</a>

<?php } ?>

</td>

</tr>

<?php } ?>

</table>

</div>

</div>

</body>

</html>