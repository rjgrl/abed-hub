/**
 * ABED IDM Hub - Form Validator
 * Client-side form validation library with real-time feedback
 */

class FormValidator {
  constructor(formElement, options = {}) {
    this.form = formElement;
    this.options = {
      showErrors: true,
      showSuccess: true,
      realTime: true,
      debounceTime: 300,
      ...options,
    };

    this.errors = {};
    this.debounceTimers = {};
    this.rules = {};

    this.init();
  }

  init() {
    // Attach event listeners
    if (this.options.realTime) {
      this.form.addEventListener("input", this.handleInput.bind(this));
      this.form.addEventListener("change", this.handleChange.bind(this));
    }

    this.form.addEventListener("submit", this.handleSubmit.bind(this));
  }

  handleInput(event) {
    const field = event.target;
    if (!field.name) return;

    // Clear existing timer
    if (this.debounceTimers[field.name]) {
      clearTimeout(this.debounceTimers[field.name]);
    }

    // Validate with debounce
    this.debounceTimers[field.name] = setTimeout(() => {
      this.validateField(field);
    }, this.options.debounceTime);
  }

  handleChange(event) {
    const field = event.target;
    if (!field.name) return;
    this.validateField(field);
  }

  handleSubmit(event) {
    if (!this.validateForm()) {
      event.preventDefault();
      this.showFormErrors();
    }
  }

  addRule(fieldName, rules) {
    this.rules[fieldName] = rules;
  }

  addRules(rulesObj) {
    Object.assign(this.rules, rulesObj);
  }

  validateField(fieldElement) {
    const fieldName = fieldElement.name;
    const value = fieldElement.value.trim();
    const rules = this.rules[fieldName] || [];

    this.errors[fieldName] = [];

    for (const rule of rules) {
      const error = this.checkRule(fieldName, value, rule);
      if (error) {
        this.errors[fieldName].push(error);
      }
    }

    if (this.options.showErrors) {
      this.showFieldError(fieldElement);
    }
    if (this.options.showSuccess && !this.errors[fieldName].length) {
      this.showFieldSuccess(fieldElement);
    }

    return this.errors[fieldName].length === 0;
  }

  validateForm() {
    this.errors = {};
    const fields = this.form.querySelectorAll("[name]");

    for (const field of fields) {
      this.validateField(field);
    }

    return Object.values(this.errors).every((errs) => errs.length === 0);
  }

  checkRule(fieldName, value, rule) {
    switch (rule.type) {
      case "required":
        return !value ? rule.message || `${fieldName} is required` : null;

      case "minLength":
        return value.length < rule.value
          ? rule.message ||
              `${fieldName} must be at least ${rule.value} characters`
          : null;

      case "maxLength":
        return value.length > rule.value
          ? rule.message ||
              `${fieldName} must not exceed ${rule.value} characters`
          : null;

      case "email":
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return !emailRegex.test(value)
          ? rule.message || "Invalid email format"
          : null;

      case "phone":
        const phoneRegex = /^[\d\s\-\+\(\)]+$/;
        return !phoneRegex.test(value) || value.replace(/\D/g, "").length < 10
          ? rule.message || "Invalid phone number"
          : null;

      case "number":
        return isNaN(value) || value === ""
          ? rule.message || `${fieldName} must be a number`
          : null;

      case "decimal":
        const decimalRegex = /^\d+(\.\d{1,2})?$/;
        return !decimalRegex.test(value)
          ? rule.message || `${fieldName} must be a valid decimal`
          : null;

      case "numeric":
        return !/^\d+$/.test(value)
          ? rule.message || `${fieldName} must contain only digits`
          : null;

      case "pattern":
        return !rule.value.test(value)
          ? rule.message || `${fieldName} format is invalid`
          : null;

      case "match":
        const otherField = this.form.querySelector(`[name="${rule.value}"]`);
        return otherField && otherField.value !== value
          ? rule.message || `${fieldName} does not match`
          : null;

      case "custom":
        return rule.value(value, this.form) || null;

      case "url":
        try {
          new URL(value);
          return null;
        } catch {
          return rule.message || "Invalid URL";
        }

      case "date":
        return isNaN(Date.parse(value))
          ? rule.message || "Invalid date format"
          : null;

      case "dateRange":
        const dateVal = new Date(value);
        if (rule.min && dateVal < new Date(rule.min)) {
          return rule.message || `Date must be after ${rule.min}`;
        }
        if (rule.max && dateVal > new Date(rule.max)) {
          return rule.message || `Date must be before ${rule.max}`;
        }
        return null;

      default:
        return null;
    }
  }

