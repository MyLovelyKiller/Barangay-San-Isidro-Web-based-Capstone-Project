<?php
require_once(__DIR__ . '/calendar_api_guard.php');
require_once(__DIR__ . '/../includes/db_connect.php');

/**
 * Search-as-you-type instead of a single dropdown of every case.
 * As the case count grows into the thousands, a <select> with
 * every case would be as slow to load as cases.php currently is
 * with its "load everything" table — this only ever returns a
 * handful of matches.
 */
$q = trim($_GET['q'] ?? '');

if (mb_strlen($q) < 2) {
    echo json_encode(['success' => true, 'cases' => []]);
    exit();
}

$like = "%{$q}%";

$stmt = $conn->prepare(
    "SELECT id, case_no, complainant_name, respondent_name
     FROM cases
     WHERE status IN ('Pending', 'Ongoing', 'CFA')
       AND (case_no LIKE ? OR complainant_name LIKE ? OR respondent_name LIKE ?)
     ORDER BY date_filed DESC
     LIMIT 10"
);
$stmt->bind_param("sss", $like, $like, $like);
$stmt->execute();
$result = $stmt->get_result();

$cases = [];
while ($row = $result->fetch_assoc()) {
    $cases[] = [
        'id'          => (int) $row['id'],
        'case_no'     => $row['case_no'],
        'complainant' => $row['complainant_name'],
        'respondent'  => $row['respondent_name'],
    ];
}
$stmt->close();

echo json_encode(['success' => true, 'cases' => $cases]);
$conn->close();
