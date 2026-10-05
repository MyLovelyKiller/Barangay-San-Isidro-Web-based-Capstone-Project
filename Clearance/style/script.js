// Topbar Live Clock
function updateClock() {
  const clockElem = document.getElementById("clock");
  if (clockElem) {
    const now = new Date();
    clockElem.textContent = now.toLocaleTimeString();
  }
}
setInterval(updateClock, 1000);
updateClock();

// Sidebar Toggle
function toggleSidebar() {
  document.body.classList.toggle("sidebar-collapsed");
}

// Profile Picture Preview Handler
const profileInput = document.getElementById("profilePictureInput");
if (profileInput) {
  profileInput.addEventListener("change", function (e) {
    const file = e.target.files[0];
    const preview = document.getElementById("profilePreview");
    const fileName = document.getElementById("fileName");

    if (file) {
      const reader = new FileReader();
      reader.onload = function (event) {
        if (preview) preview.src = event.target.result;
      };
      reader.readAsDataURL(file);
      if (fileName) fileName.textContent = file.name;
    } else {
      if (fileName) fileName.textContent = "No file chosen";
    }
  });
}

// HTML Escaper Helper
function escapeHtml(text) {
  if (text === null || text === undefined || text === "") return "N/A";
  return String(text)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

// View Details Modal Content Generator
function viewDetails(req) {
  const modalTitle = document.getElementById("modalTitle");
  const modalContent = document.getElementById("modalContent");
  const modal = document.getElementById("detailsModal");

  if (!req) return;

  if (modalTitle) {
    modalTitle.textContent =
      "Request #" +
      (req.request_id || req.id || "") +
      " - " +
      (req.request_type || req.type || "Document Request");
  }

  // Formats attachment file paths targeting ../upload/attachments/
  function formatAttachmentPath(path) {
    if (!path) return "";
    path = path.trim();
    if (path.startsWith("http://") || path.startsWith("https://")) return path;

    // Strip leading ../, ./, or /
    var cleanPath = path.replace(/^(\.\.\/|\.\/|\/)+/, "");

    // Handle common variations saved in database
    if (cleanPath.startsWith("upload/attachments/")) return "../" + cleanPath;
    if (cleanPath.startsWith("uploads/attachments/")) return "../" + cleanPath;
    if (cleanPath.startsWith("attachments/")) return "../upload/" + cleanPath;
    if (cleanPath.startsWith("upload/") || cleanPath.startsWith("uploads/"))
      return "../" + cleanPath;

    // Fallback: If DB stores filename directly
    return "../upload/attachments/" + cleanPath;
  }

  // Consolidated Attachment formatting
  var attachmentHTML = "None";
  if (req.attachment && req.attachment.trim() !== "") {
    var files = req.attachment.split(",");
    attachmentHTML =
      '<div style="display: flex; gap: 12px; flex-wrap: wrap; margin-top: 5px;">';

    for (var i = 0; i < files.length; i++) {
      var rawPath = files[i].trim();
      if (!rawPath) continue;

      var filePath = formatAttachmentPath(rawPath);
      var fileName = rawPath.substring(rawPath.lastIndexOf("/") + 1);
      var ext = fileName.split(".").pop().toLowerCase();

      if (["jpg", "jpeg", "png", "gif", "webp"].includes(ext)) {
        attachmentHTML +=
          '<div class="preview-container">' +
          '<a href="' +
          filePath +
          '" target="_blank" title="Click to view full image">' +
          '<img src="' +
          filePath +
          '" alt="Attachment Image" class="modal-img-preview">' +
          "</a>" +
          '<a href="' +
          filePath +
          '" target="_blank" class="full-img-link"><i class="fas fa-external-link-alt"></i> View Full Image</a>' +
          "</div>";
      } else {
        attachmentHTML +=
          '<a href="' +
          filePath +
          '" target="_blank" download class="attachment-badge"><i class="fas fa-file-download"></i> ' +
          escapeHtml(fileName || "Download File " + (i + 1)) +
          "</a>";
      }
    }
    attachmentHTML += "</div>";
  }

  var receiptHTML = "None";
  if (req.payment_receipt) {
    var receiptPath = formatAttachmentPath(req.payment_receipt);
    receiptHTML =
      '<div class="preview-container">' +
      '<a href="' +
      receiptPath +
      '" target="_blank">' +
      '<img src="' +
      receiptPath +
      '" alt="Receipt Preview" class="modal-img-preview">' +
      "</a>" +
      '<a href="' +
      receiptPath +
      '" target="_blank" class="full-img-link"><i class="fas fa-external-link-alt"></i> Open Full Receipt</a>' +
      "</div>";
  }

  var signatureHTML = "None";
  if (req.digital_signature) {
    var sigPath = formatAttachmentPath(req.digital_signature);
    signatureHTML =
      '<div class="preview-container">' +
      '<a href="' +
      sigPath +
      '" target="_blank">' +
      '<img src="' +
      sigPath +
      '" alt="Digital Signature" class="modal-img-preview">' +
      "</a>" +
      '<a href="' +
      sigPath +
      '" target="_blank" class="full-img-link"><i class="fas fa-external-link-alt"></i> View Signature</a>' +
      "</div>";
  }

  // Extract price or fee from request object (Added req.document_fee first)
  var rawPrice =
    req.document_fee ||
    req.amount ||
    req.fee ||
    req.price ||
    req.request_fee ||
    req.cost ||
    0;
  var priceDisplay =
    rawPrice !== null &&
    rawPrice !== undefined &&
    rawPrice !== "" &&
    !isNaN(rawPrice)
      ? "₱" + parseFloat(rawPrice).toFixed(2)
      : rawPrice
        ? escapeHtml(String(rawPrice))
        : "Free / N/A";

  var html = "";

  html +=
    '<div class="modal-section-header"><i class="fas fa-user"></i> Resident Personal Information</div>';
  html += '<table class="details-table">';
  html +=
    "  <tr><th>Full Name:</th><td>" + escapeHtml(req.fullname) + "</td></tr>";
  html +=
    "  <tr><th>Resident ID:</th><td>" +
    escapeHtml(req.resident_id || "N/A") +
    "</td></tr>";
  html +=
    "  <tr><th>Birthdate:</th><td>" +
    escapeHtml(req.birthdate || "N/A") +
    "</td></tr>";
  html +=
    "  <tr><th>Address:</th><td>" +
    escapeHtml(req.address || "N/A") +
    "</td></tr>";
  html +=
    "  <tr><th>Email Address:</th><td>" +
    escapeHtml(req.email || "N/A") +
    "</td></tr>";
  html +=
    "  <tr><th>Phone Number:</th><td>" +
    escapeHtml(req.phone || "N/A") +
    "</td></tr>";
  html += "</table>";

  html +=
    '<div class="modal-section-header"><i class="fas fa-file-invoice"></i> Request & Document Details</div>';
  html += '<table class="details-table">';
  html +=
    "  <tr><th>Document Type:</th><td>" +
    escapeHtml(req.type || req.request_type || "N/A") +
    "</td></tr>";
  html +=
    "  <tr><th>Category:</th><td>" +
    escapeHtml(req.category || "N/A") +
    "</td></tr>";
  html +=
    '  <tr><th>Request Fee:</th><td><span class="price-tag">' +
    priceDisplay +
    "</span></td></tr>";
  html +=
    "  <tr><th>Purpose:</th><td>" +
    escapeHtml(req.purpose || "N/A") +
    "</td></tr>";
  html +=
    "  <tr><th>Status:</th><td><strong>" +
    escapeHtml(req.status) +
    "</strong></td></tr>";
  html +=
    "  <tr><th>Date Submitted:</th><td>" +
    escapeHtml(req.submitted_at || "N/A") +
    "</td></tr>";
  if (req.updated_at) {
    html +=
      "  <tr><th>Last Updated:</th><td>" +
      escapeHtml(req.updated_at) +
      "</td></tr>";
  }
  if (req.reason_message) {
    html +=
      "  <tr><th>Officer Message:</th><td>" +
      escapeHtml(req.reason_message) +
      "</td></tr>";
  }
  html += "</table>";

  html +=
    '<div class="modal-section-header"><i class="fas fa-wallet"></i> Payment & Verification</div>';
  html += '<table class="details-table">';
  html +=
    "  <tr><th>Payment Method:</th><td>" +
    escapeHtml(req.payment_method || "Walk-in") +
    "</td></tr>";
  html +=
    "  <tr><th>Reference Number:</th><td>" +
    escapeHtml(req.ref_number || "N/A") +
    "</td></tr>";
  html += "  <tr><th>Payment Receipt:</th><td>" + receiptHTML + "</td></tr>";
  html +=
    "  <tr><th>Digital Signature:</th><td>" + signatureHTML + "</td></tr>";
  html += "  <tr><th>Attachments:</th><td>" + attachmentHTML + "</td></tr>";
  html += "</table>";

  if (modalContent) modalContent.innerHTML = html;

  if (modal) {
    modal.classList.add("active");
    modal.style.display = "flex";
  }
}

// Modal Toggle Helpers
function closeModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.classList.remove("active");
    modal.style.display = "none";
  }
}

