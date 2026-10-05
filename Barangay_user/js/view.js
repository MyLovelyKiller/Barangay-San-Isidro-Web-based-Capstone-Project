let currentFilter = "all";

document.addEventListener("DOMContentLoaded", () => {
  const searchInput = document.getElementById("searchInput");
  if (searchInput) {
    searchInput.addEventListener("input", applyFilters);
  }

  document.querySelectorAll(".filterbtn").forEach((btn) => {
    btn.addEventListener("click", function () {
      document
        .querySelectorAll(".filterbtn")
        .forEach((b) => b.classList.remove("active"));
      this.classList.add("active");
      currentFilter = this.getAttribute("data-filter");
      applyFilters();
    });
  });
});

function applyFilters() {
  const searchInput = document.getElementById("searchInput");
  const query = searchInput ? searchInput.value.toLowerCase() : "";

  document.querySelectorAll(".historycard").forEach((card) => {
    const status = card.getAttribute("data-status");
    const content = card.textContent.toLowerCase();
    const matchesFilter = currentFilter === "all" || status === currentFilter;
    const matchesSearch = content.includes(query);

    card.style.display = matchesFilter && matchesSearch ? "block" : "none";
  });
}

// Universal function to open the details popup
function openPopup(
  docName,
  status,
  submitted,
  fullname,
  birthdate,
  phone,
  email,
  address,
  purpose,
  price,
  method,
  ref,
  note,
  attachment,
) {
  document.getElementById("modalDocTitle").textContent = docName + " - Details";
  document.getElementById("modalStatus").textContent = status;
  document.getElementById("modalSubmitted").textContent = submitted;
  document.getElementById("modalFullname").textContent = fullname;
  document.getElementById("modalBirthdate").textContent = birthdate;
  document.getElementById("modalPhone").textContent = phone;
  document.getElementById("modalEmail").textContent = email;
  document.getElementById("modalAddress").textContent = address;
  document.getElementById("modalPurpose").textContent = purpose;

  let paymentDetails = price;
  if (method && method !== "N/A") {
    paymentDetails += ` via ${method}`;
    if (ref && ref !== "N/A") {
      paymentDetails += ` (Ref: ${ref})`;
    }
  }
  document.getElementById("modalPayment").textContent = paymentDetails;

  const noteGroup = document.getElementById("modalNoteGroup");
  if (note && note.trim() !== "") {
    noteGroup.style.display = "block";
    document.getElementById("modalNoteText").textContent = note;
  } else {
    noteGroup.style.display = "none";
  }

  // Handle Multiple Attachments Preview
  const attachGroup = document.getElementById("modalAttachmentGroup");
  const attachContent = document.getElementById("modalAttachmentContent");
  attachContent.innerHTML = ""; // Clear previous files

  if (attachment && attachment.trim() !== "") {
    // Split comma-separated file paths coming from GROUP_CONCAT
    const files = attachment.split(",");

    files.forEach((file) => {
      let cleanPath = file.trim();
      if (cleanPath.startsWith("../")) {
        cleanPath = cleanPath.replace("../", "");
      }
      const filePath = `../${cleanPath}`;
      const ext = cleanPath.split(".").pop().toLowerCase();

      let fileElement = document.createElement("div");
      fileElement.style.marginBottom = "10px";

      if (["jpg", "jpeg", "png", "gif", "webp"].includes(ext)) {
        fileElement.innerHTML = `
          <a href="${filePath}" target="_blank">
            <img src="${filePath}" alt="Attachment Proof" style="max-width: 100%; height: auto; border-radius: 6px; margin-top: 5px; border: 1px solid #ddd;" />
          </a>
        `;
      } else {
        fileElement.innerHTML = `
          <a href="${filePath}" target="_blank" class="btn-download" style="display: inline-block; margin-top: 5px; color: #007bff; text-decoration: none;">
            <i class="fa-solid fa-download"></i> View / Download File (${ext.toUpperCase()})
          </a>
        `;
      }

      attachContent.appendChild(fileElement);
    });

    attachGroup.style.display = "block";
  } else {
    attachGroup.style.display = "none";
  }

  document.getElementById("detailsModal").style.display = "flex";
}

// Universal function to close the popup
function closePopup(event) {
  if (event.target.id === "detailsModal") {
    document.getElementById("detailsModal").style.display = "none";
  }
}
