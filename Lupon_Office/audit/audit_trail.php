<?php
session_start();
include(__DIR__ . '/../includes/db_connect.php');
include(__DIR__ . '/../includes/session_time-out.php');
require_once(__DIR__ . '/../includes/case_history_helpers.php');

/* ACCESS CONTROL */
if (
    !isset($_SESSION['official_id']) ||
    !isset($_SESSION['department']) ||
    strtoupper(trim($_SESSION['department'])) !== "LUPON"
) {
    header("Location: /BMS/CODES/login.php?error=1");
    exit();
}

$page = 'audit_trail.php';

$case_id = isset($_GET['case_id']) ? intval($_GET['case_id']) : 0;
$search_term = trim($_GET['q'] ?? '');

$selected_case = null;
$history_rows = [];
$search_results = [];
$recent_activity = [];

if ($case_id > 0) {
    /* ---- Load one case's full field-level history ---- */
    $stmt = $conn->prepare("SELECT id, case_no, complainant_name, respondent_name, status FROM cases WHERE id = ?");
    $stmt->bind_param("i", $case_id);
    $stmt->execute();
    $selected_case = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($selected_case) {
        $hist_stmt = $conn->prepare(
            "SELECT field_name, old_value, new_value, changed_by, changed_at
             FROM case_history
             WHERE case_id = ?
             ORDER BY changed_at DESC, id DESC"
        );
        $hist_stmt->bind_param("i", $case_id);
        $hist_stmt->execute();
        $history_result = $hist_stmt->get_result();
        while ($row = $history_result->fetch_assoc()) {
            $history_rows[] = $row;
        }
        $hist_stmt->close();
    }
} elseif ($search_term !== '') {
    /* ---- Search for a case to view ---- */
    $like = "%{$search_term}%";
    $search_stmt = $conn->prepare(
        "SELECT id, case_no, complainant_name, respondent_name, status
         FROM cases
         WHERE case_no LIKE ? OR complainant_name LIKE ? OR respondent_name LIKE ?
         ORDER BY date_filed DESC
         LIMIT 20"
    );
    $search_stmt->bind_param("sss", $like, $like, $like);
    $search_stmt->execute();
    $search_result = $search_stmt->get_result();
    while ($row = $search_result->fetch_assoc()) {
        $search_results[] = $row;
    }
    $search_stmt->close();
} else {
    /* ---- Landing state: recent activity across all cases ---- */
    $recent_stmt = $conn->query(
        "SELECT ch.case_id, ch.field_name, ch.old_value, ch.new_value, ch.changed_by, ch.changed_at, c.case_no
         FROM case_history ch
         INNER JOIN cases c ON c.id = ch.case_id
         ORDER BY ch.changed_at DESC, ch.id DESC
         LIMIT 15"
    );
    if ($recent_stmt) {
        while ($row = $recent_stmt->fetch_assoc()) {
            $recent_activity[] = $row;
        }
    }
}

/**
 * Renders one history row as "old value -> new value", with the
 * status field shown as a pair of colored badges instead of plain
 * text since that's already a meaningful visual language elsewhere
 * in the app.
 */