function openApprovalModal(id, type) {
  const modal = document.getElementById("approvalModal");
  document.getElementById("approvalId").value = id;
  document.getElementById("approvalType").value = type;
  document.getElementById("approvalMessage").value = "";
  if (modal) {
    modal.classList.add("active");
    modal.style.display = "flex";
  }
}

function openRejectionModal(id, type) {
  const modal = document.getElementById("rejectionModal");
  document.getElementById("rejectionId").value = id;
  document.getElementById("rejectionType").value = type;
  document.getElementById("rejectionMessage").value = "";
  if (modal) {
    modal.classList.add("active");
    modal.style.display = "flex";
  }
}

function openPendingModal(id, type) {
  const modal = document.getElementById("pendingModal");
  document.getElementById("pendingId").value = id;
  document.getElementById("pendingType").value = type;
  document.getElementById("pendingMessage").value = "";
  if (modal) {
    modal.classList.add("active");
    modal.style.display = "flex";
  }
}

// Form Submission Handlers
function submitApproval(event) {
  event.preventDefault();
  submitRequestForm("approvalForm", "Request approved successfully!");
}

function submitRejection(event) {
  event.preventDefault();

  const rejectionMsg = document.getElementById("rejectionMessage");
  if (!rejectionMsg || !rejectionMsg.value.trim()) {
    alert("Please provide a rejection reason.");
    return;
  }

  submitRequestForm("rejectionForm", "Request rejected successfully!");
}

function submitPending(event) {
  event.preventDefault();
  submitRequestForm("pendingForm", "Request marked as pending!");
}

// AJAX Form Processor
function submitRequestForm(formId, successMessage) {
  const form = document.getElementById(formId);
  if (!form) return;

  const formData = new FormData(form);

  const xhr = new XMLHttpRequest();
  xhr.open("POST", "process_request.php", true);
  xhr.onload = function () {
    if (xhr.status === 200) {
      alert(successMessage);
      location.reload();
    } else {
      alert("Error: " + xhr.responseText);
    }
  };
  xhr.send(formData);
}

// Close Modal when clicking backdrop
window.onclick = function (event) {
  if (event.target.classList.contains("modal")) {
    event.target.classList.remove("active");
    event.target.style.display = "none";
  }
};
