<?php require_once('dashboard_backend.php');

include 'session_time-out.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DASHBOARD - Clearance</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="./style/dashboard.css">
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main">
    <div class="topbar">
        <div class="logo-area">
            <img src="../IMAGES/silogo.png" alt="Logo" class="topbar-logo">
            <h2 class="system-title">Welcome, <?php echo htmlspecialchars($user_full_name); ?></h2>
        </div>
        <div class="topbar-right">
            <div class="clock" id="clock"></div>
            <div class="notification-bell">
                <i class="fas fa-bell"></i>
                <span class="badge"><?php echo $pending_count; ?></span>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="greeting-section">
            <h1 class="greeting-title"><?php echo $greeting; ?></h1>
            <p class="greeting-subtitle">What can we help you today?</p>
        </div>

        <div class="quick-actions">
            <a href="requests.php" class="action-card">
                <div class="action-icon"><i class="fas fa-file-circle-plus"></i></div>
                <h3 class="action-title">View Requests</h3>
                <p class="action-description">View and manage all document requests</p>
            </a>

            <a href="requests.php?filter=Pending" class="action-card">
                <div class="action-icon"><i class="fas fa-magnifying-glass"></i></div>
                <h3 class="action-title">Track Status</h3>
                <p class="action-description">View real-time status updates</p>
            </a>

            <a href="profile.php" class="action-card">
                <div class="action-icon"><i class="fas fa-user-gear"></i></div>
                <h3 class="action-title">Profile</h3>
                <p class="action-description">Update your profile information</p>
            </a>
        </div>

        <div class="dashboard-cards">
            <div class="card-box">
                <h3><i class="fas fa-chart-bar"></i> TOTAL REQUESTS</h3>
                <p><?php echo $total_requests; ?></p>
            </div>
            <div class="card-box">
                <h3><i class="fas fa-clock"></i> PENDING</h3>
                <p><?php echo $pending_count; ?></p>
            </div>
            <div class="card-box">
                <h3><i class="fas fa-check-circle"></i> APPROVED</h3>
                <p><?php echo $approved_count; ?></p>
            </div>
            <div class="card-box">
                <h3><i class="fas fa-times-circle"></i> REJECTED</h3>
                <p><?php echo $rejected_count; ?></p>
            </div>
        </div>

        <div class="recent-section">
            <h2 class="recent-title"><i class="fas fa-history"></i> Recent Requests</h2>

            <?php if (count($recent_requests) > 0): ?>
                <div class="table-responsive">
                    <table class="requests-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Request Type</th>
                                <th>Purpose</th>
                                <th>Status</th>
                                <th>Request Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_requests as $request): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($request['fullname'] ?? 'Unknown Resident'); ?></strong>
                                    </td>
                                    <td>
                                        <span class="type-badge">
                                            <?php echo htmlspecialchars($request['request_type'] ?? 'Unknown Document'); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($request['purpose'] ?? ''); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower($request['status']); ?>">
                                            <?php echo htmlspecialchars($request['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        if (!empty($request['submitted_at'])) {
                                            echo date('M d, Y h:i A', strtotime($request['submitted_at']));
                                        } elseif (!empty($request['request_date'])) {
                                            echo date('M d, Y', strtotime($request['request_date']));
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <a href="requests.php" class="action-link">
                                            <i class="fas fa-eye"></i> View All
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-inbox" style="font-size: 48px; color: #ddd; margin-bottom: 15px; display: block;"></i>
                    <p>No recent requests found.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
    <script src="./style/script.js"></script>

</body>
</html>