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

$page = 'cases.php'; // keeps "Case Management" highlighted in the sidebar

/* =====================================================
   SAFE GET ID + PREPARED STATEMENT
   SQL Injection Defense + SATELLITE AUTHORIZATION

   The case must belong to the satellite assigned to
   the currently logged-in Lupon official.
===================================================== */
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$case = null;

if ($id > 0) {

    $stmt = $conn->prepare("
        SELECT c.*
        FROM cases c
        INNER JOIN officials o
            ON o.satellite_id = c.satellite_id
        WHERE c.id = ?
          AND o.official_id = ?
          AND UPPER(TRIM(o.department)) = 'LUPON'
        LIMIT 1
    ");

    if ($stmt) {

        $official_id = (int) $_SESSION['official_id'];

        $stmt->bind_param(
            "ii",
            $id,
            $official_id
        );

        $stmt->execute();

        $res = $stmt->get_result();

        $case = $res->fetch_assoc();

        $stmt->close();

    } else {
        error_log(
            "View Case query preparation failed: " . $conn->error
        );
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>View Case: <?php echo $case ? htmlspecialchars($case['case_no']) : 'Not Found'; ?></title>
    <link rel="stylesheet" href="/BMS/Lupon_Office/assets/css/style.css">
</head>

<body>

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

            <?php if (!$case): ?>

                <h3 style="color:red;">Case not found.</h3>

            <?php else: ?>

            <div class="header-actions">

                <h2>
                    Case Management &gt; Case View:
                    <?php echo htmlspecialchars($case['case_no']); ?>
                </h2>

                <div class="action-buttons">

                    <button
                        type="button"
                        onclick="history.back()"
                        class="btn-cancel"
                    >
                        Back
                    </button>

                    <button
                        type="button"
                        class="btn-summons"
                        onclick="window.location.href='/BMS/Lupon_Office/calendar/calendar.php?schedule_case=<?php echo (int) $case['id']; ?>'"
                    >
                        <i class="fa fa-calendar-plus"></i>
                        Schedule Hearing
                    </button>

                    <button
                        type="button"
                        class="btn-summons"
                        onclick="window.location.href='/BMS/Lupon_Office/audit/audit_trail.php?case_id=<?php echo (int) $case['id']; ?>'"
                    >
                        <i class="fa fa-clock-rotate-left"></i>
                        Audit Trail
                    </button>

                    <button
                        type="button"
                        class="btn-summons"
                        onclick="window.open('/BMS/Lupon_Office/cases/summons.php?id=<?php echo (int) $case['id']; ?>', '_blank')"
                    >
                        Print Preview
                    </button>

                </div>

            </div>

            <div class="form-section">

                <h3>Case Overview</h3>

                <div class="row">

                    <p>
                        <strong>Case Type:</strong>
                        <?php echo htmlspecialchars($case['case_type']); ?>
                    </p>

                    <p>
                        <strong>Status:</strong>

                        <span class="badge <?php echo strtolower($case['status']); ?>">
                            <?php echo htmlspecialchars($case['status']); ?>
                        </span>
                    </p>

                    <p>
                        <strong>Date Filed:</strong>

                        <?php
                        echo !empty($case['date_filed'])
                            ? date("F j, Y", strtotime($case['date_filed']))
                            : 'N/A';
                        ?>
                    </p>

                    <p>
                        <strong>Next Hearing:</strong>

                        <?php
                        if (
                            !empty($case['schedule_date']) &&
                            $case['schedule_date'] != '0000-00-00'
                        ) {
                            echo date(
                                "F j, Y",
                                strtotime($case['schedule_date'])
                            );
                        } else {
                            echo '<span class="text-muted">Not yet scheduled</span>';
                        }
                        ?>
                    </p>

                </div>

            </div>

            <div class="two-column">

                <div class="form-section">

                    <h3>Complainant Information</h3>

                    <p>
                        <strong>Name:</strong>
                        <?php echo htmlspecialchars($case['complainant_name']); ?>
                    </p>

                    <p>
                        <strong>Contact No:</strong>
                        <?php echo htmlspecialchars($case['complainant_contact']); ?>
                    </p>

                    <p>
                        <strong>Address:</strong>
                        <?php echo htmlspecialchars($case['complainant_address']); ?>
                    </p>

                </div>

                <div class="form-section">

                    <h3>Respondent Information</h3>

                    <p>
                        <strong>Name:</strong>
                        <?php echo htmlspecialchars($case['respondent_name']); ?>
                    </p>

                    <p>
                        <strong>Contact No:</strong>
                        <?php echo htmlspecialchars($case['respondent_contact']); ?>
                    </p>

                    <p>
                        <strong>Address:</strong>
                        <?php echo htmlspecialchars($case['respondent_address']); ?>
                    </p>

                </div>

            </div>

            <div class="form-section">

                <h3>Complaint Details</h3>

                <div class="details-box">
                    <?php
                    echo nl2br(
                        htmlspecialchars($case['complaint_details'])
                    );
                    ?>
                </div>

            </div>

            <div class="form-section">

                <h3>Summary of Discussions</h3>

                <div class="details-box">
                    <?php
                    echo nl2br(
                        htmlspecialchars($case['summary_discussions'])
                    );
                    ?>
                </div>

            </div>

            <?php endif; ?>

        </div>

        <script src="/BMS/Lupon_Office/assets/js/script.js"></script>

</body>

</html>