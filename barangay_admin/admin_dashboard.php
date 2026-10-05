<?php
session_start();
include 'config.php';
include 'session_time-out.php';

date_default_timezone_set('Asia/Manila');

$username = $_SESSION['username'] ?? 'Unknown';

/* ============================
   CSRF TOKEN
   ============================ */

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];

/* ============================
   SATELLITE INITIALIZATION
   ============================ */

$satellites = [
    1 => ['name' => 'Balanti Barangay Hall', 'count' => 0],
    2 => ['name' => 'Halang Barangay Hall', 'count' => 0],
    3 => ['name' => 'Karangalan Barangay Hall', 'count' => 0],
    4 => ['name' => 'Greenpark Barangay Hall', 'count' => 0],
    5 => ['name' => 'Brookside Barangay Hall', 'count' => 0]
];

/* ============================
   COUNT APPROVED RESIDENTS
   ============================ */

$residentCountQuery = "
    SELECT 
        s.satellite_id,
        s.satellite_name,
        COUNT(r.resident_id) AS total_residents
    FROM satellites s
    LEFT JOIN residents r
        ON r.satellite_id = s.satellite_id
        AND LOWER(TRIM(r.status)) = 'approved'
    WHERE LOWER(TRIM(s.status)) = 'active'
    GROUP BY s.satellite_id, s.satellite_name
    ORDER BY s.satellite_id ASC
";

$residentCountResult = $conn->query($residentCountQuery);

if (!$residentCountResult) {
    die("SQL ERROR: " . $conn->error);
}

while ($row = $residentCountResult->fetch_assoc()) {
    $satelliteId = (int)$row['satellite_id'];

    if (isset($satellites[$satelliteId])) {
        $satellites[$satelliteId]['name'] = $row['satellite_name'];
        $satellites[$satelliteId]['count'] = (int)$row['total_residents'];
    }
}

/* ============================
   MEDICINE VARIABLES
   ============================ */

$medicineSat = [];

foreach ($satellites as $satelliteId => $satellite) {
    $medicineSat[$satelliteId] = 0;
}

$needMaintenance = 0;
$noMaintenance = 0;

/* ============================
   FETCH MEDICINE DATA
   ============================ */

$query = "
    SELECT 
        r.resident_id,
        r.satellite_id,
        s.satellite_name,
        rm.quantity_remaining,
        rm.refill_threshold
    FROM residents r
    LEFT JOIN satellites s
        ON r.satellite_id = s.satellite_id
    LEFT JOIN resident_medicine rm
        ON r.resident_id = rm.resident_id
    WHERE LOWER(TRIM(r.status)) = 'approved'
";

$result = $conn->query($query);

if (!$result) {
    die("SQL ERROR: " . $conn->error);
}

/* ============================
   PROCESS MEDICINE DATA
   ============================ */

while ($row = $result->fetch_assoc()) {
    if ($row['quantity_remaining'] === null) {
        continue;
    }

    $satelliteId = (int)$row['satellite_id'];
    $remaining = (int)$row['quantity_remaining'];
    $threshold = (int)$row['refill_threshold'];

    if ($remaining <= $threshold) {
        if (isset($medicineSat[$satelliteId])) {
            $medicineSat[$satelliteId]++;
        }
        $needMaintenance++;
    } else {
        $noMaintenance++;
    }
}

/* ============================
   ADD MEDICINE
   ============================ */

