/**
 * ABED IDM Hub - Main JavaScript File
 */

// API Endpoints
const API = {
  LOGIN: "login.php",
  SIGNUP: "signup.php",
  LOGOUT: "logout.php",
  DASHBOARD: "dashboard.php",
  REGISTER_FSPF: "register-fspf-project.php",
  REGISTER_IDP: "register-idp-project.php",
  REGISTER_AFME: "register-afme-project.php",
  ADD_MACHINERY: "add-afme-machinery.php",
  UPDATE_MACHINERY_VALIDATION: "update-afme-machinery-validation.php",
  UPDATE_DELIVERED: "update-afme-machinery-delivered.php",
  TURNOVER: "afme-machinery-turnover.php",
  VIEW_INVENTORY: "view-afme-inventory.php",
};

// Helper: Format currency
function formatCurrency(amount) {
  return new Intl.NumberFormat("en-PH", {
    style: "currency",
    currency: "PHP",
  }).format(amount);
}

// Helper: Format date
function formatDate(dateString) {
  if (!dateString) return "N/A";
  return new Date(dateString).toLocaleDateString("en-PH", {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
}

// Helper: Show alert
function showAlert(type, message) {
  const alertHtml = `
    <div class="alert alert-${type} alert-dismissible fade show" role="alert">
      ${message}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  `;

  const container = document.getElementById("alertContainer") || document.body;
  const alertDiv = document.createElement("div");
  alertDiv.innerHTML = alertHtml;
  container.insertBefore(alertDiv.firstElementChild, container.firstChild);

  // Auto dismiss after 5 seconds
  setTimeout(() => {
    const alert = container.querySelector(".alert");
    if (alert) {
      alert.remove();
    }
  }, 5000);
}

// Helper: Make API call
async function apiCall(endpoint, data = null, method = "GET") {
  try {
    const options = {
      method: method,
      headers: {
        Accept: "application/json",
      },
    };

    if (data) {
      if (data instanceof FormData) {
        options.body = data;
      } else {
        options.headers["Content-Type"] = "application/json";
        options.body = JSON.stringify(data);
      }
    }

    const response = await fetch(endpoint, options);
    const result = await response.json();

    return result;
  } catch (error) {
    console.error("API Error:", error);
    return { status: "error", message: "An error occurred. Please try again." };
  }
}

// Check if user is authenticated
function checkAuth() {
  return fetch(API.DASHBOARD).then((response) => {
    if (response.status === 401) {
      window.location.href = "login.html";
      return false;
    }
    return response.ok;
  });
}

// Common form submission handler
function setupFormHandler(formId, endpoint) {
  const form = document.getElementById(formId);
  if (!form) return;

  form.addEventListener("submit", async function (e) {
    e.preventDefault();
    const formData = new FormData(this);

    try {
      const result = await apiCall(endpoint, formData, "POST");

      if (result.status === "success") {
        showAlert("success", result.message);
        this.reset();
        // Refresh page or redirect as needed
      } else {
        showAlert("danger", result.message);
      }
    } catch (error) {
      console.error("Error:", error);
      showAlert("danger", "An error occurred");
    }
  });
}

// Initialize on document ready
document.addEventListener("DOMContentLoaded", function () {
  // Add any global initializations here
  console.log("ABED IDM Hub initialized");
});
