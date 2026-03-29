// ========================================
// Reset Password Page JavaScript
// ========================================

document.addEventListener("DOMContentLoaded", function () {
  const urlParams = new URLSearchParams(window.location.search);
  const token = urlParams.get("token");

  if (!token) {
    showAlert("Invalid session. Please start over.", "danger");
    setTimeout(() => {
      window.location.href = "forgot-password.php";
    }, 2000);
    return;
  }

  const newPasswordInput = document.getElementById("newPassword");
  const confirmPasswordInput = document.getElementById("confirmPassword");
  const resetForm = document.getElementById("resetPasswordForm");

  if (newPasswordInput) {
    newPasswordInput.addEventListener("input", handleNewPasswordInput);
  }

  if (confirmPasswordInput) {
    confirmPasswordInput.addEventListener("input", checkConfirmPassword);
  }

  if (resetForm) {
    resetForm.addEventListener("submit", (e) =>
      handleResetPasswordSubmit(e, token),
    );
  }
});

/**
 * Handle new password input changes
 */
function handleNewPasswordInput() {
  const password = this.value;
  const strengthMeter = document.getElementById("strengthMeter");
  const strengthText = document.getElementById("strengthText");

  const requirements = checkPasswordRequirements(password);
  const strength = calculatePasswordStrength(requirements);

  updateCheckIcon("check-length", requirements.length);
  updateCheckIcon("check-upper", requirements.uppercase);
  updateCheckIcon("check-number", requirements.number);
  updateCheckIcon("check-special", requirements.special);

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

  checkConfirmPassword();
}

/**
 * Check confirm password
 */
function checkConfirmPassword() {
  const newPasswordInput = document.getElementById("newPassword");
  const confirmPasswordInput = document.getElementById("confirmPassword");
  const matchText = document.getElementById("matchText");

  if (!newPasswordInput || !confirmPasswordInput || !matchText) return;

  if (newPasswordInput.value && confirmPasswordInput.value) {
    if (newPasswordInput.value === confirmPasswordInput.value) {
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
 * Handle reset password form submission
 */
function handleResetPasswordSubmit(e, token) {
  e.preventDefault();

  const newPasswordInput = document.getElementById("newPassword");
  const confirmPasswordInput = document.getElementById("confirmPassword");

  if (newPasswordInput.value !== confirmPasswordInput.value) {
    showAlert("Passwords do not match. Please try again.", "danger");
    return;
  }

  const error = validatePassword(newPasswordInput.value);
  if (error) {
    showAlert(error, "danger");
    return;
  }

  const formData = new FormData();
  formData.append("token", token);
  formData.append("newPassword", newPasswordInput.value);

  const submitBtn = document.querySelector('button[type="submit"]');
  const originalText = submitBtn.innerHTML;
  submitBtn.disabled = true;
  submitBtn.innerHTML =
    '<span class="spinner-border spinner-border-sm me-2"></span>Resetting Password...';

  fetch("handlers/reset-password.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.status === "success") {
        showAlert(
          "Password reset successfully! Redirecting to login...",
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
