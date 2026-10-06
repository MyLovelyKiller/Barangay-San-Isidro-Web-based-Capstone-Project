<?php

/*
 * Do not display PHP/database errors to users.
 * Detailed errors are written to the server error log.
 */
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();

require_once '../BACKEND/db_connect.php';


/* =========================================================
   1. CHECK LOGIN
   ========================================================= */

if (!isset($_SESSION['official_id'])) {
    header("Location: /BMS/CODES/login.php?error=1");
    exit();
}

$official_id = (int)$_SESSION['official_id'];

if ($official_id <= 0) {
    header("Location: /BMS/CODES/login.php?error=1");
    exit();
}


/* =========================================================
   2. DEFAULT VALUES
   ========================================================= */

$officer = null;
$user_full_name = 'Officer';

$today = date('Y-m-d');

$is_timed_in = false;
$profile_picture = '';
$satellite_id = 0;


/* =========================================================
   3. GET OFFICER INFORMATION
   VERIFY CLEARANCE DIRECTLY FROM DATABASE
   ========================================================= */

$officer_query = "
    SELECT *
    FROM officials
    WHERE official_id = ?
      AND department = 'CLEARANCE'
    LIMIT 1
";

$stmt = $conn->prepare($officer_query);

if (!$stmt) {

    error_log(
        "Clearance profile officer query prepare failed: " .
        $conn->error
    );

    http_response_code(500);
    exit("Unable to load profile.");
}

$stmt->bind_param("i", $official_id);

if (!$stmt->execute()) {

    error_log(
        "Clearance profile officer query execute failed: " .
        $stmt->error
    );

    $stmt->close();

    http_response_code(500);
    exit("Unable to load profile.");
}

$result = $stmt->get_result();

if ($result) {
    $officer = $result->fetch_assoc();
}

$stmt->close();


/* =========================================================
   4. VERIFY OFFICER EXISTS
   ========================================================= */

if (!$officer) {
    http_response_code(403);
    exit("Access denied.");
}


$user_full_name = $officer['name'] ?? 'Officer';

$satellite_id = (int)(
    $officer['satellite_id'] ?? 0
);


/* =========================================================
   5. REQUIRE ASSIGNED SATELLITE
   ========================================================= */

if ($satellite_id <= 0) {
    http_response_code(403);

    exit(
        "Your Clearance account does not have an assigned " .
        "satellite. Please contact the administrator."
    );
}


/* =========================================================
   6. PROFILE PICTURE
   ========================================================= */

if (!empty($officer['picture_profile'])) {
    $profile_picture = $officer['picture_profile'];
}


