<?php
session_start();
require_once 'config.php';
include 'session_time-out.php';

/* =========================================================
   CSRF PROTECTION
   ========================================================= */

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];

/*
   Validate CSRF token for all state-changing requests.
   This includes POST requests and the existing GET delete action.
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['delete_id'])) {

    $submitted_token = $_POST['csrf_token']
        ?? $_GET['csrf_token']
        ?? '';

    if (
        empty($submitted_token) ||
        !hash_equals($_SESSION['csrf_token'], $submitted_token)
    ) {
        http_response_code(403);
        die("Invalid CSRF token.");
    }
}


$username = $_SESSION['username'] ?? 'Unknown';

/* =========================================================
   BPSO SECURITY + SATELLITE
   ========================================================= */

$official_id = (int)($_SESSION['official_id'] ?? 0);

if ($official_id <= 0) {
    die("Unauthorized access.");
}

$officialStmt = $conn->prepare("
    SELECT department, satellite_id
    FROM officials
    WHERE official_id = ?
    LIMIT 1
");

$officialStmt->bind_param("i", $official_id);
$officialStmt->execute();

$official = $officialStmt->get_result()->fetch_assoc();
$officialStmt->close();

if (!$official || strtoupper(trim($official['department'])) !== 'BPSO') {
    die("Unauthorized access.");
}

$satellite_id = (int)$official['satellite_id'];

if ($satellite_id <= 0) {
    die("Your account is not assigned to a satellite.");
}


/* =========================================================
   GET SATELLITE NAME
   ========================================================= */

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
   1. ADD BORROWING RECORD
   ========================================================= */

if (isset($_POST['add_borrow'])) {

    $name = trim($_POST['borrower_name'] ?? '');
    $items = trim($_POST['items'] ?? '');
    $purpose = trim($_POST['purpose'] ?? '');
    $time = trim($_POST['time_borrowed'] ?? '');

    if ($name === '' || $items === '' || $purpose === '' || $time === '') {
        die("Please complete all required fields.");
    }

    $stmt = $conn->prepare("
        INSERT INTO borrowing
        (satellite_id, borrower_name, items, purpose, time_borrowed, status)
        VALUES (?, ?, ?, ?, ?, 'Borrowed')
    ");

    $stmt->bind_param(
        "issss",
        $satellite_id,
        $name,
        $items,
        $purpose,
        $time
    );

    if ($stmt->execute()) {

        /* AUDIT TRAIL */
        $desc = "Added borrowing record for $name (Items: $items) at $satellite_name";

        $log = $conn->prepare("
            INSERT INTO audit_trail
            (action, description, user)
            VALUES ('ADD', ?, ?)
        ");

        $log->bind_param("ss", $desc, $username);
        $log->execute();
        $log->close();

        $stmt->close();

        header("Location: borrowing.php?msg=added");
        exit();
    }

    $stmt->close();

    die("Failed to add borrowing record.");
}


/* =========================================================
   2. UPDATE BORROWING STATUS
   ========================================================= */

if (isset($_POST['update_status'])) {

    $id = (int)($_POST['id'] ?? 0);
    $status = trim($_POST['status'] ?? '');

    if ($id <= 0) {
        die("Invalid borrowing record.");
    }

    if (!in_array($status, ['Borrowed', 'Returned'], true)) {
        die("Invalid status.");
    }

    /*
       IMPORTANT:
       The satellite_id condition prevents an officer
       from updating another satellite's record.
    */

    $getStmt = $conn->prepare("
        SELECT borrower_name, items
        FROM borrowing
        WHERE id = ?
        AND satellite_id = ?
        LIMIT 1
    ");

    $getStmt->bind_param("ii", $id, $satellite_id);
    $getStmt->execute();

    $data = $getStmt->get_result()->fetch_assoc();
    $getStmt->close();

    if (!$data) {
        die("Record not found or you are not authorized to modify this record.");
    }


    $updateStmt = $conn->prepare("
        UPDATE borrowing
        SET status = ?
        WHERE id = ?
        AND satellite_id = ?
    ");

    $updateStmt->bind_param(
        "sii",
        $status,
        $id,
        $satellite_id
    );

    if ($updateStmt->execute()) {

        /* AUDIT TRAIL */
        $desc = "Updated borrowing status to $status for {$data['borrower_name']} (Items: {$data['items']}) at $satellite_name";

        $log = $conn->prepare("
            INSERT INTO audit_trail
            (action, description, user)
            VALUES ('UPDATE', ?, ?)
        ");

        $log->bind_param("ss", $desc, $username);
        $log->execute();
        $log->close();

        $updateStmt->close();

        header("Location: borrowing.php?msg=updated");
        exit();
    }

    $updateStmt->close();

    die("Failed to update borrowing record.");
}


/* =========================================================
   3. DELETE BORROWING RECORD
   ========================================================= */

if (isset($_GET['delete_id'])) {

    $id = (int)$_GET['delete_id'];

    if ($id <= 0) {
        die("Invalid borrowing record.");
    }

    /*
       Only retrieve records belonging to this officer's
       satellite.
    */

    $getStmt = $conn->prepare("
        SELECT borrower_name, items
        FROM borrowing
        WHERE id = ?
        AND satellite_id = ?
        LIMIT 1
    ");

    $getStmt->bind_param("ii", $id, $satellite_id);
    $getStmt->execute();

    $data = $getStmt->get_result()->fetch_assoc();
    $getStmt->close();

    if (!$data) {
        die("Record not found or you are not authorized to delete this record.");
    }


    $delStmt = $conn->prepare("
        DELETE FROM borrowing
        WHERE id = ?
        AND satellite_id = ?
    ");

    $delStmt->bind_param(
        "ii",
        $id,
        $satellite_id
    );

    if ($delStmt->execute()) {

        /* AUDIT TRAIL */
        $desc = "Deleted borrowing record of {$data['borrower_name']} (Items: {$data['items']}) at $satellite_name";

        $log = $conn->prepare("
            INSERT INTO audit_trail
            (action, description, user)
            VALUES ('DELETE', ?, ?)
        ");

        $log->bind_param("ss", $desc, $username);
        $log->execute();
        $log->close();

        $delStmt->close();

        header("Location: borrowing.php?msg=deleted");
        exit();
    }

    $delStmt->close();

    die("Failed to delete borrowing record.");
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Borrowing Records - BPSO</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <link rel="stylesheet" href="main.css">
    <link rel="stylesheet" href="sidebar.css">
    <link rel="stylesheet" href="borrowing.css">
</head>

<body>

<?php include 'sidebar.php'; ?>

<div class="main-wrapper">

    <div class="mb-4">

        <h2 class="header-title mb-1">
            BORROWING RECORDS
        </h2>

        <p class="text-muted">
            Manage equipment and item requests
        </p>

        <small class="text-muted">
            <i class="fa-solid fa-location-dot"></i>
            <?= htmlspecialchars($satellite_name) ?>
        </small>

    </div>


    <div class="table-container">

        <div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:20px;justify-content:space-between;align-items:center;">

            <div class="input-group" style="max-width:300px;">

                <span class="input-group-text bg-white border-end-0">
                    <i class="fa fa-search text-muted"></i>
                </span>

                <input
                    type="text"
                    id="searchInput"
                    class="form-control border-start-0"
                    placeholder="Search..."
                    onkeyup="searchTable()">

            </div>


            <button
                type="button"
                class="btn btn-primary"
                onclick="openBorrowModal()">

                <i class="fa fa-plus me-2"></i>
                Add Record

            </button>

        </div>


        <div class="table-responsive">

            <table
                class="table table-hover align-middle"
                id="borrowTable">

                <thead>

                    <tr>

                        <th>Borrower</th>
                        <th>Items</th>
                        <th>Date/Time</th>
                        <th>Purpose</th>
                        <th>Status</th>
                        <th>Actions</th>

                    </tr>

                </thead>

                <tbody>

                <?php

                /*
                   ONLY SHOW BORROWING RECORDS FROM
                   THE LOGGED-IN OFFICER'S SATELLITE.
                */

                $fetchStmt = $conn->prepare("
                    SELECT *
                    FROM borrowing
                    WHERE satellite_id = ?
                    ORDER BY id DESC
                ");

                $fetchStmt->bind_param("i", $satellite_id);
                $fetchStmt->execute();

                $fetch = $fetchStmt->get_result();

                while ($row = $fetch->fetch_assoc()) {

                    $statusClass =
                        ($row['status'] == 'Borrowed')
                        ? 'status-borrowed'
                        : 'status-returned';

                ?>

                    <tr>

                        <td class="fw-bold">
                            <?= htmlspecialchars($row['borrower_name']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($row['items']) ?>
                        </td>

                        <td class="small">

                            <?php

                            echo !empty($row['time_borrowed'])
                                ? date(
                                    "M d, Y h:i A",
                                    strtotime($row['time_borrowed'])
                                )
                                : '';

                            ?>

                        </td>

                        <td>
                            <?= htmlspecialchars($row['purpose']) ?>
                        </td>

                        <td>

                            <form method="POST">

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">

                                <input
                                    type="hidden"
                                    name="id"
                                    value="<?= (int)$row['id'] ?>">

                                <input
                                    type="hidden"
                                    name="update_status"
                                    value="1">

                                <select
                                    name="status"
                                    class="form-select form-select-sm <?= $statusClass ?>"
                                    onchange="this.form.submit()">

                                    <option
                                        value="Borrowed"
                                        <?= $row['status'] == 'Borrowed' ? 'selected' : '' ?>>
                                        Borrowed
                                    </option>

                                    <option
                                        value="Returned"
                                        <?= $row['status'] == 'Returned' ? 'selected' : '' ?>>
                                        Returned
                                    </option>

                                </select>

                            </form>

                        </td>

                        <td class="text-center">

                            <a
                                href="?delete_id=<?= (int)$row['id'] ?>&csrf_token=<?= urlencode($csrf_token) ?>"
                                class="btn btn-sm btn-outline-danger"
                                onclick="return confirm('Delete this record?')">

                                <i class="fa fa-trash"></i>

                            </a>

                        </td>

                    </tr>

                <?php } ?>

                </tbody>

            </table>

        </div>

    </div>

</div>


<!-- ADD RECORD MODAL -->

<div id="borrowModal" class="custom-modal">

    <div class="custom-modal-content">

        <div class="custom-modal-header">

            <h2>
                New Borrowing Record
            </h2>

            <span
                class="close-btn"
                onclick="closeBorrowModal()">
                &times;
            </span>

        </div>


        <form
            action=""
            method="POST"
            class="borrow-form">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">

            <div class="form-group">

                <label for="borrower_name">
                    Borrower Name
                </label>

                <input
                    type="text"
                    id="borrower_name"
                    name="borrower_name"
                    required>

            </div>


            <div class="form-group">

                <label for="items">
                    Items
                </label>

                <textarea
                    id="items"
                    name="items"
                    rows="4"
                    required></textarea>

            </div>


            <div class="form-group">

                <label for="purpose">
                    Purpose
                </label>

                <input
                    type="text"
                    id="purpose"
                    name="purpose"
                    required>

            </div>


            <div class="form-group">

                <label for="time_borrowed">
                    Borrow Date & Time
                </label>

                <input
                    type="datetime-local"
                    id="time_borrowed"
                    name="time_borrowed"
                    required>

            </div>


            <div class="modal-actions">

                <button
                    type="button"
                    class="btn-cancel"
                    onclick="closeBorrowModal()">

                    Cancel

                </button>


                <button
                    type="submit"
                    name="add_borrow"
                    class="btn-save">

                    Save Record

                </button>

            </div>

        </form>

    </div>

</div>


<script>

function searchTable() {

    let filter =
        document.getElementById("searchInput")
        .value
        .toUpperCase();

    let tr =
        document.getElementById("borrowTable")
        .getElementsByTagName("tr");

    for (let i = 1; i < tr.length; i++) {

        tr[i].style.display =
            tr[i]
            .textContent
            .toUpperCase()
            .indexOf(filter) > -1
            ? ""
            : "none";

    }

}


function openBorrowModal() {

    document
        .getElementById("borrowModal")
        .classList
        .add("show");

}


function closeBorrowModal() {

    document
        .getElementById("borrowModal")
        .classList
        .remove("show");

}


window.addEventListener("click", function(e) {

    const modal =
        document.getElementById("borrowModal");

    if (e.target === modal) {

        closeBorrowModal();

    }

});

</script>

</body>
</html>