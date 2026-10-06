<?php

session_start();

include 'config.php';
include 'session_time-out.php';
bms_require_official_department($conn, 'ADMIN');
date_default_timezone_set('Asia/Manila');

/* ===== CSRF TOKEN ===== */
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];

/* USER */

$username = $_SESSION['username'] ?? 'Unknown';

/* ------------------- HANDLE ADD OFFICIAL ------------------- */

if(isset($_POST['submit'])){

    /* CHECK CSRF */
    $submitted_token = $_POST['csrf_token'] ?? '';

    if (
        empty($submitted_token) ||
        !isset($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $submitted_token)
    ) {
        http_response_code(403);
        die("Invalid CSRF token.");
    }

    $name = trim($_POST['name'] ?? '');

    $position = trim($_POST['position'] ?? '');

    if(!empty($name) && !empty($position)){

        $stmt = $conn->prepare("
            INSERT INTO officials
            (name, position, date_added, status)
            VALUES (?, ?, NOW(), 'Active')
        ");

        $stmt->bind_param("ss", $name, $position);

        if($stmt->execute()){

            $desc = "Added official ($name - $position)";

            $log = $conn->prepare("
                INSERT INTO audit_trail
                (action, description, user)
                VALUES ('ADD', ?, ?)
            ");

            $log->bind_param("ss", $desc, $username);

            $log->execute();

            header("Location: barangay_official.php");

            exit();

        } else {

            $error = "Error adding official: " . $stmt->error;

        }

        $stmt->close();

    } else {

        $error = "Please fill in all fields.";

    }

}

/* ------------------- EDIT ------------------- */

$editOfficial = null;

if(isset($_GET['edit']) && is_numeric($_GET['edit'])){

    $editId = intval($_GET['edit']);

    $stmt = $conn->prepare("
        SELECT *
        FROM officials
        WHERE official_id = ?
    ");

    $stmt->bind_param("i", $editId);

    $stmt->execute();

    $res = $stmt->get_result();

    $editOfficial = $res->fetch_assoc();

    $stmt->close();

}

/* ------------------- UPDATE ------------------- */

if(isset($_POST['update'])){

    /* CHECK CSRF */
    $submitted_token = $_POST['csrf_token'] ?? '';

    if (
        empty($submitted_token) ||
        !isset($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $submitted_token)
    ) {
        http_response_code(403);
        die("Invalid CSRF token.");
    }

    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

    $name = trim($_POST['name'] ?? '');

    $position = trim($_POST['position'] ?? '');

    if(!$id){

        $error = "Invalid official ID.";

    } elseif(empty($name) || empty($position)){

        $error = "Name and Position are required.";

    } else {

        $stmt = $conn->prepare("
            UPDATE officials
            SET name = ?, position = ?
            WHERE official_id = ?
        ");

        $stmt->bind_param("ssi", $name, $position, $id);

        if($stmt->execute()){

            $desc = "Updated official ($name - $position)";

            $log = $conn->prepare("
                INSERT INTO audit_trail
                (action, description, user)
                VALUES ('UPDATE', ?, ?)
            ");

            $log->bind_param("ss", $desc, $username);

            $log->execute();

            header("Location: barangay_official.php?updated=1");

            exit();

        } else {

            $error = "Update failed: ".$stmt->error;

        }

        $stmt->close();

    }

}

/* ------------------- TOGGLE STATUS ------------------- */

if(isset($_GET['toggle_status']) && is_numeric($_GET['toggle_status'])){

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

    $id = intval($_GET['toggle_status']);

    $stmt = $conn->prepare("
        SELECT name, status
        FROM officials
        WHERE official_id = ?
        LIMIT 1
    ");

    $stmt->bind_param("i", $id);

    $stmt->execute();

    $res = $stmt->get_result();

    if($res && $res->num_rows){

        $row = $res->fetch_assoc();

        $newStatus = ($row['status']=='Active') ? 'Inactive' : 'Active';

        $update = $conn->prepare("
            UPDATE officials
            SET status = ?
            WHERE official_id = ?
        ");

        $update->bind_param("si", $newStatus, $id);

        $update->execute();

        $desc = "Changed status of {$row['name']} to $newStatus";

        $log = $conn->prepare("
            INSERT INTO audit_trail
            (action, description, user)
            VALUES ('UPDATE', ?, ?)
        ");

        $log->bind_param("ss", $desc, $username);

        $log->execute();

    }

    header("Location: barangay_official.php");

    exit();

}

/* ------------------- LOCK ACCOUNT ------------------- */

if(isset($_GET['lock']) && is_numeric($_GET['lock'])){

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

    $id = intval($_GET['lock']);

    $stmt = $conn->prepare("
        SELECT name
        FROM officials
        WHERE official_id = ?
        LIMIT 1
    ");

    $stmt->bind_param("i", $id);

    $stmt->execute();

    $res = $stmt->get_result();

    if($res && $res->num_rows){

        $row = $res->fetch_assoc();

        $update = $conn->prepare("
            UPDATE officials
            SET is_locked = 1
            WHERE official_id = ?
        ");

        $update->bind_param("i", $id);

        $update->execute();

        $desc = "Locked official account ({$row['name']})";

        $log = $conn->prepare("
            INSERT INTO audit_trail
            (action, description, user)
            VALUES ('LOCK', ?, ?)
        ");

        $log->bind_param("ss", $desc, $username);

        $log->execute();

    }

    header("Location: barangay_official.php");

    exit();

}

/* ------------------- UNLOCK ACCOUNT ------------------- */

if(isset($_GET['unlock']) && is_numeric($_GET['unlock'])){

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

    $id = intval($_GET['unlock']);

    $stmt = $conn->prepare("
        SELECT name
        FROM officials
        WHERE official_id = ?
        LIMIT 1
    ");

    $stmt->bind_param("i", $id);

    $stmt->execute();

    $res = $stmt->get_result();

    if($res && $res->num_rows){

        $row = $res->fetch_assoc();

        $update = $conn->prepare("
            UPDATE officials
            SET is_locked = 0
            WHERE official_id = ?
        ");

        $update->bind_param("i", $id);

        $update->execute();

        $desc = "Unlocked official account ({$row['name']})";

        $log = $conn->prepare("
            INSERT INTO audit_trail
            (action, description, user)
            VALUES ('UNLOCK', ?, ?)
        ");

        $log->bind_param("ss", $desc, $username);

        $log->execute();

    }

    header("Location: barangay_official.php");

    exit();

}

/* ------------------- SEARCH & FILTER ------------------- */

$search_name = $_GET['search_name'] ?? '';

$filter_position = $_GET['filter_position'] ?? '';

$filter_status = $_GET['filter_status'] ?? '';

$where = [];

$params = [];

$types = '';

if($search_name){

    $where[] = "name LIKE ?";

    $params[] = "%$search_name%";

    $types .= "s";

}

if($filter_position){

    $where[] = "position = ?";

    $params[] = $filter_position;

    $types .= "s";

}

if($filter_status){

    $where[] = "status = ?";

    $params[] = $filter_status;

    $types .= "s";

}

$query = "
    SELECT official_id, name, position, date_added, status, is_locked
    FROM officials
";

if($where){

    $query .= " WHERE ".implode(' AND ', $where);

}

$query .= " ORDER BY official_id DESC";

$stmt = $conn->prepare($query);

if($params){

    $stmt->bind_param($types, ...$params);

}

$stmt->execute();

$result = $stmt->get_result();

/* POSITIONS */

$positions = [];

$posResult = $conn->query("
    SELECT DISTINCT position
    FROM officials
    ORDER BY position ASC
");

while($p = $posResult->fetch_assoc()){

    $positions[] = $p['position'];

}

?>

<!DOCTYPE html>

<html>

<head>

    <title>Barangay Officials</title>

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <link rel="stylesheet" href="style/main.css">

    <link rel="stylesheet" href="style/sidebar.css">

    <link rel="stylesheet" href="style/header.css">

    <link rel="stylesheet" href="style/barangay_official.css">

</head>

<body>

<?php include 'sidebar.php'; ?>

<div class="main-wrapper">

<?php include 'header.php'; ?>

<div class="content">

<h2>
    <i class="fa fa-users"></i> Barangay Officials
</h2>

<?php if(!empty($error)): ?>

<div style="color:red; font-weight:bold; margin-bottom:10px;">

    <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>

</div>

<?php endif; ?>

<?php if($editOfficial): ?>

<form method="POST">

    <input type="hidden"
           name="csrf_token"
           value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">

    <input type="hidden"
           name="id"
           value="<?php echo (int)$editOfficial['official_id']; ?>">

    <input type="text"
           name="name"
           value="<?php echo htmlspecialchars($editOfficial['name'], ENT_QUOTES, 'UTF-8'); ?>"
           required>

    <input type="text"
           name="position"
           value="<?php echo htmlspecialchars($editOfficial['position'], ENT_QUOTES, 'UTF-8'); ?>"
           required>

    <button type="submit" name="update">
        Update
    </button>

</form>

<?php endif; ?>

<form method="POST">

    <input type="hidden"
           name="csrf_token"
           value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">

    <input type="text"
           name="name"
           placeholder="Full Name"
           required>

    <input type="text"
           name="position"
           placeholder="Position"
           required>

    <button type="submit" name="submit">
        Add Official
    </button>

</form>

<br>

<table border="1" width="100%" cellpadding="10">

<tr>

    <th>Name</th>

    <th>Position</th>

    <th>Date Added</th>

    <th>Status</th>

    <th>Action</th>

</tr>

<?php while($row=$result->fetch_assoc()): ?>

<tr>

    <td>
        <?php echo htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'); ?>
    </td>

    <td>
        <?php echo htmlspecialchars($row['position'], ENT_QUOTES, 'UTF-8'); ?>
    </td>

    <td>
        <?php echo htmlspecialchars($row['date_added'], ENT_QUOTES, 'UTF-8'); ?>
    </td>

    <td>

        <a href="?toggle_status=<?php echo (int)$row['official_id']; ?>&csrf_token=<?php echo urlencode($csrf_token); ?>">

            <?php echo htmlspecialchars($row['status'], ENT_QUOTES, 'UTF-8'); ?>

        </a>

        <?php if($row['is_locked'] == 1): ?>

            <span style="color:red;">
                (Locked)
            </span>

        <?php endif; ?>

    </td>

    <td>

        <a href="?edit=<?php echo (int)$row['official_id']; ?>">
            Edit
        </a>

        |

        <a href="delete_official.php?id=<?php echo (int)$row['official_id']; ?>&csrf_token=<?php echo urlencode($csrf_token); ?>"
           onclick="return confirm('Delete?')">
            Delete
        </a>

        |

        <?php if($row['is_locked'] == 1): ?>

            <a href="?unlock=<?php echo (int)$row['official_id']; ?>&csrf_token=<?php echo urlencode($csrf_token); ?>"
               style="color:green;">
                Unlock
            </a>

        <?php else: ?>

            <a href="?lock=<?php echo (int)$row['official_id']; ?>&csrf_token=<?php echo urlencode($csrf_token); ?>"
               style="color:red;">
                Lock
            </a>

        <?php endif; ?>

    </td>

</tr>

<?php endwhile; ?>

</table>

</div>

</div>

</body>

</html>