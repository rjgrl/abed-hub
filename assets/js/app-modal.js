/**
 * In-app modal dialogs to replace window.alert / confirm (no "localhost says").
 * API: AppModal.alert(message, opts?) -> Promise<void>
 *      AppModal.confirm(message, opts?) -> Promise<boolean>
 */
(function (global) {
  const DEFAULT_TITLE = "ABED IDM Hub";

  let resolveCurrent = null;
  let onKeydown = null;
  let currentDialogIsConfirm = false;

  function closeWith(value) {
    if (onKeydown) {
      document.removeEventListener("keydown", onKeydown);
      onKeydown = null;
    }
    setOpen(false);
    const fn = resolveCurrent;
    resolveCurrent = null;
    if (fn) fn(value);
  }

  function setOpen(open) {
    const root = document.getElementById("app-modal-root");
    if (!root) return;
    root.classList.toggle("app-modal-app--open", open);
    root.setAttribute("aria-hidden", open ? "false" : "true");
    if (open) {
      const panel = root.querySelector(".app-modal__panel");
      if (panel) {
        const focusable = panel.querySelector(
          "button, [href], input, select, textarea, [tabindex]:not([tabindex='-1'])"
        );
        if (focusable) focusable.focus();
      }
    }
  }

  function ensureRoot() {
    let root = document.getElementById("app-modal-root");
    if (root) return root;

    root = document.createElement("div");
    root.id = "app-modal-root";
    root.className = "app-modal-app";
    root.setAttribute("aria-hidden", "true");
    root.innerHTML =
      '<div class="app-modal__backdrop"></div>' +
      '<div class="app-modal__panel" role="dialog" aria-modal="true" aria-labelledby="app-modal-title">' +
      '<div class="app-modal__header">' +
      '<h2 class="app-modal__title" id="app-modal-title"></h2>' +
      '<button type="button" class="app-modal__close" aria-label="Close">&times;</button>' +
      "</div>" +
      '<div class="app-modal__body" id="app-modal-body"></div>' +
      '<div class="app-modal__footer" id="app-modal-footer"></div>' +
      "</div>";

    root.addEventListener("click", function (e) {
      if (!root.classList.contains("app-modal-app--open")) return;
      if (
        e.target.classList.contains("app-modal__backdrop") ||
        e.target.closest(".app-modal__close")
      ) {
        e.preventDefault();
        closeWith(currentDialogIsConfirm ? false : undefined);
      }
    });

    document.body.appendChild(root);
    return root;
  }

  function show(config) {
    return new Promise(function (resolve) {
      if (resolveCurrent) {
        const fn = resolveCurrent;
        resolveCurrent = null;
        if (onKeydown) {
          document.removeEventListener("keydown", onKeydown);
          onKeydown = null;
        }
        fn(currentDialogIsConfirm ? false : undefined);
      }

      const root = ensureRoot();
      const titleEl = root.querySelector("#app-modal-title");
      const bodyEl = root.querySelector("#app-modal-body");
      const footerEl = root.querySelector("#app-modal-footer");

      currentDialogIsConfirm = config.type === "confirm";
      resolveCurrent = resolve;
      titleEl.textContent = config.title || DEFAULT_TITLE;
      bodyEl.textContent = config.message || "";
      bodyEl.classList.remove("app-modal__body--danger", "app-modal__body--success");
      if (config.variant === "danger") bodyEl.classList.add("app-modal__body--danger");
      if (config.variant === "success") bodyEl.classList.add("app-modal__body--success");

      footerEl.innerHTML = "";

      if (config.type === "confirm") {
        const cancelLabel = config.cancelLabel || "Cancel";
        const okLabel = config.confirmLabel || "OK";
        const okBtn = document.createElement("button");
        okBtn.type = "button";
        okBtn.className = config.variant === "danger" ? "btn btn-danger" : "btn btn-primary";
        okBtn.textContent = okLabel;
        okBtn.addEventListener("click", function () {
          closeWith(true);
        });
        const cancelBtn = document.createElement("button");
        cancelBtn.type = "button";
        cancelBtn.className = "btn btn-outline-secondary";
        cancelBtn.textContent = cancelLabel;
        cancelBtn.addEventListener("click", function () {
          closeWith(false);
        });
        footerEl.appendChild(cancelBtn);
        footerEl.appendChild(okBtn);
      } else {
        const okBtn = document.createElement("button");
        okBtn.type = "button";
        okBtn.className = "btn btn-primary";
        okBtn.textContent = config.okLabel || "OK";
        okBtn.addEventListener("click", function () {
          closeWith(undefined);
        });
        footerEl.appendChild(okBtn);
      }

      onKeydown = function (e) {
        if (e.key === "Escape") {
          e.preventDefault();
          closeWith(currentDialogIsConfirm ? false : undefined);
        }
      };
      document.addEventListener("keydown", onKeydown);

      setOpen(true);
    });
  }

  const AppModal = {
    alert: function (message, opts) {
      opts = opts || {};
      return show({
        type: "alert",
        message: String(message),
        title: opts.title,
        variant: opts.variant,
        okLabel: opts.okLabel,
      });
    },
    confirm: function (message, opts) {
      opts = opts || {};
      return show({
        type: "confirm",
        message: String(message),
        title: opts.title,
        variant: opts.variant,
        confirmLabel: opts.confirmLabel,
        cancelLabel: opts.cancelLabel,
      });
    },
  };

  global.AppModal = AppModal;
})(typeof window !== "undefined" ? window : this);

/** Forms: <button type="submit" data-app-confirm="Message?"> — confirm before native submit */
(function () {
  document.addEventListener(
    "click",
    function (e) {
      const btn = e.target.closest("button[data-app-confirm]");
      if (!btn || btn.type !== "submit") return;
      const form = btn.closest("form");
      if (!form) return;
      const msg = btn.getAttribute("data-app-confirm");
      if (!msg) return;
      e.preventDefault();
      e.stopImmediatePropagation();
      if (typeof window.AppModal === "undefined") {
        if (window.confirm(msg)) form.submit();
        return;
      }
      window.AppModal.confirm(msg, { title: "Please confirm" }).then(function (ok) {
        if (ok) form.submit();
      });
    },
    true
  );
})();