function render_history_value($field_name, $old_value, $new_value) {
    if ($field_name === 'status') {
        $old_display = $old_value !== null && $old_value !== '' ? $old_value : 'Pending';
        $new_display = $new_value !== null && $new_value !== '' ? $new_value : 'Pending';
        echo '<span class="badge ' . strtolower(htmlspecialchars($old_display)) . '">' . htmlspecialchars($old_display) . '</span>';
        echo '<i class="fa fa-arrow-right history-arrow"></i>';
        echo '<span class="badge ' . strtolower(htmlspecialchars($new_display)) . '">' . htmlspecialchars($new_display) . '</span>';
        return;
    }

    $old_text = ($old_value === null || $old_value === '') ? '(empty)' : $old_value;
    $new_text = ($new_value === null || $new_value === '') ? '(empty)' : $new_value;

    echo '<span class="history-old-value">' . htmlspecialchars($old_text) . '</span>';
    echo '<i class="fa fa-arrow-right history-arrow"></i>';
    echo '<span class="history-new-value">' . htmlspecialchars($new_text) . '</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Audit Trail - Lupon Office</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/BMS/Lupon_Office/assets/css/style.css">
<link rel="stylesheet" href="/BMS/Lupon_Office/assets/css/audit_trail.css">
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
                <h2>Audit Trail</h2>
            </div>

            <div class="audit-search-card">
                <form method="GET" action="/BMS/Lupon_Office/audit/audit_trail.php" class="audit-search-form">
                    <input type="text" name="q" placeholder="Search by case no., complainant, or respondent…" value="<?php echo htmlspecialchars($search_term); ?>">
                    <button type="submit" class="btn-save"><i class="fa fa-search"></i> Search</button>
                    <?php if ($case_id > 0 || $search_term !== ''): ?>
                        <a href="/BMS/Lupon_Office/audit/audit_trail.php" class="btn-cancel">Clear</a>
                    <?php endif; ?>
                </form>
            </div>

            <?php if ($case_id > 0): ?>

                <?php if (!$selected_case): ?>
                    <div class="audit-empty-state">
                        <p>That case couldn't be found.</p>
                        <a href="/BMS/Lupon_Office/audit/audit_trail.php" class="btn-cancel">Back to Audit Trail</a>
                    </div>
                <?php else: ?>
                    <div class="audit-case-header">
                        <div>
                            <h3><?php echo htmlspecialchars($selected_case['case_no']); ?></h3>
                            <p class="audit-case-parties">
                                <?php echo htmlspecialchars($selected_case['complainant_name']); ?>
                                <span class="vs">vs</span>
                                <?php echo htmlspecialchars($selected_case['respondent_name']); ?>
                            </p>
                        </div>
                        <div class="audit-case-actions">
                            <span class="badge <?php echo strtolower($selected_case['status']); ?>"><?php echo htmlspecialchars($selected_case['status']); ?></span>
                            <a href="/BMS/Lupon_Office/cases/view_case.php?id=<?php echo (int) $selected_case['id']; ?>" class="btn-cancel">View Case</a>
                        </div>
                    </div>

                    <?php if (empty($history_rows)): ?>
                        <div class="audit-empty-state">
                            <p>No changes have been recorded for this case yet.</p>
                        </div>
                    <?php else: ?>
                        <ul class="audit-timeline">
                            <?php foreach ($history_rows as $row): ?>
                                <li class="audit-timeline-item">
                                    <div class="audit-timeline-dot"></div>
                                    <div class="audit-timeline-content">
                                        <div class="audit-field-label"><?php echo htmlspecialchars(case_history_field_label($row['field_name'])); ?></div>
                                        <div class="audit-value-diff">
                                            <?php render_history_value($row['field_name'], $row['old_value'], $row['new_value']); ?>
                                        </div>
                                        <div class="audit-meta">
                                            <i class="fa fa-user"></i> <?php echo htmlspecialchars($row['changed_by']); ?>
                                            &middot;
                                            <i class="fa fa-clock"></i> <?php echo date("M j, Y g:i A", strtotime($row['changed_at'])); ?>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                <?php endif; ?>

            <?php elseif ($search_term !== ''): ?>

                <div class="table-container">
                    <div class="table-header">
                        <h3>Search Results</h3>
                    </div>
                    <?php if (empty($search_results)): ?>
                        <div class="audit-empty-state">
                            <p>No cases matched "<?php echo htmlspecialchars($search_term); ?>".</p>
                        </div>
                    <?php else: ?>
                        <div class="table-wrapper">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Case No</th>
                                        <th>Complainant</th>
                                        <th>Respondent</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($search_results as $row): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($row['case_no']); ?></td>
                                            <td><?php echo htmlspecialchars($row['complainant_name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['respondent_name']); ?></td>
                                            <td><span class="badge <?php echo strtolower($row['status']); ?>"><?php echo htmlspecialchars($row['status']); ?></span></td>
                                            <td>
                                                <a href="/BMS/Lupon_Office/audit/audit_trail.php?case_id=<?php echo (int) $row['id']; ?>" class="btn-view">View History</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

            <?php else: ?>

                <div class="table-container">
                    <div class="table-header">
                        <h3>Recent Activity</h3>
                    </div>
                    <?php if (empty($recent_activity)): ?>
                        <div class="audit-empty-state">
                            <p>No case changes have been recorded yet. Search above, or open a case from Case Management to get started.</p>
                        </div>
                    <?php else: ?>
                        <ul class="audit-timeline">
                            <?php foreach ($recent_activity as $row): ?>
                                <li class="audit-timeline-item">
                                    <div class="audit-timeline-dot"></div>
                                    <div class="audit-timeline-content">
                                        <a href="/BMS/Lupon_Office/audit/audit_trail.php?case_id=<?php echo (int) $row['case_id']; ?>" class="audit-case-link"><?php echo htmlspecialchars($row['case_no']); ?></a>
                                        <div class="audit-field-label"><?php echo htmlspecialchars(case_history_field_label($row['field_name'])); ?></div>
                                        <div class="audit-value-diff">
                                            <?php render_history_value($row['field_name'], $row['old_value'], $row['new_value']); ?>
                                        </div>
                                        <div class="audit-meta">
                                            <i class="fa fa-user"></i> <?php echo htmlspecialchars($row['changed_by']); ?>
                                            &middot;
                                            <i class="fa fa-clock"></i> <?php echo date("M j, Y g:i A", strtotime($row['changed_at'])); ?>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

            <?php endif; ?>

        </div>
    </div>
</div>

<script src="/BMS/Lupon_Office/assets/js/script.js"></script>
</body>
</html>