  showFieldError(fieldElement) {
    this.removeFieldFeedback(fieldElement);

    if (this.errors[fieldElement.name].length > 0) {
      fieldElement.classList.remove("is-valid");
      fieldElement.classList.add("is-invalid");

      const feedback = document.createElement("div");
      feedback.className = "invalid-feedback d-block";
      feedback.textContent = this.errors[fieldElement.name][0];
      fieldElement.parentElement.appendChild(feedback);
    }
  }

  showFieldSuccess(fieldElement) {
    this.removeFieldFeedback(fieldElement);
    fieldElement.classList.remove("is-invalid");
    fieldElement.classList.add("is-valid");
  }

  removeFieldFeedback(fieldElement) {
    const existing = fieldElement.parentElement.querySelector(
      ".invalid-feedback, .valid-feedback",
    );
    if (existing) existing.remove();
  }

  showFormErrors() {
    const errorContainer = document.createElement("div");
    errorContainer.className = "alert alert-danger alert-dismissible fade show";
    errorContainer.role = "alert";

    let errorHTML = "<strong>Please fix the following errors:</strong><ul>";

    for (const [fieldName, fieldErrors] of Object.entries(this.errors)) {
      if (fieldErrors.length > 0) {
        errorHTML += `<li>${fieldErrors[0]}</li>`;
      }
    }

    errorHTML += "</ul>";
    errorHTML +=
      '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';

    errorContainer.innerHTML = errorHTML;

    // Insert at top of form
    this.form.insertBefore(errorContainer, this.form.firstChild);

    // Scroll to error
    errorContainer.scrollIntoView({ behavior: "smooth", block: "center" });
  }

  getErrors() {
    return this.errors;
  }

  isValid() {
    return Object.values(this.errors).every((errs) => errs.length === 0);
  }

  reset() {
    this.form.reset();
    this.errors = {};
    this.form.querySelectorAll("[name]").forEach((field) => {
      this.removeFieldFeedback(field);
      field.classList.remove("is-invalid", "is-valid");
    });
  }

  setFieldsDisabled(disabled) {
    this.form.querySelectorAll("[name]").forEach((field) => {
      field.disabled = disabled;
    });
  }

  getFormData() {
    const formData = new FormData(this.form);
    const data = {};
    for (const [key, value] of formData.entries()) {
      data[key] = value;
    }
    return data;
  }
}

/**
 * Common validation rule sets for reuse
 */
const ValidationRules = {
  projectCode: [
    { type: "required", message: "Project code is required" },
    {
      type: "pattern",
      value: /^[A-Z]{2,}-\d{4}-\d{3}$/,
      message: "Format: TYPE-YYYY-###",
    },
  ],

  projectTitle: [
    { type: "required", message: "Project title is required" },
    {
      type: "minLength",
      value: 10,
      message: "Title must be at least 10 characters",
    },
    {
      type: "maxLength",
      value: 255,
      message: "Title cannot exceed 255 characters",
    },
  ],

  municipality: [{ type: "required", message: "Municipality is required" }],

  budget: [
    { type: "required", message: "Amount is required" },
    { type: "decimal", message: "Must be a valid amount" },
  ],

  email: [
    { type: "required", message: "Email is required" },
    { type: "email", message: "Invalid email address" },
  ],

  phone: [
    { type: "required", message: "Phone number is required" },
    { type: "phone", message: "Invalid phone number" },
  ],

  password: [
    { type: "required", message: "Password is required" },
    {
      type: "minLength",
      value: 8,
      message: "Password must be at least 8 characters",
    },
    {
      type: "pattern",
      value: /^(?=.*[A-Z])(?=.*\d)/,
      message: "Password must contain uppercase and numbers",
    },
  ],

  percentage: [
    { type: "required", message: "Percentage is required" },
    { type: "decimal", message: "Must be a valid percentage" },
    {
      type: "custom",
      value: (val) => (parseFloat(val) > 100 ? "Cannot exceed 100%" : null),
    },
  ],

  url: [{ type: "url", message: "Invalid URL" }],

  date: [
    { type: "required", message: "Date is required" },
    { type: "date", message: "Invalid date format" },
  ],
};

// Export for module systems
if (typeof module !== "undefined" && module.exports) {
  module.exports = { FormValidator, ValidationRules };
}
