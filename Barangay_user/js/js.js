/* =========================================================
   BARANGAY MANAGEMENT SYSTEM - CORE SCRIPT (js.js)
   ========================================================= */

/* --- 1. SIDEBAR TOGGLE --- */
function toggleSidebar() {
  const sidebar = document.getElementById("sidebar");
  if (!sidebar) return;

  if (window.innerWidth <= 768) {
    // Mobile: slide in/out
    sidebar.classList.toggle("active");
  } else {
    // Desktop: collapse
    sidebar.classList.toggle("collapsed");
  }
}

// Close sidebar when clicking outside (Mobile only)
document.addEventListener("click", function (e) {
  const sidebar = document.getElementById("sidebar");
  if (!sidebar) return;

  if (window.innerWidth <= 768 && sidebar.classList.contains("active")) {
    if (!sidebar.contains(e.target) && !e.target.closest(".burgertab")) {
      sidebar.classList.remove("active");
    }
  }
});

/* --- 2. STATUS POPUP HANDLER --- */
function handleStatusPopup() {
  const popup = document.getElementById("statusPopup");
  if (!popup) return;

  const urlParams = new URLSearchParams(window.location.search);
  const status = urlParams.get("status");
  const msg = urlParams.get("msg");

  let text = "";

  switch (status) {
    case "success":
      text = "Your request has been submitted successfully.";
      break;

    case "limit": {
      const now = new Date();
      const midnight = new Date();
      midnight.setHours(24, 0, 0, 0);

      const diffMs = midnight - now;
      const hours = Math.floor(diffMs / (1000 * 60 * 60));
      const minutes = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));

      text = `You have submitted too many same requests today. Please try again at 12:00 AM (in ${hours}h ${minutes}m).`;
      break;
    }

    case "error":
      text = msg
        ? decodeURIComponent(msg)
        : "There was an error submitting your request.";
      break;

    default:
      return;
  }

  popup.textContent = text;
  popup.classList.add("show");

  const displayTime = status === "limit" ? 8000 : 5000;

  setTimeout(() => {
    popup.classList.remove("show");

    const newUrl = window.location.pathname;
    window.history.replaceState({}, document.title, newUrl);
  }, displayTime);
}

/* --- 3. DYNAMIC DOCUMENT SELECTION (PRICE & REQUIREMENTS) --- */
function setupDocumentSelection() {
  const documentSelect = document.getElementById("document_type");
  const priceInput = document.getElementById("price");
  const reqList = document.getElementById("requirements_list");
  const reqPlaceholder = document.getElementById("req_placeholder");

  if (!documentSelect) return;

  documentSelect.addEventListener("change", function () {
    const selectedOption = this.options[this.selectedIndex];
    const docId = this.value;

    // A. Update Requirements Breakdown from data-requirements attribute
    if (reqList) {
      const rawReqs = selectedOption.getAttribute("data-requirements");
      reqList.innerHTML = "";

      if (rawReqs && rawReqs.trim() !== "" && rawReqs !== "NULL") {
        if (reqPlaceholder) {
          reqPlaceholder.style.display = "none";
        }

        reqList.style.display = "block";

        const items = rawReqs.split(",");

        items.forEach((item) => {
          if (item.trim() !== "") {
            const li = document.createElement("li");

            li.style.marginBottom = "5px";
            li.textContent = item.trim();

            reqList.appendChild(li);
          }
        });
      } else {
        if (reqPlaceholder) {
          reqPlaceholder.style.display = "block";
        }

        reqList.style.display = "none";
      }
    }

    // B. Update Price Display
    if (priceInput) {
      if (!docId) {
        priceInput.value = "₱0.00";
        return;
      }

      // First check if data-price is passed directly in the PHP option tag
      const inlinePrice = selectedOption.getAttribute("data-price");

      if (inlinePrice !== null && inlinePrice !== undefined) {
        const parsedPrice = parseFloat(inlinePrice) || 0;

        priceInput.value =
          parsedPrice === 0 ? "FREE" : "₱" + parsedPrice.toFixed(2);
      } else {
        // Fallback to fetch backend script
        fetch(`../Backend/get_price.php?id=${docId}`)
          .then((res) => res.json())
          .then((data) => {
            const price = parseFloat(data.price) || 0;

            priceInput.value = price === 0 ? "FREE" : "₱" + price.toFixed(2);
          })
          .catch((err) => {
            console.error("Error fetching price:", err);

            priceInput.value = "₱0.00";
          });
      }
    }
  });
}

