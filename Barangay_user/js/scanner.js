// ============================================================
// SCANNER.JS
// Camera / Document Scanning Functions
// ============================================================

// Track which camera section triggered the scanner
// ('general' or 'medical')
let activeScanTarget = "general";

/**
 * Open camera modal specifically for optional medical attachment
 */
function openMedicalCameraModal() {
  activeScanTarget = "medical";
  openCameraModal();
}

/**
 * Open camera modal for general required supporting documents
 */
function openGeneralCameraModal() {
  activeScanTarget = "general";
  openCameraModal();
}

/**
 * Displays the camera modal and starts the WebRTC video stream
 */
function openCameraModal() {
  const modal = document.getElementById("cameraModal");
  const video = document.getElementById("cameraVideo");

  if (!modal || !video) {
    console.error("Camera modal or video element was not found.");
    return;
  }

  modal.style.display = "flex";

  if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
    navigator.mediaDevices
      .getUserMedia({
        video: {
          facingMode: {
            ideal: "environment",
          },
        },
        audio: false,
      })
      .then((stream) => {
        video.srcObject = stream;

        const playPromise = video.play();

        if (playPromise !== undefined) {
          playPromise.catch((err) => {
            console.error("Unable to start camera video:", err);
          });
        }
      })
      .catch((err) => {
        console.error("Camera access error:", err);

        alert(
          "Unable to access camera. Please check your camera permissions or upload a file instead.",
        );

        closeCameraModal();
      });
  } else {
    alert("Camera access is not supported by your browser.");

    closeCameraModal();
  }
}

/**
 * Captures image frame from <video> into <canvas>
 * and updates the correct preview box.
 */
function captureDocument() {
  const video = document.getElementById("cameraVideo");
  const canvas = document.getElementById("scanCanvas");

  if (!video || !canvas) {
    console.error("Camera video or scan canvas was not found.");
    return;
  }

  // Make sure the camera has produced a usable frame.
  if (!video.videoWidth || !video.videoHeight) {
    alert("The camera is not ready yet. Please wait a moment and try again.");
    return;
  }

  const context = canvas.getContext("2d");

  if (!context) {
    console.error("Unable to access the canvas drawing context.");
    return;
  }

  canvas.width = video.videoWidth;
  canvas.height = video.videoHeight;

  context.drawImage(video, 0, 0, canvas.width, canvas.height);

  const imageDataUrl = canvas.toDataURL("image/jpeg", 0.85);

  // ==========================================================
  // MEDICAL DOCUMENT
  // ==========================================================

  if (activeScanTarget === "medical") {
    const medicalInput = document.getElementById("scanned_medical_data");

    const medicalPreviewImg = document.getElementById(
      "medicalScannedImagePreview",
    );

    const medicalPreviewBox = document.getElementById(
      "medicalScannerPreviewBox",
    );

    if (medicalInput) {
      medicalInput.value = imageDataUrl;
    }

    if (medicalPreviewImg) {
      medicalPreviewImg.src = imageDataUrl;
    }

    if (medicalPreviewBox) {
      medicalPreviewBox.style.display = "block";
    }

    // ==========================================================
    // GENERAL DOCUMENT
    // ==========================================================
  } else {
    const docInput = document.getElementById("scanned_document_data");

    const docPreviewImg = document.getElementById("scannedImagePreview");

    const docPreviewBox = document.getElementById("scannerPreviewBox");

    if (docInput) {
      docInput.value = imageDataUrl;
    }

    if (docPreviewImg) {
      docPreviewImg.src = imageDataUrl;
    }

    if (docPreviewBox) {
      docPreviewBox.style.display = "block";
    }
  }

  // Close camera after successful capture.
  closeCameraModal();
}

/**
 * Closes the camera modal and stops the video stream.
 */
function closeCameraModal() {
  const modal = document.getElementById("cameraModal");
  const video = document.getElementById("cameraVideo");

  if (video && video.srcObject) {
    const stream = video.srcObject;

    if (stream && typeof stream.getTracks === "function") {
      stream.getTracks().forEach((track) => {
        track.stop();
      });
    }

    video.srcObject = null;
  }

  if (video) {
    video.pause();
  }

  if (modal) {
    modal.style.display = "none";
  }
}

/**
 * Clears General Document Scan Data
 */
function removeScannedDocument() {
  const docInput = document.getElementById("scanned_document_data");

  const docPreviewImg = document.getElementById("scannedImagePreview");

  const docPreviewBox = document.getElementById("scannerPreviewBox");

  if (docInput) {
    docInput.value = "";
  }

  if (docPreviewImg) {
    docPreviewImg.src = "";
  }

  if (docPreviewBox) {
    docPreviewBox.style.display = "none";
  }
}

/**
 * Clears Medical Scan Data
 */
function removeMedicalScannedDocument() {
  const medicalInput = document.getElementById("scanned_medical_data");

  const medicalPreviewImg = document.getElementById(
    "medicalScannedImagePreview",
  );

  const medicalPreviewBox = document.getElementById("medicalScannerPreviewBox");

  if (medicalInput) {
    medicalInput.value = "";
  }

  if (medicalPreviewImg) {
    medicalPreviewImg.src = "";
  }

  if (medicalPreviewBox) {
    medicalPreviewBox.style.display = "none";
  }
}
