// ========================================
// Verify Code Page JavaScript
// ========================================

let timeRemaining = 1800; // 30 minutes
const otpInputs = [];

document.addEventListener("DOMContentLoaded", function () {
  const urlParams = new URLSearchParams(window.location.search);
  const email = urlParams.get("email");

  if (!email) {
    showAlert("Invalid session. Please start over.", "danger");
    setTimeout(() => {
      window.location.href = "forgot-password.php";
    }, 2000);
    return;
  }

  // Initialize OTP inputs
  const otpGroup = document.getElementById("otpGroup");
  if (otpGroup) {
    const inputs = otpGroup.querySelectorAll(".otp-input");
    inputs.forEach((input, index) => {
      otpInputs.push(input);
      input.addEventListener("input", (e) => handleOtpInput(e, index));
      input.addEventListener("keydown", (e) => handleOtpKeydown(e, index));
      input.addEventListener("paste", handleOtpPaste);
    });
  }

  // Start timer
  startTimer();

  // Setup form submission
  const verifyForm = document.getElementById("verifyCodeForm");
  if (verifyForm) {
    verifyForm.addEventListener("submit", (e) =>
      handleVerifyCodeSubmit(e, email),
    );
  }

  // Setup resend link
  const resendLink = document.getElementById("resendLink");
  if (resendLink) {
    resendLink.addEventListener("click", (e) => {
      e.preventDefault();
      resendCode(email);
    });
  }

  // Focus first input
  if (otpInputs.length > 0) {
    otpInputs[0].focus();
  }
});

/**
 * Handle OTP input
 */
function handleOtpInput(e, index) {
  if (e.target.value.length > 0) {
    if (index < otpInputs.length - 1) {
      otpInputs[index + 1].focus();
    }
  }
  e.target.classList.remove("error");
}

/**
 * Handle OTP keydown
 */
function handleOtpKeydown(e, index) {
  if (e.key === "Backspace" && e.target.value.length === 0) {
    if (index > 0) {
      otpInputs[index - 1].focus();
    }
  }
}

/**
 * Handle OTP paste
 */
function handleOtpPaste(e) {
  e.preventDefault();
  const pastedData = e.clipboardData.getData("text").slice(0, 6);
  pastedData.split("").forEach((char, index) => {
    if (index < otpInputs.length) {
      otpInputs[index].value = char;
    }
  });
  if (pastedData.length === 6) {
    otpInputs[5].focus();
  }
}

/**
 * Start countdown timer
 */
function startTimer() {
  const timerElement = document.getElementById("timer");
  const verifyForm = document.getElementById("verifyCodeForm");

  const interval = setInterval(() => {
    timeRemaining--;

    if (timeRemaining <= 0) {
      clearInterval(interval);
      timerElement.textContent = "Expired";
      verifyForm.style.opacity = "0.5";
      verifyForm.style.pointerEvents = "none";
      showAlert("Code expired. Please request a new one.", "danger");
      return;
    }

    timerElement.textContent = formatTimer(timeRemaining);
  }, 1000);
}

/**
 * Handle verify code form submission
 */
function handleVerifyCodeSubmit(e, email) {
  e.preventDefault();

  const code = otpInputs.map((input) => input.value).join("");

  if (code.length !== 6) {
    showAlert("Please enter the complete 6-digit code", "warning");
    otpInputs.forEach((input) => input.classList.add("error"));
    return;
  }

  const formData = new FormData();
  formData.append("email", email);
  formData.append("code", code);

  const submitBtn = document.querySelector('button[type="submit"]');
  const originalText = submitBtn.innerHTML;
  submitBtn.disabled = true;
  submitBtn.innerHTML =
    '<span class="spinner-border spinner-border-sm me-2"></span>Verifying...';

  fetch("handlers/verify-code.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.status === "success") {
        showAlert("Code verified! Redirecting to reset password...", "success");
        setTimeout(() => {
          window.location.href =
            "reset-password.php?token=" + encodeURIComponent(data.token);
        }, 2000);
      } else {
        showAlert(data.message, "danger");
        otpInputs.forEach((input) => {
          input.value = "";
          input.classList.add("error");
        });
        otpInputs[0].focus();
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

/**
 * Resend code
 */
function resendCode(email) {
  const resendLink = document.getElementById("resendLink");
  if (resendLink.classList.contains("disabled")) {
    return;
  }

  const formData = new FormData();
  formData.append("email", email);

  resendLink.style.opacity = "0.5";
  resendLink.classList.add("disabled");

  fetch("handlers/forgot-password.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.status === "success") {
        showAlert("New code sent to your email!", "success");
        timeRemaining = 1800;
        otpInputs.forEach((input) => {
          input.value = "";
          input.classList.remove("error");
        });
        otpInputs[0].focus();
        startTimer();

        let resendTimer = 60;
        const resendInterval = setInterval(() => {
          resendTimer--;
          if (resendTimer <= 0) {
            clearInterval(resendInterval);
            resendLink.style.opacity = "1";
            resendLink.classList.remove("disabled");
            resendLink.textContent = "Resend Code";
          } else {
            resendLink.textContent = `Resend Code (${resendTimer}s)`;
          }
        }, 1000);
      } else {
        showAlert(data.message, "danger");
        resendLink.style.opacity = "1";
        resendLink.classList.remove("disabled");
      }
    })
    .catch((error) => {
      console.error("Error:", error);
      showAlert("Failed to resend code", "danger");
      resendLink.style.opacity = "1";
      resendLink.classList.remove("disabled");
    });
}
