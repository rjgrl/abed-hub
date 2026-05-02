document.addEventListener("DOMContentLoaded", function () {
  // FSPF Form Submission
  const fspfForm = document.getElementById("fspfForm");
  if (fspfForm) {
    fspfForm.addEventListener("submit", function (e) {
      e.preventDefault();
      const formData = new FormData(this);

      fetch("register-fspf-project.php", {
        method: "POST",
        body: formData,
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.status === "success") {
            return AppModal
              .alert("FSPF Project registered successfully!", { title: "Registered", variant: "success" })
              .then(function () {
                location.reload();
              });
          }
          return AppModal.alert("Error: " + data.message, { title: "Error", variant: "danger" });
        })
        .catch((error) => console.error("Error:", error));
    });
  }

  // IDP Form Submission
  const idpForm = document.getElementById("idpForm");
  if (idpForm) {
    idpForm.addEventListener("submit", function (e) {
      e.preventDefault();
      const formData = new FormData(this);

      fetch("register-idp-project.php", {
        method: "POST",
        body: formData,
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.status === "success") {
            return AppModal
              .alert("IDP Project registered successfully!", { title: "Registered", variant: "success" })
              .then(function () {
                location.reload();
              });
          }
          return AppModal.alert("Error: " + data.message, { title: "Error", variant: "danger" });
        })
        .catch((error) => console.error("Error:", error));
    });
  }

  // AFME Project Form Submission
  const afmeProjectForm = document.getElementById("afmeProjectForm");
  if (afmeProjectForm) {
    afmeProjectForm.addEventListener("submit", function (e) {
      e.preventDefault();
      const formData = new FormData(this);

      fetch("register-afme-project.php", {
        method: "POST",
        body: formData,
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.status === "success") {
            return AppModal
              .alert("AFME Project registered successfully!", { title: "Registered", variant: "success" })
              .then(function () {
                location.reload();
              });
          }
          return AppModal.alert("Error: " + data.message, { title: "Error", variant: "danger" });
        })
        .catch((error) => console.error("Error:", error));
    });
  }

  // AFME Machinery Form Submission
  const afmeMachineryForm = document.getElementById("afmeMachineryForm");
  if (afmeMachineryForm) {
    afmeMachineryForm.addEventListener("submit", function (e) {
      e.preventDefault();
      const formData = new FormData(this);

      fetch("add-afme-machinery.php", {
        method: "POST",
        body: formData,
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.status === "success") {
            return AppModal
              .alert("Machinery added successfully!", { title: "Saved", variant: "success" })
              .then(function () {
                location.reload();
              });
          }
          return AppModal.alert("Error: " + data.message, { title: "Error", variant: "danger" });
        })
        .catch((error) => console.error("Error:", error));
    });
  }

  // Quick action buttons - use ModalHelper for reliable modal opening
  const quickFspf = document.getElementById("quickRegisterFspf");
  const quickIdp = document.getElementById("quickRegisterIdp");
  const quickAfme = document.getElementById("quickRegisterAfme");

  if (quickFspf) {
    quickFspf.addEventListener("click", function (e) {
      e.preventDefault();
      if (typeof ModalHelper !== "undefined") {
        ModalHelper.show("registerFspfModal");
      }
    });
  }

  if (quickIdp) {
    quickIdp.addEventListener("click", function (e) {
      e.preventDefault();
      if (typeof ModalHelper !== "undefined") {
        ModalHelper.show("registerIdpModal");
      }
    });
  }

  if (quickAfme) {
    quickAfme.addEventListener("click", function (e) {
      e.preventDefault();
      if (typeof ModalHelper !== "undefined") {
        ModalHelper.show("registerAfmeModal");
      }
    });
  }
});
