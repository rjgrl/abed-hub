/**
 * Modal Helper - Ensures modals work reliably across all scenarios
 * Provides fallback when Bootstrap modal system fails
 */

const ModalHelper = {
  /**
   * Show modal using Bootstrap or fallback method
   */
  show: function (modalId) {
    const modalEl = document.getElementById(modalId);
    if (!modalEl) {
      console.warn("Modal element not found:", modalId);
      return false;
    }

    try {
      if (typeof bootstrap !== "undefined" && bootstrap.Modal) {
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
        return true;
      }
    } catch (e) {
      console.warn("Bootstrap modal failed, using fallback:", e);
    }

    // Fallback: Manual modal show
    return this.showManual(modalId);
  },

  /**
   * Show modal manually without Bootstrap
   */
  showManual: function (modalId) {
    const modalEl = document.getElementById(modalId);
    if (!modalEl) return false;

    modalEl.classList.add("show");
    modalEl.style.display = "block";
    modalEl.setAttribute("aria-modal", "true");
    modalEl.removeAttribute("aria-hidden");

    let backdrop = document.querySelector(".modal-backdrop");
    if (!backdrop) {
      backdrop = document.createElement("div");
      backdrop.className = "modal-backdrop fade show";
      document.body.appendChild(backdrop);
    }

    document.body.classList.add("modal-open");

    const closeHandler = () => this.hide(modalId);

    // Close button handlers
    modalEl
      .querySelectorAll("[data-bs-dismiss='modal'], .btn-close")
      .forEach((btn) => {
        btn.addEventListener("click", closeHandler);
      });

    // Backdrop close
    if (backdrop) {
      backdrop.addEventListener("click", closeHandler);
    }

    return true;
  },

  /**
   * Hide modal
   */
  hide: function (modalId) {
    const modalEl = document.getElementById(modalId);
    if (!modalEl) return false;

    modalEl.classList.remove("show");
    modalEl.style.display = "none";
    modalEl.setAttribute("aria-hidden", "true");
    modalEl.removeAttribute("aria-modal");

    const backdrop = document.querySelector(".modal-backdrop");
    if (backdrop) backdrop.remove();

    document.body.classList.remove("modal-open");
    return true;
  },

  /**
   * Toggle modal
   */
  toggle: function (modalId) {
    const modalEl = document.getElementById(modalId);
    if (!modalEl) return false;

    if (modalEl.classList.contains("show")) {
      return this.hide(modalId);
    } else {
      return this.show(modalId);
    }
  },
};
