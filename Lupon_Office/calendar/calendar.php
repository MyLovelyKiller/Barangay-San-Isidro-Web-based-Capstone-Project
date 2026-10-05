<?php
session_start();
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

/* CSRF token for the AJAX calls this page makes */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Deep link from view_case.php: calendar.php?schedule_case=123
 * opens straight into the New Event modal with that case already
 * attached, so an officer doesn't have to re-search for it.
 */
$prefill_case = null;
if (isset($_GET['schedule_case'])) {
    $schedule_case_id = (int) $_GET['schedule_case'];
    if ($schedule_case_id > 0) {
        require_once(__DIR__ . '/../includes/db_connect.php');
        $stmt = $conn->prepare("SELECT id, case_no, complainant_name, respondent_name FROM cases WHERE id = ?");
        $stmt->bind_param("i", $schedule_case_id);
        $stmt->execute();
        $prefill_case = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $conn->close();
    }
}

$page = 'calendar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
<title>Calendar - Lupon Office</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/BMS/Lupon_Office/assets/css/style.css">
<link rel="stylesheet" href="/BMS/Lupon_Office/assets/css/calendar.css">
<script>
    /* Encoded with the HEX flags so complainant/respondent names typed
       by the public can never break out of this script block, even if
       they contain angle brackets or quotes. */
    window.PREFILL_CASE = <?php
        echo $prefill_case
            ? json_encode([
                'id'          => (int) $prefill_case['id'],
                'case_no'     => $prefill_case['case_no'],
                'complainant' => $prefill_case['complainant_name'],
                'respondent'  => $prefill_case['respondent_name'],
              ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
            : 'null';
    ?>;
</script>
</head>
<body>
<div class="contentwrapper">

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
            <div class="header-actions">
                <h2>Hearing Calendar</h2>
                <div class="btns">
                    <button type="button" id="btnNewEvent" class="btn-save"><i class="fa fa-plus"></i> New Event</button>
                </div>
            </div>

            <div class="calendar-layout">

                <div class="calendar-card">
                    <div class="calendar-toolbar">
                        <div class="cal-nav">
                            <button type="button" id="prevMonth" class="cal-nav-btn" aria-label="Previous month">
                                <i class="fa fa-chevron-left"></i>
                            </button>
                            <h3 id="calendarMonthLabel">&nbsp;</h3>
                            <button type="button" id="nextMonth" class="cal-nav-btn" aria-label="Next month">
                                <i class="fa fa-chevron-right"></i>
                            </button>
                        </div>
                        <button type="button" id="btnToday" class="btn-cancel">Today</button>
                    </div>

                    <div class="cal-weekdays">
                        <span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span>
                    </div>

                    <div id="calendarGrid" class="cal-grid" aria-live="polite"></div>
                </div>

                <aside class="calendar-side">
                    <div class="side-card">
                        <h3>Legend</h3>
                        <ul id="typeLegend" class="type-legend"></ul>
                    </div>

                    <div class="side-card">
                        <h3>Upcoming Hearings</h3>
                        <ul id="upcomingList" class="upcoming-list">
                            <li class="empty-note">Loading…</li>
                        </ul>
                    </div>
                </aside>

            </div>
        </div>
    </div>
</div>

<!-- EVENT MODAL -->
<div id="eventModal" class="modal-overlay" hidden>
    <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
        <div class="modal-header">
            <h3 id="modalTitle">New Event</h3>
            <button type="button" id="modalClose" class="modal-close" aria-label="Close">&times;</button>
        </div>

        <form id="eventForm" class="modal-form">
            <input type="hidden" id="eventId" value="">

            <div class="form-group">
                <label for="eventTitle">Title</label>
                <input type="text" id="eventTitle" maxlength="150" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="eventType">Event Type</label>
                    <select id="eventType" required></select>
                </div>
                <div class="form-group">
                    <label for="eventStatus">Status</label>
                    <select id="eventStatus">
                        <option value="Scheduled">Scheduled</option>
                        <option value="Completed">Completed</option>
                        <option value="Postponed">Postponed</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="eventDate">Date</label>
                    <input type="date" id="eventDate" required>
                </div>
                <div class="form-group">
                    <label for="eventStart">Start Time</label>
                    <input type="time" id="eventStart" required>
                </div>
                <div class="form-group">
                    <label for="eventEnd">End Time</label>
                    <input type="time" id="eventEnd">
                </div>
            </div>

            <div class="form-group case-link-group">
                <label for="caseSearchInput">Linked Case (optional)</label>
                <input type="text" id="caseSearchInput" placeholder="Search case no., complainant, or respondent…" autocomplete="off">
                <input type="hidden" id="linkedCaseId" value="">
                <ul id="caseSearchResults" class="case-search-results" hidden></ul>
                <div id="linkedCaseChip" class="linked-case-chip" hidden></div>
            </div>

            <div class="form-group">
                <label for="eventLocation">Location</label>
                <input type="text" id="eventLocation" maxlength="150" placeholder="Barangay Hall - Lupon Office">
            </div>

            <div class="form-group">
                <label for="eventDescription">Notes</label>
                <textarea id="eventDescription" rows="3"></textarea>
            </div>

            <p id="formError" class="form-error" hidden></p>

            <div class="modal-actions">
                <button type="button" id="btnDeleteEvent" class="btn-cancel danger" hidden>Delete</button>
                <div class="modal-actions-right">
                    <button type="button" id="btnCancelModal" class="btn-cancel">Cancel</button>
                    <button type="submit" class="btn-save">Save Event</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="/BMS/Lupon_Office/assets/js/script.js"></script>
<script src="/BMS/Lupon_Office/assets/js/calendar.js"></script>
</body>
</html>
