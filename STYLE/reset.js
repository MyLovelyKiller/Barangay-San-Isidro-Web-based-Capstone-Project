
// Password Handler
const password = document.getElementById('password');
const confirmPassword = document.getElementById('confirm_password');
const strengthFill = document.getElementById('strength-fill');
const strengthText = document.getElementById('strength-text');
const matchMessage = document.getElementById('match-message');

// Requirements Checklist Elements
const requirements = {
    length: document.getElementById('rule-length'),
    uppercase: document.getElementById('rule-uppercase'),
    lowercase: document.getElementById('rule-lowercase'),
    number: document.getElementById('rule-number'),
    special: document.getElementById('rule-special')
};

password.addEventListener('input', () => {
    const val = password.value;
    
    // 1. Check Requirements
    const checks = {
        length: val.length >= 8,
        uppercase: /[A-Z]/.test(val),
        lowercase: /[a-z]/.test(val),
        number: /\d/.test(val),
        special: /[@$!%*?&#^()_\-+=]/.test(val)
    };

    // Update UI Checklist using CSS classes
    Object.keys(checks).forEach(key => {
        if (checks[key]) {
            requirements[key].classList.add('valid');
        } else {
            requirements[key].classList.remove('valid');
        }
    });

    // 2. Calculate Strength Bar
    const passedCount = Object.values(checks).filter(Boolean).length;
    const colors = ["#ff4d4d", "#ffa64d", "#ffff4d", "#99ff33", "#2eb82e"];
    
    strengthFill.style.width = (passedCount / 5) * 100 + "%";
    strengthFill.style.backgroundColor = colors[passedCount - 1] || "#eee";
    strengthText.innerText = ["Very Weak", "Weak", "Fair", "Good", "Strong"][passedCount - 1] || "";
    
    validateMatch();
});

// 3. Confirm Password Validation
function validateMatch() {
    if (confirmPassword.value === "") {
        matchMessage.innerText = "";
    } else if (confirmPassword.value === password.value) {
        matchMessage.innerText = "Passwords match!";
        matchMessage.style.color = "green";
        confirmPassword.setCustomValidity(""); // Clears browser error
    } else {
        matchMessage.innerText = "Passwords do not match.";
        matchMessage.style.color = "red";
        confirmPassword.setCustomValidity("Invalid"); // Prevents form submission
    }
}

confirmPassword.addEventListener('input', validateMatch);

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
setupToggle('eye-confirm', 'confirm_password');