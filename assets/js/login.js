// ========================================
// Login Page JavaScript
// ========================================

document.addEventListener("DOMContentLoaded", function () {
  const loginForm = document.getElementById("loginForm");
  if (!loginForm) return;

  loginForm.addEventListener("submit", function (e) {
    e.preventDefault();

    const username = document.querySelector('input[name="username"]').value;
    const password = document.querySelector('input[name="password"]').value;
    const loginRole = document.querySelector('select[name="login_role"]').value;
    const alertContainer = document.getElementById("alertContainer");

    const formData = new FormData();
    formData.append("username", username);
    formData.append("password", password);
    formData.append("login_role", loginRole);

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
  });
});
