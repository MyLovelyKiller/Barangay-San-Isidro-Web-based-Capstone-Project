// List of icons available in your FontAwesome library
const captchaIcons = [
  { name: "Bus", class: "fa-bus" },
  { name: "Car", class: "fa-car" },
  { name: "Bicycle", class: "fa-bicycle" },
  { name: "Plane", class: "fa-plane" },
  { name: "Truck", class: "fa-truck" },
  { name: "Motorcycle", class: "fa-motorcycle" },
];

let targetName = ""; // Stores what the user is supposed to click

function openCaptcha() {
  const form = document.getElementById("requestForm");

  // Only open captcha if the main form fields are valid
  if (form.checkValidity()) {
    const modal = document.getElementById("captchaModal");
    const grid = document.getElementById("captcha-grid");
    const instruction = document.getElementById("captcha-instruction");

    // 1. Pick a random target icon
    const target =
      captchaIcons[Math.floor(Math.random() * captchaIcons.length)];
    targetName = target.name;

    // 2. Update the instruction text
    if (instruction) {
      instruction.innerText = `To continue, please click the ${targetName}`;
    }

    // 3. Clear and generate the grid
    grid.innerHTML = "";

    // Shuffle icons so they aren't always in the same place
    const shuffled = [...captchaIcons].sort(() => 0.5 - Math.random());

    shuffled.forEach((icon) => {
      const iconBox = document.createElement("div");
      iconBox.className = "captcha-icon-box";
      iconBox.innerHTML = `<i class="fa-solid ${icon.class}"></i>`;

      // Store the icon name directly in the element for easy retrieval
      iconBox.setAttribute("data-icon-name", icon.name);

      // Click event for the icon
      iconBox.onclick = function () {
        // Remove 'selected' class from all boxes
        document.querySelectorAll(".captcha-icon-box").forEach((el) => {
          el.classList.remove("selected");
        });

        // Add 'selected' to this specific box
        iconBox.classList.add("selected");
      };

      grid.appendChild(iconBox);
    });

    modal.style.display = "block";
  } else {
    form.reportValidity();
  }
}

function closeCaptcha() {
  document.getElementById("captchaModal").style.display = "none";
}

function refreshCaptcha() {
  openCaptcha();
}

function validateAndSubmit() {
  const selectedBox = document.querySelector(".captcha-icon-box.selected");

  if (!selectedBox) {
    alert("Please select an icon first.");
    return;
  }

  // Get the name from the data attribute we set during creation
  const selectedValue = selectedBox.getAttribute("data-icon-name");

  if (selectedValue === targetName) {
    // SUCCESS: Submit the form
    document.getElementById("requestForm").submit();
  } else {
    // FAIL: Alert and reset
    alert(
      `Incorrect. You clicked a ${selectedValue}, but we asked for the ${targetName}. Please try again.`,
    );
    refreshCaptcha();
  }
}

// Close modal if user clicks outside of the modal content
window.onclick = function (event) {
  const modal = document.getElementById("captchaModal");
  if (event.target == modal) {
    modal.style.display = "none";
  }
};
