<?php
require_once __DIR__ . '/../../BACKEND/security_helpers.php';
bms_start_secure_session();
bms_send_security_headers();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
include "../../BACKEND/db_connect.php";

/* Check if user is logged in */
if (!isset($_SESSION['resident_id']) && !isset($_SESSION['username'])){
  header("Location: /BMS/CODES/login.php");
  exit();
}

/* Get user info */
if (isset($_SESSION['resident_id'])) {
  $resident_id = $_SESSION['resident_id'];
  $sqlUser = "SELECT * FROM residents WHERE resident_id = ?";
  $stmt = $conn->prepare($sqlUser);
  $stmt->bind_param("i", $resident_id);
} else {
  $username = $_SESSION['username'];
  $sqlUser = "SELECT * FROM residents WHERE username = ?";
  $stmt = $conn->prepare($sqlUser);
  $stmt->bind_param("s", $username);
}

$stmt->execute();
$userResult = $stmt->get_result();

if ($userResult && $userResult->num_rows > 0) {
  $userData = $userResult->fetch_assoc();
  $resident_id = $userData['resident_id'];
  $fullname = $userData['name'];
  $email = $userData['email'];
  $phone = $userData['contact_number'] ?? '';
  $address = $userData['address'] ?? '';
  $birthdate = $userData['birthdate'] ?? '';
} else {
  session_destroy();
  header("Location: /BMS/CODES/login.php");
  exit();
}
?>

<!doctype html>
<html lang="en">

<head>

    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <title>Request for Medication Assistance</title>

    <script src="https://www.google.com/recaptcha/enterprise.js?render=6LeDTbosAAAAAEKRg2OKrhBv620cRvwTPw8Fbsv4"></script>

    <link rel="stylesheet" href="../css/Forms.css">
    <link rel="stylesheet" href="../css/Sidenav.css">
    <link rel="stylesheet" href="../css/Global.css">
    <link rel="stylesheet" href="../css/Footer.css">
    <link rel="stylesheet" href="../css/signature.css">
    <link rel="stylesheet" href="../css/scanner.css">
    <link rel="stylesheet" href="../css/steps.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>

      .grecaptcha-badge {
        z-index: 1000;
      }

      /* Honeypot hidden style */
      .visually-hidden-trap {
        opacity: 0;
        position: absolute;
        top: 0;
        left: 0;
        height: 0;
        width: 0;
        z-index: -1;
      }

      /* Divider styling for signature & prescription selection */
      .sig-divider {
        display: flex;
        align-items: center;
        text-align: center;
        margin: 15px 0;
        color: #777;
        font-weight: bold;
        font-size: 0.85em;
      }

      .sig-divider::before,
      .sig-divider::after {
        content: '';
        flex: 1;
        border-bottom: 1px solid #ccc;
      }

      .sig-divider span {
        padding: 0 10px;
      }

      /* Free Service Banner Style */
      .free-service-banner {
        background-color: #e8f5e9;
        border-left: 4px solid #2e7d32;
        padding: 12px 15px;
        border-radius: 4px;
        margin-bottom: 20px;
        color: #1b5e20;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 10px;
      }

    </style>

</head>

<body>

