<?php
require_once __DIR__ . '/../../BACKEND/security_helpers.php';
bms_start_secure_session();
bms_send_security_headers();

/* ============================================================
   CSRF TOKEN
   ============================================================ */

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];

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
  $email = $userData['email'] ?? '';
  $phone = $userData['contact_number'] ?? '';
  $address = $userData['address'] ?? '';
  $birthdate = $userData['birthdate'] ?? '';
} else {
  session_destroy();
  header("Location: /BMS/CODES/login.php");
  exit();
}
$stmt->close();
?>

<!doctype html>
<html lang="en">

<head>

    <meta charset="UTF-8" />

    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <title>Request for Clearance or Permit</title>

    <script src="https://www.google.com/recaptcha/enterprise.js?render=6LeDTbosAAAAAEKRg2OKrhBv620cRvwTPw8Fbsv4"></script>

    <link rel="stylesheet" href="../css/Forms.css">
    <link rel="stylesheet" href="../css/Sidenav.css">
    <link rel="stylesheet" href="../css/Global.css">
    <link rel="stylesheet" href="../css/Footer.css">
    <link rel="stylesheet" href="../css/signature.css">
    <link rel="stylesheet" href="../css/scanner.css">
    <link rel="stylesheet" href="../css/steps.css">
    <link rel="stylesheet" href="../css/gcash.css">

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
    >

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

        <a href="/BMS/BACKEND/logout.php">
            <i class="fa-solid fa-right-from-bracket"></i>
            <span>Log out</span>
        </a>

      </nav>

    </div>

  </aside>


  <main class="maincontent">

    <div id="statusPopup" class="statuspopup"></div>

    <div class="contentwrapper">

      <h1>Request for Barangay Clearance or Permit</h1>

      <p>
        Fill out your details below, then proceed to attachments and payment via the action buttons.
      </p>


      <form
          id="requestForm"
          class="formsection"
          action="../Backend/submit_request.php"
          method="POST"
          enctype="multipart/form-data"
      >

        <!-- Honeypot anti-bot trap -->

        <div class="visually-hidden-trap" aria-hidden="true">

            <input
                type="text"
                name="system_verification_code"
                tabindex="-1"
                autocomplete="off"
            >

        </div>


        <input
            type="hidden"
            name="g-recaptcha-response"
            id="g-recaptcha-response"
        >


        <!-- ============================================================
             CSRF PROTECTION
             ============================================================ -->

        <input
            type="hidden"
            name="csrf_token"
            value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>"
        >


        <input
            type="hidden"
            name="form_file"
            value="Clearance_Permit.php"
        />

        <input
            type="hidden"
            name="resident_id"
            value="<?php echo htmlspecialchars($resident_id); ?>"
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
                value="<?php echo htmlspecialchars($birthdate); ?>"
                required
            />


            <h3>Document Type</h3>

            <select
                name="document_type_id"
                id="document_type"
                required
                onchange="updateDocumentDetails(this)"
            >

              <option value="">-- Select Document --</option>

              <?php

              // Fetching document types filtered by Clearance or Permit categories

              $query = "
                  SELECT
                      document_type_id,
                      name,
                      price,
                      requirements
                  FROM document_types
                  WHERE category IN ('Clearance Permit')
                  ORDER BY name
              ";

              $stmt_docs = $conn->prepare($query);

              $stmt_docs->execute();

              $result = $stmt_docs->get_result();

              if ($result && $result->num_rows > 0) {

                while ($row = $result->fetch_assoc()) {

                  $req = htmlspecialchars(
                      $row['requirements'] ?? ''
                  );

                  $price = htmlspecialchars(
                      $row['price'] ?? '0.00'
                  );

                  echo "<option
                      value='{$row['document_type_id']}'
                      data-price='{$price}'
                      data-requirements='{$req}'
                  >{$row['name']}</option>";

                }

              } else {

                echo "<option value=''>
                    -- No documents available --
                </option>";

              }

              $stmt_docs->close();

              ?>

            </select>


            <h3>Payment Amount</h3>

            <input
                type="text"
                id="price"
                name="price_display"
                readonly
                placeholder="₱0.00"
            >

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


          <div class="formgroup" style="flex: 100%">

            <h3>Purpose of Request</h3>

            <input
                type="text"
                name="purpose"
                placeholder="e.g., Business operation, Employment requirement, Local clearance"
                required
            />

          </div>


          <div class="section-trigger-container">

            <div></div>

            <button
                type="button"
                class="btnsubmit"
                onclick="openModal('attachmentsModal')"
            >
                Proceed to Attachments
                <i class="fa-solid fa-arrow-right"></i>
            </button>

          </div>

        </div>


        <!-- ================= MODAL 1: Requirements & Attachments ================= -->

        <div id="attachmentsModal" class="form-modal-overlay">

          <div class="form-modal-content">

            <div class="form-modal-header">

              <h2>
                <i class="fa-solid fa-paperclip"></i>
                Requirements & Attachments
              </h2>

              <button
                  type="button"
                  class="btn-close-form-modal"
                  onclick="closeModal('attachmentsModal')"
              >
                &times;
              </button>

            </div>


            <div
                class="formgroup"
                style="flex: 100%; margin-bottom: 20px;"
            >

              <h3>Document Requirements</h3>

              <div class="requirements-card">

                <p
                    id="req_placeholder"
                    style="margin: 0; font-size: 0.88em; color: #777; font-style: italic;"
                >
                    <i class="fa-solid fa-circle-info"></i>
                    Please select a document type to view required attachments.
                </p>

                <ul
                    id="requirements_list"
                    style="display: none;"
                ></ul>

              </div>

            </div>


            <div
                class="formgroup"
                style="flex: 100%"
            >

              <h3>
                Upload or Scan Documents
                <span style="color: red;">*</span>
              </h3>


              <div class="scanner-container">

                <p
                    style="font-size: 0.9em; color: #555; margin: 0 0 10px 0;"
                >
                    Upload files OR use your camera to scan supporting documents:
                </p>


                <div class="scanner-actions">

                    <button
                        type="button"
                        class="btn-scan"
                        onclick="openGeneralCameraModal()"
                    >
                        <i class="fa-solid fa-camera"></i>
                        Scan Document via Camera
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
                    style="display: none;"
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
                        alt="Captured Document"
                        style="max-width: 100%; height: auto;"
                    >

                    <p
                        style="font-size: 0.8em; color: #28a745; margin: 5px 0 0 0; text-align: center;"
                    >
                        Document Captured
                    </p>

                </div>


                <div class="sig-divider">
                    <span>OR UPLOAD ATTACHMENT FILES</span>
                </div>


                <div class="uploadbox">

                    <p
                        style="margin-bottom: 5px; font-size: 0.85em; color: #555;"
                    >
                        Upload supporting documents (PNG, JPEG, DOC, DOCX, PDF - Max 50MB):
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
                  onclick="openModal('paymentModal'); closeModal('attachmentsModal');"
              >
                Proceed to Payment & Signature
                <i class="fa-solid fa-arrow-right"></i>
              </button>

            </div>

          </div>

        </div>


        <!-- ================= MODAL 2: Payment & Digital Signature ================= -->

        <div id="paymentModal" class="form-modal-overlay">

          <div class="form-modal-content">

            <div class="form-modal-header">

              <h2>
                <i class="fa-solid fa-credit-card"></i>
                Payment & Signature
              </h2>

              <button
                  type="button"
                  class="btn-close-form-modal"
                  onclick="closeModal('paymentModal')"
              >
                &times;
              </button>

            </div>


            <div
                class="formgroup"
                style="flex: 100%; margin-bottom: 20px;"
            >

              <h3>Choose Payment Method</h3>


              <div class="payment-selection">

                <label>

                  <input
                      type="radio"
                      name="payment_method"
                      value="GCash"
                      required
                      onclick="togglePayment(true)"
                  >

                  <i
                      class="fa-solid fa-mobile-screen-button"
                      style="color: #007bff;"
                  ></i>

                  GCash (Online)

                </label>


                <label>

                  <input
                      type="radio"
                      name="payment_method"
                      value="Walk-in"
                      required
                      onclick="togglePayment(false)"
                  >

                  <i
                      class="fa-solid fa-building-columns"
                      style="color: #6c757d;"
                  ></i>

                  Pay at Barangay Hall

                </label>

              </div>


              <div id="gcash_section">

                <p>
                    <strong>1. Scan QR to pay:</strong>

                    <span
                        id="payment_notice"
                        style="color: blue; font-weight: bold;"
                    ></span>
                </p>


                <img
                    src="../picture/gcash_qr.png"
                    alt="GCash QR Code"
                    class="qr-code"
                >


                <p>
                    <strong>
                        2. Upload Receipt Screenshot
                        (PNG, JPEG, DOC, DOCX, PDF - Max 50MB):
                    </strong>
                </p>


                <input
                    type="file"
                    name="payment_receipt"
                    id="payment_receipt"
                    accept=".png, .jpeg, .jpg, .doc, .docx, .pdf"
                    onchange="validateFiles(this, 50)"
                >


                <p>
                    <strong>3. Reference Number:</strong>
                </p>


                <input
                    type="text"
                    name="ref_number"
                    placeholder="Enter 13-digit Reference No."
                >

              </div>

            </div>


            <div
                class="formgroup"
                style="flex: 100%"
            >

              <h3>
                Digital Signature
                <span style="color: red;">*</span>
              </h3>

              <p
                  style="font-size: 0.9em; color: #555;"
              >
                  Draw your signature below OR upload an image file.
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
                  onclick="openModal('attachmentsModal'); closeModal('paymentModal');"
              >
                <i class="fa-solid fa-arrow-left"></i>
                Back
              </button>


              <button
                  type="button"
                  id="submitRequestButton"
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


<!-- WebRTC Camera Viewfinder Modal with Switch Support -->

<div
    id="cameraModal"
    class="camera-modal"
    style="display: none;"
>

    <div class="camera-modal-content">

        <h3>
            <i class="fa-solid fa-camera"></i>
            Align Document in Frame
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
                onclick="captureDocument()"
            >
                <i class="fa-solid fa-circle"></i>
                Capture Photo
            </button>


            <button
                type="button"
                class="btn-switch-cam"
                onclick="switchCamera()"
            >
                <i class="fa-solid fa-camera-rotate"></i>
                Switch Camera
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


<!-- ============================================================
     EXTERNAL JS SCRIPTS
     ============================================================ -->

<script src="../js/js.js"></script>
<script src="../js/signature.js"></script>
<script src="../js/scanner.js"></script>
<script src="../js/steps.js"></script>


</body>
</html>