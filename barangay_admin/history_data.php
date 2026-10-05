<?php

session_start();

include 'config.php';
include 'session_time-out.php';

date_default_timezone_set('Asia/Manila');



// ============================================================
// HISTORY DATA
// ============================================================
// This page DOES NOT use audit_trail.
//
// Sources:
//
// REQUEST    -> resident_request + document_types
// BLOTTER    -> blotter
// LUPON      -> cases
// MEDICATION -> resident_medicine + residents
//
// ============================================================



// ============================================================
// FILTER VARIABLES
// ============================================================

$search    = isset($_GET['search']) ? trim($_GET['search']) : '';
$type      = isset($_GET['type']) ? trim($_GET['type']) : '';
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to   = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';



// ============================================================
// ALLOWED TYPES
// ============================================================

$allowed_types = [
    'REQUEST',
    'LUPON',
    'BLOTTER',
    'MEDICATION'
];



// Prevent invalid type values
if ($type !== '' && !in_array($type, $allowed_types, true)) {
    $type = '';
}



// ============================================================
// VALIDATE DATES
// ============================================================

function validDate($date)
{
    if ($date === '') {
        return true;
    }

    $dateObject = DateTime::createFromFormat('Y-m-d', $date);

    return $dateObject &&
           $dateObject->format('Y-m-d') === $date;
}



if (!validDate($date_from)) {
    $date_from = '';
}



if (!validDate($date_to)) {
    $date_to = '';
}



// If both dates exist and Date From is later than Date To,
// swap them so the filter remains valid.
if (
    $date_from !== '' &&
    $date_to !== '' &&
    $date_from > $date_to
) {
    [$date_from, $date_to] = [$date_to, $date_from];
}



// ============================================================
// BUILD FILTERS
// ============================================================

$history_where = [];

$params = [];
$param_types = '';



// ------------------------------------------------------------
// SEARCH
// ------------------------------------------------------------

if ($search !== '') {

    $history_where[] = "
    (
        history_type LIKE ?
        OR record_title LIKE ?
        OR person_name LIKE ?
        OR status LIKE ?
        OR description LIKE ?
        OR record_id LIKE ?
    )
    ";

    $search_value = '%' . $search . '%';

    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;

    $param_types .= 'ssssss';
}



// ------------------------------------------------------------
// TYPE FILTER
// ------------------------------------------------------------

if ($type !== '') {

    $history_where[] = "
        history_type = ?
    ";

    $params[] = $type;
    $param_types .= 's';
}



// ------------------------------------------------------------
// DATE FROM
// ------------------------------------------------------------

if ($date_from !== '') {

    $history_where[] = "
        DATE(record_date) >= ?
    ";

    $params[] = $date_from;
    $param_types .= 's';
}



// ------------------------------------------------------------
// DATE TO
// ------------------------------------------------------------

if ($date_to !== '') {

    $history_where[] = "
        DATE(record_date) <= ?
    ";

    $params[] = $date_to;
    $param_types .= 's';
}



// ============================================================
// FINAL WHERE
// ============================================================

$final_where = '';

if (count($history_where) > 0) {

    $final_where = "
    WHERE " . implode(" AND ", $history_where);

}



// ============================================================
// CREATE COMBINED HISTORY QUERY
// ============================================================
//
// Every table is converted into the same structure:
//
// history_type
// record_id
// record_date
// record_title
// person_name
// status
// description
//
// ============================================================

