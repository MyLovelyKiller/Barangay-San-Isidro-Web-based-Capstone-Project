<?php
session_start();
include 'config.php';


/* =========================================================
   AUTHENTICATION
========================================================= */

if (!isset($_SESSION['username'])) {
    http_response_code(403);
    exit("Not logged in.");
}

$username = $_SESSION['username'];


/* =========================================================
   CSRF TOKEN CHECK
========================================================= */

$csrfToken = $_GET['csrf_token'] ?? '';

if (
    empty($csrfToken) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals(
        $_SESSION['csrf_token'],
        $csrfToken
    )
) {
    http_response_code(403);
    exit("Invalid CSRF token.");
}


/* =========================================================
   DELETE OFFICIAL
========================================================= */

if (isset($_GET['id'])) {

    $id = (int)$_GET['id'];

    if ($id <= 0) {
        exit("Invalid official ID.");
    }


    /* GET OFFICIAL INFORMATION */
    $stmt = $conn->prepare("
        SELECT name, position
        FROM officials
        WHERE official_id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        exit("Database error.");
    }

    $stmt->bind_param("i", $id);
    $stmt->execute();

    $res = $stmt->get_result();
    $data = $res->fetch_assoc();

    $stmt->close();


    /* AUDIT TRAIL */
    if ($data) {

        $desc =
            "Deleted official (" .
            $data['name'] .
            " - " .
            $data['position'] .
            ")";

        $log = $conn->prepare("
            INSERT INTO audit_trail
            (
                action,
                description,
                user
            )
            VALUES
            (
                'DELETE',
                ?,
                ?
            )
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
    }


    /* DELETE OFFICIAL */
    $delete = $conn->prepare("
        DELETE FROM officials
        WHERE official_id = ?
    ");

    if (!$delete) {
        exit("Database error.");
    }

    $delete->bind_param("i", $id);
    $delete->execute();
    $delete->close();
}


$conn->close();

header("Location: barangay_official.php");
exit();
?>