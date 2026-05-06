// ========================================
// Login Page JavaScript
// ========================================

(function ($) {
  $(function () {
    const $btn = $("#toggleLoginPassword");
    const $input = $("#loginPassword");
    if (!$btn.length || !$input.length) return;

    $btn.on("click", function () {
      const show = $input.attr("type") === "password";
      $input.attr("type", show ? "text" : "password");
      $(this).find("i").toggleClass("fa-eye fa-eye-slash");
      $(this).attr("aria-pressed", show ? "true" : "false");
      $(this).attr(
        "aria-label",
        show ? "Hide password" : "Show password"
      );
    });
  });
})(jQuery);

document.addEventListener("DOMContentLoaded", function () {
  const googleBtn = document.getElementById("googleLoginBtn");
  if (googleBtn) {
    googleBtn.addEventListener("click", function (e) {
      e.preventDefault();
      const roleSelect = document.querySelector('select[name="login_role"]');
      const loginRole = roleSelect ? roleSelect.value : "employee";
      const base = googleBtn.getAttribute("data-start-url") || "handlers/google-oauth-start.php";
      const url =
        base +
        "?intent=login&login_role=" +
        encodeURIComponent(loginRole);
      window.location.href = url;
    });
  }

  const loginForm = document.getElementById("loginForm");
  if (!loginForm) return;

  loginForm.addEventListener("submit", function (e) {
    e.preventDefault();

    const username = document.querySelector('input[name="username"]').value;
    const password = document.querySelector('input[name="password"]').value;
    const loginRole = document.querySelector('select[name="login_role"]').value;
    const alertContainer = document.getElementById("alertContainer");

    const recaptchaResponse =
      typeof grecaptcha !== "undefined" ? grecaptcha.getResponse() : "";
    if (!recaptchaResponse) {
      showAlert("Please verify that you are not a robot.", "warning");
      return;
    }

    const formData = new FormData();
    formData.append("username", username);
    formData.append("password", password);
    formData.append("login_role", loginRole);
    formData.append("g-recaptcha-response", recaptchaResponse);

    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML =
      '<span class="spinner-border spinner-border-sm me-2"></span>Logging in...';

    fetch("handlers/login.php", {
      method: "POST",
      body: formData,
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.status === "success") {
          showAlert("Login successful! Redirecting...", "success");
          setTimeout(() => {
            window.location.href =
              data.redirect_url || "dashboard.php";
          }, 1000);
        } else if (data.status === "redirect") {
          window.location.href = data.redirect_url || "dashboard.php";
        } else {
          showAlert(data.message, "danger");
          if (typeof grecaptcha !== "undefined") {
            grecaptcha.reset();
          }
          submitBtn.disabled = false;
          submitBtn.innerHTML = originalText;
        }
      })
      .catch((error) => {
        console.error("Error:", error);
        showAlert("An error occurred. Please try again.", "danger");
        if (typeof grecaptcha !== "undefined") {
          grecaptcha.reset();
        }
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
      });
  });
});
