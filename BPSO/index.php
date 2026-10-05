<?php
session_start();
require_once 'config.php';
include 'session_time-out.php';

$official_id = (int)($_SESSION['official_id'] ?? 0);

if ($official_id <= 0) {
    die("Unauthorized access.");
}

$stmt = $conn->prepare("
    SELECT department, satellite_id
    FROM officials
    WHERE official_id = ?
    LIMIT 1
");

$stmt->bind_param("i", $official_id);
$stmt->execute();

$official = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$official || strtoupper(trim($official['department'])) !== 'BPSO') {
    die("Unauthorized access.");
}

$satellite_id = (int)$official['satellite_id'];

if ($satellite_id <= 0) {
    die("Your account is not assigned to a satellite.");
}

$stmt = $conn->prepare("
    SELECT satellite_name
    FROM satellites
    WHERE satellite_id = ?
    LIMIT 1
");

$stmt->bind_param("i", $satellite_id);
$stmt->execute();

$satellite = $stmt->get_result()->fetch_assoc();
$stmt->close();

$satellite_name = $satellite['satellite_name'] ?? 'Unknown Satellite';


function getCount($conn, $table, $satellite_id) {

    $allowed_tables = [
        'blotter',
        'vehicle_logs',
        'borrowing',
        'residents'
    ];

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
    $stmt->execute();

    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    $stmt->close();

    return (int)($row['total'] ?? 0);
}

$blotterCount = getCount($conn, 'blotter', $satellite_id);
$vehicleCount = getCount($conn, 'vehicle_logs', $satellite_id);
$borrowingCount = getCount($conn, 'borrowing', $satellite_id);


function getRecentActivities($conn, $satellite_id, $limit = 5) {

    $limit = (int)$limit;

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

    $stmt->execute();

    return $stmt->get_result();
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

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

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

<img src="/BMS/IMAGES/silogo.png" style="width:70px;height:70px;object-fit:contain;">

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

<h4 class="mb-3 fw-bold">System Overview</h4>

<div class="row g-4 mb-5">

<?php

$overview=[

['name'=>'BLOTTER CASES','table'=>'blotter','color'=>'#198754'],

['name'=>'VEHICLE LOGS','table'=>'vehicle_logs','color'=>'#0dcaf0'],

['name'=>'BORROWED ITEMS','table'=>'borrowing','color'=>'#ffc107']

];

foreach($overview as $o){

?>

<div class="col-md-4">

<div class="card p-3 stat-card" style="border-left:5px solid <?php echo $o['color']; ?>">

<small class="text-muted fw-bold">
<?php echo $o['name']; ?>
</small>

<h3 class="fw-bold mt-2">
<?php echo getCount($conn, $o['table'], $satellite_id); ?>
</h3>

</div>

</div>

<?php } ?>

</div>

<!-- MANAGEMENT MODULES -->

<h4 class="mb-3 fw-bold">Management Modules</h4>

<div class="row g-3 mb-5">

<?php

$modules=[

['name'=>'Blotter','link'=>'blotter.php','color'=>'btn-success','icon'=>'📖'],

['name'=>'Vehicle Logs','link'=>'vehicle_logs.php','color'=>'btn-info','icon'=>'🚗'],

['name'=>'Borrowing','link'=>'borrowing.php','color'=>'btn-warning','icon'=>'📦']

];

foreach($modules as $m){

?>

<div class="col-md-4">

<div class="card p-3 module-card border-0">

<div class="d-flex justify-content-between align-items-center">

<span class="fw-bold">

<?php echo $m['icon']." ".$m['name']; ?>

</span>

<a href="<?php echo $m['link']; ?>" class="btn btn-sm text-white <?php echo $m['color']; ?>">

+ New

</a>

</div>

</div>

</div>

<?php } ?>

</div>

<!-- RECENT ACTIVITIES -->

<h4 class="mb-3 fw-bold">Recent Activities</h4>

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

$activities=getRecentActivities($conn, $satellite_id);

if($activities && mysqli_num_rows($activities)>0){

while($row=mysqli_fetch_assoc($activities)){

$status=strtolower($row['status']);

$badge="bg-secondary";

if(in_array($status,['resolved','returned','completed'])){

$badge="bg-success";

}

if(in_array($status,['pending','ongoing','borrowed'])){

$badge="bg-warning text-dark";

}

if(in_array($status,['cancelled','overdue'])){

$badge="bg-danger";

}

?>

<tr>

<td>

<strong><?php echo $row['module']; ?>:</strong>

<?php echo htmlspecialchars($row['description']); ?>

</td>

<td>

<span class="badge <?php echo $badge; ?>">

<?php echo ucfirst($row['status']); ?>

</span>

</td>

<td>

<?php echo date("M d, Y",strtotime($row['date_recorded'])); ?>

</td>

</tr>

<?php

}

}else{

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