/* --- 4. PAYMENT METHOD TOGGLE --- */
function togglePayment(isGcash) {
  const gcashDiv = document.getElementById("gcash_section");

  const receiptInput = document.getElementById("payment_receipt");

  const priceInput = document.getElementById("price");

  const notice = document.getElementById("payment_notice");

  if (!gcashDiv) return;

  if (isGcash) {
    gcashDiv.style.display = "block";

    if (notice && priceInput) {
      notice.innerText = priceInput.value;
    }

    if (receiptInput) {
      receiptInput.setAttribute("required", "required");
    }
  } else {
    gcashDiv.style.display = "none";

    if (receiptInput) {
      receiptInput.removeAttribute("required");
    }
  }
}

/* --- 5. SUBMISSION DUPLICATE PREVENTION --- */

/*
 * Tracks whether a submission has already been started.
 *
 * This is intentionally global so all request forms can use
 * the same protection through the shared js.js file.
 */
let submissionInProgress = false;

/**
 * Prevents duplicate form submissions.
 *
 * Call this immediately before the final form submission.
 *
 * Returns:
 *   true  = submission may continue
 *   false = submission is already in progress
 */
function preventDuplicateSubmission(button = null) {
  if (submissionInProgress) {
    return false;
  }

  submissionInProgress = true;

  /*
   * Disable the submit button so the user cannot click it
   * multiple times while reCAPTCHA or the server is processing.
   */
  if (button) {
    button.disabled = true;

    /*
     * Store the original text so it can be restored if
     * the page remains open for some reason.
     */
    if (!button.dataset.originalText) {
      button.dataset.originalText = button.innerHTML;
    }

    button.innerHTML =
      '<i class="fa-solid fa-spinner fa-spin"></i> Processing...';
  }

  return true;
}

/**
 * Restores submission state.
 *
 * Useful if submission needs to be cancelled before the
 * browser actually sends the form.
 */
function resetSubmissionState(button = null) {
  submissionInProgress = false;

  if (button) {
    button.disabled = false;

    if (button.dataset.originalText) {
      button.innerHTML = button.dataset.originalText;
    }
  }
}

/**
 * Automatically reset the client-side submission lock when
 * the page is restored from the browser's back/forward cache.
 */
window.addEventListener("pageshow", function (event) {
  if (event.persisted) {
    submissionInProgress = false;

    const buttons = document.querySelectorAll('button[type="submit"]');

    buttons.forEach((button) => {
      button.disabled = false;

      if (button.dataset.originalText) {
        button.innerHTML = button.dataset.originalText;
      }
    });
  }
});

/* --- 6. INITIALIZE ON DOM LOAD --- */
window.addEventListener("DOMContentLoaded", () => {
  handleStatusPopup();
  setupDocumentSelection();
});

/* --- 7. MULTI-FILE ATTACHMENT MANAGER --- */
let selectedFilesList = new DataTransfer();

function previewSelectedFiles(input) {
  const previewContainer = document.getElementById("fileListPreview");

  if (!previewContainer) return;

  // Add newly selected files to our global DataTransfer list
  for (let i = 0; i < input.files.length; i++) {
    selectedFilesList.items.add(input.files[i]);
  }

  // Assign the combined list back to the file input element
  input.files = selectedFilesList.files;

  // Render the preview list
  renderFileList();
}

function removeSingleFile(index) {
  const input = document.getElementById("attachment");

  if (!input) return;

  const dt = new DataTransfer();

  // Rebuild a fresh DataTransfer list excluding the removed index
  for (let i = 0; i < selectedFilesList.files.length; i++) {
    if (i !== index) {
      dt.items.add(selectedFilesList.files[i]);
    }
  }

  selectedFilesList = dt;

  input.files = selectedFilesList.files;

  // Render the preview list directly without triggering duplicate additions
  renderFileList();
}

function renderFileList() {
  const previewContainer = document.getElementById("fileListPreview");

  if (!previewContainer) return;

  previewContainer.innerHTML = "";

  if (selectedFilesList.files.length > 0) {
    let fileListHTML = '<ul style="margin: 5px 0 0 15px; padding: 0;">';

    for (let i = 0; i < selectedFilesList.files.length; i++) {
      const fileName = selectedFilesList.files[i].name;

      fileListHTML += `
        <li style="margin-bottom: 4px; display: flex; justify-content: space-between; align-items: center;">
          <span>
            <i class="fa-solid fa-file-arrow-up" style="color: #007bff;"></i>
            ${fileName}
          </span>

          <button
            type="button"
            onclick="removeSingleFile(${i})"
            style="background: none; border: none; color: red; cursor: pointer; font-size: 0.9em; margin-left: 10px;"
            title="Remove file"
          >
            &times;
          </button>
        </li>`;
    }

    fileListHTML += "</ul>";

    previewContainer.innerHTML = fileListHTML;
  }
}