<div class="container">

  <aside class="sidebar" id="sidebar">

    <div class="burgertab" onclick="toggleSidebar()">
      <i class="fa-solid fa-bars"></i>
    </div>

    <div class="sidebarcontent">

      <div class="sidebar-header">

        <div class="logosidebar">
          <img
            src="../picture/Logo.png"
            alt="Barangay Logo"
            id="sidebarLogo"
          >
        </div>

      </div>


      <nav class="navlinks">

        <a href="../Frontend/Userdashboard.php">
          <i class="fa-solid fa-house"></i>
          <span>Home</span>
        </a>

        <a href="../Frontend/Profile.php">
          <i class="fa-solid fa-user"></i>
          <span>My Profile</span>
        </a>

        <a href="../Frontend/Request.php" class="active">
          <i class="fa-solid fa-file-circle-plus"></i>
          <span>Request a Document</span>
        </a>

        <a href="../Frontend/History.php">
          <i class="fa-solid fa-clock-rotate-left"></i>
          <span>History</span>
        </a>

        <div class="nav-divider"></div>

        <form action="/BMS/BACKEND/logout.php" method="POST" class="logout-form">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
          <button type="submit" class="logout-link">
            <i class="fa-solid fa-right-from-bracket"></i>
            <span>Log out</span>
          </button>
        </form>

      </nav>

    </div>

  </aside>


  <main class="maincontent">

    <div id="statusPopup" class="statuspopup"></div>

    <div class="contentwrapper">

      <h1>Request for Medication Assistance</h1>

      <p>
        Fill out your details below, specify your medicine and dosage,
        then proceed to upload prescriptions and add your signature.
      </p>


      <form
        id="requestForm"
        class="formsection"
        action="../Backend/submit_request.php"
        method="POST"
        enctype="multipart/form-data"
      >

        <div
          class="visually-hidden-trap"
          aria-hidden="true"
        >
          <input
            type="text"
            name="med_verification_token"
            tabindex="-1"
            autocomplete="off"
          >
        </div>


        <input
          type="hidden"
          name="g-recaptcha-response"
          id="g-recaptcha-response"
        >

        <input
          type="hidden"
          name="form_file"
          value="Medical_Assistance.php"
        />

        <input
          type="hidden"
          name="resident_id"
          value="<?php echo $resident_id; ?>"
        />


        <!-- ================= MAIN SECTION: Details ================= -->

        <div id="step-panel-1" style="display: contents;">

          <div class="formgroup">

            <h3>Name and Birthdate</h3>

            <input
              type="text"
              name="fullname"
              placeholder="Full Name"
              value="<?php echo htmlspecialchars($fullname); ?>"
              required
            />

            <input
              type="date"
              name="birthdate"
              value="<?php echo $birthdate; ?>"
              required
            />

          </div>


          <div class="formgroup">

            <h3>Contact and Address</h3>

            <input
              type="text"
              name="phone"
              placeholder="Phone Number"
              value="<?php echo htmlspecialchars($phone); ?>"
              required
            />

            <input
              type="email"
              name="email"
              placeholder="Email Address"
              value="<?php echo htmlspecialchars($email); ?>"
              required
              readonly
            />

            <textarea
              name="address"
              placeholder="Current Address"
              rows="4"
              required
            ><?php echo htmlspecialchars($address); ?></textarea>

          </div>


          <!-- Mapped directly to match resident_medicine table fields -->

          <div
            class="formgroup"
            style="flex: 100%"
          >

            <h3>Medicine Details</h3>

            <div
              style="display: flex; gap: 15px; flex-wrap: wrap;"
            >

              <div
                style="flex: 2; min-width: 200px;"
              >

                <label
                  style="font-size: 12px; color: #666; font-weight: 600; margin-bottom: 5px; display: block;"
                >
                  Medicine Name
                </label>

                <input
                  type="text"
                  name="medicine_name"
                  placeholder="e.g., Biogesic, Maintenance for hypertension"
                  required
                />

              </div>


              <div
                style="flex: 1; min-width: 150px;"
              >

                <label
                  style="font-size: 12px; color: #666; font-weight: 600; margin-bottom: 5px; display: block;"
                >
                  Dosage
                </label>

                <input
                  type="text"
                  name="dosage"
                  placeholder="e.g., 150mg / 500mg"
                  required
                />

              </div>

            </div>

          </div>


          <div
            class="formgroup"
            style="flex: 100%"
          >

            <h3>Purpose / Additional Medical Notes</h3>

            <textarea
              name="purpose"
              placeholder="State reason or specific condition for this medical request"
              rows="3"
              required
            ></textarea>

          </div>


          <div class="section-trigger-container">

            <div></div>

            <button
              type="button"
              class="btnsubmit"
              onclick="openModal('attachmentsModal')"
            >
              Proceed to Prescriptions
              <i class="fa-solid fa-arrow-right"></i>
            </button>

          </div>

        </div>


        <!-- ================= MODAL 1: Prescription Scans ================= -->

        <div
          id="attachmentsModal"
          class="form-modal-overlay"
        >

          <div class="form-modal-content">

            <div class="form-modal-header">

              <h2>
                <i class="fa-solid fa-prescription"></i>
                Prescriptions & Medical Records
              </h2>

              <button
                type="button"
                class="btn-close-form-modal"
                onclick="closeModal('attachmentsModal')"
              >
                &times;
              </button>

            </div>


            <!-- Prescription Scans Section (REQUIRED) -->

            <div
              class="formgroup"
              style="flex: 100%"
            >

              <h3>
                Prescription & Medical Records
                <span style="color: red;">*</span>
              </h3>


              <div class="scanner-container">

                <p
                  style="font-size: 0.9em; color: #555; margin: 0 0 10px 0;"
                >
                  Capture your doctor's prescription via camera OR
                  upload image/PDF files below:
                </p>


                <div class="scanner-actions">

                  <button
                    type="button"
                    class="btn-scan"
                    onclick="openCameraModal()"
                  >
                    <i class="fa-solid fa-camera"></i>
                    Scan Prescription via Camera
                  </button>

                </div>


                <input
                  type="hidden"
                  name="scanned_document_data"
                  id="scanned_document_data"
                />


                <div
                  class="scanner-preview-box"
                  id="scannerPreviewBox"
                >

                  <button
                    type="button"
                    class="btn-remove-scan"
                    onclick="removeScannedDocument()"
                  >
                    ×
                  </button>

                  <img
                    id="scannedImagePreview"
                    src=""
                    alt="Captured Prescription"
                  >

                  <p
                    style="font-size: 0.8em; color: #28a745; margin: 5px 0 0 0; text-align: center;"
                  >
                    Prescription Captured
                  </p>

                </div>


                <div class="sig-divider">
                  <span>OR UPLOAD PRESCRIPTION FILE</span>
                </div>


                <div class="uploadbox">

                  <p
                    style="margin-bottom: 5px; font-size: 0.85em; color: #555;"
                  >
                    Upload prescription or medical file
                    (PNG, JPEG, DOC, DOCX, PDF - Max 50MB):
                  </p>

                  <input
                    type="file"
                    name="attachment[]"
                    id="attachment"
                    accept=".png, .jpeg, .jpg, .doc, .docx, .pdf"
                    multiple
                    onchange="validateFiles(this, 50); previewSelectedFiles(this);"
                  />

                  <div id="fileListPreview"></div>

                </div>

              </div>

            </div>


            <div class="modal-actions">

              <button
                type="button"
                class="btncancel"
                onclick="closeModal('attachmentsModal')"
              >
                <i class="fa-solid fa-arrow-left"></i>
                Back to Details
              </button>

              <button
                type="button"
                class="btnsubmit"
                onclick="openModal('signatureModal'); closeModal('attachmentsModal');"
              >
                Proceed to Signature
                <i class="fa-solid fa-arrow-right"></i>
              </button>

            </div>

          </div>

        </div>


        <!-- ================= MODAL 2: Digital Signature ================= -->

        <div
          id="signatureModal"
          class="form-modal-overlay"
        >

          <div class="form-modal-content">

            <div class="form-modal-header">

              <h2>
                <i class="fa-solid fa-signature"></i>
                Digital Signature
              </h2>

              <button
                type="button"
                class="btn-close-form-modal"
                onclick="closeModal('signatureModal')"
              >
                &times;
              </button>

            </div>


            <div class="free-service-banner">

              <i class="fa-solid fa-circle-check"></i>

              <span>
                This service is 100% free under the Barangay Health
                Assistance Program. No payment required!
              </span>

            </div>


            <div
              class="formgroup"
              style="flex: 100%"
            >

              <h3>
                Resident Signature
                <span style="color: red;">*</span>
              </h3>

              <p style="font-size: 0.9em; color: #555;">
                Draw your signature below, OR upload an image file
                of your signature.
              </p>


              <div class="signature-container">

                <canvas
                  id="signatureCanvas"
                  class="signature-canvas"
                  width="500"
                  height="160"
                ></canvas>

                <input
                  type="hidden"
                  name="digital_signature"
                  id="digital_signature"
                />


                <div class="sig-controls">

                  <button
                    type="button"
                    class="btn-clear-sig"
                    onclick="clearSignature()"
                  >
                    <i class="fa-solid fa-eraser"></i>
                    Clear Signature
                  </button>

                </div>

              </div>


              <div class="sig-divider">
                <span>OR UPLOAD SIGNATURE FILE</span>
              </div>


              <div class="uploadbox">

                <p style="margin-bottom: 5px;">

                  <strong>Upload Signature Image</strong>

                  (PNG, JPG, JPEG - Max 50MB):

                </p>

                <input
                  type="file"
                  name="signature_file"
                  id="signature_file"
                  accept=".png, .jpeg, .jpg"
                  onchange="validateFiles(this, 50)"
                />

              </div>

            </div>


            <div class="modal-actions">

              <button
                type="button"
                class="btncancel"
                onclick="openModal('attachmentsModal'); closeModal('signatureModal');"
              >
                <i class="fa-solid fa-arrow-left"></i>
                Back
              </button>

              <button
                type="button"
                id="submitRequestButton"
                onclick="handleDocumentSubmit(event)"
                class="btnsubmit"
              >
                Submit Request
              </button>

            </div>

          </div>

        </div>

      </form>

    </div>


    <footer class="footer">

      <div class="footerleft">

        <div class="footertext">
          A Centralized Web-based Management System Service
        </div>

      </div>

      <div class="footercenter">
        All Rights Reserved
      </div>

      <div class="footerright">
        Contact Info Part
      </div>

    </footer>

  </main>

</div>


<!-- WebRTC Camera Viewfinder Modal -->

<div
  id="cameraModal"
  class="camera-modal"
>

  <div class="camera-modal-content">

    <h3>
      <i class="fa-solid fa-camera"></i>
      Align Prescription in Frame
    </h3>

    <video
      id="cameraVideo"
      class="camera-viewfinder"
      autoplay
      playsinline
    ></video>

    <canvas
      id="scanCanvas"
      style="display: none;"
    ></canvas>


    <div class="camera-controls">

      <button
        type="button"
        class="btn-capture"
        id="captureBtn"
        onclick="captureDocument()"
      >
        <i class="fa-solid fa-circle"></i>
        Capture Prescription
      </button>

      <button
        type="button"
        class="btn-close-modal"
        onclick="closeCameraModal()"
      >
        Cancel
      </button>

    </div>

  </div>

</div>


<!-- External JS Scripts -->

<script src="../js/js.js"></script>
<script src="../js/signature.js"></script>
<script src="../js/scanner.js"></script>
<script src="../js/steps.js"></script>

</body>
</html>