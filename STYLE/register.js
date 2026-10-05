/**
 * =========================================================
 * 1. UI LOGIC FOR ACCOUNT TYPE & DEPARTMENT
 * =========================================================
 */

const accountType = document.getElementById("accountType");

const departmentGroup = document.querySelector(".form-group.department-group");

const departmentSelect = document.getElementById("department");

if (accountType) {
  accountType.addEventListener("change", function () {
    if (this.value === "official") {
      if (departmentGroup) {
        departmentGroup.style.display = "flex";
      }

      if (departmentSelect) {
        departmentSelect.required = true;
      }
    } else {
      if (departmentGroup) {
        departmentGroup.style.display = "none";
      }

      if (departmentSelect) {
        departmentSelect.required = false;

        departmentSelect.value = "";
      }
    }
  });
}

/**
 * =========================================================
 * 2. REGISTRATION SECURITY HELPERS
 * =========================================================
 */

/*
 * Get the current CSRF token from the registration form.
 */
function getCsrfToken() {
  const csrfInput = document.querySelector(
    '#requestForm input[name="csrf_token"]',
  );

  return csrfInput ? csrfInput.value : "";
}

/*
 * Get the honeypot value.
 */
function getHoneypotValue() {
  const honeypot = document.querySelector('input[name="bms_reg_v_field"]');

  return honeypot ? honeypot.value : "";
}

/**
 * =========================================================
 * 3. FILE VALIDATION
 *
 * This is client-side validation only.
 *
 * The SERVER MUST perform the same validation and
 * ClamAV scan in send_otp.php.
 * =========================================================
 */

function validateProofFile() {
  const proof = document.getElementById("proof");

  if (!proof) {
    return {
      valid: false,
      message: "Proof of valid ID field was not found.",
    };
  }

  if (!proof.files || proof.files.length === 0) {
    return {
      valid: false,
      message: "Please select your proof of valid ID.",
    };
  }

  const file = proof.files[0];

  /*
   * Maximum 5 MB.
   */
  const maxFileSize = 5 * 1024 * 1024;

  if (file.size <= 0) {
    return {
      valid: false,
      message: "The selected file is empty.",
    };
  }

  if (file.size > maxFileSize) {
    return {
      valid: false,
      message: "The proof of valid ID must not exceed 5 MB.",
    };
  }

  /*
   * Only these extensions are accepted.
   *
   * This is NOT a security boundary.
   * send_otp.php must validate the actual file.
   */
  const allowedExtensions = ["jpg", "jpeg", "png", "gif", "webp", "pdf"];

  const fileName = file.name.toLowerCase();

  const extension = fileName.includes(".") ? fileName.split(".").pop() : "";

  if (!allowedExtensions.includes(extension)) {
    return {
      valid: false,
      message:
        "Invalid file type. Please upload JPG, JPEG, PNG, GIF, WEBP, or PDF.",
    };
  }

  return {
    valid: true,
    message: "",
  };
}

/**
 * =========================================================
 * 4. MANAGE REGISTRATION
 * STEP 1: SEND OTP
 * =========================================================
 */

