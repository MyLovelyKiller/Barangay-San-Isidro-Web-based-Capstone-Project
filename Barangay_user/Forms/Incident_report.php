<?php
session_start();
include "../../BACKEND/db_connect.php";

/* Check if user is logged in */
if (!isset($_SESSION['resident_id']) && !isset($_SESSION['username'])) {
    header("Location: /BMS/CODES/login.php");
    exit();
}

/* Get user info safely */
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
    <title>Request for Blotter</title>

    <script src="https://www.google.com/recaptcha/enterprise.js?render=6LeDTbosAAAAAEKRg2OKrhBv620cRvwTPw8Fbsv4"></script>

    <link rel="stylesheet" href="../css/Forms.css">
    <link rel="stylesheet" href="../css/Sidenav.css">
    <link rel="stylesheet" href="../css/Global.css">
    <link rel="stylesheet" href="../css/Footer.css">
    <link rel="stylesheet" href="../css/signature.css">
    <link rel="stylesheet" href="../css/scanner.css">
    <link rel="stylesheet" href="../css/steps.css">
    <link rel="stylesheet" href="../css/gcash.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
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
            <img src="../picture/Logo.png" alt="Barangay Logo" id="sidebarLogo">
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

      <h1>Request for Blotter</h1>

      <p>
        Provide details of the Blotter report below, then proceed to attachments and signature via the action buttons.
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
                name="subject_verification_id"
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
            value="Incident_report.php"
        />

        <input
            type="hidden"
            name="resident_id"
            value="<?php echo htmlspecialchars($resident_id); ?>"
        />


        <!-- ================= MAIN SECTION: Details ================= -->

        <div id="step-panel-1" style="display: contents;">

          <div class="formgroup">

            <h3>Personal Details & Incident Date</h3>

            <input
                type="text"
                name="fullname"
                placeholder="Full Name"
                value="<?= htmlspecialchars($fullname) ?>"
                readonly
            />

            <h3>Date of Incident</h3>

            <input
                type="date"
                name="incident_date"
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
            />

            <textarea
                name="address"
                placeholder="Current Address"
                rows="4"
                required
            ><?php echo htmlspecialchars($address); ?></textarea>

          </div>


          <div class="formgroup" style="flex: 100%">

            <h3>Purpose / Incident Details</h3>

            <textarea
                name="purpose"
                placeholder="Describe the purpose or incident in detail"
                rows="5"
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
                Evidence & Document Requirements
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

              <h3>Evidence Guidelines</h3>

              <div class="requirements-card">

                <p style="margin: 0 0 10px 0; font-weight: bold; color: #007bff; font-size: 0.95em;">
                    <i class="fa-solid fa-circle-info"></i>
                    What to upload as supporting evidence:
                </p>

                <p style="margin: 0 0 8px 0; font-size: 0.9em; color: #333; line-height: 1.5;">
                    To process your Blotter report, please attach or scan at least one (1) clear proof related to your incident:
                </p>

                <p style="margin: 0 0 6px 0; font-size: 0.88em; color: #444; padding-left: 8px;">
                    • <strong>Verbal Threats / Cyber Harassment:</strong>
                    Screenshots of text messages, chat conversations, or social media posts.
                </p>

                <p style="margin: 0 0 6px 0; font-size: 0.88em; color: #444; padding-left: 8px;">
                    • <strong>Property Damage or Theft:</strong>
                    Photos of damaged items/property, receipts, or CCTV snapshots.
                </p>

                <p style="margin: 0 0 6px 0; font-size: 0.88em; color: #444; padding-left: 8px;">
                    • <strong>Physical Assault / Injury:</strong>
                    Photos of physical injuries or doctor's medical certificate.
                </p>

                <p style="margin: 0; font-size: 0.88em; color: #444; padding-left: 8px;">
                    • <strong>If no incident photo is available:</strong>
                    Upload a photo/scan of your Valid Government ID or Barangay ID for identity verification.
                </p>

              </div>

            </div>


            <div
                class="formgroup"
                style="flex: 100%; margin-bottom: 20px;"
            >

              <h3>
                Proof of Medical Record
                <span style="font-size: 0.8em; color: #6c757d; font-weight: normal;">
                    (Optional)
                </span>
              </h3>

              <div class="uploadbox">

                <p style="margin-bottom: 5px; font-size: 0.85em; color: #555;">
                    Upload medical records, doctor's prescription, or injury assessment reports
                    (PNG, JPEG, DOC, DOCX, PDF - Max 50MB):
                </p>

                <input
                    type="file"
                    name="proof_of_medical_record"
                    id="proof_of_medical_record"
                    accept=".png, .jpeg, .jpg, .doc, .docx, .pdf"
                    onchange="validateFiles(this, 50)"
                />

              </div>

            </div>


            <div class="formgroup" style="flex: 100%">

              <h3>
                Attachments & Scanned Evidence
                <span style="color: red;">*</span>
              </h3>

              <div class="scanner-container">

                <p style="font-size: 0.88em; color: #555; margin: 0 0 10px 0;">
                    Upload your selected evidence files or use your camera to capture it directly:
                </p>


                <div class="scanner-actions">

                    <button
                        type="button"
                        class="btn-scan"
                        onclick="openCameraModal()"
                    >
                        <i class="fa-solid fa-camera"></i>
                        Scan / Take Photo via Camera
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

                    <p style="margin-bottom: 5px; font-size: 0.85em; color: #555;">
                        Upload evidence files (PNG, JPEG, DOC, DOCX, PDF - Max 50MB):
                    </p>

                    <input
                        type="file"
                        name="attachment[]"
                        id="attachment"
                        accept=".png, .jpeg, .jpg, .doc, .docx, .pdf"
                        multiple
                        onchange="validateFiles(this, 50); previewSelectedFiles(this);"
                    />

                    <div
                        id="fileListPreview"
                        style="margin-top: 8px; font-size: 0.85em; color: #333;"
                    ></div>

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

        <div id="signatureModal" class="form-modal-overlay">

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


            <div class="formgroup" style="flex: 100%">

              <h3>
                Digital Signature
                <span style="color: red;">*</span>
              </h3>

              <p style="font-size: 0.9em; color: #555;">
                Draw your signature below, OR upload an image file of your signature.
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
                  onclick="handleBlotterSubmit(event)"
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


<!-- External JS Scripts -->

<script src="../js/js.js"></script>
<script src="../js/signature.js"></script>
<script src="../js/scanner.js"></script>
<script src="../js/steps.js"></script>

</body>
</html>