<?php
session_start();


/* =========================================================
   CSRF TOKEN
   ========================================================= */

if (
    empty($_SESSION['csrf_token']) ||
    !is_string($_SESSION['csrf_token'])
) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];


/* =========================================================
   CHECK IF USER IS LOGGED IN
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


require_once '../BACKEND/db_connect.php';


/* =========================================================
   VERIFY CLEARANCE OFFICER FROM DATABASE
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
        "Clearance edit-profile officer query prepare failed: " .
        $conn->error
    );

    http_response_code(500);
    exit("Unable to load profile.");
}

$stmt->bind_param("i", $official_id);

if (!$stmt->execute()) {

    error_log(
        "Clearance edit-profile officer query execute failed: " .
        $stmt->error
    );

    $stmt->close();

    http_response_code(500);
    exit("Unable to load profile.");
}

$officer_result = $stmt->get_result();

if (
    !$officer_result ||
    $officer_result->num_rows !== 1
) {
    $stmt->close();

    header("Location: /BMS/CODES/login.php?error=1");
    exit();
}

$officer = $officer_result->fetch_assoc();

$stmt->close();


/* =========================================================
   OFFICER DISPLAY INFORMATION
   ========================================================= */

$user_full_name = $officer['name'] ?? 'Officer';


/* =========================================================
   PROFILE PICTURE
   ========================================================= */

/*
 * Only store/display the filename.
 * basename() prevents a database value such as:
 *
 * ../../some-other-file.php
 *
 * from being used as a filesystem path.
 */

$profile_picture = "../IMAGES/default-avatar.png";

if (!empty($officer['picture_profile'])) {

    $picture_filename = basename(
        trim((string)$officer['picture_profile'])
    );

    if (
        $picture_filename !== '' &&
        $picture_filename !== '.' &&
        $picture_filename !== '..'
    ) {

        $picture_path =
            "../IMAGES/" . $picture_filename;

        $picture_filesystem_path =
            __DIR__ .
            DIRECTORY_SEPARATOR .
            "IMAGES" .
            DIRECTORY_SEPARATOR .
            $picture_filename;

        if (is_file($picture_filesystem_path)) {
            $profile_picture = $picture_path;
        }
    }
}


/* =========================================================
   SAFE DISPLAY VALUES
   ========================================================= */

$display_name = htmlspecialchars(
    (string)($officer['name'] ?? ''),
    ENT_QUOTES,
    'UTF-8'
);

$display_username = htmlspecialchars(
    (string)($officer['username'] ?? ''),
    ENT_QUOTES,
    'UTF-8'
);

$display_email = htmlspecialchars(
    (string)($officer['email'] ?? ''),
    ENT_QUOTES,
    'UTF-8'
);

$display_contact = htmlspecialchars(
    (string)($officer['contact_number'] ?? ''),
    ENT_QUOTES,
    'UTF-8'
);

$display_position = htmlspecialchars(
    (string)($officer['position'] ?? ''),
    ENT_QUOTES,
    'UTF-8'
);

$display_full_name = htmlspecialchars(
    (string)$user_full_name,
    ENT_QUOTES,
    'UTF-8'
);

