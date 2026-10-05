<?php
session_start();
include 'config.php';

/* CHECK LOGIN */
if (!isset($_SESSION['username'])) {
    header("Location: /BMS/CODES/login.php");
    exit();
}

/* CSRF TOKEN */
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];

/* GET RESIDENT ID */
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    header("Location: residents.php");
    exit();
}

/* GET RESIDENT DATA */
$stmt = $conn->prepare("SELECT * FROM residents WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();
$data = $result->fetch_assoc();

if (!$data) {
    header("Location: residents.php");
    exit();
}

/* UPDATE RESIDENT */
if (isset($_POST['update'])) {

    /* CHECK CSRF */
    $submitted_token = $_POST['csrf_token'] ?? '';

    if (
        empty($submitted_token) ||
        !isset($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $submitted_token)
    ) {
        http_response_code(403);
        die("Invalid CSRF token.");
    }

    $fullname = trim($_POST['fullname'] ?? '');
    $gender = $_POST['gender'] ?? '';
    $age = filter_input(INPUT_POST, 'age', FILTER_VALIDATE_INT);
    $civil = $_POST['civil_status'] ?? '';
    $sector = $_POST['sector'] ?? '';

    /* ALLOWED VALUES */
    $allowed_gender = ['Male', 'Female', 'Non-Binary'];
    $allowed_civil = ['Single', 'Married', 'Separated', 'Widow/ER'];
    $allowed_sector = ['None', 'Senior Citizen', 'Solo Parent', 'PWD'];

    if (
        $fullname === '' ||
        $age === false ||
        $age === null ||
        !in_array($gender, $allowed_gender, true) ||
        !in_array($civil, $allowed_civil, true) ||
        !in_array($sector, $allowed_sector, true)
    ) {
        die("Invalid resident information.");
    }

    /* UPDATE USING PREPARED STATEMENT */
    $stmt = $conn->prepare("
        UPDATE residents SET
            fullname = ?,
            gender = ?,
            age = ?,
            civil_status = ?,
            sector = ?
        WHERE id = ?
    ");

    $stmt->bind_param(
        "ssissi",
        $fullname,
        $gender,
        $age,
        $civil,
        $sector,
        $id
    );

    if ($stmt->execute()) {
        header("Location: residents.php");
        exit();
    }

    die("Failed to update resident.");
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Edit Resident</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5">
<div class="card">
<div class="card-header bg-warning">Edit Resident</div>
<div class="card-body">

<form method="POST">

<input type="hidden" name="csrf_token"
       value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">

<input type="text"
       name="fullname"
       value="<?= htmlspecialchars($data['fullname'], ENT_QUOTES, 'UTF-8') ?>"
       class="form-control mb-2"
       required>

<input type="number"
       name="age"
       value="<?= htmlspecialchars($data['age'], ENT_QUOTES, 'UTF-8') ?>"
       class="form-control mb-2"
       required>

<select name="gender" class="form-control mb-2">
<option value="Male" <?= $data['gender']=="Male"?"selected":"" ?>>Male</option>
<option value="Female" <?= $data['gender']=="Female"?"selected":"" ?>>Female</option>
<option value="Non-Binary" <?= $data['gender']=="Non-Binary"?"selected":"" ?>>Non-Binary</option>
</select>

<select name="civil_status" class="form-control mb-2">
<option value="Single" <?= $data['civil_status']=="Single"?"selected":"" ?>>Single</option>
<option value="Married" <?= $data['civil_status']=="Married"?"selected":"" ?>>Married</option>
<option value="Separated" <?= $data['civil_status']=="Separated"?"selected":"" ?>>Separated</option>
<option value="Widow/ER" <?= $data['civil_status']=="Widow/ER"?"selected":"" ?>>Widow/ER</option>
</select>

<select name="sector" class="form-control mb-3">
<option value="None" <?= $data['sector']=="None"?"selected":"" ?>>None</option>
<option value="Senior Citizen" <?= $data['sector']=="Senior Citizen"?"selected":"" ?>>Senior Citizen</option>
<option value="Solo Parent" <?= $data['sector']=="Solo Parent"?"selected":"" ?>>Solo Parent</option>
<option value="PWD" <?= $data['sector']=="PWD"?"selected":"" ?>>PWD</option>
</select>

<button type="submit" name="update" class="btn btn-success">Update</button>
<a href="residents.php" class="btn btn-secondary">Back</a>

</form>

</div>
</div>
</div>

</body>
</html>