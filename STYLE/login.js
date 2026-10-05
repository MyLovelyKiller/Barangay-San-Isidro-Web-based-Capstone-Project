function closePopup() {
    const popup = document.getElementById("popup-message");
    if (popup) {
        popup.style.display = "none";
    }

    const url = new URL(window.location.href);
    url.searchParams.delete("reset");
    window.history.replaceState({}, document.title, url.pathname + (url.search ? url.search : ""));
}

function closeToast() {
    const toast = document.getElementById("toast");
    if (toast) {
        toast.classList.remove("show");
    }

    const url = new URL(window.location.href);
    url.searchParams.delete("error");
    window.history.replaceState({}, document.title, url.pathname + (url.search ? url.search : ""));
}

function closeRegisterPopup() {
    const popup = document.getElementById("register-popup");
    if (popup) {
        popup.style.display = "none";
        popup.classList.remove("show");
    }

    const url = new URL(window.location.href);
    url.searchParams.delete("register");
    window.history.replaceState({}, document.title, url.pathname + (url.search ? url.search : ""));
}

window.addEventListener("load", function () {
    const params = new URLSearchParams(window.location.search);

    const popup = document.getElementById("popup-message");
    const popupTitle = document.getElementById("popup-title");
    const popupText = document.getElementById("popup-text");

    if (params.get("reset") === "sent" && popup) {
        popupTitle.innerText = "Password Reset";
        popupText.innerHTML = "We will be sending you a confirmation email shortly.<br>Please check your inbox or spam.";
        popup.style.display = "flex";
    }

    const toast = document.getElementById("toast");
    if (toast) {
        setTimeout(function () {
            toast.classList.remove("show");
        }, 60000);
    }
});


// Password Handler
const password = document.getElementById('password');

// Function to toggle password visibility
function setupToggle(iconId, inputId) {
    const icon = document.getElementById(iconId);
    const input = document.getElementById(inputId);

    icon.addEventListener('click', function() {
        // Toggle the type
        const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
        input.setAttribute('type', type);
        
        // Toggle the icon class (eye vs eye-slash)
        this.classList.toggle('fa-eye');
        this.classList.toggle('fa-eye-slash');
    });
}

// Initialize for both fields
setupToggle('eye-password', 'password');
