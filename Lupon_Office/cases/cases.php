<?php
session_start();
include(__DIR__ . '/../includes/db_connect.php');
include(__DIR__ . '/../includes/session_time-out.php');

/* =========================================================
   CSRF TOKEN
   ========================================================= */

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];

/* ACCESS CONTROL */
if (
    !isset($_SESSION['official_id']) ||
    !isset($_SESSION['department']) ||
    strtoupper(trim($_SESSION['department'])) !== "LUPON"
) {
    header("Location: /BMS/CODES/login.php?error=1");
    exit();
}

$page = 'cases.php';

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

/* FETCH ONLY CASES FROM THIS SATELLITE */
$stmt = $conn->prepare("
    SELECT *
    FROM cases
    WHERE satellite_id = ?
    ORDER BY date_filed DESC
");

$stmt->bind_param("i", $satellite_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Case Management - Lupon Office</title>
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

            <?php
            $msg = $_GET['msg'] ?? '';

            $msg_map = [
                'added'   => ['type' => 'success', 'text' => 'Case added successfully.'],
                'updated' => ['type' => 'success', 'text' => 'Case updated successfully.'],
                'error'   => ['type' => 'error', 'text' => 'Something went wrong. Please try again.']
            ];
            ?>

            <?php if (isset($msg_map[$msg])): ?>
                <div class="alert alert-<?php echo $msg_map[$msg]['type']; ?>">
                    <?php echo htmlspecialchars($msg_map[$msg]['text']); ?>
                </div>
            <?php endif; ?>

            <div class="header-actions">
                <div>
                    <h2>Case Management</h2>
                    <p style="margin-top:-10px;color:#777;">
                        <?php echo htmlspecialchars($satellite_name); ?>
                    </p>
                </div>
            </div>

            <div class="table-container">

                <div class="table-header">

                    <h3>Case Records</h3>

                    <div class="table-actions">

                        <div class="table-controls">

                            <select id="statusFilter">
                                <option value="filter status">Filter Status</option>
                                <option value="Pending">Pending</option>
                                <option value="Ongoing">Ongoing</option>
                                <option value="Settled">Settled</option>
                                <option value="CFA">CFA</option>
                            </select>

                            <input
                                type="text"
                                id="caseSearch"
                                placeholder="Search records..."
                            >

                        </div>

                    </div>

                </div>

                <div class="table-wrapper">

                    <table>

                        <thead>
                            <tr>
                                <th>Case No</th>
                                <th>Complainant</th>
                                <th>Respondents</th>
                                <th>Status</th>
                                <th>Schedule</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody id="caseTableBody">

                            <?php if ($result->num_rows > 0): ?>

                                <?php while ($row = $result->fetch_assoc()): ?>

                                    <tr>

                                        <td>
                                            <?php echo htmlspecialchars($row['case_no']); ?>
                                        </td>

                                        <td>
                                            <?php echo htmlspecialchars($row['complainant_name']); ?>
                                        </td>

                                        <td>
                                            <?php echo htmlspecialchars($row['respondent_name']); ?>
                                        </td>

                                        <td>
                                            <span class="badge <?php echo strtolower(htmlspecialchars($row['status'])); ?>">
                                                <?php echo htmlspecialchars($row['status']); ?>
                                            </span>
                                        </td>

                                        <td>
                                            <?php
                                            if (
                                                !empty($row['schedule_date']) &&
                                                $row['schedule_date'] !== '0000-00-00'
                                            ) {
                                                echo date(
                                                    "Y-m-d",
                                                    strtotime($row['schedule_date'])
                                                );
                                            } else {
                                                echo "Not yet scheduled";
                                            }
                                            ?>
                                        </td>

                                        <td>

                                            <button
                                                class="btn-edit"
                                                onclick="editCase(<?php echo (int)$row['id']; ?>)"
                                            >
                                                Edit
                                            </button>

                                            <button
                                                class="btn-view"
                                                onclick="viewCase(<?php echo (int)$row['id']; ?>)"
                                            >
                                                View
                                            </button>

                                        </td>

                                    </tr>

                                <?php endwhile; ?>

                            <?php else: ?>

                                <tr>
                                    <td colspan="6" style="text-align:center;">
                                        No records found.
                                    </td>
                                </tr>

                            <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </div>

        </div>

    </div>

</div>

<script src="/BMS/Lupon_Office/assets/js/script.js"></script>

</body>
</html>