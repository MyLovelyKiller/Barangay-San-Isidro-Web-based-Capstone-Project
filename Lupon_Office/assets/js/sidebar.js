document.addEventListener("DOMContentLoaded", function() {
    const sidebar = document.getElementById("sidebar");
    const burgertab = document.getElementById("burgertab");

    burgertab.addEventListener("click", function() {
        sidebar.classList.toggle("collapsed");
    });
});