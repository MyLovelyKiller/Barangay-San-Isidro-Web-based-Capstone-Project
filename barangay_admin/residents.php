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

/* ===== CSRF VALIDATION FOR ACTIONS ===== */
if(
    isset($_GET['approve']) ||
    isset($_GET['decline']) ||
    isset($_GET['lock']) ||
    isset($_GET['unlock'])
){
    $submitted_token = $_GET['csrf_token'] ?? '';

    if(
        empty($submitted_token) ||
        !isset($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $submitted_token)
    ){
        http_response_code(403);
        die("Invalid CSRF token.");
    }
}

/* -------- APPROVE -------- */
if(isset($_GET['approve'])){
    $id = intval($_GET['approve']);

    $get = $conn->prepare("SELECT name FROM residents WHERE resident_id = ?");
    $get->bind_param("i", $id);
    $get->execute();
    $data = $get->get_result()->fetch_assoc();

    $update = $conn->prepare("UPDATE residents SET status='approved' WHERE resident_id = ?");
    $update->bind_param("i", $id);
    $update->execute();

    if($data){
        $desc = "Approved resident ({$data['name']})";
        $log = $conn->prepare("INSERT INTO audit_trail (action, description, user) VALUES ('APPROVE', ?, ?)");
        $log->bind_param("ss", $desc, $name);
        $log->execute();
    }

    header("Location: residents.php");
    exit();
}

/* -------- DECLINE -------- */
if(isset($_GET['decline'])){
    $id = intval($_GET['decline']);

    $get = $conn->prepare("SELECT name FROM residents WHERE resident_id = ?");
    $get->bind_param("i", $id);
    $get->execute();
    $data = $get->get_result()->fetch_assoc();

    $update = $conn->prepare("UPDATE residents SET status='rejected' WHERE resident_id = ?");
    $update->bind_param("i", $id);
    $update->execute();

    if($data){
        $desc = "Declined resident ({$data['name']})";
        $log = $conn->prepare("INSERT INTO audit_trail (action, description, user) VALUES ('DECLINE', ?, ?)");
        $log->bind_param("ss", $desc, $name);
        $log->execute();
    }

    header("Location: residents.php");
    exit();
}

/* -------- LOCK ACCOUNT (ADDED) -------- */
if(isset($_GET['lock'])){
    $id = intval($_GET['lock']);

    $get = $conn->prepare("SELECT name FROM residents WHERE resident_id = ?");
    $get->bind_param("i", $id);
    $get->execute();
    $data = $get->get_result()->fetch_assoc();

    $update = $conn->prepare("UPDATE residents SET is_locked=1 WHERE resident_id = ?");
    $update->bind_param("i", $id);
    $update->execute();

    if($data){
        $desc = "Locked resident account ({$data['name']})";
        $log = $conn->prepare("INSERT INTO audit_trail (action, description, user) VALUES ('LOCK', ?, ?)");
        $log->bind_param("ss", $desc, $name);
        $log->execute();
    }

    header("Location: residents.php");
    exit();
}

/* -------- UNLOCK ACCOUNT (ADDED) -------- */
if(isset($_GET['unlock'])){
    $id = intval($_GET['unlock']);

    $get = $conn->prepare("SELECT name FROM residents WHERE resident_id = ?");
    $get->bind_param("i", $id);
    $get->execute();
    $data = $get->get_result()->fetch_assoc();

    $update = $conn->prepare("UPDATE residents SET is_locked=0 WHERE resident_id = ?");
    $update->bind_param("i", $id);
    $update->execute();

    if($data){
        $desc = "Unlocked resident account ({$data['name']})";
        $log = $conn->prepare("INSERT INTO audit_trail (action, description, user) VALUES ('UNLOCK', ?, ?)");
        $log->bind_param("ss", $desc, $name);
        $log->execute();
    }

    header("Location: residents.php");
    exit();
}

/* -------- GET RESIDENTS -------- */
$result = $conn->query("SELECT * FROM residents ORDER BY resident_id DESC");

if(!$result){
    die("SQL Error: " . $conn->error);
}
?>

<!DOCTYPE html>
<html>
<head>

<title>Residents</title>

<link rel="stylesheet" href="style/main.css">
<link rel="stylesheet" href="style/sidebar.css">
<link rel="stylesheet" href="style/header.css">
<link rel="stylesheet" href="style/residents.css">

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

</head>

<body>

<?php include 'sidebar.php'; ?>

<div class="main-wrapper">

<?php include 'header.php'; ?>

<div class="content">

<h2>Residents List</h2>

<table>

<tr>
<th>ID</th>
<th>Name</th>
<th>Address</th>
<th>Status</th>
<th>Action</th>
</tr>

<?php while($row = $result->fetch_assoc()){ ?>

<tr>

<td><?= (int)$row['resident_id'] ?></td>

<td>
<?= htmlspecialchars($row['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
</td>

<td>
<?= htmlspecialchars($row['address'] ?? '', ENT_QUOTES, 'UTF-8') ?>
</td>

<td>
<?php
$status = $row['status'];

if($status == "pending"){
    echo "<span class='status-pending'>Pending</span>";
}

if($status == "approved"){
    echo "<span class='status-approved'>Approved</span>";
}

if($status == "rejected"){
    echo "<span class='status-declined'>Rejected</span>";
}
?>
</td>

<td>

<?php if($status == "pending"){ ?>

<a href="?approve=<?= (int)$row['resident_id'] ?>&csrf_token=<?= urlencode($csrf_token) ?>">
<button class="btn btn-approve">
<i class="fa fa-check"></i> Approve
</button>
</a>

<a href="?decline=<?= (int)$row['resident_id'] ?>&csrf_token=<?= urlencode($csrf_token) ?>">
<button class="btn btn-decline">
<i class="fa fa-times"></i> Decline
</button>
</a>

<?php } ?>

<!-- ===== LOCK / UNLOCK BUTTON (ADDED) ===== -->
<?php if(isset($row['is_locked']) && $row['is_locked'] == 1){ ?>

<a href="?unlock=<?= (int)$row['resident_id'] ?>&csrf_token=<?= urlencode($csrf_token) ?>">
<button class="btn btn-approve">
<i class="fa fa-unlock"></i> Unlock
</button>
</a>

<?php } else { ?>

<a href="?lock=<?= (int)$row['resident_id'] ?>&csrf_token=<?= urlencode($csrf_token) ?>">
<button class="btn btn-decline">
<i class="fa fa-lock"></i> Lock
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