$history_source = "

    /* ========================================================
       DOCUMENT REQUESTS
       ======================================================== */

    SELECT

        'REQUEST' AS history_type,

        rr.request_id AS record_id,

        rr.submitted_at AS record_date,

        dt.name AS record_title,

        rr.fullname AS person_name,

        rr.status AS status,

        CONCAT(
            'Document Request: ',
            dt.name,
            ' | Purpose: ',
            rr.purpose,
            ' | Payment: ',
            rr.payment_method,
            ' | Amount: ₱',
            FORMAT(rr.price, 2)
        ) AS description

    FROM resident_request rr

    LEFT JOIN document_types dt
        ON rr.document_type_id = dt.document_type_id



    UNION ALL



    /* ========================================================
       BLOTTER RECORDS
       ======================================================== */

    SELECT

        'BLOTTER' AS history_type,

        b.id AS record_id,

        b.date AS record_date,

        b.complaint AS record_title,

        b.complainants AS person_name,

        b.status AS status,

        CONCAT(
            'Complaint: ',
            COALESCE(b.complaint, 'N/A'),
            ' | Officer: ',
            COALESCE(b.officer, 'N/A'),
            ' | Remarks: ',
            COALESCE(b.summary_remarks, 'N/A'),
            ' | Response: ',
            COALESCE(b.response, 'N/A')
        ) AS description

    FROM blotter b



    UNION ALL



    /* ========================================================
       LUPON CASES
       ======================================================== */

    SELECT

        'LUPON' AS history_type,

        c.id AS record_id,

        c.date_filed AS record_date,

        c.case_no AS record_title,

        c.complainant_name AS person_name,

        c.status AS status,

        CONCAT(
            'Case Type: ',
            COALESCE(c.case_type, 'N/A'),
            ' | Complainant: ',
            COALESCE(c.complainant_name, 'N/A'),
            ' | Respondent: ',
            COALESCE(c.respondent_name, 'N/A'),
            ' | Complaint: ',
            COALESCE(c.complaint_details, 'N/A')
        ) AS description

    FROM cases c



    UNION ALL



    /* ========================================================
       MEDICATION RECORDS
       ======================================================== */

    SELECT

        'MEDICATION' AS history_type,

        rm.id AS record_id,

        rm.last_given AS record_date,

        rm.medicine_name AS record_title,

        COALESCE(
            r.name,
            CONCAT('Resident ID: ', rm.resident_id)
        ) AS person_name,

        CASE

            WHEN rm.quantity_remaining IS NULL
                THEN 'No Quantity'

            WHEN rm.quantity_remaining <= 0
                THEN 'Out of Stock'

            WHEN rm.refill_threshold IS NOT NULL
                 AND rm.quantity_remaining <= rm.refill_threshold
                THEN 'Refill Needed'

            ELSE 'Available'

        END AS status,

        CONCAT(
            'Medicine: ',
            COALESCE(rm.medicine_name, 'N/A'),
            ' | Dosage: ',
            COALESCE(rm.dosage, 'N/A'),
            ' | Given: ',
            COALESCE(rm.quantity_given, 0),
            ' | Remaining: ',
            COALESCE(rm.quantity_remaining, 0),
            ' | Refill Threshold: ',
            COALESCE(rm.refill_threshold, 0)
        ) AS description

    FROM resident_medicine rm

    LEFT JOIN residents r
        ON rm.resident_id = r.resident_id

";



// ============================================================
// PAGINATION
// ============================================================

$limit = 20;

$page = isset($_GET['page'])
    ? (int)$_GET['page']
    : 1;

if ($page < 1) {
    $page = 1;
}



// ============================================================
// COUNT TOTAL RECORDS
// ============================================================

$count_query = "

    SELECT COUNT(*) AS total

    FROM (

        $history_source

    ) AS history

    $final_where

";



$count_stmt = $conn->prepare($count_query);

if (!$count_stmt) {

    die("Database Error.");

}



if (!empty($params)) {
    $count_stmt->bind_param(
        $param_types,
        ...$params
    );
}



if (!$count_stmt->execute()) {

    $count_stmt->close();

    die("Database Error.");

}



$count_result = $count_stmt->get_result();

$count_row = $count_result->fetch_assoc();

$total = (int)$count_row['total'];

$count_stmt->close();



// ============================================================
// TOTAL PAGES
// ============================================================

$total_pages = ($total > 0)
    ? (int)ceil($total / $limit)
    : 1;



// If requested page is greater than total pages
if ($page > $total_pages) {
    $page = $total_pages;
}



$start = ($page - 1) * $limit;



// ============================================================
// GET HISTORY RECORDS
// ============================================================

$query = "

    SELECT *

    FROM (

        $history_source

    ) AS history

    $final_where

    ORDER BY
        record_date DESC,
        record_id DESC

    LIMIT $start, $limit

";



$stmt = $conn->prepare($query);

if (!$stmt) {

    die("Database Error.");

}



if (!empty($params)) {
    $stmt->bind_param(
        $param_types,
        ...$params
    );
}



if (!$stmt->execute()) {

    $stmt->close();

    die("Database Error.");

}



$result = $stmt->get_result();



