
function editCase(caseId) {
    window.location.href = `/BMS/Lupon_Office/cases/edit_case.php?id=${caseId}`;
}

function viewCase(caseId) {
    window.location.href = `/BMS/Lupon_Office/cases/view_case.php?id=${caseId}`;
}

function updateClock() {
    const now = new Date();
    const timeString = now.toLocaleTimeString();
    document.getElementById('clock').textContent = timeString;
}
setInterval(updateClock, 1000);
updateClock();


document.addEventListener('DOMContentLoaded', function () {
    // Select the filter elements
    const statusFilter = document.getElementById('statusFilter');
    const caseSearch = document.getElementById('caseSearch');
    const tableBody = document.getElementById('caseTableBody');

    // Only run the code if the table and filters exist on the current page
    if (tableBody && statusFilter && caseSearch) {
        const rows = tableBody.getElementsByTagName('tr');

        function filterTable() {
            const filterValue = statusFilter.value.toLowerCase();
            const searchValue = caseSearch.value.toLowerCase();

            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];

                // Extract text from columns (Case No, Complainant, Respondent, Status)
                const caseNo = row.cells[0].textContent.toLowerCase();
                const complainant = row.cells[1].textContent.toLowerCase();
                const respondent = row.cells[2].textContent.toLowerCase();
                const status = row.cells[3].textContent.toLowerCase();

                // Logic: Match status OR "filter status" (default)
                const matchesStatus = filterValue === "filter status" || status.includes(filterValue);

                // Logic: Search text matches any of the first three columns
                const matchesSearch = caseNo.includes(searchValue) ||
                    complainant.includes(searchValue) ||
                    respondent.includes(searchValue);

                // Show row if both conditions are true
                if (matchesStatus && matchesSearch) {
                    row.style.display = "";
                } else {
                    row.style.display = "none";
                }
            }
        }

        // Trigger filtering when user interacts
        statusFilter.addEventListener('change', filterTable);
        caseSearch.addEventListener('input', filterTable); // 'input' catches every keystroke
    }
});

function confirmLogout(event) {
    event.preventDefault();
    var href = event.currentTarget.href;

    showConfirmModal('Are you sure you want to log out of the Lupon Office?', {
        title: 'Log Out',
        confirmText: 'Log Out'
    }).then(function (confirmed) {
        if (confirmed) window.location.href = href;
    });

    return false;
}

