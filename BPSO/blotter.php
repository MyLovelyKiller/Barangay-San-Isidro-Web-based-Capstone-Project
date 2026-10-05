<?php

session_start();

require_once 'config.php';

include 'session_time-out.php';

date_default_timezone_set('Asia/Manila');

/* =========================================================
   CSRF PROTECTION
   ========================================================= */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];

/* USER */

$username = $_SESSION['username'] ?? 'Unknown';

/* =========================================================
   BPSO SECURITY + SATELLITE
   ========================================================= */

$official_id = (int)($_SESSION['official_id'] ?? 0);

/* If official_id is not stored in the session, use the logged-in username. */

if ($official_id > 0) {

    $officialStmt = $conn->prepare("
        SELECT official_id, department, satellite_id
        FROM officials
        WHERE official_id = ?
        LIMIT 1
    ");

    $officialStmt->bind_param("i", $official_id);

} else {

    $officialStmt = $conn->prepare("
        SELECT official_id, department, satellite_id
        FROM officials
        WHERE username = ?
        LIMIT 1
    ");

    $officialStmt->bind_param("s", $username);
}

$officialStmt->execute();

$official = $officialStmt->get_result()->fetch_assoc();

$officialStmt->close();

if (!$official || strtoupper(trim($official['department'] ?? '')) !== 'BPSO') {
    die("Unauthorized access.");
}

$official_id = (int)$official['official_id'];

$satellite_id = (int)($official['satellite_id'] ?? 0);

if ($satellite_id <= 0) {
    die("Your BPSO account is not assigned to a satellite.");
}

/* Get satellite name for display/audit purposes. */

$satelliteStmt = $conn->prepare("
    SELECT satellite_name
    FROM satellites
    WHERE satellite_id = ?
    LIMIT 1
");

$satelliteStmt->bind_param("i", $satellite_id);

$satelliteStmt->execute();

$satelliteData = $satelliteStmt->get_result()->fetch_assoc();

$satelliteStmt->close();

$satellite_name = $satelliteData['satellite_name'] ?? 'Unknown Satellite';


/* =========================================================
   CSRF VALIDATION
   ========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $submitted_token = $_POST['csrf_token'] ?? '';

    if (
        empty($submitted_token) ||
        empty($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $submitted_token)
    ) {
        http_response_code(403);
        die("Invalid CSRF token.");
    }
}


/* =========================================================
   ADD RECORD
   ========================================================= */

if (isset($_POST['submit'])) {

    $complaint = trim($_POST['complaint'] ?? '');

    $complainants = trim($_POST['complainants'] ?? '');

    $date = trim($_POST['date'] ?? '');

    $dateObj = DateTime::createFromFormat('Y-m-d', $date);

if (
    !$dateObj ||
    $dateObj->format('Y-m-d') !== $date
) {
    http_response_code(400);
    die("Invalid date.");
}

    $officer = trim($_POST['officer'] ?? '');

    $summary = trim($_POST['summary_remarks'] ?? '');

    $status = 'Pending';

    $stmt = $conn->prepare("
        INSERT INTO blotter
        (satellite_id, complaint, complainants, date, officer, summary_remarks, status)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "issssss",
        $satellite_id,
        $complaint,
        $complainants,
        $date,
        $officer,
        $summary,
        $status
    );

    if ($stmt->execute()) {

        $desc = "Added blotter record ($complainants - $complaint) at $satellite_name";

        $log = $conn->prepare("
            INSERT INTO audit_trail (action, description, user)
            VALUES ('ADD', ?, ?)
        ");

        $log->bind_param("ss", $desc, $username);

        $log->execute();

        $log->close();

        $stmt->close();

        header("Location: blotter.php");

        exit();
    }

    $stmt->close();

    die("Failed to add blotter record.");
}


/* =========================================================
   UPDATE RECORD
   ========================================================= */

if (isset($_POST['update'])) {

    $id = (int)($_POST['id'] ?? 0);

    $checkLock = $conn->prepare("
        SELECT * FROM blotter
        WHERE id = ? AND satellite_id = ?
        LIMIT 1
    ");

    $checkLock->bind_param("ii", $id, $satellite_id);

    $checkLock->execute();

    $currentData = $checkLock->get_result()->fetch_assoc();

    $checkLock->close();

    if (!$currentData) {
        die("Record not found or you are not authorized to modify this record.");
    }

    if (
        in_array(
            $currentData['status'],
            ["Resolved", "Transferred to Lupon", "Rejected"],
            true
        )
    ) {
        die("Error: This record is locked.");
    }

    $status = trim($_POST['status'] ?? 'Pending');
    
    $allowed_statuses = [
    'Pending',
    'Resolved',
    'Rejected',
    'Transferred to Lupon'
];

if (!in_array($status, $allowed_statuses, true)) {
    http_response_code(400);
    die("Invalid status.");
}

    $summary = trim($_POST['summary_remarks'] ?? '');

    $officer = trim($_POST['officer'] ?? '');

    $response = trim($_POST['response'] ?? '');

    $updateStmt = $conn->prepare("
        UPDATE blotter
        SET officer = ?, status = ?, summary_remarks = ?, response = ?
        WHERE id = ? AND satellite_id = ?
    ");

    $updateStmt->bind_param(
        "ssssii",
        $officer,
        $status,
        $summary,
        $response,
        $id,
        $satellite_id
    );

    if ($updateStmt->execute()) {

        $desc = "Updated blotter ({$currentData['complainants']} - Status: $status) at $satellite_name";

        $log = $conn->prepare("
            INSERT INTO audit_trail (action, description, user)
            VALUES ('UPDATE', ?, ?)
        ");

        $log->bind_param("ss", $desc, $username);

        $log->execute();

        $log->close();

        $updateStmt->close();

        header("Location: blotter.php?updated=1");

        exit();
    }

    $updateStmt->close();

    die("Failed to update blotter record.");
}


/* =========================================================
   RESOLVE RECORD
   ========================================================= */

if (isset($_POST['resolve'])) {

    $id = (int)($_POST['id'] ?? 0);

    $officer = trim($_POST['officer'] ?? '');

    $response = trim($_POST['response'] ?? '');

    $checkLock = $conn->prepare("
        SELECT * FROM blotter
        WHERE id = ? AND satellite_id = ?
        LIMIT 1
    ");

    $checkLock->bind_param("ii", $id, $satellite_id);

    $checkLock->execute();

    $currentData = $checkLock->get_result()->fetch_assoc();

    $checkLock->close();

    if (!$currentData) {
        die("Record not found or you are not authorized to modify this record.");
    }

    if (
        in_array(
            $currentData['status'],
            ["Resolved", "Rejected", "Transferred to Lupon"],
            true
        )
    ) {

        echo "<script>
            alert('This record is already closed and cannot be updated.');
            window.location='blotter.php';
        </script>";

        exit();
    }

    $updateStmt = $conn->prepare("
        UPDATE blotter
        SET status = 'Resolved', officer = ?, response = ?
        WHERE id = ? AND satellite_id = ?
    ");

    $updateStmt->bind_param(
        "ssii",
        $officer,
        $response,
        $id,
        $satellite_id
    );

    if ($updateStmt->execute()) {

        $desc = "Resolved blotter ({$currentData['complainants']} - {$currentData['complaint']}) at $satellite_name";

        $log = $conn->prepare("
            INSERT INTO audit_trail (action, description, user)
            VALUES ('UPDATE', ?, ?)
        ");

        $log->bind_param("ss", $desc, $username);

        $log->execute();

        $log->close();

        $updateStmt->close();

        header("Location: blotter.php?updated=1");

        exit();
    }

    $updateStmt->close();

    die("Failed to resolve blotter record.");
}


/* =========================================================
   REJECT RECORD
   ========================================================= */

if (isset($_POST['reject'])) {

    $id = (int)($_POST['id'] ?? 0);

    $officer = trim($_POST['officer'] ?? '');

    $response = trim($_POST['response'] ?? '');

    if ($response === '') {

        echo "<script>
            alert('A reason is required to reject a complaint.');
            window.location='blotter.php';
        </script>";

        exit();
    }

    $checkLock = $conn->prepare("
        SELECT * FROM blotter
        WHERE id = ? AND satellite_id = ?
        LIMIT 1
    ");

    $checkLock->bind_param("ii", $id, $satellite_id);

    $checkLock->execute();

    $currentData = $checkLock->get_result()->fetch_assoc();

    $checkLock->close();

    if (!$currentData) {
        die("Record not found or you are not authorized to modify this record.");
    }

    if (
        in_array(
            $currentData['status'],
            ["Resolved", "Rejected", "Transferred to Lupon"],
            true
        )
    ) {

        echo "<script>
            alert('This record is already closed and cannot be updated.');
            window.location='blotter.php';
        </script>";

        exit();
    }

    $updateStmt = $conn->prepare("
        UPDATE blotter
        SET status = 'Rejected', officer = ?, response = ?
        WHERE id = ? AND satellite_id = ?
    ");

    $updateStmt->bind_param(
        "ssii",
        $officer,
        $response,
        $id,
        $satellite_id
    );

    if ($updateStmt->execute()) {

        $desc = "Rejected blotter ({$currentData['complainants']} - {$currentData['complaint']}) at $satellite_name";

        $log = $conn->prepare("
            INSERT INTO audit_trail (action, description, user)
            VALUES ('UPDATE', ?, ?)
        ");

        $log->bind_param("ss", $desc, $username);

        $log->execute();

        $log->close();

        $updateStmt->close();

        header("Location: blotter.php?updated=1");

        exit();
    }

    $updateStmt->close();

    die("Failed to reject blotter record.");
}


/* =========================================================
   DELETE RECORD
   ========================================================= */

if (isset($_POST['delete'])) {

    $id = (int)($_POST['delete_id'] ?? 0);

    $checkLock = $conn->prepare("
        SELECT * FROM blotter
        WHERE id = ? AND satellite_id = ?
        LIMIT 1
    ");

    $checkLock->bind_param("ii", $id, $satellite_id);

    $checkLock->execute();

    $data = $checkLock->get_result()->fetch_assoc();

    $checkLock->close();

    if (!$data) {
        die("Record not found or you are not authorized to delete this record.");
    }

    if (
        $data['status'] === "Resolved" ||
        $data['status'] === "Transferred to Lupon"
    ) {

        echo "<script>
            alert('Locked records cannot be deleted.');
            window.location='blotter.php';
        </script>";

    } else {

        $desc = "Deleted blotter ({$data['complainants']} - {$data['complaint']}) at $satellite_name";

        $log = $conn->prepare("
            INSERT INTO audit_trail (action, description, user)
            VALUES ('DELETE', ?, ?)
        ");

        $log->bind_param("ss", $desc, $username);

        $log->execute();

        $log->close();

        $delStmt = $conn->prepare("
            DELETE FROM blotter
            WHERE id = ? AND satellite_id = ?
        ");

        $delStmt->bind_param("ii", $id, $satellite_id);

        $delStmt->execute();

        $delStmt->close();

        header("Location: blotter.php");
    }

    exit();
}


/* =========================================================
   TRANSFER TO LUPON
   ========================================================= */

if (isset($_POST['transfer'])) {

    $id = (int)($_POST['id'] ?? 0);

    /* Only retrieve a blotter record belonging to this satellite. */

    $getStmt = $conn->prepare("
        SELECT * FROM blotter
        WHERE id = ? AND satellite_id = ?
        LIMIT 1
    ");

    $getStmt->bind_param("ii", $id, $satellite_id);

    $getStmt->execute();

    $data = $getStmt->get_result()->fetch_assoc();

    $getStmt->close();

    if (!$data) {
        die("Record not found or you are not authorized to transfer this record.");
    }

    if ($data['status'] === "Resolved") {

        echo "<script>
            alert('Resolved cannot be transferred.');
            window.location='blotter.php';
        </script>";

        exit();
    }

    if ($data['status'] === "Transferred to Lupon") {

        echo "<script>
            alert('This record has already been transferred to Lupon.');
            window.location='blotter.php';
        </script>";

        exit();
    }

    $case_type = $data['complaint'];

    $complainant_name = $data['complainants'];

    $complaint_details = $data['summary_remarks'];

    $date_filed = $data['date'];

    $case_no = "Case No. " . date("Y") . "-" . $id;

    /* Check if this case already exists. */

    $checkStmt = $conn->prepare("
        SELECT case_no
        FROM cases
        WHERE case_no = ?
        LIMIT 1
    ");

    $checkStmt->bind_param("s", $case_no);

    $checkStmt->execute();

    $duplicate = $checkStmt->get_result()->fetch_assoc();

    $checkStmt->close();

    if ($duplicate) {

        $updateStmt = $conn->prepare("
            UPDATE blotter
            SET status = 'Transferred to Lupon'
            WHERE id = ? AND satellite_id = ?
        ");

        $updateStmt->bind_param("ii", $id, $satellite_id);

        $updateStmt->execute();

        $updateStmt->close();

        header("Location: blotter.php");

        exit();
    }

    /*
       IMPORTANT: satellite_id is copied to cases so the Lupon
       office can later restrict cases to the same satellite.
    */

    $insertCase = $conn->prepare("
        INSERT INTO cases
        (
            satellite_id,
            case_no,
            case_type,
            complainant_name,
            complainant_contact,
            complainant_address,
            respondent_name,
            respondent_contact,
            respondent_address,
            status,
            date_filed,
            complaint_details,
            summary_discussions
        )
        VALUES
        (
            ?, ?, ?, ?, 'N/A', 'N/A',
            'To be identified', 'N/A', 'N/A',
            'Pending', ?, ?, ''
        )
    ");

    $insertCase->bind_param(
        "isssss",
        $satellite_id,
        $case_no,
        $case_type,
        $complainant_name,
        $date_filed,
        $complaint_details
    );

    if (!$insertCase->execute()) {

        $insertCase->close();

        die(
            "Failed to transfer the blotter record to Lupon: " .
            htmlspecialchars($conn->error)
        );
    }

    $insertCase->close();

    $updateStmt = $conn->prepare("
        UPDATE blotter
        SET status = 'Transferred to Lupon'
        WHERE id = ? AND satellite_id = ?
    ");

    $updateStmt->bind_param("ii", $id, $satellite_id);

    $updateStmt->execute();

    $updateStmt->close();

    $desc = "Transferred blotter ({$data['complainants']}) to Lupon at $satellite_name";

    $log = $conn->prepare("
        INSERT INTO audit_trail (action, description, user)
        VALUES ('UPDATE', ?, ?)
    ");

    $log->bind_param("ss", $desc, $username);

    $log->execute();

    $log->close();

    header("Location: blotter.php");

    exit();
}

?>

<!DOCTYPE html>

<html>

<head>

    <title>Blotter Records</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <link rel="stylesheet" href="main.css">

</head>

<body>

    <?php include 'sidebar.php'; ?>

    <div class="main-wrapper">

        <div class="content">

            <h2 class="mb-4 fw-bold">Blotter Records</h2>

            <p class="text-muted mb-3">

                <i class="fa-solid fa-location-dot"></i>

                <?= htmlspecialchars($satellite_name) ?>

            </p>

            <div class="d-flex justify-content-between mb-3">

                <input type="text"
                    id="searchInput"
                    onkeyup="searchTable()"
                    class="form-control w-25"
                    placeholder="Search...">

                <button class="btn btn-primary"
                    data-bs-toggle="modal"
                    data-bs-target="#addModal">

                    <i class="fa fa-plus"></i> Add Record

                </button>

            </div>

            <div class="card shadow-sm">

                <table class="table table-hover" id="blotterTable">

                    <thead class="table-light">

                        <tr>

                            <th>Officer</th>

                            <th>Complaint Type</th>

                            <th>Complainants Name</th>

                            <th>Date</th>

                            <th>Status</th>

                            <th>Actions</th>

                        </tr>

                    </thead>

                    <tbody>

                        <?php

                        $listStmt = $conn->prepare("
                            SELECT *
                            FROM blotter
                            WHERE satellite_id = ?
                            ORDER BY id DESC
                        ");

                        $listStmt->bind_param("i", $satellite_id);

                        $listStmt->execute();

                        $result = $listStmt->get_result();

                        while ($row = $result->fetch_assoc()) {

                            $raw_status = trim($row['status']);

                            $check_status = strtolower($raw_status);

                            $display_status = strtoupper($raw_status);

                            if ($check_status === "resolved") {

                                $badge = "bg-success";

                            } elseif ($check_status === "transferred to lupon") {

                                $badge = "bg-primary";

                            } elseif ($check_status === "rejected") {

                                $badge = "bg-danger";

                            } else {

                                $badge = "bg-warning text-dark";

                            }

                            $isLocked = (
                                $check_status === "resolved" ||
                                $check_status === "transferred to lupon"
                            );

                        ?>

                        <tr>

                            <td>
                                <?php echo htmlspecialchars($row['officer']); ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars($row['complaint']); ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars($row['complainants']); ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars($row['date']); ?>
                            </td>

                            <td>

                                <span class="badge <?php echo $badge; ?>">

                                    <?php echo htmlspecialchars($display_status); ?>

                                </span>

                            </td>

                            <td>

                                <div class="dropdown">

                                    <button class="btn btn-light btn-sm"
                                        data-bs-toggle="dropdown">

                                        <i class="fa fa-ellipsis-v"></i>

                                    </button>

                                    <ul class="dropdown-menu">

                                        <li>

                                            <button class="dropdown-item"
                                                onclick='openManageModal(<?= htmlspecialchars(
    json_encode($row, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
    ENT_QUOTES,
    'UTF-8'
) ?>)'

                                                <i class="fa fa-file-alt me-2 text-primary"></i>

                                                View / Manage

                                            </button>

                                        </li>

                                        <li>

                                            <?php if ($isLocked): ?>

                                                <button class="dropdown-item disabled text-muted">

                                                    <i class="fa fa-ban me-2"></i>

                                                    Delete (Restricted)

                                                </button>

                                            <?php else: ?>

                                                <!-- CSRF-PROTECTED DELETE -->

                                                <form method="POST"
                                                    class="m-0"
                                                    onsubmit="return confirm('Are you sure?');">

                                                    <input type="hidden"
                                                        name="csrf_token"
                                                        value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">

                                                    <input type="hidden"
                                                        name="delete_id"
                                                        value="<?= (int)$row['id'] ?>">

                                                    <button type="submit"
                                                        name="delete"
                                                        class="dropdown-item text-danger">

                                                        <i class="fa fa-trash me-2"></i>

                                                        Delete

                                                    </button>

                                                </form>

                                            <?php endif; ?>

                                        </li>

                                    </ul>

                                </div>

                            </td>

                        </tr>

                        <?php } ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>


    <!-- =====================================================
         ADD MODAL
         ===================================================== -->

    <div class="modal fade" id="addModal">

        <div class="modal-dialog modal-lg">

            <div class="modal-content">

                <form method="POST">

                    <!-- CSRF -->

                    <input type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">

                    <div class="modal-header">

                        <h5 class="modal-title">
                            New Blotter Entry
                        </h5>

                        <button type="button"
                            class="btn-close"
                            data-bs-dismiss="modal">
                        </button>

                    </div>

                    <div class="modal-body">

                        <div class="row">

                            <div class="col-md-6 mb-3">

                                <label class="form-label fw-bold">
                                    Complaint Type
                                </label>

                                <select name="complaint"
                                    class="form-select"
                                    required>

                                    <option value="" selected disabled>
                                        -- Select Case Type --
                                    </option>

                                    <optgroup label="Crimes Against Persons">

                                        <option value="Slight Physical Injuries">
                                            Slight Physical Injuries
                                        </option>

                                        <option value="Less Serious Physical Injuries">
                                            Less Serious Physical Injuries
                                        </option>

                                        <option value="Maltreatment">
                                            Maltreatment
                                        </option>

                                    </optgroup>

                                    <optgroup label="Crimes Against Property">

                                        <option value="Theft (Minor)">
                                            Theft (Minor)
                                        </option>

                                        <option value="Swindling (Estafa)">
                                            Swindling (Estafa)
                                        </option>

                                        <option value="Malicious Mischief">
                                            Malicious Mischief
                                        </option>

                                        <option value="Boundary Dispute">
                                            Boundary Dispute
                                        </option>

                                    </optgroup>

                                    <optgroup label="Crimes Against Honor/Security">

                                        <option value="Oral Defamation (Slander)">
                                            Oral Defamation (Slander)
                                        </option>

                                        <option value="Intriguing Against Honor">
                                            Intriguing Against Honor (Gossip)
                                        </option>

                                        <option value="Light Threats">
                                            Light Threats
                                        </option>

                                        <option value="Unjust Vexation">
                                            Unjust Vexation
                                        </option>

                                    </optgroup>

                                    <optgroup label="Public Order/Ordinances">

                                        <option value="Alarms and Scandals">
                                            Alarms and Scandals
                                        </option>

                                        <option value="Curfew Violation">
                                            Curfew Violation
                                        </option>

                                        <option value="Noise Complaint">
                                            Noise Complaint
                                        </option>

                                        <option value="Traffic/Parking Obstruction">
                                            Traffic/Parking Obstruction
                                        </option>

                                    </optgroup>

                                    <optgroup label="Others">

                                        <option value="Collection of Debt">
                                            Collection of Debt
                                        </option>

                                        <option value="Family/Neighborhood Feud">
                                            Family/Neighborhood Feud
                                        </option>

                                        <option value="Other">
                                            Other (Specify in Remarks)
                                        </option>

                                    </optgroup>

                                </select>

                            </div>

                            <div class="col-md-6 mb-3">

                                <label>
                                    Complainant Name
                                </label>

                                <input type="text"
                                    name="complainants"
                                    class="form-control"
                                    required>

                            </div>

                            <div class="col-md-6 mb-3">

                                <label>
                                    Date
                                </label>

                                <input type="date"
                                    name="date"
                                    class="form-control"
                                    value="<?php echo date('Y-m-d'); ?>"
                                    required>

                            </div>

                            <div class="col-md-6 mb-3">

                                <label>
                                    Officer
                                </label>

                                <input type="text"
                                    name="officer"
                                    class="form-control">

                            </div>

                            <div class="col-md-12 mb-3">

                                <label>
                                    Complaint Details
                                </label>

                                <textarea name="summary_remarks"
                                    class="form-control"></textarea>

                            </div>

                        </div>

                    </div>

                    <div class="modal-footer">

                        <button type="button"
                            class="btn btn-secondary"
                            data-bs-dismiss="modal">

                            Close

                        </button>

                        <button class="btn btn-primary"
                            name="submit">

                            Save Record

                        </button>

                    </div>

                </form>

            </div>

        </div>

    </div>


    <!-- =====================================================
         MANAGE MODAL
         ===================================================== -->

    <div class="modal fade" id="manageModal">

        <div class="modal-dialog modal-lg">

            <div class="modal-content">

                <form method="POST" id="manageForm">

                    <!-- CSRF -->

                    <input type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">

                    <input type="hidden"
                        name="id"
                        id="viewId">

                    <div class="modal-header py-2">

                        <h5 class="modal-title">

                            Complaint Summary —

                            <span id="modalCaseRef"></span>

                        </h5>

                        <button type="button"
                            class="btn-close"
                            data-bs-dismiss="modal">
                        </button>

                    </div>

                    <ul class="nav nav-tabs px-3 pt-2"
                        id="manageTabs">

                        <li class="nav-item">

                            <button class="nav-link active manage-tab-link"
                                id="reportTabBtn"
                                data-bs-toggle="tab"
                                data-bs-target="#tabReport"
                                type="button">

                                <i class="fa fa-file-alt me-1"></i>
                                Report

                            </button>

                        </li>

                        <li class="nav-item">

                            <button class="nav-link manage-tab-link"
                                data-bs-toggle="tab"
                                data-bs-target="#tabActions"
                                type="button">

                                <i class="fa fa-gears me-1"></i>
                                Manage / Actions

                            </button>

                        </li>

                    </ul>

                    <style>

                        #manageTabs .manage-tab-link {
                            color: #333 !important;
                            background: transparent !important;
                            border: 1px solid transparent;
                            border-bottom: none;
                        }

                        #manageTabs .manage-tab-link:hover {
                            color: #0d6efd !important;
                            background: #f1f3f5 !important;
                            border-color: #dee2e6 #dee2e6 transparent;
                        }

                        #manageTabs .manage-tab-link.active {
                            color: #0d6efd !important;
                            background: #fff !important;
                            border-color: #dee2e6 #dee2e6 #fff;
                            font-weight: 600;
                        }

                    </style>

                    <div class="modal-body pt-3">

                        <div class="tab-content">

                            <!-- REPORT TAB -->

                            <div class="tab-pane fade show active"
                                id="tabReport">

                                <div id="printArea"
                                    class="border rounded p-4"
                                    style="font-family: 'Times New Roman', Times, serif; background:#fff; max-height: 52vh; overflow-y: auto;">

                                    <div class="text-center pb-2 mb-3"
                                        style="border-bottom: 2px solid #333;">

                                        <div class="text-uppercase small text-muted"
                                            style="letter-spacing:1px;">

                                            Republic of the Philippines

                                        </div>

                                        <div class="fw-bold"
                                            style="font-size: 1.1rem;">

                                            Barangay San Isidro

                                        </div>

                                        <div class="small text-muted mb-2">

                                            Office of the Barangay Public Safety Officer

                                        </div>

                                        <div class="fw-bold text-uppercase"
                                            style="font-size: 1.25rem; letter-spacing: 1px;">

                                            Blotter Report

                                        </div>

                                    </div>

                                    <div class="d-flex justify-content-between small text-muted mb-3">

                                        <span>

                                            Case Ref:

                                            <strong>
                                                BR-<span id="viewIdDisplay"></span>
                                            </strong>

                                        </span>

                                        <span>

                                            Status:

                                            <span class="badge"
                                                id="viewStatusBadge">
                                            </span>

                                        </span>

                                    </div>

                                    <div class="row gy-3 mb-3">

                                        <div class="col-6">

                                            <div class="text-muted small">
                                                Date Filed
                                            </div>

                                            <div class="fw-semibold"
                                                id="viewDate">
                                            </div>

                                        </div>

                                        <div class="col-6">

                                            <div class="text-muted small">
                                                Assigned Officer
                                            </div>

                                            <div class="fw-semibold"
                                                id="viewOfficerDisplay">
                                            </div>

                                        </div>

                                        <div class="col-12">

                                            <div class="text-muted small">
                                                Complainant/s
                                            </div>

                                            <div class="fw-semibold"
                                                id="viewComplainants">
                                            </div>

                                        </div>

                                        <div class="col-12">

                                            <div class="text-muted small">
                                                Nature of Complaint
                                            </div>

                                            <div class="fw-semibold"
                                                id="viewComplaint">
                                            </div>

                                        </div>

                                    </div>

                                    <div class="mb-3">

                                        <div class="text-muted small text-uppercase mb-1"
                                            style="letter-spacing:.5px; border-bottom:1px solid #ddd; padding-bottom:2px;">

                                            Summary of Complaint

                                        </div>

                                        <div id="viewSummaryDisplay"
                                            class="p-2 rounded"
                                            style="background:#f8f9fa; white-space: pre-wrap; min-height: 60px;">
                                        </div>

                                    </div>

                                    <div class="mb-3">

                                        <div class="text-muted small text-uppercase mb-1"
                                            style="letter-spacing:.5px; border-bottom:1px solid #ddd; padding-bottom:2px;">

                                            Resolution / Remarks

                                        </div>

                                        <div id="viewResponseDisplay"
                                            class="p-2 rounded"
                                            style="background:#f8f9fa; white-space: pre-wrap; min-height: 40px;">

                                            —

                                        </div>

                                    </div>

                                    <div class="text-end mt-4">

                                        <div style="display:inline-block; border-top:1px solid #333; padding-top:4px; min-width:220px; text-align:center;">

                                            Prepared by

                                        </div>

                                        <div class="small text-muted">

                                            Barangay Public Safety Officer

                                        </div>

                                    </div>

                                </div>

                            </div>


                            <!-- ACTIONS TAB -->

                            <div class="tab-pane fade"
                                id="tabActions">

                                <div class="alert alert-secondary d-none py-2"
                                    id="lockedNotice">

                                    <i class="fa fa-lock me-2"></i>

                                    This complaint is already closed
                                    (<span id="lockedStatusText"></span>)
                                    and can no longer be modified.

                                    You can still view, print, or email this report.

                                </div>

                                <div class="row">

                                    <div class="col-md-6 mb-3">

                                        <label class="form-label">
                                            Assign / Update Officer
                                        </label>

                                        <input type="text"
                                            name="officer"
                                            id="editOfficer"
                                            class="form-control">

                                    </div>

                                </div>

                                <div class="mb-3">

                                    <label class="form-label">

                                        Notes

                                        <span class="text-danger">
                                            (required if rejecting)
                                        </span>

                                    </label>

                                    <textarea name="response"
                                        id="editResponse"
                                        class="form-control"
                                        rows="3"
                                        placeholder="Enter resolution notes or rejection reason..."></textarea>

                                </div>

                                <hr>

                                <div class="mb-1">

                                    <label class="form-label fw-bold">

                                        <i class="fa fa-envelope me-1"></i>

                                        Send Report to Email

                                    </label>

                                    <div class="input-group">

                                        <input type="email"
                                            id="emailRecipient"
                                            class="form-control"
                                            placeholder="recipient@email.com">

                                        <button type="button"
                                            class="btn btn-outline-primary"
                                            onclick="sendBlotterEmail()">

                                            Send

                                        </button>

                                    </div>

                                    <div id="emailStatusMsg"
                                        class="small mt-1">
                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                    <div class="modal-footer py-2 flex-wrap">

                        <button type="button"
                            class="btn btn-outline-dark btn-sm"
                            onclick="printLetter()">

                            <i class="fa fa-print me-1"></i>

                            Print

                        </button>

                        <button type="submit"
                            class="btn btn-success btn-sm"
                            name="resolve"
                            id="btnResolve">

                            <i class="fa fa-check me-1"></i>

                            Resolve

                        </button>

                        <button type="submit"
                            class="btn btn-danger btn-sm"
                            name="reject"
                            id="btnReject"
                            onclick="return validateReject()">

                            <i class="fa fa-times me-1"></i>

                            Reject

                        </button>

                        <button type="submit"
                            class="btn btn-warning btn-sm"
                            name="transfer"
                            id="btnTransfer">

                            <i class="fa fa-share me-1"></i>

                            Transfer to Lupon

                        </button>

                        <button type="button"
                            class="btn btn-secondary btn-sm"
                            data-bs-dismiss="modal">

                            Close

                        </button>

                    </div>

                </form>

            </div>

        </div>

    </div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>

        /* =====================================================
           CSRF TOKEN FOR JAVASCRIPT REQUESTS
           ===================================================== */

        const CSRF_TOKEN = <?= json_encode($csrf_token) ?>;


        const LOCKED_STATUSES = [
            "Resolved",
            "Transferred to Lupon",
            "Rejected"
        ];

        const STATUS_BADGES = {

            "resolved": "bg-success",

            "transferred to lupon": "bg-primary",

            "rejected": "bg-danger",

            "pending": "bg-warning text-dark"

        };

        let currentRecord = null;


        function openManageModal(data) {

            currentRecord = data;

            const status = (data.status || 'Pending').trim();

            const isLocked = LOCKED_STATUSES.includes(status);

            document.getElementById("viewId").value = data.id;

            document.getElementById("viewIdDisplay").innerText = data.id;

            document.getElementById("modalCaseRef").innerText = "BR-" + data.id;

            document.getElementById("viewDate").innerText = data.date || '';

            document.getElementById("viewComplainants").innerText =
                data.complainants || '';

            document.getElementById("viewComplaint").innerText =
                data.complaint || '';

            document.getElementById("viewOfficerDisplay").innerText =
                data.officer || 'Pending Assignment';

            document.getElementById("viewSummaryDisplay").innerText =
                data.summary_remarks || '—';

            document.getElementById("viewResponseDisplay").innerText =
                data.response || '—';

            const badge = document.getElementById("viewStatusBadge");

            badge.innerText = status.toUpperCase();

            badge.className =
                "badge " +
                (STATUS_BADGES[status.toLowerCase()] || "bg-secondary");

            document.getElementById("editOfficer").value =
                data.officer || '';

            document.getElementById("editResponse").value =
                data.response || '';

            document.getElementById("emailRecipient").value = '';

            document.getElementById("emailStatusMsg").innerText = '';

            const lockedNotice =
                document.getElementById("lockedNotice");

            const lockedStatusText =
                document.getElementById("lockedStatusText");

            const btnResolve =
                document.getElementById("btnResolve");

            const btnReject =
                document.getElementById("btnReject");

            const btnTransfer =
                document.getElementById("btnTransfer");

            btnResolve.disabled = isLocked;

            btnReject.disabled = isLocked;

            btnTransfer.disabled = isLocked;

            if (isLocked) {

                lockedNotice.classList.remove("d-none");

                lockedStatusText.innerText = status;

            } else {

                lockedNotice.classList.add("d-none");

            }

            new bootstrap.Tab(
                document.getElementById("reportTabBtn")
            ).show();

            document.getElementById("printArea").scrollTop = 0;

            new bootstrap.Modal(
                document.getElementById("manageModal")
            ).show();

        }


        function validateReject() {

            const val =
                document.getElementById("editResponse").value.trim();

            if (val === '') {

                alert(
                    'Please provide a reason for rejection before submitting.'
                );

                document.getElementById("editResponse").focus();

                return false;
            }

            return true;

        }


        function escapeHtml(str) {

            const div = document.createElement('div');

            div.innerText = str || '';

            return div.innerHTML;

        }


        function printLetter() {

            if (!currentRecord) return;

            const status =
                (currentRecord.status || 'Pending').trim();

            const badgeClass =
                STATUS_BADGES[status.toLowerCase()] ||
                'bg-secondary';

            const generatedOn =
                new Date().toLocaleString(
                    'en-PH',
                    {
                        dateStyle: 'long',
                        timeStyle: 'short'
                    }
                );

            const html = `

                <!DOCTYPE html>

                <html>

                <head>

                <meta charset="utf-8">

                <title>
                    Print Preview - BR-${currentRecord.id}
                </title>

                <style>

                    @page {
                        size: letter;
                        margin: 0.75in;
                    }

                    * {
                        box-sizing: border-box;
                    }

                    body {
                        font-family: 'Times New Roman', Times, serif;
                        color:#111;
                        margin:0;
                        background:#e9ebee;
                    }

                    .toolbar {
                        position: sticky;
                        top: 0;
                        z-index: 10;
                        display:flex;
                        align-items:center;
                        justify-content:space-between;
                        background:#2b2f36;
                        color:#fff;
                        padding:12px 24px;
                        font-family: Arial, Helvetica, sans-serif;
                        box-shadow: 0 2px 6px rgba(0,0,0,.25);
                    }

                    .toolbar .title {
                        font-size:14px;
                        opacity:.85;
                    }

                    .toolbar .actions button {
                        font-family: Arial, Helvetica, sans-serif;
                        font-size:14px;
                        padding:8px 18px;
                        margin-left:10px;
                        border-radius:5px;
                        border:none;
                        cursor:pointer;
                    }

                    .btn-print {
                        background:#0d6efd;
                        color:#fff;
                    }

                    .btn-print:hover {
                        background:#0b5ed7;
                    }

                    .btn-close {
                        background:#495057;
                        color:#fff;
                    }

                    .btn-close:hover {
                        background:#3d4247;
                    }

                    .page-wrap {
                        padding: 32px 16px;
                        display:flex;
                        justify-content:center;
                    }

                    .page {
                        background:#fff;
                        width: 8.5in;
                        min-height: 11in;
                        padding: 0.9in 0.85in;
                        box-shadow: 0 4px 14px rgba(0,0,0,.18);
                    }

                    .header {
                        text-align:center;
                        border-bottom:2px solid #333;
                        padding-bottom:14px;
                        margin-bottom:24px;
                    }

                    .header .country {
                        text-transform:uppercase;
                        letter-spacing:1.5px;
                        font-size:13px;
                        color:#444;
                    }

                    .header .barangay {
                        font-weight:bold;
                        font-size:19px;
                        margin:3px 0;
                    }

                    .header .office {
                        font-size:13px;
                        color:#444;
                        margin-bottom:10px;
                    }

                    .header .title {
                        font-weight:bold;
                        text-transform:uppercase;
                        font-size:21px;
                        letter-spacing:1.5px;
                    }

                    .meta {
                        display:flex;
                        justify-content:space-between;
                        font-size:13px;
                        color:#555;
                        margin-bottom:22px;
                    }

                    table.fields {
                        width:100%;
                        border-collapse:collapse;
                        margin-bottom:18px;
                    }

                    table.fields td {
                        padding:7px 4px;
                        vertical-align:top;
                        font-size:14px;
                        border-bottom:1px dotted #ddd;
                    }

                    table.fields td.label {
                        width:35%;
                        color:#555;
                    }

                    .section-title {
                        font-size:13px;
                        text-transform:uppercase;
                        letter-spacing:0.5px;
                        color:#555;
                        margin-bottom:6px;
                        margin-top:20px;
                        border-bottom:1px solid #ccc;
                        padding-bottom:3px;
                    }

                    .section-box {
                        white-space:pre-wrap;
                        font-size:14px;
                        line-height:1.7;
                        padding:6px 2px 2px;
                        min-height:44px;
                    }

                    .attestation {
                        font-size:13px;
                        line-height:1.7;
                        margin-top:26px;
                        color:#333;
                    }

                    .signatures {
                        display:flex;
                        justify-content:space-between;
                        margin-top:70px;
                    }

                    .signature-block {
                        width:42%;
                        text-align:center;
                        font-size:13px;
                    }

                    .signature-block .line {
                        border-top:1px solid #333;
                        padding-top:6px;
                    }

                    .signature-block .role {
                        color:#666;
                        font-size:12px;
                        margin-top:2px;
                    }

                    .badge {
                        display:inline-block;
                        padding:3px 10px;
                        border-radius:12px;
                        font-size:12px;
                        font-weight:bold;
                        color:#fff;
                    }

                    .bg-success {
                        background:#198754;
                    }

                    .bg-primary {
                        background:#0d6efd;
                    }

                    .bg-danger {
                        background:#dc3545;
                    }

                    .bg-warning {
                        background:#ffc107;
                        color:#333;
                    }

                    .bg-secondary {
                        background:#6c757d;
                    }

                    .footer-note {
                        margin-top:40px;
                        font-size:10.5px;
                        color:#888;
                        text-align:center;
                        border-top:1px solid #eee;
                        padding-top:10px;
                        font-family: Arial, Helvetica, sans-serif;
                    }

                    @media print {

                        .toolbar {
                            display:none;
                        }

                        body {
                            background:#fff;
                        }

                        .page-wrap {
                            padding:0;
                        }

                        .page {
                            box-shadow:none;
                            width:auto;
                            min-height:0;
                            padding:0;
                        }

                    }

                </style>

                </head>

                <body>

                    <div class="toolbar">

                        <span class="title">

                            Print Preview —
                            Blotter Report
                            BR-${currentRecord.id}

                        </span>

                        <span class="actions">

                            <button
                                class="btn-close"
                                onclick="window.close()">

                                Close

                            </button>

                            <button
                                class="btn-print"
                                onclick="window.print()">

                                🖨 Print

                            </button>

                        </span>

                    </div>

                    <div class="page-wrap">

                        <div class="page">

                            <div class="header">

                                <div class="country">
                                    Republic of the Philippines
                                </div>

                                <div class="barangay">
                                    Barangay San Isidro
                                </div>

                                <div class="office">
                                    Office of the Barangay Public Safety Officer
                                </div>

                                <div class="title">
                                    Blotter Report
                                </div>

                            </div>

                            <div class="meta">

                                <span>
                                    Case Ref: BR-${currentRecord.id}
                                </span>

                                <span>
                                    Generated: ${generatedOn}
                                </span>

                            </div>

                            <table class="fields">

                                <tr>

                                    <td class="label">
                                        Date Filed
                                    </td>

                                    <td>
                                        ${escapeHtml(currentRecord.date)}
                                    </td>

                                </tr>

                                <tr>

                                    <td class="label">
                                        Complainant/s
                                    </td>

                                    <td>
                                        ${escapeHtml(currentRecord.complainants)}
                                    </td>

                                </tr>

                                <tr>

                                    <td class="label">
                                        Nature of Complaint
                                    </td>

                                    <td>
                                        ${escapeHtml(currentRecord.complaint)}
                                    </td>

                                </tr>

                                <tr>

                                    <td class="label">
                                        Assigned Officer
                                    </td>

                                    <td>
                                        ${escapeHtml(
                                            currentRecord.officer ||
                                            'Pending Assignment'
                                        )}
                                    </td>

                                </tr>

                                <tr>

                                    <td class="label">
                                        Current Status
                                    </td>

                                    <td>

                                        <span class="badge ${badgeClass}">

                                            ${status.toUpperCase()}

                                        </span>

                                    </td>

                                </tr>

                            </table>

                            <div class="section-title">
                                Summary of Complaint
                            </div>

                            <div class="section-box">

                                ${
                                    escapeHtml(
                                        currentRecord.summary_remarks
                                    ) || '—'
                                }

                            </div>

                            <div class="section-title">
                                Resolution / Remarks
                            </div>

                            <div class="section-box">

                                ${
                                    escapeHtml(
                                        currentRecord.response
                                    ) || '—'
                                }

                            </div>

                            <p class="attestation">

                                This document certifies that the above is a true and accurate record of the
                                complaint filed with the Office of the Barangay Public Safety Officer, and
                                reflects its status as of the date this report was generated.

                            </p>

                            <div class="signatures">

                                <div class="signature-block">

                                    <div class="line">
                                        &nbsp;
                                    </div>

                                    <div>
                                        Complainant's Signature
                                    </div>

                                </div>

                                <div class="signature-block">

                                    <div class="line">

                                        ${
                                            escapeHtml(
                                                currentRecord.officer
                                            ) || '&nbsp;'
                                        }

                                    </div>

                                    <div>
                                        Barangay Public Safety Officer
                                    </div>

                                </div>

                            </div>

                            <div class="footer-note">

                                This is a system-generated report from the Barangay Public Safety Officer (BPSO) records system.

                            </div>

                        </div>

                    </div>

                </body>

                </html>

            `;

            const printWin =
                window.open(
                    '',
                    '_blank',
                    'width=950,height=1000'
                );

            if (!printWin) {

                alert(
                    'Please allow pop-ups for this site to open the print preview.'
                );

                return;
            }

            printWin.document.open();

            printWin.document.write(html);

            printWin.document.close();

            printWin.focus();

        }


        function sendBlotterEmail() {

            const id =
                document.getElementById("viewId").value;

            const email =
                document.getElementById("emailRecipient").value.trim();

            const statusEl =
                document.getElementById("emailStatusMsg");

            if (
                !email ||
                !/^[^\s@]+\.[^\s@]+$/.test(email)
            ) {

                statusEl.className =
                    "small mt-1 text-danger";

                statusEl.innerText =
                    "Please enter a valid email address.";

                return;
            }

            statusEl.className =
                "small mt-1 text-muted";

            statusEl.innerText =
                "Sending...";


            fetch("send_blotter_email.php", {

                method: "POST",

                headers: {
                    "Content-Type":
                        "application/x-www-form-urlencoded"
                },

                body:
                    "csrf_token=" +
                    encodeURIComponent(CSRF_TOKEN) +

                    "&id=" +
                    encodeURIComponent(id) +

                    "&recipient_email=" +
                    encodeURIComponent(email)

            })

            .then(res => res.json())

            .then(data => {

                if (data.success) {

                    statusEl.className =
                        "small mt-1 text-success";

                    statusEl.innerText =
                        "✔ Report sent to " + email;

                } else {

                    statusEl.className =
                        "small mt-1 text-danger";

                    statusEl.innerText =
                        "✖ " +
                        (
                            data.message ||
                            "Failed to send email."
                        );

                }

            })

            .catch(() => {

                statusEl.className =
                    "small mt-1 text-danger";

                statusEl.innerText =
                    "✖ Network error. Please try again.";

            });

        }


        function searchTable() {

            let filter =
                document
                    .getElementById("searchInput")
                    .value
                    .toLowerCase();

            document
                .querySelectorAll("#blotterTable tbody tr")
                .forEach(row => {

                    row.style.display =
                        row.innerText
                            .toLowerCase()
                            .includes(filter)
                            ? ""
                            : "none";

                });

        }

    </script>

</body>

</html>