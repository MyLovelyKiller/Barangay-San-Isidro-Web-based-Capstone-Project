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
*/
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
   USER / BPSO / SATELLITE SECURITY
   ========================================================= */

$username = $_SESSION['username'] ?? 'Unknown';
$official_id = (int)($_SESSION['official_id'] ?? 0);

$satellite_id = 0;
$satellite_name = '';

if ($official_id > 0) {

    $stmt = $conn->prepare("
        SELECT satellite_id
        FROM officials
        WHERE official_id = ?
          AND department = 'BPSO'
        LIMIT 1
    ");

    if (!$stmt) {
        http_response_code(500);
        die("Database error.");
    }

    $stmt->bind_param("i", $official_id);

} else {

    $stmt = $conn->prepare("
        SELECT satellite_id, official_id
        FROM officials
        WHERE username = ?
          AND department = 'BPSO'
        LIMIT 1
    ");

    if (!$stmt) {
        http_response_code(500);
        die("Database error.");
    }

    $stmt->bind_param("s", $username);
}

if (!$stmt->execute()) {
    $stmt->close();
    http_response_code(500);
    die("Database error.");
}

$officialResult = $stmt->get_result();
$official = $officialResult->fetch_assoc();
$stmt->close();

if (!$official) {
    http_response_code(403);
    die('Unauthorized access.');
}

$satellite_id = (int)$official['satellite_id'];

if ($satellite_id <= 0) {
    http_response_code(403);
    die('Your account is not assigned to a satellite. Please contact the administrator.');
}


/* =========================================================
   VERIFY SATELLITE IS ACTIVE
   ========================================================= */

$stmt = $conn->prepare("
    SELECT satellite_name
    FROM satellites
    WHERE satellite_id = ?
      AND LOWER(TRIM(status)) = 'active'
    LIMIT 1
");

if (!$stmt) {
    http_response_code(500);
    die("Database error.");
}

$stmt->bind_param("i", $satellite_id);

if (!$stmt->execute()) {
    $stmt->close();
    http_response_code(500);
    die("Database error.");
}

$satelliteResult = $stmt->get_result();
$satellite = $satelliteResult->fetch_assoc();
$stmt->close();

if (!$satellite) {
    http_response_code(403);
    die('Your assigned satellite is inactive or unavailable.');
}

$satellite_name = $satellite['satellite_name'];


/* =========================================================
   ALLOWED VEHICLE STATUSES
   ========================================================= */

$allowed_statuses = [
    'Inside',
    'Left'
];


/* =========================================================
   1. ADD LOG
   ========================================================= */

if (isset($_POST['add_log'])) {

    $plate  = trim($_POST['plate_number'] ?? '');
    $driver = trim($_POST['driver_name'] ?? '');
    $v      = trim($_POST['vehicle_type'] ?? '');
    $a      = trim($_POST['patrol_area'] ?? '');
    $r      = trim($_POST['remarks'] ?? '');
    $s      = trim($_POST['status'] ?? '');

    /* Required fields */
    if (
        $plate === '' ||
        $driver === '' ||
        $v === '' ||
        $a === ''
    ) {
        http_response_code(400);
        die("Please complete all required fields.");
    }

    /* Length validation */
    if (
        mb_strlen($plate) > 50 ||
        mb_strlen($driver) > 150 ||
        mb_strlen($v) > 100 ||
        mb_strlen($a) > 255 ||
        mb_strlen($r) > 1000
    ) {
        http_response_code(400);
        die("One or more fields exceed the allowed length.");
    }

    /* Status validation */
    if (!in_array($s, $allowed_statuses, true)) {
        http_response_code(400);
        die("Invalid vehicle status.");
    }

    $query = "
        INSERT INTO vehicle_logs
        (
            satellite_id,
            plate_number,
            driver_name,
            vehicle_type,
            patrol_area,
            remarks,
            status
        )
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ";

    $stmt = $conn->prepare($query);

    if (!$stmt) {
        http_response_code(500);
        die("Failed to prepare vehicle log.");
    }

    $stmt->bind_param(
        "issssss",
        $satellite_id,
        $plate,
        $driver,
        $v,
        $a,
        $r,
        $s
    );

    if (!$stmt->execute()) {
        $stmt->close();
        http_response_code(500);
        die("Failed to add vehicle log.");
    }

    $stmt->close();


    /* AUDIT TRAIL */

    $desc = "Added vehicle log: $plate driven by $driver ($s) at $satellite_name";

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

    header("Location: vehicle_logs.php?msg=added");
    exit();
}


/* =========================================================
   2. UPDATE LOG
   ========================================================= */

if (isset($_POST['update_log'])) {

    $id = filter_var(
        $_POST['id'] ?? null,
        FILTER_VALIDATE_INT
    );

    if ($id === false || $id <= 0) {
        http_response_code(400);
        die("Invalid vehicle log ID.");
    }

    $plate  = trim($_POST['plate_number'] ?? '');
    $driver = trim($_POST['driver_name'] ?? '');
    $v      = trim($_POST['vehicle_type'] ?? '');
    $a      = trim($_POST['patrol_area'] ?? '');
    $r      = trim($_POST['remarks'] ?? '');
    $s      = trim($_POST['status'] ?? '');

    /* Required fields */
    if (
        $plate === '' ||
        $driver === '' ||
        $v === '' ||
        $a === ''
    ) {
        http_response_code(400);
        die("Please complete all required fields.");
    }

    /* Length validation */
    if (
        mb_strlen($plate) > 50 ||
        mb_strlen($driver) > 150 ||
        mb_strlen($v) > 100 ||
        mb_strlen($a) > 255 ||
        mb_strlen($r) > 1000
    ) {
        http_response_code(400);
        die("One or more fields exceed the allowed length.");
    }

    /* Status validation */
    if (!in_array($s, $allowed_statuses, true)) {
        http_response_code(400);
        die("Invalid vehicle status.");
    }


    /* Get old data first */

    $stmt = $conn->prepare("
        SELECT
            plate_number,
            driver_name
        FROM vehicle_logs
        WHERE id = ?
          AND satellite_id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        http_response_code(500);
        die("Database error.");
    }

    $stmt->bind_param(
        "ii",
        $id,
        $satellite_id
    );

    if (!$stmt->execute()) {
        $stmt->close();
        http_response_code(500);
        die("Database error.");
    }

    $get = $stmt->get_result();
    $old = $get->fetch_assoc();
    $stmt->close();

    if (!$old) {
        header("Location: vehicle_logs.php?msg=not_found");
        exit();
    }


    /* Update */

    $stmt = $conn->prepare("
        UPDATE vehicle_logs
        SET
            plate_number = ?,
            driver_name = ?,
            vehicle_type = ?,
            patrol_area = ?,
            remarks = ?,
            status = ?
        WHERE id = ?
          AND satellite_id = ?
    ");

    if (!$stmt) {
        http_response_code(500);
        die("Failed to prepare vehicle update.");
    }

    $stmt->bind_param(
        "ssssssii",
        $plate,
        $driver,
        $v,
        $a,
        $r,
        $s,
        $id,
        $satellite_id
    );

    if (!$stmt->execute()) {
        $stmt->close();
        http_response_code(500);
        die("Failed to update vehicle log.");
    }

    $stmt->close();


    /* AUDIT TRAIL */

    $desc =
        "Updated vehicle log: {$old['plate_number']} " .
        "(Driver: {$old['driver_name']}) " .
        "→ New status: $s at $satellite_name";

    $log = $conn->prepare("
        INSERT INTO audit_trail
        (action, description, user)
        VALUES ('UPDATE', ?, ?)
    ");

    if ($log) {
        $log->bind_param(
            "ss",
            $desc,
            $username
        );

        $log->execute();
        $log->close();
    }

    header("Location: vehicle_logs.php?msg=updated");
    exit();
}


/* =========================================================
   3. DELETE LOG
   ========================================================= */

if (isset($_POST['delete_log'])) {

    $id = filter_var(
        $_POST['delete_id'] ?? null,
        FILTER_VALIDATE_INT
    );

    if ($id === false || $id <= 0) {
        http_response_code(400);
        die("Invalid vehicle log ID.");
    }


    /* Get data before delete */

    $stmt = $conn->prepare("
        SELECT
            plate_number,
            driver_name
        FROM vehicle_logs
        WHERE id = ?
          AND satellite_id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        http_response_code(500);
        die("Database error.");
    }

    $stmt->bind_param(
        "ii",
        $id,
        $satellite_id
    );

    if (!$stmt->execute()) {
        $stmt->close();
        http_response_code(500);
        die("Database error.");
    }

    $get = $stmt->get_result();
    $data = $get->fetch_assoc();
    $stmt->close();

    if (!$data) {
        header("Location: vehicle_logs.php?msg=not_found");
        exit();
    }


    /* Delete */

    $stmt = $conn->prepare("
        DELETE FROM vehicle_logs
        WHERE id = ?
          AND satellite_id = ?
    ");

    if (!$stmt) {
        http_response_code(500);
        die("Failed to prepare delete.");
    }

    $stmt->bind_param(
        "ii",
        $id,
        $satellite_id
    );

    if (!$stmt->execute()) {
        $stmt->close();
        http_response_code(500);
        die("Failed to delete vehicle log.");
    }

    $stmt->close();


    /* AUDIT TRAIL */

    $desc =
        "Deleted vehicle log: " .
        "{$data['plate_number']} driven by " .
        "{$data['driver_name']} at $satellite_name";

    $log = $conn->prepare("
        INSERT INTO audit_trail
        (action, description, user)
        VALUES ('DELETE', ?, ?)
    ");

    if ($log) {
        $log->bind_param(
            "ss",
            $desc,
            $username
        );

        $log->execute();
        $log->close();
    }

    header("Location: vehicle_logs.php?msg=deleted");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Vehicle Logs - San Isidro System</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <link rel="stylesheet" href="main.css">
    <link rel="stylesheet" href="sidebar.css">
    <link rel="stylesheet" href="vehicle.css">

</head>

<body>

<?php include 'sidebar.php'; ?>

<div class="main-wrapper">

    <div class="mb-4">

        <h2 class="text-uppercase header-title mb-1">
            Vehicle Logs
        </h2>

        <p class="text-muted">
            Manage patrol vehicles and monitoring records |
            Satellite:
            <strong>
                <?php echo htmlspecialchars($satellite_name, ENT_QUOTES, 'UTF-8'); ?>
            </strong>
        </p>

    </div>


    <div class="table-container">

        <div
            style="
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
                margin-bottom: 20px;
                justify-content: space-between;
                align-items: center;
            "
        >

            <div
                class="input-group"
                style="max-width: 300px;"
            >

                <span class="input-group-text bg-white border-end-0">

                    <i class="fa fa-search text-muted"></i>

                </span>

                <input
                    type="text"
                    id="searchInput"
                    class="form-control border-start-0"
                    placeholder="Search plate or driver..."
                    onkeyup="searchTable()"
                >

            </div>


            <button
                type="button"
                class="btn btn-primary"
                onclick="openAddModal()"
            >

                <i class="fas fa-plus-circle me-1"></i>
                New Entry

            </button>

        </div>


        <div class="table-responsive">

            <table
                class="table table-hover align-middle"
                id="vehicleTable"
            >

                <thead>

                    <tr>

                        <th>Plate Number</th>
                        <th>Driver Name</th>
                        <th>Vehicle Type</th>
                        <th>Area</th>
                        <th>Status</th>
                        <th class="text-center">Actions</th>

                    </tr>

                </thead>


                <tbody>

                <?php

                $stmt = $conn->prepare("
                    SELECT *
                    FROM vehicle_logs
                    WHERE satellite_id = ?
                    ORDER BY id DESC
                ");

                if ($stmt) {

                    $stmt->bind_param(
                        "i",
                        $satellite_id
                    );

                    if ($stmt->execute()) {

                        $fetch = $stmt->get_result();

                        while ($row = $fetch->fetch_assoc()) {

                            $statusClass =
                                ($row['status'] === 'Inside')
                                ? 'status-inside'
                                : 'status-left';

                            $rowJson = htmlspecialchars(
                                json_encode(
                                    $row,
                                    JSON_HEX_TAG |
                                    JSON_HEX_AMP |
                                    JSON_HEX_APOS |
                                    JSON_HEX_QUOT
                                ),
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>

                            <tr>

                                <td class="fw-bold text-primary">

                                    <?php
                                    echo htmlspecialchars(
                                        $row['plate_number'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                    ?>

                                </td>


                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $row['driver_name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                    ?>

                                </td>


                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $row['vehicle_type'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                    ?>

                                </td>


                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $row['patrol_area'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                    ?>

                                </td>


                                <td>

                                    <span class="<?php echo htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8'); ?>">

                                        <?php
                                        echo htmlspecialchars(
                                            $row['status'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                        ?>

                                    </span>

                                </td>


                                <td class="text-center">

                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-primary"
                                        onclick='openEditModal(<?php echo $rowJson; ?>)'
                                    >

                                        <i class="fas fa-edit"></i>

                                    </button>


                                    <!-- DELETE IS POST + CSRF -->

                                    <form
                                        method="POST"
                                        class="d-inline"
                                        onsubmit="return confirm('Delete this record?')"
                                    >

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?php echo htmlspecialchars(
                                                $csrf_token,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="delete_id"
                                            value="<?php echo (int)$row['id']; ?>"
                                        >

                                        <button
                                            type="submit"
                                            name="delete_log"
                                            class="btn btn-sm btn-outline-danger"
                                        >

                                            <i class="fas fa-trash"></i>

                                        </button>

                                    </form>

                                </td>

                            </tr>

                            <?php
                        }
                    }

                    $stmt->close();
                }

                ?>

                </tbody>

            </table>

        </div>

    </div>

</div>


<!-- =========================================================
     ADD MODAL
     ========================================================= -->

<div id="addModal" class="custom-modal">

    <div class="custom-modal-content">

        <div class="custom-modal-header">

            <h2>
                Add Vehicle Log
            </h2>

            <span
                class="close-btn"
                onclick="closeAddModal()"
            >
                &times;
            </span>

        </div>


        <form
            method="POST"
            class="custom-form"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo htmlspecialchars(
                    $csrf_token,
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>"
            >


            <div class="row">

                <div class="col-md-6">

                    <div class="form-group">

                        <label for="plate_number">
                            Plate Number
                        </label>

                        <input
                            type="text"
                            id="plate_number"
                            name="plate_number"
                            required
                            maxlength="50"
                            placeholder="ABC-123"
                        >

                    </div>

                </div>


                <div class="col-md-6">

                    <div class="form-group">

                        <label for="driver_name">
                            Driver Name
                        </label>

                        <input
                            type="text"
                            id="driver_name"
                            name="driver_name"
                            required
                            maxlength="150"
                        >

                    </div>

                </div>

            </div>


            <div class="form-group">

                <label for="vehicle_type">
                    Vehicle Type
                </label>

                <input
                    type="text"
                    id="vehicle_type"
                    name="vehicle_type"
                    required
                    maxlength="100"
                >

            </div>


            <div class="form-group">

                <label for="patrol_area">
                    Patrol Area / Destination
                </label>

                <input
                    type="text"
                    id="patrol_area"
                    name="patrol_area"
                    required
                    maxlength="255"
                >

            </div>


            <div class="form-group">

                <label for="status">
                    Status
                </label>

                <select
                    id="status"
                    name="status"
                    required
                >

                    <option value="Inside">
                        Inside
                    </option>

                    <option value="Left">
                        Left
                    </option>

                </select>

            </div>


            <div class="form-group">

                <label for="remarks">
                    Remarks
                </label>

                <textarea
                    id="remarks"
                    name="remarks"
                    rows="3"
                    maxlength="1000"
                ></textarea>

            </div>


            <div class="modal-actions">

                <button
                    type="button"
                    class="btn-cancel"
                    onclick="closeAddModal()"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    name="add_log"
                    class="btn-save"
                >
                    Save Entry
                </button>

            </div>

        </form>

    </div>

</div>


<!-- =========================================================
     EDIT MODAL
     ========================================================= -->

<div id="editModal" class="custom-modal">

    <div class="custom-modal-content">

        <div class="custom-modal-header">

            <h2>
                Edit Vehicle Log
            </h2>

            <span
                class="close-btn"
                onclick="closeEditModal()"
            >
                &times;
            </span>

        </div>


        <form
            method="POST"
            class="custom-form"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo htmlspecialchars(
                    $csrf_token,
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>"
            >


            <input
                type="hidden"
                name="id"
                id="edit_id"
            >


            <div class="row">

                <div class="col-md-6">

                    <div class="form-group">

                        <label for="edit_plate">
                            Plate Number
                        </label>

                        <input
                            type="text"
                            id="edit_plate"
                            name="plate_number"
                            required
                            maxlength="50"
                        >

                    </div>

                </div>


                <div class="col-md-6">

                    <div class="form-group">

                        <label for="edit_driver">
                            Driver Name
                        </label>

                        <input
                            type="text"
                            id="edit_driver"
                            name="driver_name"
                            required
                            maxlength="150"
                        >

                    </div>

                </div>

            </div>


            <div class="form-group">

                <label for="edit_vehicle">
                    Vehicle Type
                </label>

                <input
                    type="text"
                    id="edit_vehicle"
                    name="vehicle_type"
                    required
                    maxlength="100"
                >

            </div>


            <div class="form-group">

                <label for="edit_area">
                    Patrol Area / Destination
                </label>

                <input
                    type="text"
                    id="edit_area"
                    name="patrol_area"
                    required
                    maxlength="255"
                >

            </div>


            <div class="form-group">

                <label for="edit_status">
                    Status
                </label>

                <select
                    id="edit_status"
                    name="status"
                    required
                >

                    <option value="Inside">
                        Inside
                    </option>

                    <option value="Left">
                        Left
                    </option>

                </select>

            </div>


            <div class="form-group">

                <label for="edit_remarks">
                    Remarks
                </label>

                <textarea
                    id="edit_remarks"
                    name="remarks"
                    rows="3"
                    maxlength="1000"
                ></textarea>

            </div>


            <div class="modal-actions">

                <button
                    type="button"
                    class="btn-cancel"
                    onclick="closeEditModal()"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    name="update_log"
                    class="btn-update"
                >
                    Update Changes
                </button>

            </div>

        </form>

    </div>

</div>


<script>

function searchTable() {

    let filter =
        document
            .getElementById("searchInput")
            .value
            .toUpperCase();

    let tr =
        document
            .getElementById("vehicleTable")
            .getElementsByTagName("tr");

    for (let i = 1; i < tr.length; i++) {

        tr[i].style.display =
            tr[i]
                .innerText
                .toUpperCase()
                .indexOf(filter) > -1
                ? ""
                : "none";
    }
}


function openAddModal() {

    document
        .getElementById("addModal")
        .classList
        .add("show");
}


function closeAddModal() {

    document
        .getElementById("addModal")
        .classList
        .remove("show");
}


function openEditModal(data) {

    document.getElementById("edit_id").value =
        data.id;

    document.getElementById("edit_plate").value =
        data.plate_number;

    document.getElementById("edit_driver").value =
        data.driver_name;

    document.getElementById("edit_vehicle").value =
        data.vehicle_type;

    document.getElementById("edit_area").value =
        data.patrol_area;

    document.getElementById("edit_status").value =
        data.status;

    document.getElementById("edit_remarks").value =
        data.remarks ?? '';

    document
        .getElementById("editModal")
        .classList
        .add("show");
}


function closeEditModal() {

    document
        .getElementById("editModal")
        .classList
        .remove("show");
}


window.addEventListener("click", function(e) {

    const addModal =
        document.getElementById("addModal");

    const editModal =
        document.getElementById("editModal");

    if (e.target === addModal) {
        closeAddModal();
    }

    if (e.target === editModal) {
        closeEditModal();
    }

});

</script>


<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js">
</script>

</body>

</html>