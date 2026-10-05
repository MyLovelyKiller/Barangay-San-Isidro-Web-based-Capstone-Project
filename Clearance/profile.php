<?php

/* =========================================================
   PROFILE BACKEND
   Handles:
   - Authentication
   - Clearance officer verification
   - Attendance
   - Officer information
   ========================================================= */

require_once 'profile_backend.php';


/* =========================================================
   SESSION TIMEOUT
   ========================================================= */

include 'session_time-out.php';


/* =========================================================
   SAFE DISPLAY VALUES
   ========================================================= */

$display_name = htmlspecialchars(
    (string)($user_full_name ?? 'Officer'),
    ENT_QUOTES,
    'UTF-8'
);

$display_officer_name = htmlspecialchars(
    (string)($officer['name'] ?? 'Officer'),
    ENT_QUOTES,
    'UTF-8'
);

$display_department = htmlspecialchars(
    (string)($officer['department'] ?? 'N/A'),
    ENT_QUOTES,
    'UTF-8'
);

$display_status = htmlspecialchars(
    (string)($officer['status'] ?? 'N/A'),
    ENT_QUOTES,
    'UTF-8'
);

$display_official_id = htmlspecialchars(
    (string)($officer['official_id'] ?? 'N/A'),
    ENT_QUOTES,
    'UTF-8'
);

$display_username = htmlspecialchars(
    (string)($officer['username'] ?? 'N/A'),
    ENT_QUOTES,
    'UTF-8'
);

$display_contact = htmlspecialchars(
    (string)($officer['contact_number'] ?? 'N/A'),
    ENT_QUOTES,
    'UTF-8'
);

$display_email = htmlspecialchars(
    (string)($officer['email'] ?? 'N/A'),
    ENT_QUOTES,
    'UTF-8'
);


/* =========================================================
   PROFILE PICTURE
   ========================================================= */

/*
 * Only use the basename of the stored filename.
 * This prevents a malicious database value such as:
 *
 * ../../some-file.php
 *
 * from escaping the IMAGES directory.
 */

$profile_image_url = '';

if (!empty($officer['picture_profile'])) {

    $picture_filename = basename(
        trim((string)$officer['picture_profile'])
    );

    if (
        $picture_filename !== '' &&
        $picture_filename !== '.' &&
        $picture_filename !== '..'
    ) {

        $picture_filesystem_path =
            __DIR__ .
            DIRECTORY_SEPARATOR .
            'IMAGES' .
            DIRECTORY_SEPARATOR .
            $picture_filename;

        if (is_file($picture_filesystem_path)) {

            $profile_image_url =
                "../IMAGES/" .
                rawurlencode($picture_filename);
        }
    }
}


/* =========================================================
   DATE JOINED
   ========================================================= */

$date_joined = 'N/A';

if (!empty($officer['created_at'])) {

    $timestamp = strtotime(
        (string)$officer['created_at']
    );

    if ($timestamp !== false) {

        $date_joined = date(
            'M d, Y',
            $timestamp
        );
    }
}

$display_date_joined = htmlspecialchars(
    $date_joined,
    ENT_QUOTES,
    'UTF-8'
);


/* =========================================================
   CSRF TOKEN
   ========================================================= */

$csrf_token = htmlspecialchars(
    (string)($_SESSION['csrf_token'] ?? ''),
    ENT_QUOTES,
    'UTF-8'
);

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>PROFILE - Clearance Officer</title>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
    >

    <link
        rel="stylesheet"
        href="./style/dashboard.css"
    >

    <link
        rel="stylesheet"
        href="./style/profile.css"
    >

</head>


<body>


<?php include 'sidebar.php'; ?>


