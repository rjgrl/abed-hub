// ========================================
// Forgot Password Page JavaScript
// ========================================

document.addEventListener("DOMContentLoaded", function () {
  const forgotPasswordForm = document.getElementById("forgotPasswordForm");
  if (!forgotPasswordForm) return;

  forgotPasswordForm.addEventListener("submit", handleForgotPasswordSubmit);
});

/**
 * Handle forgot password form submission
 */
function handleForgotPasswordSubmit(e) {
  e.preventDefault();

  const email = document.querySelector('input[name="email"]').value;

  if (!email || !isValidEmail(email)) {
    showAlert("Please provide a valid email address", "danger");
    return;
  }

  const formData = new FormData();
  formData.append("email", email);

  const submitBtn = this.querySelector('button[type="submit"]');
  const originalText = submitBtn.innerHTML;
  submitBtn.disabled = true;
  submitBtn.innerHTML =
    '<span class="spinner-border spinner-border-sm me-2"></span>Sending...';

  fetch("forgot-password.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.status === "success") {
        showAlert(
          "Recovery code sent! Check your email and enter the code on the next page.",
          "success",
        );
        setTimeout(() => {
          window.location.href =
            "verify-code.html?email=" + encodeURIComponent(email);
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
