<?php
session_start();
require_once __DIR__ . '/../includes/db_connect.php';

/* CSRF */
if (!isset($_SESSION['csrf_token'])) {
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

$official_id = $_SESSION['official_id'];
$page = 'profile.php';

/* Fetch current data */
$query = "SELECT * FROM officials WHERE official_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $official_id);
$stmt->execute();
$officer = $stmt->get_result()->fetch_assoc();
$stmt->close();

$profile_picture = (!empty($officer['picture_profile']))
    ? "/BMS/IMAGES/" . basename($officer['picture_profile'])
    : "/BMS/IMAGES/default-avatar.png";

$error_message = isset($_GET['error']) ? trim($_GET['error']) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - Lupon Office</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="/BMS/Lupon_Office/assets/css/edit_profile.css">
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
                <div class="edit-profile-wrapper">
                    <div class="edit-profile-container">
                        <h1 class="page-title">
                            <i class="fas fa-edit"></i> Edit My Profile
                        </h1>

                        <?php if ($error_message !== ''): ?>
                            <div class="alert alert-error">
                                <?= htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        <?php endif; ?>

                        <form
                            method="POST"
                            action="/BMS/Lupon_Office/profile/update_profile.php"
                            enctype="multipart/form-data"
                            id="editProfileForm"
                        >
                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>"
                            >

                            <!-- PROFILE PICTURE -->
                            <div class="profile-picture-section">
                                <img
                                    id="profilePreview"
                                    src="<?= htmlspecialchars($profile_picture, ENT_QUOTES, 'UTF-8') ?>"
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

                                <div class="file-name" id="fileName">
                                    No file chosen
                                </div>
                            </div>

                            <!-- PERSONAL INFORMATION -->
                            <div class="form-section">
                                <h2 class="form-section-title">Personal Information</h2>

                                <div class="form-grid">
                                    <div class="form-group">
                                        <label>Full Name</label>
                                        <input
                                            type="text"
                                            name="name"
                                            value="<?= htmlspecialchars($officer['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                            required
                                        />
                                    </div>

                                    <div class="form-group">
                                        <label>Username</label>
                                        <input
                                            type="text"
                                            name="username"
                                            value="<?= htmlspecialchars($officer['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                            required
                                        />
                                    </div>

                                    <div class="form-group">
                                        <label>Email Address</label>
                                        <input
                                            type="email"
                                            name="email"
                                            value="<?= htmlspecialchars($officer['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                            required
                                        />
                                    </div>

                                    <div class="form-group">
                                        <label>Contact Number</label>
                                        <input
                                            type="text"
                                            name="contact_number"
                                            value="<?= htmlspecialchars($officer['contact_number'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                            required
                                        />
                                    </div>

                                    <div class="form-group">
                                        <label>Position</label>
                                        <input
                                            type="text"
                                            name="position"
                                            value="<?= htmlspecialchars($officer['position'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                            required
                                        />
                                    </div>
                                </div>
                            </div>

                            <!-- CHANGE PASSWORD -->
                            <div class="form-section">
                                <h2 class="form-section-title">Change Password (Optional)</h2>

                                <div class="form-grid">
                                    <div class="form-group">
                                        <label>Current Password</label>
                                        <input
                                            type="password"
                                            name="current_password"
                                            id="currentPassword"
                                            placeholder="Enter current password"
                                            autocomplete="current-password"
                                        />
                                    </div>

                                    <div class="form-group">
                                        <label>New Password</label>
                                        <input
                                            type="password"
                                            name="new_password"
                                            id="newPassword"
                                            placeholder="Enter new password"
                                            autocomplete="new-password"
                                        />
                                    </div>

                                    <div class="form-group">
                                        <label>Confirm New Password</label>
                                        <input
                                            type="password"
                                            name="confirm_password"
                                            id="confirmPassword"
                                            placeholder="Confirm new password"
                                            autocomplete="new-password"
                                        />
                                    </div>
                                </div>
                            </div>

                            <!-- FORM ACTIONS -->
                            <div class="form-actions">
                                <button
                                    type="submit"
                                    class="btn-save"
                                    id="editProfileSaveBtn"
                                >
                                    <i class="fas fa-save"></i> Save Changes
                                </button>

                                <a
                                    href="/BMS/Lupon_Office/profile/profile.php"
                                    class="btn-cancel"
                                    id="editProfileCancel"
                                >
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="/BMS/Lupon_Office/assets/js/script.js"></script>

    <script>
        document.getElementById('profilePictureInput').addEventListener('change', function(e) {
            const file = e.target.files[0];

            if (file) {
                const reader = new FileReader();

                reader.onload = function(event) {
                    document.getElementById('profilePreview').src = event.target.result;
                };

                reader.readAsDataURL(file);
                document.getElementById('fileName').textContent = file.name;
            } else {
                document.getElementById('fileName').textContent = 'No file chosen';
            }
        });

        (function () {
            var form = document.getElementById('editProfileForm');
            if (!form) return;

            function serializeForm() {
                var data = new FormData(form);
                var pairs = [];

                data.forEach(function (value, key) {
                    if (key === 'picture_profile') return;
                    pairs.push(key + '=' + value);
                });

                return pairs.join('&') + '|' +
                    document.getElementById('fileName').textContent;
            }

            var initialState = serializeForm();

            document.getElementById('editProfileCancel').addEventListener('click', function (e) {
                if (serializeForm() !== initialState) {
                    e.preventDefault();

                    var href = this.href;

                    showConfirmModal('Any unsaved changes will be lost.', {
                        title: 'Discard changes?',
                        confirmText: 'Discard',
                        danger: true
                    }).then(function (confirmed) {
                        if (confirmed) {
                            window.location.href = href;
                        }
                    });
                }
            });

            form.addEventListener('submit', function (e) {
                e.preventDefault();

                function proceedWithSubmit() {
                    var btn = document.getElementById('editProfileSaveBtn');

                    if (btn) {
                        btn.disabled = true;
                        btn.innerHTML = '<i class="fas fa-save"></i> Saving…';
                    }

                    form.submit();
                }

                var newPassword = document.getElementById('newPassword').value;

                if (newPassword !== '') {
                    showConfirmModal('Save changes and update your password?', {
                        title: 'Change Password',
                        confirmText: 'Save'
                    }).then(function (confirmed) {
                        if (confirmed) {
                            proceedWithSubmit();
                        }
                    });
                } else {
                    proceedWithSubmit();
                }
            });
        })();
    </script>
</body>
</html>