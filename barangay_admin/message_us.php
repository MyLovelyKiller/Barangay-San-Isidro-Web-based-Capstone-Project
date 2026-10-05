<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'config.php';
include 'session_time-out.php';
date_default_timezone_set('Asia/Manila');

/* ===== CSRF TOKEN ===== */
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];

/* USER */
$username = $_SESSION['username'] ?? 'Unknown';

/* ----------------------------
   NOTIFICATIONS
---------------------------- */
$messages_notif = [];
$notif_count = 0;

if ($conn) {
    $notif_stmt = $conn->prepare("
        SELECT *
        FROM messages
        WHERE is_read = 0
        ORDER BY created_at DESC
    ");

    if ($notif_stmt) {
        $notif_stmt->execute();
        $notif_result = $notif_stmt->get_result();

        if ($notif_result) {
            $messages_notif = $notif_result->fetch_all(MYSQLI_ASSOC);
            $notif_count = count($messages_notif);
        }
    }
}

/* ----------------------------
   HANDLE MARK AS READ
---------------------------- */
if (isset($_GET['read'])) {

    /* CHECK CSRF */
    $submitted_token = $_GET['csrf_token'] ?? '';

    if (
        empty($submitted_token) ||
        !isset($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $submitted_token)
    ) {
        http_response_code(403);
        die("Invalid CSRF token.");
    }

    $id = filter_input(INPUT_GET, 'read', FILTER_VALIDATE_INT);

    if (!$id) {
        http_response_code(400);
        die("Invalid message ID.");
    }

    /* Get message info BEFORE update */
    $res = $conn->prepare("
        SELECT name
        FROM messages
        WHERE id = ?
        LIMIT 1
    ");

    $res->bind_param("i", $id);
    $res->execute();
    $result = $res->get_result();
    $data = $result->fetch_assoc();

    if ($data) {

        $update = $conn->prepare("
            UPDATE messages
            SET is_read = 1
            WHERE id = ?
        ");

        $update->bind_param("i", $id);
        $update->execute();

        /* AUDIT LOG */
        $desc = "Marked message as READ from {$data['name']}";
        $log = $conn->prepare("
            INSERT INTO audit_trail (action, description, user)
            VALUES ('UPDATE', ?, ?)
        ");
        $log->bind_param("ss", $desc, $username);
        $log->execute();
    }

    header("Location: message_us.php");
    exit();
}

/* ----------------------------
   HANDLE DELETE
---------------------------- */
if (isset($_GET['delete'])) {

    /* CHECK CSRF */
    $submitted_token = $_GET['csrf_token'] ?? '';

    if (
        empty($submitted_token) ||
        !isset($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $submitted_token)
    ) {
        http_response_code(403);
        die("Invalid CSRF token.");
    }

    $id = filter_input(INPUT_GET, 'delete', FILTER_VALIDATE_INT);

    if (!$id) {
        http_response_code(400);
        die("Invalid message ID.");
    }

    /* Get message info BEFORE delete */
    $res = $conn->prepare("
        SELECT name
        FROM messages
        WHERE id = ?
        LIMIT 1
    ");

    $res->bind_param("i", $id);
    $res->execute();
    $result = $res->get_result();
    $data = $result->fetch_assoc();

    if ($data) {

        /* AUDIT LOG */
        $desc = "Deleted message from {$data['name']}";
        $log = $conn->prepare("
            INSERT INTO audit_trail (action, description, user)
            VALUES ('DELETE', ?, ?)
        ");
        $log->bind_param("ss", $desc, $username);
        $log->execute();

        /* DELETE MESSAGE */
        $delete_stmt = $conn->prepare("
            DELETE FROM messages
            WHERE id = ?
        ");

        $delete_stmt->bind_param("i", $id);
        $delete_stmt->execute();
    }

    header("Location: message_us.php");
    exit();
}

/* ----------------------------
   FETCH ALL MESSAGES
---------------------------- */
$messages = [];

if ($conn) {

    $result = $conn->query("
        SELECT *
        FROM messages
        ORDER BY created_at DESC
    ");

    if ($result) {
        $messages = $result->fetch_all(MYSQLI_ASSOC);
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Message Us - Admin</title>

<link rel="stylesheet" href="style/main.css">
<link rel="stylesheet" href="style/header.css">
<link rel="stylesheet" href="style/sidebar.css">
<link rel="stylesheet" href="style/message_us.css">

<link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

</head>

<body>

<?php include "sidebar.php"; ?>

<div class="main-wrapper">

<?php include "header.php"; ?> 

<div class="content">

    <div class="page-title">Message Us</div>

    <div class="message-container">

        <?php if(!empty($messages)): ?>

            <table class="message-table">

                <thead>

                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Contact</th>
                        <th>Message</th>
                        <th>Date Sent</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>

                </thead>

                <tbody>

                    <?php foreach($messages as $row): ?>

                        <tr class="<?php echo ($row['is_read'] == 0) ? 'unread' : ''; ?>">

                            <td>
                                <?php echo htmlspecialchars(
                                    $row['name'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars(
                                    $row['email'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars(
                                    $row['contact'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>
                            </td>

                            <td style="max-width:300px; white-space:normal;">

                                <?php
                                echo nl2br(
                                    htmlspecialchars(
                                        $row['message'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    )
                                );
                                ?>

                            </td>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    date(
                                        "M d, Y h:i A",
                                        strtotime($row['created_at'])
                                    ),
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                                ?>
                            </td>

                            <td>

                                <?php if($row['is_read'] == 0): ?>

                                    <span class="badge-unread">
                                        Unread
                                    </span>

                                <?php else: ?>

                                    <span class="badge-read">
                                        Read
                                    </span>

                                <?php endif; ?>

                            </td>

                            <td>

                                <?php if($row['is_read'] == 0): ?>

                                    <a class="btn btn-read"
                                       href="message_us.php?read=<?= (int)$row['id'] ?>&csrf_token=<?= urlencode($csrf_token) ?>">
                                        Mark Read
                                    </a>

                                <?php endif; ?>

                                <a class="btn btn-delete"
                                   href="message_us.php?delete=<?= (int)$row['id'] ?>&csrf_token=<?= urlencode($csrf_token) ?>"
                                   onclick="return confirm('Delete this message?')">
                                    Delete
                                </a>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        <?php else: ?>

            <div class="no-data">
                No messages found.
            </div>

        <?php endif; ?>

    </div>

</div>

</div>

</body>

</html>