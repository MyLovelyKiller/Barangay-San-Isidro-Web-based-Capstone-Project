<?php
/* SQL Injection Defense: Finalized Update Protocol (merged from update_case.php / cancel_edit_case.php) */
ob_start();
session_start();
include(__DIR__ . '/../includes/db_connect.php');
include(__DIR__ . '/../includes/session_time-out.php');
require_once(__DIR__ . '/../includes/case_history_helpers.php');
require_once(__DIR__ . '/../includes/calendar_helpers.php');

date_default_timezone_set('Asia/Manila');

/* =========================================================
   CSRF TOKEN
========================================================= */

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];

/* ACCESS CONTROL */
if (
    !isset($_SESSION['official_id']) ||
    !isset($_SESSION['department']) ||
    strtoupper(trim($_SESSION['department'])) !== "LUPON"
) {
    header("Location: /BMS/CODES/login.php?error=1");
    exit();
}

$username = $_SESSION['username'] ?? 'Unknown';
$official_id = (int) $_SESSION['official_id'];
$page = 'cases.php';

/* =====================================================
   GET LOGGED-IN LUPON OFFICER'S SATELLITE
===================================================== */
$official_stmt = $conn->prepare("
    SELECT o.satellite_id, s.satellite_name
    FROM officials o
    LEFT JOIN satellites s ON s.satellite_id = o.satellite_id
    WHERE o.official_id = ?
      AND UPPER(TRIM(o.department)) = 'LUPON'
    LIMIT 1
");

if (!$official_stmt) {
    error_log("Official lookup prepare failed: " . $conn->error);
    die("Unable to verify your satellite assignment.");
}

$official_stmt->bind_param("i", $official_id);
$official_stmt->execute();
$official_data = $official_stmt->get_result()->fetch_assoc();
$official_stmt->close();

if (!$official_data || empty($official_data['satellite_id'])) {
    die("Your Lupon account is not assigned to a satellite.");
}

$satellite_id = (int) $official_data['satellite_id'];
$satellite_name = $official_data['satellite_name'] ?? 'Unknown Satellite';

/* SAFE GET ID — POST (Update Record) takes priority, then GET (view/cancel) */
$id = isset($_POST['id']) ? intval($_POST['id']) : (isset($_GET['id']) ? intval($_GET['id']) : 0);

/* =====================================================
   BRANCH 1 — CANCEL
   Reached via "edit_case.php?id=X&cancel=1".
   Only reverts the Ongoing status if THIS officer's
   session caused the auto-transition and the case
   belongs to THIS officer's satellite.
===================================================== */
if (isset($_GET['cancel']) && $id > 0) {

    /* =====================================================
       CSRF VALIDATION FOR CANCEL ACTION
    ===================================================== */

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

    if (isset($_SESSION['auto_ongoing_cases'][$id])) {

        $revert = $conn->prepare("
            UPDATE cases
            SET status = 'Pending'
            WHERE id = ?
              AND satellite_id = ?
              AND status = 'Ongoing'
        ");

        $revert->bind_param("ii", $id, $satellite_id);
        $revert->execute();

        if ($revert->affected_rows > 0) {
            case_history_log_field(
                $conn,
                $id,
                'status',
                'Ongoing',
                'Pending',
                $username
            );

            $desc = "Case auto-reverted from Ongoing back to Pending (edit cancelled by {$username})";

            $log = $conn->prepare("
                INSERT INTO audit_trail (action, description, user)
                VALUES ('UPDATE', ?, ?)
            ");

            $log->bind_param("ss", $desc, $username);
            $log->execute();
            $log->close();
        }

        $revert->close();

        unset($_SESSION['auto_ongoing_cases'][$id]);
    }

    header("Location: /BMS/Lupon_Office/cases/cases.php");
    exit();
}

/* =====================================================
   BRANCH 2 — UPDATE (form POST)
===================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_btn'])) {

    /* =====================================================
       CSRF VALIDATION FOR UPDATE
    ===================================================== */

    $csrfToken = $_POST['csrf_token'] ?? '';

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

    $case_type = $_POST['case_type'] ?? '';
    $status = $_POST['status'] ?? 'Pending';

    /* Only allow valid status values */
    $allowed_statuses = ['Pending', 'Ongoing', 'Settled', 'CFA'];

    if (!in_array($status, $allowed_statuses, true)) {
        $status = 'Pending';
    }

    $date_filed = $_POST['date_filed'] ?? '';
    $schedule = $_POST['schedule'] ?? '';

    /* Complainant Details */
    $c_name = $_POST['c_name'] ?? '';
    $c_contact = $_POST['c_contact'] ?? '';
    $c_address = $_POST['c_address'] ?? '';

    /* Respondent Details */
    $r_name = $_POST['r_name'] ?? '';
    $r_contact = $_POST['r_contact'] ?? '';
    $r_address = $_POST['r_address'] ?? '';

    /* Complaint Details / Summary */
    $details_new_entry = trim($_POST['details_new'] ?? '');
    $details_correct_mode = isset($_POST['details_correct_mode']);
    $details_full_edit = $_POST['details_full'] ?? '';

    $summary_new_entry = trim($_POST['summary_discussions_new'] ?? '');
    $summary_correct_mode = isset($_POST['summary_discussions_correct_mode']);
    $summary_full_edit = $_POST['summary_discussions_full'] ?? '';

    /* =====================================================
       FETCH CURRENT CASE
       IMPORTANT: ID + SATELLITE
    ===================================================== */
    $old_row = null;

    $old_stmt = $conn->prepare("
        SELECT *
        FROM cases
        WHERE id = ?
          AND satellite_id = ?
        LIMIT 1
    ");

    $old_stmt->bind_param("ii", $id, $satellite_id);
    $old_stmt->execute();

    $old_row = $old_stmt->get_result()->fetch_assoc();
    $old_stmt->close();

    /* Case does not belong to this satellite */
    if (!$old_row) {
        header("Location: /BMS/Lupon_Office/cases/cases.php?msg=unauthorized");
        exit();
    }

    /* =====================================================
       COMPLAINT DETAILS
    ===================================================== */
    if ($details_correct_mode) {
        $details = case_append_entry(
            trim($details_full_edit),
            $details_new_entry,
            $username
        );
    } else {
        $details = case_append_entry(
            $old_row['complaint_details'] ?? '',
            $details_new_entry,
            $username
        );
    }

    /* =====================================================
       SUMMARY
    ===================================================== */
    if ($summary_correct_mode) {
        $summary = case_append_entry(
            trim($summary_full_edit),
            $summary_new_entry,
            $username
        );
    } else {
        $summary = case_append_entry(
            $old_row['summary_discussions'] ?? '',
            $summary_new_entry,
            $username
        );
    }

    /* =====================================================
       UPDATE CASE
       IMPORTANT: ID + SATELLITE
    ===================================================== */
    $query = "
        UPDATE cases SET 
            case_type = ?, 
            status = ?, 
            date_filed = ?, 
            schedule_date = ?, 
            complainant_name = ?, 
            complainant_contact = ?, 
            complainant_address = ?, 
            respondent_name = ?, 
            respondent_contact = ?, 
            respondent_address = ?, 
            complaint_details = ?,
            summary_discussions = ? 
        WHERE id = ?
          AND satellite_id = ?
    ";

    $stmt = $conn->prepare($query);

    if ($stmt) {

        /*
         * 12 strings + 2 integers
         * 12 "s" = case fields
         * 2 "i" = id + satellite_id
         */
        $stmt->bind_param(
            "ssssssssssssii",
            $case_type,
            $status,
            $date_filed,
            $schedule,
            $c_name,
            $c_contact,
            $c_address,
            $r_name,
            $r_contact,
            $r_address,
            $details,
            $summary,
            $id,
            $satellite_id
        );

        if ($stmt->execute()) {

            $stmt->close();

            /* Real save happened */
            unset($_SESSION['auto_ongoing_cases'][$id]);

            /* =====================================================
               CASE HISTORY
            ===================================================== */
            if ($old_row) {

                $new_values = [
                    'case_type' => $case_type,
                    'status' => $status,
                    'date_filed' => $date_filed,
                    'schedule_date' => $schedule,
                    'complainant_name' => $c_name,
                    'complainant_contact' => $c_contact,
                    'complainant_address' => $c_address,
                    'respondent_name' => $r_name,
                    'respondent_contact' => $r_contact,
                    'respondent_address' => $r_address,
                    'complaint_details' => $details,
                    'summary_discussions' => $summary,
                ];

                $changed_count = case_history_log_diff(
                    $conn,
                    $id,
                    $old_row,
                    $new_values,
                    $username
                );

                if ($changed_count > 0) {

                    $summary_desc =
                        "Updated Case {$old_row['case_no']} ({$changed_count} field(s) changed)";

                    $audit_log = $conn->prepare("
                        INSERT INTO audit_trail (action, description, user)
                        VALUES ('UPDATE', ?, ?)
                    ");

                    $audit_log->bind_param(
                        "ss",
                        $summary_desc,
                        $username
                    );

                    $audit_log->execute();
                    $audit_log->close();
                }
            }

            /* =====================================================
               UPDATE CALENDAR
            ===================================================== */
            calendar_apply_case_schedule_change(
                $conn,
                $id,
                $old_row['case_no'] ?? '',
                $schedule,
                $official_id
            );

            header(
                "Location: /BMS/Lupon_Office/cases/cases.php?msg=updated"
            );
            exit();

        } else {

            error_log(
                "Update error in Lupon: " . $stmt->error
            );

            $stmt->close();

            header(
                "Location: /BMS/Lupon_Office/cases/cases.php?msg=error"
            );
            exit();
        }

    } else {

        error_log(
            "Prepare failed: " . $conn->error
        );

        header(
            "Location: /BMS/Lupon_Office/cases/cases.php?msg=error"
        );
        exit();
    }
}

/* =====================================================
   BRANCH 3 — VIEW
   Default: show the edit form
===================================================== */

/* =====================================================
   FETCH CASE
   IMPORTANT: ID + SATELLITE
===================================================== */
$case = null;

if ($id > 0) {

    $stmt = $conn->prepare("
        SELECT *
        FROM cases
        WHERE id = ?
          AND satellite_id = ?
        LIMIT 1
    ");

    $stmt->bind_param("ii", $id, $satellite_id);
    $stmt->execute();

    $res = $stmt->get_result();
    $case = $res->fetch_assoc();

    $stmt->close();
}

/* =====================================================
   AUTO STATUS TRANSITION
   Pending -> Ongoing
   IMPORTANT: ID + SATELLITE
===================================================== */

unset($_SESSION['auto_ongoing_cases'][$id]);

if ($case && $case['status'] === 'Pending') {

    $transition = $conn->prepare("
        UPDATE cases
        SET status = 'Ongoing'
        WHERE id = ?
          AND satellite_id = ?
          AND status = 'Pending'
    ");

    $transition->bind_param(
        "ii",
        $id,
        $satellite_id
    );

    $transition->execute();

    if ($transition->affected_rows > 0) {

        $case['status'] = 'Ongoing';

        $_SESSION['auto_ongoing_cases'][$id] = true;

        case_history_log_field(
            $conn,
            $id,
            'status',
            'Pending',
            'Ongoing',
            $username
        );

        $transition_desc =
            "Case {$case['case_no']} auto-moved from Pending to Ongoing (opened by {$username})";

        $transition_log = $conn->prepare("
            INSERT INTO audit_trail (action, description, user)
            VALUES ('UPDATE', ?, ?)
        ");

        $transition_log->bind_param(
            "ss",
            $transition_desc,
            $username
        );

        $transition_log->execute();
        $transition_log->close();
    }

    $transition->close();
}

/* =====================================================
   AUDIT LOG
===================================================== */

if ($case) {

    $desc =
        "Opened Edit Case ({$case['case_no']})";

    $log = $conn->prepare("
        INSERT INTO audit_trail (action, description, user)
        VALUES ('VIEW', ?, ?)
    ");

    $log->bind_param(
        "ss",
        $desc,
        $username
    );

    $log->execute();
    $log->close();
}

/* HELPER FUNCTION */
function isSelected($val, $db_val) {
    return ($val == $db_val) ? 'selected' : '';
}

/**
 * Renders Complaint Details / Summary as an append-only journal.
 */
function render_cumulative_field($field_key, $current_value) {
    $current_value = trim((string) $current_value);
    ?>
    <div class="cumulative-field">
        <div class="cumulative-existing">
            <label>Recorded so far</label>

            <div class="cumulative-log">
                <?php if ($current_value === ''): ?>

                    <span class="cumulative-empty">
                        Nothing recorded yet.
                    </span>

                <?php else: ?>

                    <?php echo nl2br(htmlspecialchars($current_value)); ?>

                <?php endif; ?>
            </div>
        </div>

        <div class="cumulative-new">
            <label for="<?php echo $field_key; ?>_new">
                Add new information
            </label>

            <textarea
                name="<?php echo $field_key; ?>_new"
                id="<?php echo $field_key; ?>_new"
                placeholder="Type only the new update. It will be added below with today's date and your name — nothing above will be removed."
            ></textarea>
        </div>

        <label class="cumulative-correct-toggle">

            <input
                type="checkbox"
                name="<?php echo $field_key; ?>_correct_mode"
                value="1"
                class="correct-toggle-checkbox"
                data-target="<?php echo $field_key; ?>_full"
            >

            I need to correct or replace the existing text above
            (use only to fix a mistake)

        </label>

        <textarea
            name="<?php echo $field_key; ?>_full"
            id="<?php echo $field_key; ?>_full"
            class="cumulative-full-edit"
            hidden
            readonly
        ><?php echo htmlspecialchars($current_value); ?></textarea>

    </div>
    <?php
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<title>
Edit Case:
<?php echo htmlspecialchars($case['case_no'] ?? 'N/A'); ?>
</title>

<link
    rel="stylesheet"
    href="/BMS/Lupon_Office/assets/css/style.css"
>

</head>

<body>

<div class="contentwrapper">

<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<div class="main">

<div class="topbar">

    <div class="logo-area">

        <img
            src="/BMS/IMAGES/silogo.png"
            class="topbar-logo"
        >

        <h2 class="system-title">
            Lupon Department
        </h2>

    </div>

    <div class="topbar-right">

        <div
            class="clock"
            id="clock"
        ></div>

    </div>

</div>

<div class="content">

<?php if (!$case): ?>

    <h3 style="color:red;">
        Case not found.
    </h3>

<?php else: ?>

<form
    action="/BMS/Lupon_Office/cases/edit_case.php"
    method="POST"
    id="editCaseForm"
>

<input
    type="hidden"
    name="id"
    value="<?php echo $case['id']; ?>"
>

<!-- CSRF TOKEN -->
<input
    type="hidden"
    name="csrf_token"
    value="<?php echo htmlspecialchars(
        $csrf_token,
        ENT_QUOTES,
        'UTF-8'
    ); ?>"
>

<div class="header-actions">

    <h2>
        Case Management > Edit Case
    </h2>

    <div class="btns">

        <a
            href="/BMS/Lupon_Office/cases/edit_case.php?id=<?php echo (int) $case['id']; ?>&cancel=1&csrf_token=<?php echo urlencode($csrf_token); ?>"
            class="btn-cancel"
            id="editCaseCancel"
        >
            Cancel
        </a>

        <button
            type="submit"
            name="update_btn"
            class="btn-save"
            id="editCaseSaveBtn"
        >
            Update Record
        </button>

    </div>

</div>

<div class="form-section">

<h3>Case Information</h3>

<div class="row">

<div class="input-group">

<label>Complaint Type:</label>

<select
    name="case_type"
    required
>

<option
    value=""
    disabled
>
    -- Select Case Type --
</option>

<option
    value="Slight Physical Injuries"
    <?= isSelected("Slight Physical Injuries",$case['case_type']); ?>
>
    Slight Physical Injuries
</option>

<option
    value="Less Serious Physical Injuries"
    <?= isSelected("Less Serious Physical Injuries",$case['case_type']); ?>
>
    Less Serious Physical Injuries
</option>

<option
    value="Maltreatment"
    <?= isSelected("Maltreatment",$case['case_type']); ?>
>
    Maltreatment
</option>

<option
    value="Theft (Minor)"
    <?= isSelected("Theft (Minor)",$case['case_type']); ?>
>
    Theft (Minor)
</option>

<option
    value="Swindling (Estafa)"
    <?= isSelected("Swindling (Estafa)",$case['case_type']); ?>
>
    Swindling (Estafa)
</option>

<option
    value="Malicious Mischief"
    <?= isSelected("Malicious Mischief",$case['case_type']); ?>
>
    Malicious Mischief
</option>

<option
    value="Oral Defamation (Slander)"
    <?= isSelected("Oral Defamation (Slander)",$case['case_type']); ?>
>
    Oral Defamation
</option>

<option
    value="Curfew Violation"
    <?= isSelected("Curfew Violation",$case['case_type']); ?>
>
    Curfew Violation
</option>

<option
    value="Collection of Debt"
    <?= isSelected("Collection of Debt",$case['case_type']); ?>
>
    Collection of Debt
</option>

</select>

</div>

<div class="input-group">

<label>Status:</label>

<select name="status">

<option
    value="Pending"
    <?= isSelected("Pending",$case['status']); ?>
>
    Pending
</option>

<option
    value="Ongoing"
    <?= isSelected("Ongoing",$case['status']); ?>
>
    Ongoing
</option>

<option
    value="Settled"
    <?= isSelected("Settled",$case['status']); ?>
>
    Settled
</option>

<option
    value="CFA"
    <?= isSelected("CFA",$case['status']); ?>
>
    CFA
</option>

</select>

</div>

<div class="input-group">

<label>Date Filed:</label>

<input
    type="date"
    name="date_filed"
    value="<?php echo htmlspecialchars($case['date_filed']); ?>"
>

</div>

<div class="input-group">

<label>Schedule:</label>

<input
    type="date"
    name="schedule"
    value="<?php echo htmlspecialchars($case['schedule_date']); ?>"
>

</div>

</div>

</div>

<div class="two-column">

<div class="form-section">

<h3>Complainant</h3>

<input
    type="text"
    name="c_name"
    value="<?php echo htmlspecialchars($case['complainant_name']); ?>"
>

<input
    type="text"
    name="c_contact"
    value="<?php echo htmlspecialchars($case['complainant_contact']); ?>"
>

<input
    type="text"
    name="c_address"
    value="<?php echo htmlspecialchars($case['complainant_address']); ?>"
>

</div>

<div class="form-section">

<h3>Respondent</h3>

<input
    type="text"
    name="r_name"
    value="<?php echo htmlspecialchars($case['respondent_name']); ?>"
>

<input
    type="text"
    name="r_contact"
    value="<?php echo htmlspecialchars($case['respondent_contact']); ?>"
>

<input
    type="text"
    name="r_address"
    value="<?php echo htmlspecialchars($case['respondent_address']); ?>"
>

</div>

</div>

<div class="form-section">

<h3>Complaint Details</h3>

<?php
render_cumulative_field(
    'details',
    $case['complaint_details']
);
?>

</div>

<div class="form-section">

<h3>Summary</h3>

<?php
render_cumulative_field(
    'summary_discussions',
    $case['summary_discussions']
);
?>

</div>

</form>

<?php endif; ?>

</div>

</div>

</div>

<script src="/BMS/Lupon_Office/assets/js/script.js"></script>

<script>

document
.querySelectorAll('.correct-toggle-checkbox')
.forEach(function (checkbox) {

    checkbox.addEventListener('change', function () {

        var target =
            document.getElementById(this.dataset.target);

        if (!target) return;

        target.hidden = !this.checked;
        target.readOnly = !this.checked;

    });

});

/* Unsaved-changes guard on Cancel,
   and duplicate-submission guard on Save. */

(function () {

    var form =
        document.getElementById('editCaseForm');

    if (!form) return;

    function serializeForm() {

        var data =
            new FormData(form);

        var pairs = [];

        data.forEach(function (value, key) {
            pairs.push(key + '=' + value);
        });

        return pairs.join('&');

    }

    var initialState =
        serializeForm();

    var cancelLink =
        document.getElementById('editCaseCancel');

    if (cancelLink) {

        cancelLink.addEventListener(
            'click',
            function (e) {

                if (
                    serializeForm() !==
                    initialState
                ) {

                    e.preventDefault();

                    var href =
                        cancelLink.href;

                    showConfirmModal(
                        'Any unsaved changes will be lost.',
                        {
                            title: 'Discard changes?',
                            confirmText: 'Discard',
                            danger: true
                        }
                    ).then(function (confirmed) {

                        if (confirmed) {
                            window.location.href = href;
                        }

                    });
                }

            }
        );
    }

    form.addEventListener(
        'submit',
        function () {

            var btn =
                document.getElementById(
                    'editCaseSaveBtn'
                );

            if (btn) {

                btn.disabled = true;
                btn.textContent = 'Saving…';

            }

        }
    );

})();

</script>

</body>
</html>