/* ============================================================
   REUSABLE CONFIRMATION MODAL
   Replaces native confirm() popups with one that matches the
   rest of the app. Injects its own markup and styles the first
   time this file runs, so any page just calls:

       showConfirmModal(message, options).then(function (confirmed) {
           if (!confirmed) return;
           // proceed
       });

   options: { title, confirmText, cancelText, danger }
   "danger" swaps the confirm button to red, for destructive
   actions like Delete or Cancel-with-unsaved-changes.
============================================================ */
(function () {
    var styleTag = document.createElement('style');
    styleTag.textContent =
        '.confirm-modal-overlay{position:fixed;inset:0;background:rgba(10,35,81,0.45);' +
        'display:flex;align-items:center;justify-content:center;padding:20px;z-index:3000;}' +
        '.confirm-modal-overlay[hidden]{display:none;}' +
        '.confirm-modal-box{background:#fff;border-radius:10px;max-width:420px;width:100%;' +
        'box-shadow:0 12px 32px rgba(0,0,0,0.25);padding:24px;font-family:Arial,Helvetica,sans-serif;}' +
        '.confirm-modal-title{margin:0 0 10px;color:#0a2351;font-size:1.1rem;font-weight:700;' +
        'display:flex;align-items:center;gap:10px;}' +
        '.confirm-modal-title i{color:#f39c12;}' +
        '.confirm-modal-title.danger i{color:#e74c3c;}' +
        '.confirm-modal-message{margin:0 0 22px;color:#4a5568;font-size:0.92rem;line-height:1.5;white-space:pre-line;}' +
        '.confirm-modal-actions{display:flex;justify-content:flex-end;gap:10px;}' +
        '.confirm-modal-actions button{padding:10px 22px;border:none;border-radius:5px;' +
        'font-weight:600;cursor:pointer;font-size:0.9rem;}' +
        '.confirm-modal-cancel-btn{background:#e0e0e0;color:#333;}' +
        '.confirm-modal-cancel-btn:hover{background:#d0d0d0;}' +
        '.confirm-modal-confirm-btn{background:#0a2351;color:#fff;}' +
        '.confirm-modal-confirm-btn:hover{background:#1a2558;}' +
        '.confirm-modal-confirm-btn.danger{background:#e74c3c;}' +
        '.confirm-modal-confirm-btn.danger:hover{background:#c0392b;}';
    document.head.appendChild(styleTag);

    var overlay = document.createElement('div');
    overlay.className = 'confirm-modal-overlay';
    overlay.hidden = true;
    overlay.innerHTML =
        '<div class="confirm-modal-box" role="alertdialog" aria-modal="true" aria-labelledby="confirmModalTitleText">' +
            '<h3 class="confirm-modal-title" id="confirmModalTitle">' +
                '<i class="fa fa-triangle-exclamation"></i><span id="confirmModalTitleText"></span>' +
            '</h3>' +
            '<p class="confirm-modal-message" id="confirmModalMessage"></p>' +
            '<div class="confirm-modal-actions">' +
                '<button type="button" class="confirm-modal-cancel-btn" id="confirmModalCancelBtn"></button>' +
                '<button type="button" class="confirm-modal-confirm-btn" id="confirmModalConfirmBtn"></button>' +
            '</div>' +
        '</div>';

    function mount() {
        document.body.appendChild(overlay);
    }
    if (document.body) {
        mount();
    } else {
        document.addEventListener('DOMContentLoaded', mount);
    }

    window.showConfirmModal = function (message, options) {
        options = options || {};

        var titleWrap = document.getElementById('confirmModalTitle');
        var titleText = document.getElementById('confirmModalTitleText');
        var messageEl = document.getElementById('confirmModalMessage');
        var confirmBtn = document.getElementById('confirmModalConfirmBtn');
        var cancelBtn = document.getElementById('confirmModalCancelBtn');

        titleText.textContent = options.title || 'Please Confirm';
        messageEl.textContent = message || '';
        confirmBtn.textContent = options.confirmText || 'Confirm';
        cancelBtn.textContent = options.cancelText || 'Cancel';

        titleWrap.className = 'confirm-modal-title' + (options.danger ? ' danger' : '');
        confirmBtn.className = 'confirm-modal-confirm-btn' + (options.danger ? ' danger' : '');

        overlay.hidden = false;

        return new Promise(function (resolve) {
            function cleanup(result) {
                overlay.hidden = true;
                confirmBtn.removeEventListener('click', onConfirm);
                cancelBtn.removeEventListener('click', onCancel);
                overlay.removeEventListener('click', onOverlayClick);
                document.removeEventListener('keydown', onKeydown);
                resolve(result);
            }
            function onConfirm() { cleanup(true); }
            function onCancel() { cleanup(false); }
            function onOverlayClick(e) { if (e.target === overlay) cleanup(false); }
            function onKeydown(e) { if (e.key === 'Escape') cleanup(false); }

            confirmBtn.addEventListener('click', onConfirm);
            cancelBtn.addEventListener('click', onCancel);
            overlay.addEventListener('click', onOverlayClick);
            document.addEventListener('keydown', onKeydown);
            confirmBtn.focus();
        });
    };
})();

/* ============================================================
   ATTENDANCE — TIME OUT CONFIRMATION
   Used by profile.php. Self-wiring: any page that has a form
   with id="timeOutForm" automatically gets this behavior just
   by including script.js — no page-specific inline <script>
   needed anymore.

   Guards against the case where showConfirmModal itself somehow
   isn't available (e.g. this file loaded but got interrupted
   partway through) by falling back to the native confirm() so
   a missing modal can never silently swallow a real Time Out
   click and leave the attendance record stuck as "still clocked
   in".
============================================================ */
document.addEventListener('DOMContentLoaded', function () {
    var timeOutForm = document.getElementById('timeOutForm');
    if (!timeOutForm) return;

    timeOutForm.addEventListener('submit', function (e) {
        e.preventDefault();

        if (typeof showConfirmModal !== 'function') {
            if (confirm('Time out now?\nThis finalizes your work hours for today.')) {
                timeOutForm.submit();
            }
            return;
        }

        showConfirmModal('This finalizes your work hours for today.', {
            title: 'Time out now?',
            confirmText: 'Time Out'
        }).then(function (confirmed) {
            if (confirmed) timeOutForm.submit();
        });
    });
});