// ============================================================
// HELPER FUNCTIONS
// ============================================================

function e($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}



function formatHistoryDate($date)
{
    if (empty($date)) {
        return 'No date';
    }

    $timestamp = strtotime($date);

    if (!$timestamp) {
        return e($date);
    }

    return date(
        'F d, Y h:i A',
        $timestamp
    );
}



function getTypeBadge($type)
{
    switch ($type) {

        case 'REQUEST':
            return '<span class="badge bg-primary">REQUEST</span>';

        case 'BLOTTER':
            return '<span class="badge bg-danger">BLOTTER</span>';

        case 'LUPON':
            return '<span class="badge bg-warning text-dark">LUPON</span>';

        case 'MEDICATION':
            return '<span class="badge bg-success">MEDICATION</span>';

        default:
            return '<span class="badge bg-secondary">'
                . e($type)
                . '</span>';
    }
}



function getStatusBadge($status)
{
    $status_lower = strtolower(trim((string)$status));

    switch ($status_lower) {

        case 'approved':
        case 'resolved':
        case 'settled':
        case 'available':

            return '<span class="badge bg-success">'
                . e($status)
                . '</span>';

        case 'pending':
        case 'refill needed':

            return '<span class="badge bg-warning text-dark">'
                . e($status)
                . '</span>';

        case 'rejected':
        case 'out of stock':

            return '<span class="badge bg-danger">'
                . e($status)
                . '</span>';

        case 'transferred to lupon':

            return '<span class="badge bg-info text-dark">'
                . e($status)
                . '</span>';

        case 'cfa':

            return '<span class="badge bg-secondary">'
                . e($status)
                . '</span>';

        default:

            return '<span class="badge bg-secondary">'
                . e($status ?: 'N/A')
                . '</span>';
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>History Data</title>

    <!-- Font Awesome -->

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
    >

    <!-- Bootstrap -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <!-- Existing System CSS -->

    <link
        rel="stylesheet"
        href="style/main.css"
    >

    <link
        rel="stylesheet"
        href="style/header.css"
    >

    <link
        rel="stylesheet"
        href="style/sidebar.css"
    >

    <style>

        .history-card {

            border: none;

            border-radius: 12px;

            overflow: hidden;

        }

        .history-card .card-header {

            background: #ffffff;

            border-bottom: 1px solid #dee2e6;

            padding: 20px;

        }

        .history-title {

            font-weight: 700;

            margin: 0;

        }

        .history-subtitle {

            color: #6c757d;

            font-size: 14px;

            margin-top: 4px;

        }

        .history-table {

            vertical-align: middle;

        }

        .history-table th {

            white-space: nowrap;

        }

        .history-table td {

            vertical-align: middle;

        }

        .record-description {

            min-width: 300px;

            max-width: 500px;

            white-space: normal;

            line-height: 1.5;

        }

        .record-title {

            font-weight: 600;

        }

        .person-name {

            font-weight: 500;

        }

        .history-id {

            color: #6c757d;

            font-size: 13px;

        }

        .filter-label {

            font-size: 13px;

            font-weight: 600;

            margin-bottom: 5px;

        }

        .history-count {

            font-size: 14px;

            color: #6c757d;

        }

        .pagination {

            margin-bottom: 0;

        }

        .empty-history {

            padding: 50px 20px;

            text-align: center;

            color: #6c757d;

        }

        .empty-history i {

            font-size: 45px;

            margin-bottom: 15px;

            opacity: .5;

        }

        @media(max-width: 768px) {

            .record-description {

                min-width: 220px;

            }

        }

    </style>

</head>



<body>



<?php include "sidebar.php"; ?>



<div class="main-wrapper">



    <?php include "header.php"; ?>



    <div class="content">



        <div class="container-fluid">



            <!-- PAGE HEADER -->

            <div class="card history-card mt-4 shadow-sm">



                <div class="card-header">



                    <div
                        class="d-flex justify-content-between align-items-center flex-wrap gap-2"
                    >

                        <div>

                            <h4 class="history-title">

                                <i class="fa-solid fa-clock-rotate-left"></i>

                                System History Archive

                            </h4>



                            <div class="history-subtitle">

                                Actual records from Requests, Lupon,
                                Blotter, and Medication

                            </div>

                        </div>



                        <div class="history-count">

                            Total Records:

                            <strong>

                                <?=number_format($total)?>

                            </strong>

                        </div>

                    </div>

                </div>



                <div class="card-body">



                    <!-- SEARCH / FILTER -->

                    <form method="GET">



                        <div class="row g-3 align-items-end">



                            <!-- SEARCH -->

                            <div class="col-lg-3 col-md-6">

                                <label class="filter-label">

                                    Search

                                </label>

                                <input
                                    type="text"
                                    name="search"
                                    class="form-control"
                                    placeholder="Search records..."
                                    value="<?=e($search)?>"
                                >

                            </div>



                            <!-- TYPE -->

                            <div class="col-lg-2 col-md-6">

                                <label class="filter-label">

                                    Record Type

                                </label>



                                <select
                                    name="type"
                                    class="form-select"
                                >

                                    <option value="">

                                        All Records

                                    </option>



                                    <option
                                        value="REQUEST"
                                        <?=$type === 'REQUEST' ? 'selected' : ''?>
                                    >

                                        Request

                                    </option>



                                    <option
                                        value="LUPON"
                                        <?=$type === 'LUPON' ? 'selected' : ''?>
                                    >

                                        Lupon

                                    </option>



                                    <option
                                        value="BLOTTER"
                                        <?=$type === 'BLOTTER' ? 'selected' : ''?>
                                    >

                                        Blotter

                                    </option>



                                    <option
                                        value="MEDICATION"
                                        <?=$type === 'MEDICATION' ? 'selected' : ''?>
                                    >

                                        Medication

                                    </option>

                                </select>

                            </div>



                            <!-- DATE FROM -->

                            <div class="col-lg-2 col-md-6">

                                <label class="filter-label">

                                    Date From

                                </label>

                                <input
                                    type="date"
                                    name="date_from"
                                    class="form-control"
                                    value="<?=e($date_from)?>"
                                >

                            </div>



                            <!-- DATE TO -->

                            <div class="col-lg-2 col-md-6">

                                <label class="filter-label">

                                    Date To

                                </label>

                                <input
                                    type="date"
                                    name="date_to"
                                    class="form-control"
                                    value="<?=e($date_to)?>"
                                >

                            </div>



                            <!-- SEARCH BUTTON -->

                            <div class="col-lg-1 col-md-6">

                                <button
                                    type="submit"
                                    class="btn btn-primary w-100"
                                >

                                    <i class="fa-solid fa-magnifying-glass"></i>

                                </button>

                            </div>



                            <!-- RESET -->

                            <div class="col-lg-2 col-md-6">

                                <a
                                    href="history_data.php"
                                    class="btn btn-secondary w-100"
                                >

                                    <i class="fa-solid fa-rotate-left"></i>

                                    Reset

                                </a>

                            </div>



                        </div>



                    </form>



                    <hr class="my-4">



                    <!-- HISTORY TABLE -->

                    <div class="table-responsive">



                        <table
                            class="table table-bordered table-striped table-hover history-table"
                        >



                            <thead class="table-dark">



                                <tr>

                                    <th style="width: 160px;">
                                        Date
                                    </th>

                                    <th style="width: 110px;">
                                        Type
                                    </th>

                                    <th style="width: 90px;">
                                        ID
                                    </th>

                                    <th>
                                        Record
                                    </th>

                                    <th>
                                        Person
                                    </th>

                                    <th style="width: 130px;">
                                        Status
                                    </th>

                                    <th>
                                        Details
                                    </th>

                                </tr>



                            </thead>



                            <tbody>



                            <?php

                            if ($result->num_rows > 0) {

                                while ($row = $result->fetch_assoc()) {

                            ?>



                                <tr>



                                    <!-- DATE -->

                                    <td>

                                        <div>

                                            <?=formatHistoryDate(
                                                $row['record_date']
                                            )?>

                                        </div>

                                    </td>



                                    <!-- TYPE -->

                                    <td>

                                        <?=getTypeBadge(
                                            $row['history_type']
                                        )?>

                                    </td>



                                    <!-- ID -->

                                    <td>

                                        <span class="history-id">

                                            #<?=e($row['record_id'])?>

                                        </span>

                                    </td>



                                    <!-- RECORD -->

                                    <td>

                                        <div class="record-title">

                                            <?=e(
                                                $row['record_title']
                                            )?>

                                        </div>

                                    </td>



                                    <!-- PERSON -->

                                    <td>

                                        <span class="person-name">

                                            <?=e(
                                                $row['person_name']
                                            )?>

                                        </span>

                                    </td>



                                    <!-- STATUS -->

                                    <td>

                                        <?=getStatusBadge(
                                            $row['status']
                                        )?>

                                    </td>



                                    <!-- DETAILS -->

                                    <td>

                                        <div class="record-description">

                                            <?=e(
                                                $row['description']
                                            )?>

                                        </div>

                                    </td>



                                </tr>



                            <?php

                                }

                            } else {

                            ?>



                                <tr>

                                    <td
                                        colspan="7"
                                        class="empty-history"
                                    >

                                        <i class="fa-solid fa-folder-open d-block"></i>



                                        <strong>
                                            No history records found
                                        </strong>



                                        <div class="mt-1">

                                            Try changing your search
                                            or filter.

                                        </div>

                                    </td>

                                </tr>



                            <?php

                            }

                            ?>



                            </tbody>



                        </table>



                    </div>



                    <!-- PAGINATION -->

                    <?php if ($total > 0 && $total_pages > 1): ?>



                        <nav
                            aria-label="History pagination"
                            class="mt-4"
                        >



                            <ul
                                class="pagination justify-content-center flex-wrap"
                            >



                                <!-- PREVIOUS -->

                                <?php

                                $prev_page = $page - 1;

                                if ($prev_page < 1) {
                                    $prev_page = 1;
                                }

                                ?>



                                <li
                                    class="page-item
                                    <?=$page <= 1 ? 'disabled' : ''?>"
                                >

                                    <a
                                        class="page-link"
                                        href="?page=<?=$prev_page?>&search=<?=urlencode($search)?>&type=<?=urlencode($type)?>&date_from=<?=urlencode($date_from)?>&date_to=<?=urlencode($date_to)?>"
                                    >

                                        &laquo;

                                    </a>

                                </li>



                                <?php

                                /*
                                 * Show a maximum of 7 page buttons.
                                 */

                                $start_page = max(
                                    1,
                                    $page - 3
                                );

                                $end_page = min(
                                    $total_pages,
                                    $page + 3
                                );



                                for (
                                    $i = $start_page;
                                    $i <= $end_page;
                                    $i++
                                ) {

                                ?>



                                    <li
                                        class="page-item
                                        <?=$page == $i ? 'active' : ''?>"
                                    >

                                        <a
                                            class="page-link"
                                            href="?page=<?=$i?>&search=<?=urlencode($search)?>&type=<?=urlencode($type)?>&date_from=<?=urlencode($date_from)?>&date_to=<?=urlencode($date_to)?>"
                                        >

                                            <?=$i?>

                                        </a>

                                    </li>



                                <?php

                                }

                                ?>



                                <!-- NEXT -->

                                <?php

                                $next_page = $page + 1;

                                if ($next_page > $total_pages) {
                                    $next_page = $total_pages;
                                }

                                ?>



                                <li
                                    class="page-item
                                    <?=$page >= $total_pages ? 'disabled' : ''?>"
                                >

                                    <a
                                        class="page-link"
                                        href="?page=<?=$next_page?>&search=<?=urlencode($search)?>&type=<?=urlencode($type)?>&date_from=<?=urlencode($date_from)?>&date_to=<?=urlencode($date_to)?>"
                                    >

                                        &raquo;

                                    </a>

                                </li>



                            </ul>



                        </nav>



                    <?php endif; ?>



                    <!-- PAGINATION INFORMATION -->

                    <?php if ($total > 0): ?>



                        <div class="text-center text-muted mt-2">

                            <?php

                            $showing_from = $start + 1;

                            $showing_to = min(
                                $start + $limit,
                                $total
                            );

                            ?>



                            Showing

                            <strong>
                                <?=$showing_from?>
                            </strong>

                            to

                            <strong>
                                <?=$showing_to?>
                            </strong>

                            of

                            <strong>
                                <?=$total?>
                            </strong>

                            records



                        </div>



                    <?php endif; ?>



                </div>



            </div>



        </div>



    </div>



</div>



<!-- Bootstrap JS -->

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
></script>



</body>

</html>

<?php
$stmt->close();
$conn->close();
?>