/* =========================================================
   7. ATTENDANCE ACTION
   ========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['attendance_action'])
) {


    /* =====================================================
       7A. CSRF TOKEN VALIDATION
       ===================================================== */

    $submitted_token = $_POST['csrf_token'] ?? '';

    if (
        empty($submitted_token) ||
        empty($_SESSION['csrf_token']) ||
        !hash_equals(
            $_SESSION['csrf_token'],
            $submitted_token
        )
    ) {
        http_response_code(403);
        exit("Invalid CSRF token.");
    }


    /* =====================================================
       7B. VALIDATE ATTENDANCE ACTION
       ===================================================== */

    $action = $_POST['attendance_action'];

    $allowed_actions = [
        'time_in',
        'time_out'
    ];

    if (!in_array($action, $allowed_actions, true)) {
        http_response_code(400);
        exit("Invalid attendance action.");
    }


    /* =====================================================
       7C. CURRENT DATE/TIME
       ===================================================== */

    $current_time = date('H:i:s');
    $current_datetime = date('Y-m-d H:i:s');


    /* =====================================================
       TIME IN
       ===================================================== */

    if ($action === 'time_in') {

        $check = $conn->prepare("
            SELECT id
            FROM attendance
            WHERE official_id = ?
              AND satellite_id = ?
              AND date = ?
              AND time_out IS NULL
            LIMIT 1
        ");

        if (!$check) {

            error_log(
                "Clearance attendance time-in check prepare failed: " .
                $conn->error
            );

            http_response_code(500);
            exit("Unable to process attendance.");
        }

        $check->bind_param(
            "iis",
            $official_id,
            $satellite_id,
            $today
        );

        if (!$check->execute()) {

            error_log(
                "Clearance attendance time-in check execute failed: " .
                $check->error
            );

            $check->close();

            http_response_code(500);
            exit("Unable to process attendance.");
        }

        $existing = $check
            ->get_result()
            ->fetch_assoc();

        $check->close();


        /* -------------------------------------------------
           Only create a new attendance record if there is
           no active attendance record for today.
           ------------------------------------------------- */

        if (!$existing) {

            $status = 'Present';
            $auto_timeout = 0;

            $insert = $conn->prepare("
                INSERT INTO attendance
                (
                    official_id,
                    satellite_id,
                    date,
                    time_in,
                    status,
                    is_auto_timeout,
                    created_at
                )
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            if (!$insert) {

                error_log(
                    "Clearance attendance time-in insert prepare failed: " .
                    $conn->error
                );

                http_response_code(500);
                exit("Unable to process attendance.");
            }

            $insert->bind_param(
                "iisssis",
                $official_id,
                $satellite_id,
                $today,
                $current_time,
                $status,
                $auto_timeout,
                $current_datetime
            );

            if (!$insert->execute()) {

                error_log(
                    "Clearance attendance time-in insert failed: " .
                    $insert->error
                );

                $insert->close();

                http_response_code(500);
                exit("Unable to process attendance.");
            }

            $insert->close();
        }
    }


    /* =====================================================
       TIME OUT
       ===================================================== */

    if ($action === 'time_out') {

        $fetch = $conn->prepare("
            SELECT
                id,
                time_in
            FROM attendance
            WHERE official_id = ?
              AND satellite_id = ?
              AND date = ?
              AND time_out IS NULL
            ORDER BY id DESC
            LIMIT 1
        ");

        if (!$fetch) {

            error_log(
                "Clearance attendance time-out fetch prepare failed: " .
                $conn->error
            );

            http_response_code(500);
            exit("Unable to process attendance.");
        }

        $fetch->bind_param(
            "iis",
            $official_id,
            $satellite_id,
            $today
        );

        if (!$fetch->execute()) {

            error_log(
                "Clearance attendance time-out fetch execute failed: " .
                $fetch->error
            );

            $fetch->close();

            http_response_code(500);
            exit("Unable to process attendance.");
        }

        $attendance = $fetch
            ->get_result()
            ->fetch_assoc();

        $fetch->close();


        if ($attendance) {

            $work_hours = "0.00";


            /* ---------------------------------------------
               Calculate work hours
               --------------------------------------------- */

            if (!empty($attendance['time_in'])) {

                try {

                    $start = new DateTime(
                        $attendance['time_in']
                    );

                    $end = new DateTime(
                        $current_time
                    );

                    $interval = $start->diff($end);

                    /*
                     * Preserve the existing calculation behavior.
                     */
                    $work_hours = number_format(
                        ($interval->days * 24) +
                        $interval->h +
                        ($interval->i / 60),
                        2
                    );

                } catch (Exception $e) {

                    error_log(
                        "Clearance attendance work-hour calculation failed: " .
                        $e->getMessage()
                    );

                    $work_hours = "0.00";
                }
            }


            $attendance_id = (int)$attendance['id'];


            /* ---------------------------------------------
               Update attendance
               --------------------------------------------- */

            $update = $conn->prepare("
                UPDATE attendance
                SET
                    time_out = ?,
                    work_hours = ?,
                    updated_at = ?
                WHERE id = ?
                  AND official_id = ?
                  AND satellite_id = ?
                  AND time_out IS NULL
            ");

            if (!$update) {

                error_log(
                    "Clearance attendance time-out update prepare failed: " .
                    $conn->error
                );

                http_response_code(500);
                exit("Unable to process attendance.");
            }

            $update->bind_param(
                "sssiii",
                $current_time,
                $work_hours,
                $current_datetime,
                $attendance_id,
                $official_id,
                $satellite_id
            );

            if (!$update->execute()) {

                error_log(
                    "Clearance attendance time-out update failed: " .
                    $update->error
                );

                $update->close();

                http_response_code(500);
                exit("Unable to process attendance.");
            }

            $update->close();
        }
    }


    /* =====================================================
       REDIRECT AFTER ATTENDANCE ACTION
       ===================================================== */

    $conn->close();

    header("Location: profile.php");
    exit();
}


/* =========================================================
   8. CHECK IF TIMED IN TODAY
   ========================================================= */

$status_query = "
    SELECT id
    FROM attendance
    WHERE official_id = ?
      AND satellite_id = ?
      AND date = ?
      AND time_out IS NULL
    ORDER BY id DESC
    LIMIT 1
";

$status_stmt = $conn->prepare($status_query);

if ($status_stmt) {

    $status_stmt->bind_param(
        "iis",
        $official_id,
        $satellite_id,
        $today
    );

    if ($status_stmt->execute()) {

        $row = $status_stmt
            ->get_result()
            ->fetch_assoc();

        $is_timed_in = $row ? true : false;

    } else {

        error_log(
            "Clearance attendance status query failed: " .
            $status_stmt->error
        );
    }

    $status_stmt->close();

} else {

    error_log(
        "Clearance attendance status prepare failed: " .
        $conn->error
    );
}


/* =========================================================
   9. CHECK ACTIVE SESSION
   ========================================================= */

$has_active = false;

$check_active = $conn->prepare("
    SELECT id
    FROM attendance
    WHERE official_id = ?
      AND satellite_id = ?
      AND date = ?
      AND time_out IS NULL
    LIMIT 1
");

if ($check_active) {

    $check_active->bind_param(
        "iis",
        $official_id,
        $satellite_id,
        $today
    );

    if ($check_active->execute()) {

        $has_active =
            $check_active
                ->get_result()
                ->fetch_assoc();

    } else {

        error_log(
            "Clearance active attendance query failed: " .
            $check_active->error
        );
    }

    $check_active->close();

} else {

    error_log(
        "Clearance active attendance prepare failed: " .
        $conn->error
    );
}


/* =========================================================
   10. CHECK COMPLETED SHIFT
   ========================================================= */

$has_completed = false;

$check_completed = $conn->prepare("
    SELECT id
    FROM attendance
    WHERE official_id = ?
      AND satellite_id = ?
      AND date = ?
      AND time_out IS NOT NULL
    LIMIT 1
");

if ($check_completed) {

    $check_completed->bind_param(
        "iis",
        $official_id,
        $satellite_id,
        $today
    );

    if ($check_completed->execute()) {

        $has_completed =
            $check_completed
                ->get_result()
                ->fetch_assoc();

    } else {

        error_log(
            "Clearance completed attendance query failed: " .
            $check_completed->error
        );
    }

    $check_completed->close();

} else {

    error_log(
        "Clearance completed attendance prepare failed: " .
        $conn->error
    );
}


/* =========================================================
   11. DEFINE FRONTEND ATTENDANCE STATUS
   ========================================================= */

if ($has_active) {

    $attendance_status = "time_out";

} elseif ($has_completed) {

    $attendance_status = "completed";

} else {

    $attendance_status = "time_in";
}


/* =========================================================
   12. DECRYPT ID NUMBER
   ========================================================= */

$decrypted_id = "N/A";

if (!empty($officer['id_number'])) {
    $decryptedId = bms_decrypt_profile_id((string)$officer['id_number']);
    if ($decryptedId === false) {
        error_log('Clearance profile ID could not be decrypted.');
        $decrypted_id = "Encryption Error";
    } else {
        $decrypted_id = $decryptedId;
    }
}

?>
