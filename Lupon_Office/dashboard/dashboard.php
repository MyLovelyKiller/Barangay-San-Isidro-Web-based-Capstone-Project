<?php
session_start();
include(__DIR__ . '/../includes/db_connect.php');
include(__DIR__ . '/../includes/session_time-out.php');

/* ACCESS CONTROL */
if (
    !isset($_SESSION['official_id']) ||
    !isset($_SESSION['department']) ||
    strtoupper(trim($_SESSION['department'])) !== "LUPON"
) {
    header("Location: /BMS/CODES/login.php?error=1");
    exit();
}

$page = 'dashboard.php';

$official_id = (int)$_SESSION['official_id'];

/* GET LUPON OFFICIAL'S SATELLITE */
$stmt = $conn->prepare("
    SELECT satellite_id
    FROM officials
    WHERE official_id = ?
    AND UPPER(TRIM(department)) = 'LUPON'
    LIMIT 1
");

$stmt->bind_param("i", $official_id);
$stmt->execute();
$result = $stmt->get_result();
$official = $result->fetch_assoc();
$stmt->close();

if (!$official) {
    header("Location: /BMS/CODES/login.php?error=usernotfound");
    exit();
}

$satellite_id = (int)($official['satellite_id'] ?? 0);

/* OFFICIAL MUST HAVE A SATELLITE */
if ($satellite_id <= 0) {
    header("Location: /BMS/CODES/login.php?error=satellite_not_assigned");
    exit();
}

/* GET SATELLITE NAME */
$stmt = $conn->prepare("
    SELECT satellite_name
    FROM satellites
    WHERE satellite_id = ?
    AND LOWER(TRIM(status)) = 'active'
    LIMIT 1
");

$stmt->bind_param("i", $satellite_id);
$stmt->execute();
$satellite_result = $stmt->get_result();
$satellite = $satellite_result->fetch_assoc();
$stmt->close();

if (!$satellite) {
    header("Location: /BMS/CODES/login.php?error=invalid_satellite");
    exit();
}

$satellite_name = $satellite['satellite_name'];

/* DASHBOARD STATISTICS */

/* Pending */
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM cases
    WHERE status = 'Pending'
    AND satellite_id = ?
");
$stmt->bind_param("i", $satellite_id);
$stmt->execute();
$pending = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();

/* Ongoing */
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM cases
    WHERE status = 'Ongoing'
    AND satellite_id = ?
");
$stmt->bind_param("i", $satellite_id);
$stmt->execute();
$ongoing = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();

/* Settled */
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM cases
    WHERE status = 'Settled'
    AND satellite_id = ?
");
$stmt->bind_param("i", $satellite_id);
$stmt->execute();
$settled = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();

/* CFA */
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM cases
    WHERE status = 'CFA'
    AND satellite_id = ?
");
$stmt->bind_param("i", $satellite_id);
$stmt->execute();
$cfa = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();

/* TOTAL CASES */
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM cases
    WHERE satellite_id = ?
");
$stmt->bind_param("i", $satellite_id);
$stmt->execute();
$total_cases = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();

/* RECENT CASES */
$stmt = $conn->prepare("
    SELECT *
    FROM cases
    WHERE satellite_id = ?
    ORDER BY date_filed DESC
    LIMIT 8
");

$stmt->bind_param("i", $satellite_id);
$stmt->execute();
$cases_result = $stmt->get_result();
?>
<!DOCTYPE html>
<html>

<head>
    <title>Lupon Dashboard</title>
    <link rel="stylesheet" href="/BMS/Lupon_Office/assets/css/style.css">
</head>

<body>
<div class="contentwrapper">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="main">
        <div class="topbar">
            <div class="logo-area">
                <img src="/BMS/IMAGES/silogo.png" alt="Logo" class="topbar-logo">
                <h2 class="system-title">Lupon Department</h2>
            </div>
            <div class="topbar-right">
                <div class="clock" id="clock"></div>
            </div>
        </div>
        
        <div class="content">
            <div class="header-actions">
                <h2>Dashboard</h2>
            </div>

            <div class="stats-container">
                <div class="card pending">Pending Cases <span><?= $pending ?></span></div>
                <div class="card ongoing">Ongoing Cases <span><?= $ongoing ?></span></div>
                <div class="card settled">Settled Cases <span><?= $settled ?></span></div>
                <div class="card cfa">CFA Cases <span><?= $cfa ?></span></div>
                <div class="card total">Total Cases <span><?= $total_cases ?></span></div>
            </div>

            <div class="table-container">
                <div class="table-header">
                    <h3>Recent Cases</h3>
                    
                    <div class="table-actions">                               
                    </div>
                </div>

                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Case No</th>
                                <th>Complainant</th>
                                <th>Respondent</th>
                                <th>Status</th>
                                <th>Schedule</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="caseTableBody">
                            <?php if ($cases_result && $cases_result->num_rows > 0): ?>
                                <?php while ($row = $cases_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['case_no']) ?></td>
                                        <td><?= htmlspecialchars($row['complainant_name']) ?></td>
                                        <td><?= htmlspecialchars($row['respondent_name']) ?></td>
                                        <td>
                                            <span class="badge <?= strtolower(htmlspecialchars($row['status'])) ?>">
                                                <?= htmlspecialchars($row['status']) ?>
                                            </span>
                                        </td>
<td>
                                <?php
                                if (!empty($row['schedule_date']) && $row['schedule_date'] !== '0000-00-00') {
                                    echo date("Y-m-d", strtotime($row['schedule_date']));
                                } else {
                                    echo "Not yet scheduled";
                                }
                                ?>
                            </td>                                        <td>
                                            <button class="btn-edit" onclick="editCase(<?= $row['id'] ?>)">Edit</button>
                                            <button class="btn-view" onclick="viewCase(<?= $row['id'] ?>)">View</button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="6" style="text-align:center;">No recent cases found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            </div>
    </div>
</div>        <script src="/BMS/Lupon_Office/assets/js/script.js"></script>

</body>

</html>