<?php
require_once __DIR__ . '/../BACKEND/security_helpers.php';
bms_start_secure_session();
bms_send_security_headers();

include 'config.php';
include 'session_time-out.php';

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

date_default_timezone_set('Asia/Manila');

/* ===== CHECK LOGIN ===== */
if (!isset($_SESSION['username'])) {
    header("Location: /BMS/CODES/login.php");
    exit();
}

/* TODAY DATE */
$today = date("Y-m-d");

/* GET ACTIVE OFFICIALS */
$officials = [];

$q = $conn->query("
    SELECT
        o.official_id,
        o.name,
        o.position,
        o.salary,
        o.satellite_id,
        s.satellite_name
    FROM officials o
    LEFT JOIN satellites s
        ON o.satellite_id = s.satellite_id
    WHERE o.status = 'Active'
    ORDER BY o.name ASC
");

if ($q) {
    while ($row = $q->fetch_assoc()) {
        $officials[] = $row;
    }
}

/* FUNCTION TO GET ATTENDANCE */
function getAttendance($conn, $date, $official_id = null) {

    $query = "
        SELECT
            a.*,
            o.name,
            o.position,
            o.salary,
            o.satellite_id,
            s.satellite_name
        FROM attendance a
        INNER JOIN officials o
            ON a.official_id = o.official_id
        LEFT JOIN satellites s
            ON o.satellite_id = s.satellite_id
        WHERE a.date = ?
    ";

    $types = "s";
    $params = [$date];

    if ($official_id !== null && $official_id > 0) {
        $query .= " AND a.official_id = ?";
        $types .= "i";
        $params[] = $official_id;
    }

    $query .= " ORDER BY a.time_in DESC";

    $stmt = $conn->prepare($query);

    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param($types, ...$params);
    $stmt->execute();

    return $stmt->get_result();
}

/* FUNCTION TO GET MONTHLY SUMMARY */
function getMonthlySummary($conn, $month, $year) {

    $summary = [];

    $query = "
        SELECT
            o.official_id,
            o.name,
            o.position,
            o.salary,
            o.satellite_id,
            s.satellite_name,

            COALESCE(
                SUM(
                    TIMESTAMPDIFF(
                        HOUR,
                        a.time_in,
                        IFNULL(a.time_out, NOW())
                    )
                ),
                0
            ) AS total_hours,

            COUNT(a.time_in) AS days_present

        FROM officials o

        LEFT JOIN attendance a
            ON a.official_id = o.official_id
            AND MONTH(a.date) = ?
            AND YEAR(a.date) = ?

        LEFT JOIN satellites s
            ON o.satellite_id = s.satellite_id

        WHERE o.status = 'Active'

        GROUP BY
            o.official_id,
            o.name,
            o.position,
            o.salary,
            o.satellite_id,
            s.satellite_name

        ORDER BY o.name ASC
    ";

    $stmt = $conn->prepare($query);

    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("ii", $month, $year);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {

        $row['total_salary'] =
            ((int)$row['days_present'] * (float)$row['salary']);

        $summary[] = $row;
    }

    $stmt->close();

    return $summary;
}

/* TODAY ATTENDANCE */
$todayResult = getAttendance($conn, $today);

/* HISTORY FILTER */
$filter_date = $_GET['filter_date'] ?? $today;

/* ===== VALIDATE DATE ===== */
$dateObject = DateTime::createFromFormat('Y-m-d', $filter_date);

if (
    !$dateObject ||
    $dateObject->format('Y-m-d') !== $filter_date
) {
    $filter_date = $today;
}

/* ===== VALIDATE OFFICIAL FILTER ===== */
$filter_official =
    isset($_GET['filter_official']) &&
    ctype_digit((string)$_GET['filter_official']) &&
    (int)$_GET['filter_official'] > 0
        ? intval($_GET['filter_official'])
        : null;

/* Make sure selected official exists in active officials */
if ($filter_official !== null) {

    $officialExists = false;

    foreach ($officials as $official) {
        if ((int)$official['official_id'] === $filter_official) {
            $officialExists = true;
            break;
        }
    }

    if (!$officialExists) {
        $filter_official = null;
    }
}

$historyResult =
    getAttendance(
        $conn,
        $filter_date,
        $filter_official
    );

/* MONTHLY SUMMARY */
$month = date('m', strtotime($filter_date));
$year = date('Y', strtotime($filter_date));

$monthlySummary =
    getMonthlySummary(
        $conn,
        $month,
        $year
    );
?>

<!DOCTYPE html>
<html>
<head>

    <title>Attendance</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <link rel="stylesheet" href="style/main.css">
    <link rel="stylesheet" href="style/sidebar.css">
    <link rel="stylesheet" href="style/header.css">
    <link rel="stylesheet" href="style/attendance.css">

    <style>
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        th,
        td {
            border: 1px solid #ccc;
            padding: 8px;
            text-align: center;
        }

        th {
            background-color: #3f51b5;
            color: #fff;
        }

        tr.present {
            background-color: #d4edda;
        }

        tr.absent {
            background-color: #f8d7da;
        }

        form.filter {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            align-items: center;
        }
    </style>

</head>

<body>

<?php include "sidebar.php"; ?>

<div class="main-wrapper">

    <?php include "header.php"; ?>

    <div class="content">

        <h2>
            <i class="fa fa-user-check"></i> Attendance Dashboard
        </h2>

        <!-- TODAY ATTENDANCE -->

        <h3>Today's Attendance</h3>

        <table>

            <tr>
                <th>Name</th>
                <th>Satellite</th>
                <th>Position</th>
                <th>Time In</th>
                <th>Time Out</th>
                <th>Hours</th>
                <th>Status</th>
                <th>Salary</th>
            </tr>

            <?php while($row = $todayResult->fetch_assoc()):

                // Initialize hours as Pending
                $hours = "Pending";

                // Only proceed with calculation if both values exist and are not null
                if (!empty($row['time_in']) && !empty($row['time_out'])) {

                    $timeIn = strtotime($row['time_in']);
                    $timeOut = strtotime($row['time_out']);

                    $hours = round(($timeOut - $timeIn) / 3600, 2);
                }

            ?>

            <tr class="<?php echo ($row['status'] === 'Present') ? 'present' : 'absent'; ?>">

                <td>
                    <?php echo htmlspecialchars($row['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                </td>

                <td>
                    <?php echo htmlspecialchars($row['satellite_name'] ?? 'Unassigned', ENT_QUOTES, 'UTF-8'); ?>
                </td>

                <td>
                    <?php echo htmlspecialchars($row['position'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?>
                </td>

                <td>
                    <?php echo htmlspecialchars($row['time_in'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                </td>

                <td>
                    <?php echo htmlspecialchars($row['time_out'] ?? 'Pending', ENT_QUOTES, 'UTF-8'); ?>
                </td>

                <td>
                    <?php echo htmlspecialchars((string)$hours, ENT_QUOTES, 'UTF-8'); ?>
                </td>

                <td>
                    <?php echo htmlspecialchars($row['status'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                </td>

                <td>
                    <?php echo number_format((float)$row['salary'], 2); ?>
                </td>

            </tr>

            <?php endwhile; ?>

        </table>

        <!-- HISTORY FILTER -->

        <h3>Attendance History</h3>

        <form method="GET" class="filter">

            <label>
                Date:
                <input
                    type="date"
                    name="filter_date"
                    value="<?php echo htmlspecialchars($filter_date, ENT_QUOTES, 'UTF-8'); ?>"
                >
            </label>

            <label>
                Official:

                <select name="filter_official">

                    <option value="">All Officials</option>

                    <?php foreach($officials as $o): ?>

                        <option
                            value="<?php echo (int)$o['official_id']; ?>"
                            <?php echo ($filter_official == $o['official_id']) ? 'selected' : ''; ?>
                        >
                            <?php echo htmlspecialchars($o['name'], ENT_QUOTES, 'UTF-8'); ?>
                            -
                            <?php echo htmlspecialchars($o['satellite_name'] ?? 'Unassigned', ENT_QUOTES, 'UTF-8'); ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </label>

            <button type="submit">
                <i class="fa fa-search"></i> Search
            </button>

        </form>

        <!-- HISTORY TABLE -->

        <table>

            <tr>
                <th>Date</th>
                <th>Name</th>
                <th>Satellite</th>
                <th>Position</th>
                <th>Time In</th>
                <th>Time Out</th>
                <th>Hours</th>
                <th>Status</th>
                <th>Salary</th>
            </tr>

            <?php while($row = $historyResult->fetch_assoc()):

                // Check if both times exist before converting to timestamp
                $timeIn = !empty($row['time_in'])
                    ? strtotime($row['time_in'])
                    : null;

                $timeOut = !empty($row['time_out'])
                    ? strtotime($row['time_out'])
                    : null;

                $hours =
                    ($timeOut && $timeIn)
                        ? round(($timeOut - $timeIn) / 3600, 2)
                        : 0;

            ?>

            <tr class="<?php echo ($row['status'] === 'Present') ? 'present' : 'absent'; ?>">

                <td>
                    <?php echo htmlspecialchars($row['date'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                </td>

                <td>
                    <?php echo htmlspecialchars($row['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                </td>

                <td>
                    <?php echo htmlspecialchars($row['satellite_name'] ?? 'Unassigned', ENT_QUOTES, 'UTF-8'); ?>
                </td>

                <td>
                    <?php echo htmlspecialchars($row['position'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?>
                </td>

                <td>
                    <?php echo htmlspecialchars($row['time_in'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                </td>

                <td>
                    <?php echo htmlspecialchars($row['time_out'] ?? 'Pending', ENT_QUOTES, 'UTF-8'); ?>
                </td>

                <td>
                    <?php echo htmlspecialchars((string)$hours, ENT_QUOTES, 'UTF-8'); ?>
                </td>

                <td>
                    <?php echo htmlspecialchars($row['status'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                </td>

                <td>
                    <?php echo number_format((float)$row['salary'], 2); ?>
                </td>

            </tr>

            <?php endwhile; ?>

        </table>

        <!-- MONTHLY SUMMARY -->

        <h3>
            Monthly Summary
            (<?php echo htmlspecialchars(date('F Y', strtotime($filter_date)), ENT_QUOTES, 'UTF-8'); ?>)
        </h3>

        <table>

            <tr>
                <th>Name</th>
                <th>Satellite</th>
                <th>Position</th>
                <th>Total Hours</th>
                <th>Days Present</th>
                <th>Total Salary</th>
            </tr>

            <?php foreach($monthlySummary as $sum): ?>

            <tr>

                <td>
                    <?php echo htmlspecialchars($sum['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                </td>

                <td>
                    <?php echo htmlspecialchars($sum['satellite_name'] ?? 'Unassigned', ENT_QUOTES, 'UTF-8'); ?>
                </td>

                <td>
                    <?php echo htmlspecialchars($sum['position'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?>
                </td>

                <td>
                    <?php echo htmlspecialchars((string)($sum['total_hours'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>
                </td>

                <td>
                    <?php echo (int)($sum['days_present'] ?? 0); ?>
                </td>

                <td>
                    <?php echo number_format((float)$sum['total_salary'], 2); ?>
                </td>

            </tr>

            <?php endforeach; ?>

        </table>

    </div>

</div>

</body>
</html>