$display_profile_picture = htmlspecialchars(
    $profile_picture,
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

    <title>EDIT PROFILE - Clearance Officer</title>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
    >

    <link
        rel="stylesheet"
        href="/style/dashboard.css"
    >

    <style>

        .edit-profile-wrapper {
            max-width: 900px;
            margin: 0 auto;
        }

        .edit-profile-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .page-title {
            font-size: 28px;
            font-weight: 700;
            color: #2c3e93;
            margin-bottom: 30px;
        }

        /* PROFILE PICTURE SECTION */

        .profile-picture-section {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px;
            margin-bottom: 30px;
            background: #f8f9fa;
            border: 2px dashed #e0e0e0;
            border-radius: 10px;
            transition: all 0.3s;
        }

        .profile-picture-section:hover {
            border-color: #2c3e93;
            background: #f0f3ff;
        }

        .profile-picture-preview {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 20px;
            border: 4px solid white;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            background: #e0e0e0;
        }

        .profile-picture-input {
            display: none;
        }

        .btn-choose-photo {
            background: #5b8def;
            color: white;
            padding: 10px 25px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-choose-photo:hover {
            background: #2c3e93;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(44, 62, 147, 0.2);
        }

        .file-name {
            font-size: 13px;
            color: #999;
            margin-top: 10px;
        }

        .form-section {
            margin-bottom: 30px;
        }

        .form-section-title {
            font-size: 16px;
            font-weight: 600;
            color: #2c3e93;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e0e0e0;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(
                auto-fit,
                minmax(280px, 1fr)
            );
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-size: 12px;
            color: #999;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 8px;
            letter-spacing: 0.5px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #2c3e93;
            box-shadow:
                0 0 0 3px rgba(44, 62, 147, 0.1);
        }

        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #e0e0e0;
        }

        .btn-save {
            background: #2c3e93;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-save:hover {
            background: #1a2558;
            transform: translateY(-2px);
            box-shadow:
                0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .btn-cancel {
            background: #e0e0e0;
            color: #333;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-cancel:hover {
            background: #d0d0d0;
        }

        .alert {
            padding: 12px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: none;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            display: block;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            display: block;
        }

        @media (max-width: 768px) {

            .edit-profile-container {
                padding: 20px;
            }

            .page-title {
                font-size: 22px;
            }

            .profile-picture-section {
                padding: 30px 20px;
            }

            .profile-picture-preview {
                width: 120px;
                height: 120px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-actions {
                flex-direction: column;
            }

            .btn-save,
            .btn-cancel {
                width: 100%;
                justify-content: center;
            }
        }

        /* TOPBAR */

        .topbar {
            background: #ffffff;
            padding: 15px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow:
                0 2px 10px rgba(0, 0, 0, 0.05);
            border-radius: 0;
            margin-bottom: 0;
        }

        .logo-area {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .topbar-logo {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #2c3e93;
        }

        /* FIX HEADER LOGO */

        .topbar .logo1 {
            height: 45px;
            width: auto;
            display: none;
        }

        .system-title {
            font-size: 20px;
            font-weight: 600;
            color: #2c3e93;
            margin: 0;
            white-space: nowrap;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 30px;
        }

        .clock {
            font-size: 14px;
            color: #666;
            min-width: 120px;
            text-align: right;
        }

    </style>

</head>


<body>

<div class="contentwrapper">

    <?php include 'sidebar.php'; ?>


    <div class="main">


        <!-- TOPBAR -->

        <div class="topbar">

            <div class="logo-area">

                <img
                    src="../IMAGES/silogo.png"
                    alt="Logo"
                    class="topbar-logo"
                >

                <h2 class="system-title">
                    Welcome,
                    <?php echo $display_full_name; ?>
                </h2>

            </div>


            <div class="topbar-right">

                <div
                    class="clock"
                    id="clock"
                ></div>

            </div>

        </div>


        <!-- CONTENT -->

        <div class="content">

            <div class="edit-profile-wrapper">

                <div class="edit-profile-container">

                    <h1 class="page-title">
                        <i class="fas fa-edit"></i>
                        Edit My Profile
                    </h1>


                    <!-- =====================================================
                         PROFILE UPDATE FORM
                         ===================================================== -->

                    <form
                        method="POST"
                        action="update-profile.php"
                        enctype="multipart/form-data"
                        autocomplete="off"
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


                        <!-- PROFILE PICTURE SECTION -->

                        <div class="profile-picture-section">

                            <img
                                id="profilePreview"
                                src="<?php echo $display_profile_picture; ?>"
                                alt="Profile Picture"
                                class="profile-picture-preview"
                            >


                            <button
                                type="button"
                                class="btn-choose-photo"
                                onclick="document.getElementById('profilePictureInput').click()"
                            >
                                Choose Photo
                            </button>


                            <input
                                type="file"
                                id="profilePictureInput"
                                name="picture_profile"
                                class="profile-picture-input"
                                accept="image/jpeg,image/png,image/gif"
                            >


                            <div
                                class="file-name"
                                id="fileName"
                            >
                                No file chosen
                            </div>

                        </div>


                        <!-- PERSONAL INFORMATION -->

                        <div class="form-section">

                            <h2 class="form-section-title">
                                Personal Information
                            </h2>


                            <div class="form-grid">


                                <div class="form-group">

                                    <label for="name">
                                        Full Name
                                    </label>

                                    <input
                                        type="text"
                                        id="name"
                                        name="name"
                                        value="<?php echo $display_name; ?>"
                                        maxlength="150"
                                        autocomplete="name"
                                        required
                                    >

                                </div>


                                <div class="form-group">

                                    <label for="username">
                                        Username
                                    </label>

                                    <input
                                        type="text"
                                        id="username"
                                        name="username"
                                        value="<?php echo $display_username; ?>"
                                        minlength="3"
                                        maxlength="100"
                                        autocomplete="username"
                                        required
                                    >

                                </div>


                                <div class="form-group">

                                    <label for="email">
                                        Email Address
                                    </label>

                                    <input
                                        type="email"
                                        id="email"
                                        name="email"
                                        value="<?php echo $display_email; ?>"
                                        maxlength="150"
                                        autocomplete="email"
                                        required
                                    >

                                </div>


                                <div class="form-group">

                                    <label for="contact_number">
                                        Contact Number
                                    </label>

                                    <input
                                        type="text"
                                        id="contact_number"
                                        name="contact_number"
                                        value="<?php echo $display_contact; ?>"
                                        maxlength="50"
                                        autocomplete="tel"
                                        required
                                    >

                                </div>


                                <div class="form-group">

                                    <label for="position">
                                        Position
                                    </label>

                                    <input
                                        type="text"
                                        id="position"
                                        name="position"
                                        value="<?php echo $display_position; ?>"
                                        maxlength="150"
                                        required
                                    >

                                </div>


                            </div>

                        </div>


                        <!-- CHANGE PASSWORD -->

                        <div class="form-section">

                            <h2 class="form-section-title">
                                Change Password (Optional)
                            </h2>


                            <div class="form-grid">


                                <div class="form-group">

                                    <label for="current_password">
                                        Current Password
                                    </label>

                                    <input
                                        type="password"
                                        id="current_password"
                                        name="current_password"
                                        placeholder="Enter current password"
                                        autocomplete="current-password"
                                    >

                                </div>


                                <div class="form-group">

                                    <label for="new_password">
                                        New Password
                                    </label>

                                    <input
                                        type="password"
                                        id="new_password"
                                        name="new_password"
                                        placeholder="Enter new password"
                                        minlength="8"
                                        maxlength="255"
                                        autocomplete="new-password"
                                    >

                                </div>


                                <div class="form-group">

                                    <label for="confirm_password">
                                        Confirm New Password
                                    </label>

                                    <input
                                        type="password"
                                        id="confirm_password"
                                        name="confirm_password"
                                        placeholder="Confirm new password"
                                        minlength="8"
                                        maxlength="255"
                                        autocomplete="new-password"
                                    >

                                </div>


                            </div>

                        </div>


                        <!-- FORM ACTIONS -->

                        <div class="form-actions">

                            <button
                                type="submit"
                                class="btn-save"
                            >
                                <i class="fas fa-save"></i>
                                Save Changes
                            </button>


                            <a
                                href="profile.php"
                                class="btn-cancel"
                            >
                                <i class="fas fa-times"></i>
                                Cancel
                            </a>

                        </div>


                    </form>

                </div>

            </div>

        </div>

    </div>

</div>


<script src="./style/script.js"></script>


</body>
</html>

