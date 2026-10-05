<?php
require_once 'request_backend.php';
include 'session_time-out.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>REQUESTS - Clearance Officer</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="./style/dashboard.css">
    <link rel="stylesheet" href="./style/request.css">
</head>

<body>

<?php include 'sidebar.php'; ?>

<div class="main">

    <!-- =====================================================
         TOP BAR
         ===================================================== -->
    <div class="topbar">

        <div class="logo-area">
            <img src="../IMAGES/silogo.png" alt="Logo" class="topbar-logo">

            <h2 class="system-title">
                Welcome,
                <?= htmlspecialchars($user_full_name, ENT_QUOTES, 'UTF-8'); ?>
            </h2>
        </div>

        <div class="topbar-right">

            <div class="clock" id="clock"></div>

            <div class="notification-bell">
                <i class="fas fa-bell"></i>
                <span class="badge">
                    <?= (int)$pending_count; ?>
                </span>
            </div>

        </div>
    </div>


    <!-- =====================================================
         CONTENT
         ===================================================== -->
    <div class="content">

        <div class="requests-header">
            <h1>
                <i class="fas fa-file-alt"></i>
                All Requests
            </h1>
        </div>


        <!-- =================================================
             FILTER / SEARCH
             ================================================= -->
        <div class="filter-search-section">

            <form method="GET" class="filter-form">

                <div class="search-box">

                    <input
                        type="text"
                        name="search"
                        placeholder="Search by name..."
                        value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>"
                    >

                    <i class="fas fa-search"></i>

                </div>

                <input
                    type="hidden"
                    name="filter"
                    value="<?= htmlspecialchars($filter, ENT_QUOTES, 'UTF-8'); ?>"
                >

                <div class="filter-buttons">

                    <a
                        href="requests.php?filter=all&search=<?= urlencode($search); ?>"
                        class="filter-btn <?= ($filter === 'all') ? 'active' : ''; ?>"
                    >
                        All
                    </a>

                    <a
                        href="requests.php?filter=Pending&search=<?= urlencode($search); ?>"
                        class="filter-btn <?= ($filter === 'Pending') ? 'active' : ''; ?>"
                    >
                        Pending
                    </a>

                    <a
                        href="requests.php?filter=Approved&search=<?= urlencode($search); ?>"
                        class="filter-btn <?= ($filter === 'Approved') ? 'active' : ''; ?>"
                    >
                        Approved
                    </a>

                    <a
                        href="requests.php?filter=Rejected&search=<?= urlencode($search); ?>"
                        class="filter-btn <?= ($filter === 'Rejected') ? 'active' : ''; ?>"
                    >
                        Rejected
                    </a>

                </div>

            </form>

        </div>


        <!-- =================================================
             STATISTICS
             ================================================= -->
        <div class="stats-summary">

            <div class="stat-box">
                <div class="stat-label">Total Requests</div>
                <div class="stat-value">
                    <?= (int)$total_requests; ?>
                </div>
            </div>

            <div class="stat-box">
                <div class="stat-label">Pending</div>
                <div class="stat-value pending-color">
                    <?= (int)$pending_count; ?>
                </div>
            </div>

            <div class="stat-box">
                <div class="stat-label">Approved</div>
                <div class="stat-value approved-color">
                    <?= (int)$approved_count; ?>
                </div>
            </div>

            <div class="stat-box">
                <div class="stat-label">Rejected</div>
                <div class="stat-value rejected-color">
                    <?= (int)$rejected_count; ?>
                </div>
            </div>

        </div>


        <!-- =================================================
             REQUEST CARDS
             ================================================= -->
        <div class="requests-container">

            <?php if (count($filtered_requests) > 0): ?>

                <?php foreach ($filtered_requests as $request): ?>

                    <?php
                    /*
                     * Only allow known status values to become CSS
                     * classes. This prevents arbitrary database
                     * content from being inserted into the class
                     * attribute.
                     */
                    $status = trim($request['status'] ?? '');

                    $status_class_map = [
                        'Pending' => 'status-pending',
                        'Pending (Pay at Hall)' => 'status-pending',
                        'Pending Verification' => 'status-pending',
                        'Approved' => 'status-approved',
                        'Rejected' => 'status-rejected'
                    ];

                    $status_class = $status_class_map[$status] ?? 'status-pending';

                    /*
                     * JSON encode the request type so it can safely
                     * be passed into the JavaScript modal functions.
                     */
                    $request_type = $request['request_type'] ?? '';
                    $request_type_json = json_encode(
                        $request_type,
                        JSON_HEX_TAG |
                        JSON_HEX_APOS |
                        JSON_HEX_QUOT |
                        JSON_HEX_AMP
                    );

                    /*
                     * Safely encode the complete request object
                     * for viewDetails().
                     */
                    $request_json = json_encode(
                        $request,
                        JSON_HEX_TAG |
                        JSON_HEX_APOS |
                        JSON_HEX_QUOT |
                        JSON_HEX_AMP
                    );
                    ?>

                    <div class="request-card">

                        <!-- =====================================
                             CARD HEADER
                             ===================================== -->
                        <div class="request-card-header">

                            <div class="request-card-title">

                                <h3>
                                    <?= htmlspecialchars(
                                        $request['fullname'] ?? '',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>
                                </h3>

                                <span class="request-type-badge">
                                    <?= htmlspecialchars(
                                        $request['request_type'] ?? 'N/A',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>
                                </span>

                                <?php if (!empty($request['resident_id'])): ?>

                                    <span class="resident-id-badge">
                                        Resident ID:
                                        <?= htmlspecialchars(
                                            $request['resident_id'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ); ?>
                                    </span>

                                <?php endif; ?>

                            </div>

                            <span class="request-status <?= htmlspecialchars($status_class, ENT_QUOTES, 'UTF-8'); ?>">
                                <?= htmlspecialchars(
                                    $status,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>
                            </span>

                        </div>


                        <!-- =====================================
                             REQUEST DETAILS
                             ===================================== -->
                        <div class="request-details">

                            <div class="detail-item">
                                <span class="detail-label">Type</span>

                                <span class="detail-value">
                                    <?= htmlspecialchars(
                                        $request['type'] ?? 'N/A',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>
                                </span>
                            </div>


                            <div class="detail-item">
                                <span class="detail-label">Fee</span>

                                <span class="detail-value">
                                    ₱<?= isset($request['document_fee'])
                                        ? number_format((float)$request['document_fee'], 2)
                                        : '0.00'; ?>
                                </span>
                            </div>


                            <?php if (!empty($request['payment_method'])): ?>

                                <div class="payment-info-section">

                                    <div>
                                        <i class="fas fa-credit-card"></i>

                                        <strong>Method:</strong>

                                        <?= htmlspecialchars(
                                            $request['payment_method'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ); ?>
                                    </div>


                                    <?php if (!empty($request['ref_number'])): ?>

                                        <div>
                                            <i class="fas fa-hashtag"></i>

                                            <strong>Ref:</strong>

                                            <?= htmlspecialchars(
                                                $request['ref_number'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>
                                        </div>

                                    <?php endif; ?>

                                </div>

                            <?php endif; ?>


                            <div class="detail-item">

                                <span class="detail-label">
                                    Address
                                </span>

                                <span class="detail-value">
                                    <?= htmlspecialchars(
                                        $request['address'] ?? 'N/A',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>
                                </span>

                            </div>


                            <div class="detail-item">

                                <span class="detail-label">
                                    Purpose
                                </span>

                                <span class="detail-value">
                                    <?= htmlspecialchars(
                                        $request['purpose'] ?? '',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>
                                </span>

                            </div>


                            <div class="detail-item">

                                <span class="detail-label">
                                    Email
                                </span>

                                <span class="detail-value">
                                    <?= htmlspecialchars(
                                        $request['email'] ?? '',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>
                                </span>

                            </div>


                            <div class="detail-item">

                                <span class="detail-label">
                                    Phone
                                </span>

                                <span class="detail-value">
                                    <?= htmlspecialchars(
                                        $request['phone'] ?? '',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>
                                </span>

                            </div>


                            <div class="detail-item">

                                <span class="detail-label">
                                    Submitted
                                </span>

                                <span class="detail-value">

                                    <?php
                                    if (!empty($request['submitted_at'])) {
                                        echo htmlspecialchars(
                                            date(
                                                'M d, Y H:i',
                                                strtotime($request['submitted_at'])
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>

                                </span>

                            </div>


                            <?php
                            $pending_statuses = [
                                'Pending',
                                'Pending (Pay at Hall)',
                                'Pending Verification'
                            ];
                            ?>

                            <?php if (
                                !empty($request['updated_at']) &&
                                !in_array($status, $pending_statuses, true)
                            ): ?>

                                <div class="detail-item">

                                    <span class="detail-label">

                                        <?= ($status === 'Rejected')
                                            ? 'Rejected'
                                            : 'Approved'; ?>

                                    </span>

                                    <span class="detail-value">

                                        <?= htmlspecialchars(
                                            date(
                                                'M d, Y h:i A',
                                                strtotime($request['updated_at'])
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ); ?>

                                    </span>

                                </div>

                            <?php endif; ?>

                        </div>


                        <!-- =====================================
                             REASON / OFFICER NOTE
                             ===================================== -->
                        <?php if (!empty($request['reason_message'])): ?>

                            <?php
                            if ($status === 'Rejected') {
                                $reason_class = 'rejected';
                                $reason_label = '⛔ Rejection Reason:';
                            } elseif ($status === 'Approved') {
                                $reason_class = 'approved';
                                $reason_label = '✓ Approval Note:';
                            } else {
                                $reason_class = 'pending';
                                $reason_label = '⏳ Officer Note:';
                            }
                            ?>

                            <div class="reason-box <?= htmlspecialchars($reason_class, ENT_QUOTES, 'UTF-8'); ?>">

                                <div class="reason-label">
                                    <?= htmlspecialchars(
                                        $reason_label,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>
                                </div>

                                <div class="reason-text">
                                    <?= htmlspecialchars(
                                        $request['reason_message'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>
                                </div>

                            </div>

                        <?php endif; ?>


                        <!-- =====================================
                             ACTION BUTTONS
                             ===================================== -->
                        <div class="request-actions">

                            <button
                                type="button"
                                class="action-btn btn-view"
                                onclick='viewDetails(<?= $request_json; ?>)'
                            >
                                <i class="fas fa-eye"></i>
                                View Details
                            </button>


                            <?php if (
                                in_array(
                                    $status,
                                    [
                                        'Pending',
                                        'Pending (Pay at Hall)',
                                        'Pending Verification'
                                    ],
                                    true
                                )
                            ): ?>

                                <button
                                    type="button"
                                    class="action-btn btn-approve"
                                    onclick='openApprovalModal(<?= (int)$request['request_id']; ?>, <?= $request_type_json; ?>)'
                                >
                                    <i class="fas fa-check"></i>
                                    Approve
                                </button>


                                <button
                                    type="button"
                                    class="action-btn btn-reject"
                                    onclick='openRejectionModal(<?= (int)$request['request_id']; ?>, <?= $request_type_json; ?>)'
                                >
                                    <i class="fas fa-times"></i>
                                    Reject
                                </button>

                            <?php endif; ?>

                        </div>

                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <div class="no-requests">

                    <i class="fas fa-inbox"></i>

                    <h2>No Requests Found</h2>

                    <p>
                        There are no requests matching your filter criteria.
                    </p>

                </div>

            <?php endif; ?>

        </div>

    </div>
</div>


<!-- =========================================================
     DETAILS MODAL
     ========================================================= -->
<div id="detailsModal" class="modal">

    <div class="modal-content">

        <div class="modal-header">

            <h2 id="modalTitle">
                Request Details
            </h2>

            <button
                type="button"
                class="modal-close"
                onclick="closeModal('detailsModal')"
                aria-label="Close"
            >
                &times;
            </button>

        </div>

        <div id="modalContent"></div>

    </div>

</div>


<!-- =========================================================
     APPROVAL MODAL
     ========================================================= -->
<div id="approvalModal" class="modal">

    <div class="modal-content">

        <div class="modal-header">

            <h2>
                <i class="fas fa-check"></i>
                Approve Request
            </h2>

            <button
                type="button"
                class="modal-close"
                onclick="closeModal('approvalModal')"
                aria-label="Close"
            >
                &times;
            </button>

        </div>


        <form
            id="approvalForm"
            onsubmit="submitApproval(event)"
        >

            <input
                type="hidden"
                id="approvalId"
                name="id"
            >

            <input
                type="hidden"
                id="approvalType"
                name="type"
            >

            <input
                type="hidden"
                name="action"
                value="approve"
            >

            <!-- CSRF TOKEN -->
            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(
                    $csrf_token,
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>"
            >


            <div class="form-group">

                <label>
                    Approval Note (Optional)
                </label>

                <textarea
                    name="reason_message"
                    id="approvalMessage"
                    placeholder="Add any additional notes or remarks (optional)..."
                    maxlength="2000"
                ></textarea>

            </div>


            <div class="modal-actions">

                <button
                    type="button"
                    class="modal-btn btn-cancel"
                    onclick="closeModal('approvalModal')"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    class="modal-btn btn-submit"
                >
                    Approve Request
                </button>

            </div>

        </form>

    </div>

</div>


<!-- =========================================================
     REJECTION MODAL
     ========================================================= -->
<div id="rejectionModal" class="modal">

    <div class="modal-content">

        <div class="modal-header">

            <h2>
                <i class="fas fa-times"></i>
                Reject Request
            </h2>

            <button
                type="button"
                class="modal-close"
                onclick="closeModal('rejectionModal')"
                aria-label="Close"
            >
                &times;
            </button>

        </div>


        <form
            id="rejectionForm"
            onsubmit="submitRejection(event)"
        >

            <input
                type="hidden"
                id="rejectionId"
                name="id"
            >

            <input
                type="hidden"
                id="rejectionType"
                name="type"
            >

            <input
                type="hidden"
                name="action"
                value="reject"
            >

            <!-- CSRF TOKEN -->
            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(
                    $csrf_token,
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>"
            >


            <div class="form-group">

                <label class="required-label">
                    Rejection Reason
                    <span>*</span>
                </label>

                <textarea
                    name="reason_message"
                    id="rejectionMessage"
                    placeholder="Please explain why this request is being rejected..."
                    maxlength="2000"
                    required
                ></textarea>

                <small class="helper-text">
                    This message will be sent to the applicant.
                    Be clear and helpful.
                </small>

            </div>


            <div class="modal-actions">

                <button
                    type="button"
                    class="modal-btn btn-cancel"
                    onclick="closeModal('rejectionModal')"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    class="modal-btn btn-submit reject-submit"
                >
                    Reject Request
                </button>

            </div>

        </form>

    </div>

</div>


<script src="./style/script.js"></script>

</body>
</html>
