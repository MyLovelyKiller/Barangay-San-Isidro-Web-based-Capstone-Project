<?php
require_once __DIR__ . '/../BACKEND/security_helpers.php';
bms_start_secure_session();
bms_send_security_headers();

/* =========================================================
   CSRF TOKEN
========================================================= */

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(
        random_bytes(32)
    );
}

$csrf_token = $_SESSION['csrf_token'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Barangay San Isidro - Register</title>

    <!-- =====================================================
         reCAPTCHA ENTERPRISE
    ====================================================== -->

    <script src="https://www.google.com/recaptcha/enterprise.js?render=6LeDTbosAAAAAEKRg2OKrhBv620cRvwTPw8Fbsv4"></script>

    <!-- =====================================================
         STYLES
    ====================================================== -->

    <link
        rel="stylesheet"
        href="/BMS/STYLE/register.css"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css"
    >

    <style>
        .v-check {
            display: none !important;
        }

        .grecaptcha-badge {
            z-index: 1000;
        }
    </style>
</head>

<body>

<header class="topnav">

    <div class="logo-area">

        <img
            src="/BMS/IMAGES/silogo.png"
            alt="San Isidro Logo"
            class="logo1"
        >

        <img
            src="/BMS/IMAGES/Home.png"
            alt="One Cainta Logo"
            class="logo2"
        >

    </div>

</header>


<div class="fullbg">

    <section class="topp">

        <div class="top-logo-container">

            <div class="top-logo">

                <img
                    src="/BMS/IMAGES/silogo.png"
                    alt="San Isidro Logo"
                >

            </div>

        </div>


        <div class="login-form">

            <a
                href="/BMS/CODES/login.php"
                class="back-btn"
            >
                ← Back
            </a>


            <h2>REGISTER</h2>


            <!-- =================================================
                 REGISTRATION FORM
            ================================================== -->

            <form
                id="requestForm"
                enctype="multipart/form-data"
                method="POST"
                novalidate
            >

                <!-- =================================================
                     CSRF
                ================================================== -->

                <input
                    type="hidden"
                    name="csrf_token"
                    id="csrf_token"
                    value="<?= htmlspecialchars(
                        $csrf_token,
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>"
                >


                <!-- =================================================
                     HONEYPOT
                ================================================== -->

                <div class="v-check">

                    <input
                        type="text"
                        name="bms_reg_v_field"
                        value=""
                        tabindex="-1"
                        autocomplete="off"
                    >

                </div>


                <!-- =================================================
                     reCAPTCHA TOKEN
                ================================================== -->

                <input
                    type="hidden"
                    name="g-recaptcha-response"
                    id="g-recaptcha-response"
                    value=""
                >


                <div id="registrationFields">


                    <!-- =================================================
                         ACCOUNT TYPE
                    ================================================== -->

                    <div class="form-group">

                        <label for="accountType">
                            Who is the owner of the account?
                        </label>

                        <select
                            id="accountType"
                            name="account_type"
                            required
                        >

                            <option
                                value=""
                                disabled
                                selected
                            >
                                Select account type
                            </option>

                            <option value="resident">
                                Brgy. San Isidro Resident
                            </option>

                            <option value="official">
                                Brgy. Official
                            </option>

                        </select>

                    </div>


                    <!-- =================================================
                         SATELLITE
                    ================================================== -->

                    <div class="form-group">

                        <label for="satellite_id">
                            Nearest / Assigned Barangay Satellite
                        </label>

                        <select
                            id="satellite_id"
                            name="satellite_id"
                            required
                        >

                            <option
                                value=""
                                disabled
                                selected
                            >
                                Select satellite
                            </option>

                            <option value="1">
                                Balanti
                            </option>

                            <option value="2">
                                Halang
                            </option>

                            <option value="3">
                                Karangalan
                            </option>

                            <option value="4">
                                Greenpark
                            </option>

                            <option value="5">
                                Brookside
                            </option>

                        </select>

                        <small id="satelliteHelp">
                            Select the barangay satellite nearest to your residence.
                        </small>

                    </div>


                    <!-- =================================================
                         DEPARTMENT
                    ================================================== -->

                    <div
                        class="form-group department-group"
                        style="display: none;"
                    >

                        <label for="department">
                            From what department are you from?
                        </label>

                        <select
                            id="department"
                            name="department"
                        >

                            <option
                                value=""
                                disabled
                                selected
                            >
                                Select department
                            </option>

                            <option value="ADMIN">
                                Admin Office
                            </option>

                            <option value="BPSO">
                                BPSO
                            </option>

                            <option value="CLEARANCE">
                                Clearance Office
                            </option>

                            <option value="LUPON">
                                Lupon Office
                            </option>

                        </select>

                    </div>


                    <!-- =================================================
                         FULL NAME
                    ================================================== -->

                    <div class="form-group">

                        <label for="fullName">
                            Full Name
                        </label>

                        <input
                            type="text"
                            id="fullName"
                            name="name"
                            placeholder="Enter your Name"
                            autocomplete="name"
                            maxlength="150"
                            required
                        >

                    </div>


                    <!-- =================================================
                         USERNAME
                    ================================================== -->

                    <div class="form-group">

                        <label for="username">
                            Username
                        </label>

                        <input
                            type="text"
                            id="username"
                            name="username"
                            placeholder="Enter your username"
                            autocomplete="username"
                            minlength="4"
                            maxlength="50"
                            pattern="[A-Za-z0-9_.-]+"
                            required
                        >

                    </div>


                    <!-- =================================================
                         EMAIL
                    ================================================== -->

                    <div class="form-group">

                        <label for="email">
                            Email Address
                        </label>

                        <input
                            type="email"
                            id="email"
                            name="email"
                            placeholder="Enter your email address"
                            autocomplete="email"
                            maxlength="254"
                            required
                        >

                    </div>


                    <!-- =================================================
                         CONTACT NUMBER
                    ================================================== -->

                    <div class="form-group">

                        <label for="contact">
                            Contact Number
                        </label>

                        <input
                            type="tel"
                            id="contact"
                            name="contact_number"
                            placeholder="09XXXXXXXXX"
                            autocomplete="tel"
                            pattern="[0-9]{11}"
                            required
                            maxlength="11"
                            inputmode="numeric"
                            oninput="this.value = this.value.replace(/[^0-9]/g, '');"
                        >

                    </div>


                    <!-- =================================================
                         PASSWORD
                    ================================================== -->

                    <div class="form-group">

                        <label for="password">
                            Password
                        </label>

                        <div class="input-wrapper">

                            <input
                                type="password"
                                id="password"
                                name="password"
                                autocomplete="new-password"
                                minlength="8"
                                maxlength="128"
                                required
                            >

                            <i
                                class="fas fa-eye toggle-password"
                                id="eye-password"
                            ></i>

                        </div>


                        <div class="strength-container">

                            <div class="strength-bar">
                                <div id="strength-fill"></div>
                            </div>

                            <small id="strength-text">
                                Password Strength
                            </small>

                        </div>

                    </div>


                    <!-- =================================================
                         CONFIRM PASSWORD
                    ================================================== -->

                    <div class="form-group">

                        <label for="confirm_password">
                            Confirm Password
                        </label>

                        <div class="input-wrapper">

                            <input
                                type="password"
                                id="confirm_password"
                                name="confirm_password"
                                autocomplete="new-password"
                                minlength="8"
                                maxlength="128"
                                required
                            >

                            <i
                                class="fas fa-eye toggle-password"
                                id="eye-confirm"
                            ></i>

                        </div>

                        <p
                            id="match-message"
                            style="font-size: 12px; margin-top: 5px;"
                        ></p>

                    </div>


                    <!-- =================================================
                         PROOF OF VALID ID
                    ================================================== -->

                    <div class="form-group">

                        <label for="proof">
                            Proof of Valid ID (Image or PDF)
                        </label>

                        <input
                            type="file"
                            id="proof"
                            name="proof"
                            accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,image/jpeg,image/png,image/gif,image/webp,application/pdf"
                            required
                        >

                        <small>
                            Accepted: JPG, JPEG, PNG, GIF, WEBP, or PDF.
                            Maximum size: 5 MB.
                        </small>

                    </div>


                    <!-- =================================================
                         ID NUMBER
                    ================================================== -->

                    <div class="form-group">

                        <label for="idNumber">
                            ID Number
                        </label>

                        <input
                            type="text"
                            id="idNumber"
                            name="id_number"
                            placeholder="12-digit PhilSys Number"
                            autocomplete="off"
                            inputmode="numeric"
                            maxlength="12"
                            minlength="12"
                            pattern="[0-9]{12}"
                            required
                            oninput="this.value = this.value.replace(/[^0-9]/g, '');"
                        >

                    </div>

                </div>


                <!-- =================================================
                     SEND OTP
                ================================================== -->

                <button
                    type="button"
                    id="sendOtpBtn"
                    onclick="handleRegistrationStart()"
                >
                    Send OTP
                </button>

            </form>


            <!-- =====================================================
                 OTP VERIFICATION FORM
            ====================================================== -->

            <form
                id="otpVerifyForm"
                style="display: none;"
                method="POST"
            >

                <!-- CSRF -->

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(
                        $csrf_token,
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>"
                >


                <p
                    style="
                        color: #27ae60;
                        margin-bottom: 15px;
                        font-weight: bold;
                    "
                >
                    An OTP has been sent to your email.
                </p>


                <div class="form-group">

                    <label for="otpCode">
                        Enter 6-Digit OTP
                    </label>

                    <input
                        type="text"
                        id="otpCode"
                        name="otp_code"
                        placeholder="XXXXXX"
                        maxlength="6"
                        minlength="6"
                        pattern="[0-9]{6}"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        required
                        oninput="this.value = this.value.replace(/[^0-9]/g, '');"
                    >

                </div>


                <button
                    type="submit"
                    id="confirmRegisterBtn"
                >
                    Verify & Register
                </button>

            </form>

        </div>

    </section>

</div>


<script>

/* =========================================================
   REGISTRATION START
========================================================= */

async function handleRegistrationStart()
{
    const form =
        document.getElementById('requestForm');

    const pass =
        document.getElementById('password').value;

    const confirmPass =
        document.getElementById('confirm_password').value;

    const submitBtn =
        document.getElementById('sendOtpBtn');

    const accountType =
        document.getElementById('accountType').value;

    const department =
        document.getElementById('department');

    const proof =
        document.getElementById('proof');


    /* =====================================================
       CLIENT-SIDE VALIDATION
    ===================================================== */

    if (!form.checkValidity()) {

        form.reportValidity();

        return;
    }


    /* =====================================================
       DEPARTMENT VALIDATION
    ===================================================== */

    if (
        accountType === 'official' &&
        !department.value
    ) {

        alert(
            "Please select your department."
        );

        department.focus();

        return;
    }


    /* =====================================================
       PASSWORD MATCH
    ===================================================== */

    if (pass !== confirmPass) {

        alert(
            "Passwords do not match!"
        );

        return;
    }


    /* =====================================================
       FILE VALIDATION
       CLIENT-SIDE ONLY
       SERVER MUST VALIDATE AGAIN
    ===================================================== */

    if (
        !proof.files ||
        proof.files.length === 0
    ) {

        alert(
            "Please select your proof of valid ID."
        );

        return;
    }


    const selectedFile =
        proof.files[0];


    const maxFileSize =
        5 * 1024 * 1024;


    if (
        selectedFile.size >
        maxFileSize
    ) {

        alert(
            "The proof file must not exceed 5 MB."
        );

        proof.value = '';

        return;
    }


    const allowedExtensions = [
        'jpg',
        'jpeg',
        'png',
        'gif',
        'webp',
        'pdf'
    ];


    const fileName =
        selectedFile.name.toLowerCase();


    const extension =
        fileName.includes('.')
            ? fileName
                .split('.')
                .pop()
            : '';


    if (
        !allowedExtensions.includes(
            extension
        )
    ) {

        alert(
            "Invalid file type. Please upload JPG, JPEG, PNG, GIF, WEBP, or PDF."
        );

        proof.value = '';

        return;
    }


    /* =====================================================
       DISABLE BUTTON
    ===================================================== */

    submitBtn.innerText =
        "Verifying Security...";

    submitBtn.disabled = true;


    /* =====================================================
       reCAPTCHA ENTERPRISE
    ===================================================== */

    grecaptcha.enterprise.ready(
        async () => {

            try {

                const token =
                    await grecaptcha.enterprise.execute(
                        '6LeDTbosAAAAAEKRg2OKrhBv620cRvwTPw8Fbsv4',
                        {
                            action: 'register'
                        }
                    );


                if (!token) {

                    throw new Error(
                        "Empty reCAPTCHA token."
                    );
                }


                document.getElementById(
                    'g-recaptcha-response'
                ).value = token;


                /* =================================================
                   CALL register.js
                ================================================= */

                if (
                    typeof window.validateAndSubmit ===
                    "function"
                ) {

                    await window.validateAndSubmit();

                } else {

                    console.error(
                        "External JS function 'validateAndSubmit' not found."
                    );

                    submitBtn.innerText =
                        "Send OTP";

                    submitBtn.disabled =
                        false;
                }


            } catch (error) {

                console.error(
                    "reCAPTCHA Error:",
                    error
                );


                /*
                 * Do NOT bypass reCAPTCHA if the service fails.
                 *
                 * The backend is already expected to require
                 * successful reCAPTCHA verification.
                 */
                alert(
                    "Security verification failed. Please try again."
                );


                submitBtn.innerText =
                    "Send OTP";

                submitBtn.disabled =
                    false;
            }

        }
    );
}


/* =========================================================
   ACCOUNT TYPE / DEPARTMENT UI
========================================================= */

document
    .getElementById('accountType')
    .addEventListener(
        'change',
        function ()
        {
            const departmentGroup =
                document.querySelector(
                    '.department-group'
                );

            const department =
                document.getElementById(
                    'department'
                );


            if (
                this.value ===
                'official'
            ) {

                departmentGroup.style.display =
                    'block';

                department.required =
                    true;

            } else {

                departmentGroup.style.display =
                    'none';

                department.required =
                    false;

                department.value =
                    '';

            }
        }
    );


/* =========================================================
   OTP INPUT
========================================================= */

document
    .getElementById('otpCode')
    .addEventListener(
        'input',
        function ()
        {
            this.value =
                this.value
                    .replace(
                        /[^0-9]/g,
                        ''
                    )
                    .slice(0, 6);
        }
    );

</script>


<script src="/BMS/STYLE/register.js"></script>

</body>
</html>