window.validateAndSubmit = function () {
  const form = document.getElementById("requestForm");

  const submitBtn = document.getElementById("sendOtpBtn");

  if (!form || !submitBtn) {
    console.error("Registration form or submit button not found.");

    return;
  }

  /*
   * HTML validation.
   */
  if (!form.checkValidity()) {
    form.reportValidity();

    return;
  }

  /*
   * Password matching.
   */
  const password = document.getElementById("password");

  const confirmPassword = document.getElementById("confirm_password");

  if (password && confirmPassword && password.value !== confirmPassword.value) {
    alert("Passwords do not match.");

    confirmPassword.focus();

    return;
  }

  /*
   * Department validation.
   */
  const selectedAccountType = document.getElementById("accountType");

  if (selectedAccountType && selectedAccountType.value === "official") {
    if (!departmentSelect || !departmentSelect.value) {
      alert("Please select your department.");

      if (departmentSelect) {
        departmentSelect.focus();
      }

      return;
    }
  }

  /*
   * Proof file validation.
   */
  const fileCheck = validateProofFile();

  if (!fileCheck.valid) {
    alert(fileCheck.message);

    return;
  }

  /*
   * Make sure CSRF exists.
   */
  const csrfToken = getCsrfToken();

  if (!csrfToken) {
    alert("Security token is missing. Please reload the page and try again.");

    return;
  }

  /*
   * Disable button while request is running.
   */
  submitBtn.innerText = "Sending OTP...";

  submitBtn.disabled = true;

  /*
   * FormData automatically includes:
   *
   * - account_type
   * - satellite_id
   * - department
   * - name
   * - username
   * - email
   * - contact_number
   * - password
   * - confirm_password
   * - proof
   * - id_number
   * - csrf_token
   * - honeypot
   * - reCAPTCHA token
   */
  const formData = new FormData(form);

  /*
   * Explicitly ensure the CSRF token is present.
   */
  formData.set("csrf_token", csrfToken);

  /*
   * Explicitly ensure the honeypot is present.
   */
  formData.set("bms_reg_v_field", getHoneypotValue());

  /*
   * Make sure reCAPTCHA token exists.
   */
  const captchaToken = document.getElementById("g-recaptcha-response");

  if (!captchaToken || !captchaToken.value) {
    alert("Security verification is missing. Please try again.");

    submitBtn.innerText = "Send OTP";

    submitBtn.disabled = false;

    return;
  }

  formData.set("g-recaptcha-response", captchaToken.value);

  fetch("/BMS/BACKEND/send_otp.php", {
    method: "POST",
    body: formData,
    credentials: "same-origin",
  })
    .then(async (response) => {
      const text = await response.text();

      let data;

      try {
        data = JSON.parse(text);
      } catch (error) {
        console.error("Invalid JSON from server:", text);

        throw new Error("Server returned an invalid response.");
      }

      if (!response.ok) {
        throw new Error(data.message || "The server rejected the request.");
      }

      return data;
    })
    .then((data) => {
      if (data.success) {
        /*
         * Hide registration fields.
         */
        const fields = document.getElementById("registrationFields");

        if (fields) {
          fields.style.display = "none";
        }

        /*
         * Hide Send OTP button.
         */
        submitBtn.style.display = "none";

        /*
         * Show OTP form.
         */
        const otpForm = document.getElementById("otpVerifyForm");

        if (otpForm) {
          otpForm.style.display = "block";
        }

        /*
         * Focus OTP input.
         */
        const otpInput = document.getElementById("otpCode");

        if (otpInput) {
          otpInput.focus();
        }
      } else {
        alert("Error: " + (data.message || "Unable to send OTP."));

        submitBtn.innerText = "Send OTP";

        submitBtn.disabled = false;
      }
    })
    .catch((error) => {
      console.error("Registration request error:", error);

      alert(
        error.message ||
          "An error occurred. Please check your connection and try again.",
      );

      submitBtn.innerText = "Send OTP";

      submitBtn.disabled = false;
    });
};

/**
 * =========================================================
 * 5. OTP VERIFICATION
 * STEP 2: FINAL REGISTRATION
 * =========================================================
 */

const otpForm = document.getElementById("otpVerifyForm");