if (isset($_POST['add'])) {

    /* ===== CSRF VALIDATION ===== */
    $submitted_token = $_POST['csrf_token'] ?? '';

    if (
        empty($submitted_token) ||
        !isset($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $submitted_token)
    ) {
        http_response_code(403);
        die("Invalid CSRF token.");
    }

    $resident_id = intval($_POST['resident_id'] ?? 0);
    $medicine_name = trim($_POST['medicine_name'] ?? '');
    $dosage = trim($_POST['dosage'] ?? '');
    $quantity_given = intval($_POST['quantity_given'] ?? 0);
    $quantity_remaining = intval($_POST['quantity_remaining'] ?? 0);
    $refill_threshold = intval($_POST['refill_threshold'] ?? 0);
    $last_given = $_POST['last_given'] ?? null;

    /* ===== BASIC VALIDATION ===== */
    if ($resident_id <= 0) {
        die("Invalid resident.");
    }

    if ($medicine_name === '') {
        die("Medicine name is required.");
    }

    if ($quantity_given < 0 || $quantity_remaining < 0 || $refill_threshold < 0) {
        die("Invalid quantity.");
    }

    if ($last_given === '') {
        $last_given = null;
    }

    $stmtName = $conn->prepare("SELECT name FROM residents WHERE resident_id = ?");

    if (!$stmtName) {
        die("SQL ERROR: " . $conn->error);
    }

    $stmtName->bind_param("i", $resident_id);
    $stmtName->execute();

    $nameResult = $stmtName->get_result();
    $resData = $nameResult->fetch_assoc();
    $resident_name = $resData['name'] ?? 'Unknown';

    $stmtName->close();

    if (!$resData) {
        die("Resident not found.");
    }

    $stmt = $conn->prepare("
        INSERT INTO resident_medicine
        (resident_id, medicine_name, dosage, quantity_given, quantity_remaining, refill_threshold, last_given)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    if (!$stmt) {
        die("SQL ERROR: " . $conn->error);
    }

    $stmt->bind_param(
        "issiiis",
        $resident_id,
        $medicine_name,
        $dosage,
        $quantity_given,
        $quantity_remaining,
        $refill_threshold,
        $last_given
    );

    if ($stmt->execute()) {
        $desc = "Added medicine ($medicine_name) for $resident_name";

        $log = $conn->prepare("
            INSERT INTO audit_trail
            (action, description, user)
            VALUES ('ADD', ?, ?)
        ");

        if ($log) {
            $log->bind_param("ss", $desc, $username);
            $log->execute();
            $log->close();
        }
    }

    $stmt->close();

    header("Location: admin_dashboard.php");
    exit;
}

/* ============================
   DELETE MEDICINE
   ============================ */

if (isset($_GET['delete'])) {

    /* ===== CSRF VALIDATION ===== */
    $submitted_token = $_GET['csrf_token'] ?? '';

    if (
        empty($submitted_token) ||
        !isset($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $submitted_token)
    ) {
        http_response_code(403);
        die("Invalid CSRF token.");
    }

    $id = intval($_GET['delete']);

    if ($id <= 0) {
        die("Invalid medicine record.");
    }

    $stmt = $conn->prepare("
        SELECT rm.*, r.name
        FROM resident_medicine rm
        JOIN residents r ON rm.resident_id = r.resident_id
        WHERE rm.id = ?
    ");

    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();

        $get = $stmt->get_result();
        $data = $get->fetch_assoc();

        $stmt->close();

        if ($data) {
            $desc = "Deleted medicine ({$data['medicine_name']}) for {$data['name']}";

            $log = $conn->prepare("
                INSERT INTO audit_trail
                (action, description, user)
                VALUES ('DELETE', ?, ?)
            ");

            if ($log) {
                $log->bind_param("ss", $desc, $username);
                $log->execute();
                $log->close();
            }
        }
    }

    $stmtDelete = $conn->prepare("DELETE FROM resident_medicine WHERE id = ?");

    if ($stmtDelete) {
        $stmtDelete->bind_param("i", $id);
        $stmtDelete->execute();
        $stmtDelete->close();
    }

    header("Location: admin_dashboard.php");
    exit;
}

/* ============================
   GET APPROVED RESIDENTS
   ============================ */

$residents = $conn->query("
    SELECT resident_id, name
    FROM residents
    WHERE LOWER(TRIM(status)) = 'approved'
    ORDER BY name ASC
");

if (!$residents) {
    die("SQL ERROR: " . $conn->error);
}

/* ============================
   GET MEDICINE TABLE DATA
   ============================ */

$medicineRecords = $conn->query("
    SELECT rm.*, r.name
    FROM resident_medicine rm
    JOIN residents r ON rm.resident_id = r.resident_id
    ORDER BY rm.id DESC
");

if (!$medicineRecords) {
    die("SQL ERROR: " . $conn->error);
}

/* ============================
   GET AUDIT TRAIL
   ============================ */

$logs = $conn->query("
    SELECT *
    FROM audit_trail
    ORDER BY id DESC
");

if (!$logs) {
    die("SQL ERROR: " . $conn->error);
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Admin Dashboard</title>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<link rel="stylesheet" href="style/main.css">
<link rel="stylesheet" href="style/header.css">
<link rel="stylesheet" href="style/sidebar.css">
<link rel="stylesheet" href="style/dashboard.css">
</head>

<body>

<?php include "sidebar.php"; ?>

<div class="main-wrapper">

<?php include "header.php"; ?>

<div class="content">

<h2>Residents in San Isidro</h2>

<div class="row">

    <div class="card bg1">
        <h4><?= htmlspecialchars($satellites[1]['name'], ENT_QUOTES, 'UTF-8') ?></h4>
        <h3><?= (int)$satellites[1]['count'] ?></h3>
    </div>

    <div class="card bg2">
        <h4><?= htmlspecialchars($satellites[2]['name'], ENT_QUOTES, 'UTF-8') ?></h4>
        <h3><?= (int)$satellites[2]['count'] ?></h3>
    </div>

    <div class="card bg3">
        <h4><?= htmlspecialchars($satellites[3]['name'], ENT_QUOTES, 'UTF-8') ?></h4>
        <h3><?= (int)$satellites[3]['count'] ?></h3>
    </div>

    <div class="card bg4">
        <h4><?= htmlspecialchars($satellites[4]['name'], ENT_QUOTES, 'UTF-8') ?></h4>
        <h3><?= (int)$satellites[4]['count'] ?></h3>
    </div>

    <div class="card bg5">
        <h4><?= htmlspecialchars($satellites[5]['name'], ENT_QUOTES, 'UTF-8') ?></h4>
        <h3><?= (int)$satellites[5]['count'] ?></h3>
    </div>

</div>

<!-- ADD FORM -->

<div class="card mb-3">

    <div class="card-header bg-primary text-white">
        Add Medicine Record
    </div>

    <div class="card-body">

        <form method="POST">

            <input type="hidden"
                   name="csrf_token"
                   value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">

            <div class="row g-2">

                <div class="col-md-3">

                    <select name="resident_id" class="form-control" required>

                        <option value="">Select Resident</option>

                        <?php while ($r = $residents->fetch_assoc()) { ?>

                            <option value="<?= (int)$r['resident_id'] ?>">
                                <?= htmlspecialchars($r['name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>

                        <?php } ?>

                    </select>

                </div>

                <div class="col-md-2">

                    <input type="text"
                           name="medicine_name"
                           class="form-control"
                           placeholder="Medicine Name"
                           required>

                </div>

                <div class="col-md-2">

                    <input type="text"
                           name="dosage"
                           class="form-control"
                           placeholder="Dosage">

                </div>

                <div class="col-md-1">

                    <input type="number"
                           name="quantity_given"
                           class="form-control"
                           placeholder="Given"
                           min="0">

                </div>

                <div class="col-md-1">

                    <input type="number"
                           name="quantity_remaining"
                           class="form-control"
                           placeholder="Left"
                           min="0">

                </div>

                <div class="col-md-1">

                    <input type="number"
                           name="refill_threshold"
                           class="form-control"
                           placeholder="Alert"
                           min="0">

                </div>

                <div class="col-md-2">

                    <input type="date"
                           name="last_given"
                           class="form-control">

                </div>

                <div class="col-md-12 mt-2">

                    <button type="submit"
                            name="add"
                            class="btn btn-success">
                        Add Record
                    </button>

                </div>

            </div>

        </form>

    </div>

</div>

<!-- TABLE -->

<div class="card">

    <div class="card-header bg-dark text-white">
        Resident Medicine Monitoring
    </div>

    <div class="card-body">

        <table class="table table-bordered">

            <thead>

                <tr>
                    <th>Resident</th>
                    <th>Medicine</th>
                    <th>Dosage</th>
                    <th>Remaining</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>

            </thead>

            <tbody>

                <?php while ($row = $medicineRecords->fetch_assoc()) {

                    $low = (int)$row['quantity_remaining'] <= (int)$row['refill_threshold'];

                ?>

                <tr>

                    <td>
                        <?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($row['medicine_name'], ENT_QUOTES, 'UTF-8') ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($row['dosage'], ENT_QUOTES, 'UTF-8') ?>
                    </td>

                    <td>
                        <?= (int)$row['quantity_remaining'] ?>
                    </td>

                    <td>

                        <?php if ($low) { ?>

                            <span class="low-medicine">
                                Low Medicine
                            </span>

                        <?php } else { ?>

                            <span class="normal-medicine">
                                Normal
                            </span>

                        <?php } ?>

                    </td>

                    <td>

                        <a href="?delete=<?= (int)$row['id'] ?>&csrf_token=<?= urlencode($csrf_token) ?>"
                           class="btn btn-danger btn-sm"
                           onclick="return confirm('Delete record?')">
                            Delete
                        </a>

                    </td>

                </tr>

                <?php } ?>

            </tbody>

        </table>

    </div>

</div>

<!-- AUDIT TRAIL -->

<div class="card mt-3 audit-wrapper">

    <div class="card-header bg-secondary text-white">
        Audit Trail
    </div>

    <div class="card-body audit-column">

        <?php while ($log = $logs->fetch_assoc()) { ?>

        <div class="audit-card">

            <div class="audit-top">

                <span class="audit-action">
                    <?= htmlspecialchars($log['action'], ENT_QUOTES, 'UTF-8') ?>
                </span>

                <span class="audit-date">
                    <?= htmlspecialchars($log['date_time'], ENT_QUOTES, 'UTF-8') ?>
                </span>

            </div>

            <div class="audit-desc">
                <?= htmlspecialchars($log['description'], ENT_QUOTES, 'UTF-8') ?>
            </div>

            <div class="audit-user">
                By: <?= htmlspecialchars($log['user'], ENT_QUOTES, 'UTF-8') ?>
            </div>

        </div>

        <?php } ?>

    </div>

</div>

</div>
</div>

</body>
</html>
