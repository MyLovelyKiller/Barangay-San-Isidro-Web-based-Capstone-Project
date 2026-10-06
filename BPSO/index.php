```php
<?php
session_start();
require_once 'config.php';
include 'session_time-out.php';

$official_id = (int)($_SESSION['official_id'] ?? 0);

if ($official_id <= 0) {
    die("Unauthorized access.");
}

/* GET BPSO ACCOUNT */
$stmt = $conn->prepare("
    SELECT department, satellite_id
    FROM officials
    WHERE official_id = ?
    LIMIT 1
");

if (!$stmt) {
    die("Unable to verify account.");
}

$stmt->bind_param("i", $official_id);
$stmt->execute();

$official = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (
    !$official ||
    strtoupper(trim($official['department'] ?? '')) !== 'BPSO'
) {
    die("Unauthorized access.");
}

/* GET ASSIGNED SATELLITE */
$satellite_id = (int)($official['satellite_id'] ?? 0);

if ($satellite_id <= 0) {
    die("Your account is not assigned to a satellite.");
}

/* VERIFY SATELLITE EXISTS */
$stmt = $conn->prepare("
    SELECT satellite_name
    FROM satellites
    WHERE satellite_id = ?
    LIMIT 1
");

if (!$stmt) {
    die("Unable to verify satellite.");
}

$stmt->bind_param("i", $satellite_id);
$stmt->execute();

$satellite = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$satellite) {
    die("Assigned satellite was not found.");
}

$satellite_name = $satellite['satellite_name'] ?? 'Unknown Satellite';


/* =========================
   GET COUNT
========================= */
function getCount($conn, $table, $satellite_id)
{
    $allowed_tables = [
        'blotter',
        'vehicle_logs',
        'borrowing',
        'residents'
    ];

    /*
     * Table names cannot be bound using prepared
     * statement placeholders, so use an allowlist.
     */
    if (!in_array($table, $allowed_tables, true)) {
        return 0;
    }

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM `$table`
        WHERE satellite_id = ?
    ");

    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param("i", $satellite_id);

    if (!$stmt->execute()) {
        $stmt->close();
        return 0;
    }

    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    $stmt->close();

    return (int)($row['total'] ?? 0);
}


/* =========================
   DASHBOARD COUNTS
========================= */
$blotterCount = getCount(
    $conn,
    'blotter',
    $satellite_id
);

$vehicleCount = getCount(
    $conn,
    'vehicle_logs',
    $satellite_id
);

$borrowingCount = getCount(
    $conn,
    'borrowing',
    $satellite_id
);


/* =========================
   RECENT ACTIVITIES
========================= */
function getRecentActivities($conn, $satellite_id, $limit = 5)
{
    $limit = (int)$limit;

    if ($limit <= 0) {
        $limit = 5;
    }

    /*
     * Keep the limit within a reasonable range.
     */
    if ($limit > 50) {
        $limit = 50;
    }

    $query = "
        SELECT
            'Blotter' AS module,
            complaint AS description,
            status,
            date AS date_recorded
        FROM blotter
        WHERE satellite_id = ?

        UNION ALL

        SELECT
            'Vehicle' AS module,
            plate_number AS description,
            status,
            NOW() AS date_recorded
        FROM vehicle_logs
        WHERE satellite_id = ?

        UNION ALL

        SELECT
            'Borrowing' AS module,
            items AS description,
            status,
            time_borrowed AS date_recorded
        FROM borrowing
        WHERE satellite_id = ?

        ORDER BY date_recorded DESC
        LIMIT ?
    ";

    $stmt = $conn->prepare($query);

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param(
        "iiii",
        $satellite_id,
        $satellite_id,
        $satellite_id,
        $limit
    );

    if (!$stmt->execute()) {
        $stmt->close();
        return false;
    }

    $result = $stmt->get_result();

    /*
     * Do not close the statement here because the
     * returned result is still being used by the page.
     */
    return $result;
}

$recentActivities = getRecentActivities(
    $conn,
    $satellite_id,
    5
);

?>

<!doctype html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>BPSO Dashboard</title>

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet"
>

<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
>

<link rel="stylesheet" href="main.css">
<link rel="stylesheet" href="sidebar.css">
<link rel="stylesheet" href="index.css">

</head>

<body>

<?php include 'sidebar.php'; ?>

<div class="main-wrapper">

<div class="content">

<!-- HEADER -->

<div class="d-flex align-items-center mb-4 p-4 bg-white shadow-sm rounded-4 border-bottom border-primary border-3">

<img
    src="/BMS/IMAGES/silogo.png"
    alt="Barangay San Isidro Logo"
    style="width:70px;height:70px;object-fit:contain;"
>

<div class="ms-3">

<h2 class="fw-bold text-primary mb-0">
Barangay Public Service Officers
</h2>

<p class="text-muted mb-0">
BPSO Management System Dashboard
</p>

</div>

</div>


<!-- SYSTEM OVERVIEW -->

<h4 class="mb-3 fw-bold">
System Overview
</h4>

<div class="row g-4 mb-5">

<?php

$overview = [

    [
        'name' => 'BLOTTER CASES',
        'count' => $blotterCount,
        'color' => '#198754'
    ],

    [
        'name' => 'VEHICLE LOGS',
        'count' => $vehicleCount,
        'color' => '#0dcaf0'
    ],

    [
        'name' => 'BORROWED ITEMS',
        'count' => $borrowingCount,
        'color' => '#ffc107'
    ]

];

foreach ($overview as $o) {

?>

<div class="col-md-4">

<div
    class="card p-3 stat-card"
    style="border-left:5px solid <?= htmlspecialchars($o['color'], ENT_QUOTES, 'UTF-8'); ?>"
>

<small class="text-muted fw-bold">
<?= htmlspecialchars($o['name'], ENT_QUOTES, 'UTF-8'); ?>
</small>

<h3 class="fw-bold mt-2">
<?= (int)$o['count']; ?>
</h3>

</div>

</div>

<?php } ?>

</div>


<!-- MANAGEMENT MODULES -->

<h4 class="mb-3 fw-bold">
Management Modules
</h4>

<div class="row g-3 mb-5">

<?php

$modules = [

    [
        'name' => 'Blotter',
        'link' => 'blotter.php',
        'color' => 'btn-success',
        'icon' => '📖'
    ],

    [
        'name' => 'Vehicle Logs',
        'link' => 'vehicle_logs.php',
        'color' => 'btn-info',
        'icon' => '🚗'
    ],

    [
        'name' => 'Borrowing',
        'link' => 'borrowing.php',
        'color' => 'btn-warning',
        'icon' => '📦'
    ]

];

foreach ($modules as $m) {

?>

<div class="col-md-4">

<div class="card p-3 module-card border-0">

<div class="d-flex justify-content-between align-items-center">

<span class="fw-bold">

<?= htmlspecialchars($m['icon'] . ' ' . $m['name'], ENT_QUOTES, 'UTF-8'); ?>

</span>

<a
    href="<?= htmlspecialchars($m['link'], ENT_QUOTES, 'UTF-8'); ?>"
    class="btn btn-sm text-white <?= htmlspecialchars($m['color'], ENT_QUOTES, 'UTF-8'); ?>"
>
    + New
</a>

</div>

</div>

</div>

<?php } ?>

</div>


<!-- RECENT ACTIVITIES -->

<h4 class="mb-3 fw-bold">
Recent Activities
</h4>

<div class="table-container">

<table class="table table-hover align-middle">

<thead class="table-light">

<tr>

<th>Module / Description</th>
<th>Status</th>
<th>Date Recorded</th>

</tr>

</thead>

<tbody>

<?php

if ($recentActivities && $recentActivities->num_rows > 0) {

    while ($row = $recentActivities->fetch_assoc()) {

        $status = strtolower(
            trim($row['status'] ?? '')
        );

        $badge = "bg-secondary";

        if (
            in_array(
                $status,
                ['resolved', 'returned', 'completed'],
                true
            )
        ) {
            $badge = "bg-success";
        }

        if (
            in_array(
                $status,
                ['pending', 'ongoing', 'borrowed'],
                true
            )
        ) {
            $badge = "bg-warning text-dark";
        }

        if (
            in_array(
                $status,
                ['cancelled', 'overdue', 'rejected'],
                true
            )
        ) {
            $badge = "bg-danger";
        }

        $description = htmlspecialchars(
            $row['description'] ?? '',
            ENT_QUOTES,
            'UTF-8'
        );

        $module = htmlspecialchars(
            $row['module'] ?? '',
            ENT_QUOTES,
            'UTF-8'
        );

        $displayStatus = htmlspecialchars(
            ucfirst($row['status'] ?? ''),
            ENT_QUOTES,
            'UTF-8'
        );

        $recordedDate = '';

        if (
            !empty($row['date_recorded']) &&
            strtotime($row['date_recorded']) !== false
        ) {
            $recordedDate = date(
                "M d, Y",
                strtotime($row['date_recorded'])
            );
        }

?>

<tr>

<td>

<strong>
<?= $module; ?>:
</strong>

<?= $description; ?>

</td>

<td>

<span class="badge <?= htmlspecialchars($badge, ENT_QUOTES, 'UTF-8'); ?>">

<?= $displayStatus; ?>

</span>

</td>

<td>

<?= htmlspecialchars($recordedDate, ENT_QUOTES, 'UTF-8'); ?>

</td>

</tr>

<?php

    }

} else {

?>

<tr>

<td colspan="3" class="text-center text-muted">

No recent activities found.

</td>

</tr>

<?php

}

?>

</tbody>

</table>

</div>

</div>

</div>

</body>
</html>