if (otpForm) {
  otpForm.addEventListener("submit", function (e) {
    e.preventDefault();

    const otpInput = document.getElementById("otpCode");

    const submitBtn = document.getElementById("confirmRegisterBtn");

    if (!otpInput || !submitBtn) {
      return;
    }

    const otpCode = otpInput.value.trim();

    /*
     * Client-side OTP validation.
     */
    if (!/^\d{6}$/.test(otpCode)) {
      alert("Please enter the 6-digit OTP.");

      otpInput.focus();

      return;
    }

    /*
     * Retrieve CSRF token.
     */
    const csrfToken = getCsrfToken();

    if (!csrfToken) {
      alert("Security token is missing. Please reload the page and try again.");

      return;
    }

    submitBtn.innerText = "Verifying...";

    submitBtn.disabled = true;

    /*
     * Create OTP request.
     */
    const otpFormData = new FormData();

    /*
     * OTP.
     */
    otpFormData.append("otp_code", otpCode);

    /*
     * CSRF TOKEN.
     *
     * IMPORTANT:
     * Your previous JavaScript was missing this.
     */
    otpFormData.append("csrf_token", csrfToken);

    /*
     * Honeypot.
     */
    otpFormData.append("bms_reg_v_field", getHoneypotValue());

    fetch("/BMS/BACKEND/register1.php", {
      method: "POST",
      body: otpFormData,
      credentials: "same-origin",
    })
      .then(async (response) => {
        const text = await response.text();

        let data;

        try {
          data = JSON.parse(text);
        } catch (error) {
          console.error("JSON Parsing Error. Server returned:", text);

          throw new Error("Server returned an invalid response.");
        }

        if (!response.ok) {
          throw new Error(data.message || "Registration request was rejected.");
        }

        return data;
      })
      .then((data) => {
        if (data.success) {
          alert("Registration Complete! Redirecting to login...");

          window.location.href = "/BMS/CODES/login.php?success=pending";
        } else {
          alert(
            "Error: " +
              (data.message || "Registration could not be completed."),
          );

          submitBtn.innerText = "Verify & Register";

          submitBtn.disabled = false;
        }
      })
      .catch((error) => {
        console.error("OTP verification error:", error);

        alert(error.message || "Could not connect to the server.");

        submitBtn.innerText = "Verify & Register";

        submitBtn.disabled = false;
      });
  });
}

/**
 * =========================================================
 * 6. PASSWORD STRENGTH
 * =========================================================
 */

const password = document.getElementById("password");

const confirmPassword = document.getElementById("confirm_password");

const strengthFill = document.getElementById("strength-fill");

const strengthText = document.getElementById("strength-text");

const matchMessage = document.getElementById("match-message");

if (password) {
  password.addEventListener("input", () => {
    const val = password.value;

    const checks = {
      length: val.length >= 8,

      uppercase: /[A-Z]/.test(val),

      lowercase: /[a-z]/.test(val),

      number: /\d/.test(val),

      special: /[@$!%*?&#^()_\-+=]/.test(val),
    };

    const passedCount = Object.values(checks).filter(Boolean).length;

    const colors = ["#ff4d4d", "#ffa64d", "#ffff4d", "#99ff33", "#2eb82e"];

    if (strengthFill) {
      strengthFill.style.width = (passedCount / 5) * 100 + "%";

      strengthFill.style.backgroundColor = colors[passedCount - 1] || "#eee";
    }

    if (strengthText) {
      strengthText.innerText =
        ["Very Weak", "Weak", "Fair", "Good", "Strong"][passedCount - 1] || "";
    }

    if (confirmPassword && confirmPassword.value !== "") {
      validateMatch();
    }
  });
}

/**
 * =========================================================
 * 7. PASSWORD MATCHING
 * =========================================================
 */

function validateMatch() {
  if (!confirmPassword || !password || !matchMessage) {
    return;
  }

  if (confirmPassword.value === "") {
    matchMessage.innerText = "";

    confirmPassword.setCustomValidity("");
  } else if (confirmPassword.value === password.value) {
    matchMessage.innerText = "Passwords match!";

    matchMessage.style.color = "green";

    confirmPassword.setCustomValidity("");
  } else {
    matchMessage.innerText = "Passwords do not match.";

    matchMessage.style.color = "red";

    confirmPassword.setCustomValidity("Passwords do not match.");
  }
}

if (confirmPassword) {
  confirmPassword.addEventListener("input", validateMatch);
}

/**
 * =========================================================
 * 8. PASSWORD VISIBILITY TOGGLE
 * =========================================================
 */

function setupToggle(iconId, inputId) {
  const icon = document.getElementById(iconId);

  const input = document.getElementById(inputId);

  if (icon && input) {
    icon.addEventListener("click", function () {
      const type =
        input.getAttribute("type") === "password" ? "text" : "password";

      input.setAttribute("type", type);

      this.classList.toggle("fa-eye");

      this.classList.toggle("fa-eye-slash");
    });
  }
}

setupToggle("eye-password", "password");

setupToggle("eye-confirm", "confirm_password");