<div class="main">


    <!-- =====================================================
         TOPBAR
         ===================================================== -->

    <div class="topbar">

        <div class="logo-area">

            <img
                src="../IMAGES/silogo.png"
                alt="Logo"
                class="topbar-logo"
            >

            <h2 class="system-title">
                Welcome,
                <?php echo $display_name; ?>
            </h2>

        </div>


        <div class="topbar-right">

            <div
                class="clock"
                id="clock"
            ></div>

        </div>

    </div>


    <!-- =====================================================
         CONTENT
         ===================================================== -->

    <div class="content">

        <div class="profile-wrapper">


            <!-- =================================================
                 PROFILE HEADER
                 ================================================= -->

            <div class="profile-header">


                <!-- PROFILE AVATAR -->

                <div class="profile-avatar-section">

                    <div
                        class="profile-avatar <?php echo empty($profile_image_url) ? 'no-image' : ''; ?>"
                    >

                        <?php if (!empty($profile_image_url)): ?>

                            <img
                                src="<?php echo htmlspecialchars(
                                    $profile_image_url,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>"
                                alt="Profile Picture"
                            >

                        <?php else: ?>

                            <i class="fas fa-user"></i>

                        <?php endif; ?>

                    </div>

                </div>


                <!-- PROFILE INFORMATION -->

                <div class="profile-info">


                    <h1 class="profile-name">
                        <?php echo $display_officer_name; ?>
                    </h1>


                    <p class="profile-position">
                        Department -
                        <?php echo $display_department; ?>
                        Officer
                    </p>


                    <div class="profile-meta">

                        <div class="meta-item">

                            <span class="meta-label">
                                Status
                            </span>

                            <span class="meta-value">
                                <?php echo $display_status; ?>
                            </span>

                        </div>

                    </div>


                    <!-- =================================================
                         PROFILE ACTIONS
                         ================================================= -->

                    <div class="profile-actions">


                        <?php if ($attendance_status === "time_in"): ?>

                            <!-- TIME IN -->

                            <form
                                method="POST"
                                style="display: inline;"
                            >

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?php echo $csrf_token; ?>"
                                >

                                <button
                                    type="submit"
                                    name="attendance_action"
                                    value="time_in"
                                    class="btn-attendance btn-time-in"
                                >

                                    <i class="fas fa-sign-in-alt"></i>
                                    Time In

                                </button>

                            </form>


                        <?php elseif ($attendance_status === "time_out"): ?>

                            <!-- TIME OUT -->

                            <form
                                method="POST"
                                style="display: inline;"
                            >

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?php echo $csrf_token; ?>"
                                >

                                <button
                                    type="submit"
                                    name="attendance_action"
                                    value="time_out"
                                    class="btn-attendance btn-time-out"
                                >

                                    <i class="fas fa-sign-out-alt"></i>
                                    Time Out

                                </button>

                            </form>


                        <?php else: ?>

                            <!-- SHIFT COMPLETED -->

                            <button
                                type="button"
                                class="btn-attendance"
                                style="
                                    background: #28a745;
                                    cursor: default;
                                    border: none;
                                    color: white;
                                "
                                disabled
                            >

                                <i class="fas fa-check-double"></i>
                                Shift Completed

                            </button>

                        <?php endif; ?>


                        <!-- EDIT PROFILE -->

                        <a
                            href="edit-profile.php"
                            class="btn-edit-profile"
                        >

                            <i class="fas fa-edit"></i>
                            Edit Profile

                        </a>


                    </div>

                </div>

            </div>


            <!-- =================================================
                 PROFILE DETAILS
                 ================================================= -->

            <div class="profile-details">


                <h2 class="section-title">

                    <i class="fas fa-info-circle"></i>
                    Official Information

                </h2>


                <div class="details-grid">


                    <!-- =================================================
                         COLUMN 1
                         ================================================= -->

                    <div class="detail-column">


                        <div class="detail-item">

                            <span class="detail-label">
                                Full Name
                            </span>

                            <span class="detail-value">
                                <?php echo $display_officer_name; ?>
                            </span>

                        </div>


                        <div class="detail-item">

                            <span class="detail-label">
                                Official ID
                            </span>

                            <span class="detail-value">
                                <?php echo $display_official_id; ?>
                            </span>

                        </div>


                        <div class="detail-item">

                            <span class="detail-label">
                                Username
                            </span>

                            <span class="detail-value">
                                <?php echo $display_username; ?>
                            </span>

                        </div>


                    </div>


                    <!-- =================================================
                         COLUMN 2
                         ================================================= -->

                    <div class="detail-column">


                        <div class="detail-item">

                            <span class="detail-label">
                                Department
                            </span>

                            <span class="detail-value">
                                <?php echo $display_department; ?>
                            </span>

                        </div>


                        <div class="detail-item">

                            <span class="detail-label">
                                Contact Number
                            </span>

                            <span class="detail-value">
                                <?php echo $display_contact; ?>
                            </span>

                        </div>


                    </div>


                    <!-- =================================================
                         COLUMN 3
                         ================================================= -->

                    <div class="detail-column">


                        <div class="detail-item">

                            <span class="detail-label">
                                Email Address
                            </span>

                            <span class="detail-value">
                                <?php echo $display_email; ?>
                            </span>

                        </div>


                        <div class="detail-item">

                            <span class="detail-label">
                                Date Joined
                            </span>

                            <span class="detail-value">
                                <?php echo $display_date_joined; ?>
                            </span>

                        </div>


                    </div>


                </div>

            </div>


        </div>

    </div>


</div>


<script src="./style/script.js"></script>


</body>
</html>

