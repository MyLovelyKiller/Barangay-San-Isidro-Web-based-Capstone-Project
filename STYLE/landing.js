document.addEventListener("DOMContentLoaded", function () {
    const topLogo = document.querySelector(".top-logo img");

    if (topLogo) {
        topLogo.style.transition = "transform 1.5s ease";
        topLogo.style.transform = "rotateY(360deg)";
    }

    const homeBtn = document.getElementById("homeBtn");

    if (homeBtn) {
        homeBtn.addEventListener("click", function (e) {
            e.preventDefault();
            window.scrollTo({
                top: 0,
                behavior: "smooth"
            });
        });
    }

    const logo1 = document.querySelector(".logo1");

    if (logo1) {
        logo1.addEventListener("click", function () {
            window.scrollTo({
                top: 0,
                behavior: "smooth"
            });
        });
    }

    const aboutBtn = document.getElementById("aboutBtn");
    const aboutSection = document.querySelector(".about");

    if (aboutBtn && aboutSection) {
        aboutBtn.addEventListener("click", function (e) {
            e.preventDefault();
            aboutSection.scrollIntoView({
                behavior: "smooth"
            });
        });
    }

    const contactBtn = document.getElementById("contactBtn");
    const contactSection = document.querySelector(".contact");

    if (contactBtn && contactSection) {
        contactBtn.addEventListener("click", function (e) {
            e.preventDefault();
            contactSection.scrollIntoView({
                behavior: "smooth"
            });
        });
    }

    const learnMoreBtn = document.getElementById("learnMoreBtn");

    if (learnMoreBtn && aboutSection) {
        learnMoreBtn.addEventListener("click", function (e) {
            e.preventDefault();
            aboutSection.scrollIntoView({
                behavior: "smooth"
            });
        });
    }
});

function closePopup() {
    const popup = document.getElementById("popupModal");
    if (popup) popup.style.display = "none";
}

setTimeout(() => {
    const popup = document.getElementById("popupModal");
    if (popup) popup.style.display = "none";
}, 3000);

function setCookie(name, value, days) {
    let expires = "";
    if (days) {
        const date = new Date();
        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
        expires = "; expires=" + date.toUTCString();
    }
    document.cookie = name + "=" + value + expires + "; path=/; SameSite=Lax";
}

function getCookie(name) {
    const nameEQ = name + "=";
    const cookies = document.cookie.split(';');
    for (let i = 0; i < cookies.length; i++) {
        let c = cookies[i];
        while (c.charAt(0) === ' ') c = c.substring(1, c.length);
        if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
    }
    return null;
}

function acceptCookies() {
    setCookie("cookie_consent", "accepted", 180);
    document.getElementById("cookieBanner").style.display = "none";
}

function rejectCookies() {
    setCookie("cookie_consent", "rejected", 180);
    document.getElementById("cookieBanner").style.display = "none";
}

window.addEventListener("load", function () {
    const consent = getCookie("cookie_consent");
    if (!consent) {
        document.getElementById("cookieBanner").style.display = "block";
    }
});