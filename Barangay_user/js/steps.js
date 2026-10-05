// ============================================================
// STEPS.JS
// Modal Navigation and Document Submission
// ============================================================

/**
 * Opens a modal.
 *
 * Before opening:
 * - Attachments modal requires the main form details to be valid.
 * - Payment modal requires at least one supporting document.
 */
function openModal(modalId) {
  // ==========================================================
  // VALIDATE MAIN DETAILS BEFORE OPENING ATTACHMENTS
  // ==========================================================

  if (modalId === "attachmentsModal") {
    const mainInputs = document.querySelectorAll(
      "#step-panel-1 input[required], " +
        "#step-panel-1 select[required], " +
        "#step-panel-1 textarea[required]",
    );

    let isValid = true;

    for (const input of mainInputs) {
      if (!input.checkValidity()) {
        input.reportValidity();
        isValid = false;

        break;
      }
    }

    if (!isValid) {
      return;
    }
  }

  // ==========================================================
  // VALIDATE ATTACHMENTS BEFORE OPENING PAYMENT
  // ==========================================================

  if (modalId === "paymentModal") {
    const scannedData =
      document.getElementById("scanned_document_data")?.value || "";

    const attachmentFile = document.getElementById("attachment");

    const hasScannedDocument = scannedData.trim() !== "";

    const hasUploadedAttachment =
      attachmentFile && attachmentFile.files && attachmentFile.files.length > 0;

    if (!hasScannedDocument && !hasUploadedAttachment) {
      alert(
        "Please attach a supporting document by scanning via camera OR uploading an attachment file first.",
      );

      return;
    }
  }

  // ==========================================================
  // OPEN MODAL
  // ==========================================================

  const modal = document.getElementById(modalId);

  if (modal) {
    modal.classList.add("active");
  }
}

/**
 * Closes a modal.
 */
function closeModal(modalId) {
  const modal = document.getElementById(modalId);

  if (modal) {
    modal.classList.remove("active");
  }
}

/**
 * Handles final document submission.
 *
 * Validates:
 * - Required form fields
 * - Digital signature
 * - reCAPTCHA Enterprise
 *
 * IMPORTANT:
 * The form will NOT submit if reCAPTCHA fails.
 */
async function handleDocumentSubmit(e) {
  e.preventDefault();

  const form = document.getElementById("requestForm");

  if (!form) {
    console.error("Request form was not found.");

    return;
  }

  // ==========================================================
  // VALIDATE COMPLETE FORM
  // ==========================================================

  if (!form.reportValidity()) {
    alert("Please complete all required fields across the form sections.");

    return;
  }

  // ==========================================================
  // CHECK DIGITAL SIGNATURE
  // ==========================================================

  const canvasHasSignature =
    typeof window.hasSignature === "function" && window.hasSignature();

  const signatureFile = document.getElementById("signature_file");

  const fileHasSignature =
    signatureFile && signatureFile.files && signatureFile.files.length > 0;

  if (!canvasHasSignature && !fileHasSignature) {
    alert(
      "Please provide a digital signature by drawing on the canvas OR uploading a signature image file.",
    );

    return;
  }

  // ==========================================================
  // SAVE CANVAS SIGNATURE TO HIDDEN INPUT
  // ==========================================================

  const digitalSignature = document.getElementById("digital_signature");

  if (canvasHasSignature && typeof window.getSignatureData === "function") {
    if (digitalSignature) {
      digitalSignature.value = window.getSignatureData();
    }
  } else {
    if (digitalSignature) {
      digitalSignature.value = "";
    }
  }

  // ==========================================================
  // CHECK reCAPTCHA ENTERPRISE
  // ==========================================================

  if (typeof grecaptcha === "undefined" || !grecaptcha.enterprise) {
    console.error("Google reCAPTCHA Enterprise is not available.");

    alert(
      "Security verification is unavailable. Please refresh the page and try again.",
    );

    return;
  }

  // ==========================================================
  // GENERATE reCAPTCHA ENTERPRISE TOKEN
  // ==========================================================

  try {
    await new Promise((resolve) => {
      grecaptcha.enterprise.ready(resolve);
    });

    const token = await grecaptcha.enterprise.execute(
      "6LeDTbosAAAAAEKRg2OKrhBv620cRvwTPw8Fbsv4",
      {
        action: "REQUEST_DOCUMENT",
      },
    );

    // ========================================================
    // VALIDATE TOKEN
    // ========================================================

    if (typeof token !== "string" || token.trim() === "") {
      console.error("reCAPTCHA returned an empty token.");

      alert("Security verification failed. Please try again.");

      return;
    }

    // ========================================================
    // STORE TOKEN IN HIDDEN INPUT
    // ========================================================

    const captchaInput = document.getElementById("g-recaptcha-response");

    if (!captchaInput) {
      console.error("g-recaptcha-response input was not found.");

      alert(
        "Security verification could not be completed. Please refresh the page and try again.",
      );

      return;
    }

    captchaInput.value = token;

    // ========================================================
    // SUBMIT ONLY AFTER SUCCESSFUL CAPTCHA GENERATION
    // ========================================================

    form.submit();
  } catch (error) {
    console.error("reCAPTCHA Enterprise error:", error);

    alert(
      "Security verification failed. Please refresh the page and try again.",
    );

    return;
  }
}
