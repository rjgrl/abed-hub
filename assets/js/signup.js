// ========================================
// Signup Page JavaScript
// ========================================

document.addEventListener("DOMContentLoaded", function () {
  const passwordInput = document.getElementById("password");
  const confirmPasswordInput = document.getElementById("confirmPassword");
  const signupForm = document.getElementById("signupForm");

  if (passwordInput) {
    passwordInput.addEventListener("input", handlePasswordInput);
  }

  if (confirmPasswordInput) {
    confirmPasswordInput.addEventListener("input", checkPasswordMatch);
  }

  if (signupForm) {
    signupForm.addEventListener("submit", handleSignupSubmit);
  }
});

/**
 * Handle password input changes
 */
function handlePasswordInput() {
  const password = this.value;
  const strengthMeter = document.getElementById("strengthMeter");
  const strengthText = document.getElementById("strengthText");

  const requirements = checkPasswordRequirements(password);
  const strength = calculatePasswordStrength(requirements);

  // Update check icons
  updateCheckIcon("check-length", requirements.length);
  updateCheckIcon("check-upper", requirements.uppercase);
  updateCheckIcon("check-number", requirements.number);
  updateCheckIcon("check-special", requirements.special);

  // Update strength meter
  if (strengthMeter) {
    strengthMeter.style.width = strength + "%";
    strengthMeter.className = "password-strength-meter";

    const label = getPasswordStrengthLabel(strength);
    strengthMeter.classList.add(label.class);

    if (strengthText) {
      strengthText.textContent = label.text;
      strengthText.className = `strength-text ${label.textClass}`;
    }
  }

  checkPasswordMatch();
}

/**
 * Check if passwords match
 */
function checkPasswordMatch() {
  const passwordInput = document.getElementById("password");
  const confirmPasswordInput = document.getElementById("confirmPassword");
  const matchText = document.getElementById("matchText");

  if (!passwordInput || !confirmPasswordInput || !matchText) return;

  if (passwordInput.value && confirmPasswordInput.value) {
    if (passwordInput.value === confirmPasswordInput.value) {
      matchText.textContent = "✓ Passwords match";
      matchText.className = "text-success";
    } else {
      matchText.textContent = "✗ Passwords do not match";
      matchText.className = "text-danger";
    }
  } else {
    matchText.textContent = "";
  }
}

/**
 * Handle signup form submission
 */
function handleSignupSubmit(e) {
  e.preventDefault();

  const passwordInput = document.getElementById("password");
  const confirmPasswordInput = document.getElementById("confirmPassword");
  const termsCheck = document.getElementById("termsCheck");

  if (passwordInput.value !== confirmPasswordInput.value) {
    showAlert("Passwords do not match. Please try again.", "danger");
    return;
  }

  if (!termsCheck.checked) {
    showAlert("Please agree to the Terms and Conditions.", "warning");
    return;
  }

  const formData = new FormData(this);
  const submitBtn = this.querySelector('button[type="submit"]');
  const originalText = submitBtn.innerHTML;
  submitBtn.disabled = true;
  submitBtn.innerHTML =
    '<span class="spinner-border spinner-border-sm me-2"></span>Creating Account...';

  fetch("handlers/signup.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.status === "success") {
        showAlert(
          "Account created successfully! Redirecting to login...",
          "success",
        );
        setTimeout(() => {
          window.location.href = "login.php";
        }, 2000);
      } else {
        showAlert(data.message, "danger");
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
      }
    })
    .catch((error) => {
      console.error("Error:", error);
      showAlert("An error occurred. Please try again.", "danger");
      submitBtn.disabled = false;
      submitBtn.innerHTML = originalText;
    });
}
