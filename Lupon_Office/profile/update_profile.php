<?php
session_start();

if (!isset($_SESSION['official_id'])) {
    header("Location: /BMS/CODES/login.php?error=1");
    exit();
}

require_once __DIR__ . '/../includes/db_connect.php';

/* CSRF */
$csrf_token = $_POST['csrf_token'] ?? '';

if (
    empty($csrf_token) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $csrf_token)
) {
    http_response_code(403);
    exit("Invalid CSRF token.");
}

$official_id = $_SESSION['official_id'];

$name = trim($_POST['name'] ?? '');
$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$contact_number = trim($_POST['contact_number'] ?? '');
$id_number = trim($_POST['id_number'] ?? '');
$position = trim($_POST['position'] ?? '');

$current_password = $_POST['current_password'] ?? '';
$new_password = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

/* PASSWORD CHANGE */
$password_hash = null;

if ($new_password !== '') {
    $pw_stmt = $conn->prepare("SELECT password FROM officials WHERE official_id = ?");
    $pw_stmt->bind_param("i", $official_id);
    $pw_stmt->execute();
    $pw_row = $pw_stmt->get_result()->fetch_assoc();
    $pw_stmt->close();

    if (
        !$pw_row ||
        $current_password === '' ||
        !password_verify($current_password, $pw_row['password'])
    ) {
        header("Location: /BMS/Lupon_Office/profile/edit_profile.php?error=" . urlencode("Current password is incorrect."));
        exit();
    }

    if ($new_password !== $confirm_password) {
        header("Location: /BMS/Lupon_Office/profile/edit_profile.php?error=" . urlencode("New password and confirmation do not match."));
        exit();
    }

    if (strlen($new_password) < 8) {
        header("Location: /BMS/Lupon_Office/profile/edit_profile.php?error=" . urlencode("New password must be at least 8 characters."));
        exit();
    }

    $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
}

/* PROFILE PICTURE */
$picture_profile = null;
$old_filename = null;

if (isset($_FILES['picture_profile']) && $_FILES['picture_profile']['error'] === UPLOAD_ERR_OK) {

    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
    $file_extension = strtolower(pathinfo($_FILES['picture_profile']['name'], PATHINFO_EXTENSION));

    if (!in_array($file_extension, $allowed_extensions, true)) {
        header("Location: /BMS/Lupon_Office/profile/edit_profile.php?error=" . urlencode("Invalid profile picture format."));
        exit();
    }

    if ($_FILES['picture_profile']['size'] > 5 * 1024 * 1024) {
        header("Location: /BMS/Lupon_Office/profile/edit_profile.php?error=" . urlencode("Profile picture must not exceed 5MB."));
        exit();
    }

    if (@getimagesize($_FILES['picture_profile']['tmp_name']) === false) {
        header("Location: /BMS/Lupon_Office/profile/edit_profile.php?error=" . urlencode("Invalid image file."));
        exit();
    }

    /* Get old filename */
    $get_old = $conn->prepare("SELECT picture_profile FROM officials WHERE official_id = ?");
    $get_old->bind_param("i", $official_id);
    $get_old->execute();
    $old_res = $get_old->get_result()->fetch_assoc();
    $old_filename = $old_res['picture_profile'] ?? null;
    $get_old->close();

    /* Quarantine */
    $quarantine_dir = __DIR__ . '/../../UPLOADS/quarantine/';

    if (!is_dir($quarantine_dir) && !mkdir($quarantine_dir, 0755, true)) {
        header("Location: /BMS/Lupon_Office/profile/edit_profile.php?error=" . urlencode("Upload storage is unavailable."));
        exit();
    }

    $file_name = 'official_' . $official_id . '_' . bin2hex(random_bytes(8)) . '.' . $file_extension;
    $quarantine_path = $quarantine_dir . $file_name;

    if (!move_uploaded_file($_FILES['picture_profile']['tmp_name'], $quarantine_path)) {
        header("Location: /BMS/Lupon_Office/profile/edit_profile.php?error=" . urlencode("Failed to upload profile picture."));
        exit();
    }

    /* ClamAV */
    $clamScanPath = getenv('BMS_CLAMSCAN_PATH') ?: '';

    if (!is_file($clamScanPath) || !function_exists('exec')) {
        unlink($quarantine_path);
        header("Location: /BMS/Lupon_Office/profile/edit_profile.php?error=" . urlencode("File security scanner is unavailable."));
        exit();
    }

    $output = [];
    $exitCode = -1;

    exec(
        escapeshellarg($clamScanPath) .
        ' --no-summary --max-filesize=20M --max-scansize=100M ' .
        escapeshellarg($quarantine_path),
        $output,
        $exitCode
    );

    if ($exitCode !== 0) {
        unlink($quarantine_path);

        $message = $exitCode === 1
            ? "Profile picture was detected as infected."
            : "File security scan failed.";

        header("Location: /BMS/Lupon_Office/profile/edit_profile.php?error=" . urlencode($message));
        exit();
    }

    /* Move clean file to permanent storage */
    $upload_dir = __DIR__ . '/../../uploads/profile_pictures/';

    if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true)) {
        unlink($quarantine_path);
        header("Location: /BMS/Lupon_Office/profile/edit_profile.php?error=" . urlencode("Image storage is unavailable."));
        exit();
    }

    $file_path = $upload_dir . $file_name;

    if (!rename($quarantine_path, $file_path)) {
        unlink($quarantine_path);
        header("Location: /BMS/Lupon_Office/profile/edit_profile.php?error=" . urlencode("Failed to save profile picture."));
        exit();
    }

    $picture_profile = $file_name;
}

/* UPDATE */
$set_clauses = [];
$params = [];
$types = '';

$fields = [
    'name'           => $name,
    'username'       => $username,
    'email'          => $email,
    'contact_number' => $contact_number,
    'id_number'      => $id_number,
    'position'       => $position
];

foreach ($fields as $column => $value) {
    $set_clauses[] = "{$column} = ?";
    $params[] = $value;
    $types .= 's';
}

if ($picture_profile !== null) {
    $set_clauses[] = 'picture_profile = ?';
    $params[] = $picture_profile;
    $types .= 's';
}

if ($password_hash !== null) {
    $set_clauses[] = 'password = ?';
    $params[] = $password_hash;
    $types .= 's';
}

$params[] = $official_id;
$types .= 'i';

$update_query =
    "UPDATE officials SET " .
    implode(', ', $set_clauses) .
    " WHERE official_id = ?";

$stmt = $conn->prepare($update_query);
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {

    /* Delete old picture only after successful DB update */
    if (
        $picture_profile !== null &&
        !empty($old_filename)
    ) {
        $old_path = __DIR__ . '/../../uploads/profile_pictures/' . basename($old_filename);
        if (!is_file($old_path)) {
            $old_path = __DIR__ . '/../../IMAGES/' . basename($old_filename);
        }

        if (is_file($old_path) && $old_path !== ($upload_dir . $picture_profile)) {
            unlink($old_path);
        }
    }

    header("Location: /BMS/Lupon_Office/profile/profile.php?success=1");
} else {
    /* Remove newly uploaded picture if database update failed */
    if ($picture_profile !== null) {
        $new_path = __DIR__ . '/../../uploads/profile_pictures/' . basename($picture_profile);

        if (is_file($new_path)) {
            unlink($new_path);
        }
    }

    header("Location: /BMS/Lupon_Office/profile/edit_profile.php?error=" . urlencode("Error updating profile. Please try again."));
}

$stmt->close();
$conn->close();
?>