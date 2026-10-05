<?php
session_start();
include 'config.php';

/* Check if user is logged in */
if (!isset($_SESSION['username'])) {
    header("Location: /BMS/CODES/login.php");
    exit();
}

$month = date('m');
$year = date('Y');

$stmt = $conn->prepare("
    SELECT 
        s.fullname, 
        s.position, 
        s.daily_rate,
        COUNT(a.id) AS days_present,
        (COUNT(a.id) * s.daily_rate) AS total_salary
    FROM barangay_staff s
    LEFT JOIN attendance a 
        ON s.id = a.staff_id 
        AND MONTH(a.date) = ?
        AND YEAR(a.date) = ?
        AND a.status = 'Present'
    GROUP BY s.id, s.fullname, s.position, s.daily_rate
");

if (!$stmt) {
    die("Database error.");
}

$stmt->bind_param("ii", $month, $year);

if (!$stmt->execute()) {
    $stmt->close();
    die("Database error.");
}

$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
<title>Monthly Salary Report</title>
<link rel="stylesheet" href="style.css">
</head>
<body>

<h2>Salary Report (<?= htmlspecialchars(date('F Y'), ENT_QUOTES, 'UTF-8') ?>)</h2>

<table>
<tr>
<th>Name</th>
<th>Position</th>
<th>Daily Rate</th>
<th>Days Present</th>
<th>Total Salary</th>
</tr>

<?php while($row = $result->fetch_assoc()): ?>
<tr>
<td><?= htmlspecialchars($row['fullname'], ENT_QUOTES, 'UTF-8') ?></td>
<td><?= htmlspecialchars($row['position'], ENT_QUOTES, 'UTF-8') ?></td>
<td>₱<?= number_format((float)$row['daily_rate'], 2) ?></td>
<td><?= (int)$row['days_present'] ?></td>
<td><strong>₱<?= number_format((float)$row['total_salary'], 2) ?></strong></td>
</tr>
<?php endwhile; ?>

</table>

<?php
$stmt->close();
?>

</body>
</html>