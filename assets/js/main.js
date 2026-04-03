// ========================================
// ABED IDM Hub - Main JavaScript
// ========================================

/**
 * Show alert message
 * @param {string} message - Alert message
 * @param {string} type - Alert type (success, danger, warning, info)
 * @param {string} containerId - Container element ID
 */
function showAlert(message, type, containerId = "alertContainer") {
  const alertContainer = document.getElementById(containerId);
  if (!alertContainer) return;

  const iconMap = {
    success: "check-circle",
    danger: "exclamation-circle",
    warning: "exclamation-triangle",
    info: "info-circle",
  };

  const icon = iconMap[type] || "info-circle";
  const alertHTML = `
    <div class="alert alert-${type} alert-dismissible fade show" role="alert">
      <i class="fas fa-${icon} me-2"></i>${message}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  `;
  alertContainer.innerHTML = alertHTML;
  alertContainer.scrollIntoView({ behavior: "smooth", block: "start" });
}

/**
 * Toggle password visibility
 * @param {string} fieldId - Password input field ID
 */
function togglePassword(fieldId) {
  const field = document.getElementById(fieldId);
  if (!field) return;
  field.type = field.type === "password" ? "text" : "password";
}

/**
 * Update password requirement check icon
 * @param {string} elementId - Element ID
 * @param {boolean} isValid - Is requirement valid
 */
function updateCheckIcon(elementId, isValid) {
  const element = document.getElementById(elementId);
  if (!element) return;
  element.style.color = isValid ? "#28a745" : "#ccc";
}

/**
 * Check password strength
 * @param {string} password - Password string
 * @returns {object} Requirements object with boolean values
 */
function checkPasswordRequirements(password) {
  return {
    length: password.length >= 8,
    uppercase: /[A-Z]/.test(password),
    number: /\d/.test(password),
    special: /[!@#$%^&*]/.test(password),
  };
}

/**
 * Calculate password strength score
 * @param {object} requirements - Requirements object
 * @returns {number} Strength score 0-100
 */
function calculatePasswordStrength(requirements) {
  let strength = 0;
  if (requirements.length) strength += 25;
  if (requirements.uppercase) strength += 25;
  if (requirements.number) strength += 25;
  if (requirements.special) strength += 25;
  return strength;
}

/**
 * Get password strength label and class
 * @param {number} strength - Strength score
 * @returns {object} Label and class
 */
function getPasswordStrengthLabel(strength) {
  if (strength < 50) {
    return {
      text: "Weak Password",
      class: "strength-weak",
      textClass: "text-danger",
    };
  } else if (strength < 75) {
    return {
      text: "Fair Password",
      class: "strength-fair",
      textClass: "text-warning",
    };
  } else if (strength < 100) {
    return {
      text: "Good Password",
      class: "strength-good",
      textClass: "text-info",
    };
  } else {
    return {
      text: "Strong Password",
      class: "strength-strong",
      textClass: "text-success",
    };
  }
}

/**
 * Format timer display
 * @param {number} seconds - Seconds remaining
 * @returns {string} Formatted time string (MM:SS)
 */
function formatTimer(seconds) {
  const minutes = Math.floor(seconds / 60);
  const secs = seconds % 60;
  return `${minutes}:${secs.toString().padStart(2, "0")}`;
}

/**
 * Validate email format
 * @param {string} email - Email address
 * @returns {boolean} Is valid email
 */
function isValidEmail(email) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

/**
 * Validate password requirements
 * @param {string} password - Password
 * @returns {string|null} Error message or null if valid
 */
function validatePassword(password) {
  if (!password || password.length < 8) {
    return "Password must be at least 8 characters";
  }
  if (!/[A-Z]/.test(password)) {
    return "Password must contain at least one uppercase letter";
  }
  if (!/\d/.test(password)) {
    return "Password must contain at least one number";
  }
  if (!/[!@#$%^&*]/.test(password)) {
    return "Password must contain at least one special character";
  }
  return null;
}

function adjustAppMainMargins() {
  const rootStyles = getComputedStyle(document.documentElement);
  const sidebarWidth =
    parseInt(rootStyles.getPropertyValue("--sidebar-width")) || 250;
  const gutterTop = 16;
  const appMain = document.querySelector(".app-main");

  if (!appMain) return;

  // Measure actual header heights (accounts for padding and dynamic content)
  const topbarEl =
    document.querySelector(".topbar-fixed") ||
    document.querySelector(".topbar");
  const navbarEl =
    document.querySelector(".navbar-fixed") ||
    document.querySelector(".navbar");

  let navbarHeight = 0;
  let topbarHeight = 0;

  if (navbarEl) {
    navbarHeight = Math.ceil(navbarEl.getBoundingClientRect().height);
  } else {
    navbarHeight =
      parseInt(rootStyles.getPropertyValue("--navbar-height")) || 56;
  }

  if (topbarEl) {
    topbarHeight = Math.ceil(topbarEl.getBoundingClientRect().height);
  } else {
    topbarHeight =
      parseInt(rootStyles.getPropertyValue("--topbar-height")) || 70;
  }

  if (window.innerWidth <= 768) {
    appMain.style.marginLeft = "0";
    appMain.style.width = "100%";
    appMain.style.marginTop = `${navbarHeight + topbarHeight + 8}px`;
    appMain.style.minHeight = `calc(100vh - ${navbarHeight + topbarHeight + 8}px)`;
  } else {
    appMain.style.marginLeft = `${sidebarWidth}px`;
    appMain.style.width = `calc(100% - ${sidebarWidth}px)`;
    appMain.style.marginTop = `${navbarHeight + topbarHeight + gutterTop}px`;
    appMain.style.minHeight = `calc(100vh - ${navbarHeight + topbarHeight + gutterTop}px)`;
  }

  appMain.style.position = "relative";
  appMain.style.zIndex = "10";
}

window.addEventListener("load", adjustAppMainMargins);
window.addEventListener("resize", adjustAppMainMargins);

// Export for use in other files
if (typeof module !== "undefined" && module.exports) {
  module.exports = {
    showAlert,
    togglePassword,
    updateCheckIcon,
    checkPasswordRequirements,
    calculatePasswordStrength,
    getPasswordStrengthLabel,
    formatTimer,
    isValidEmail,
    validatePassword,
    adjustAppMainMargins,
  };
}
