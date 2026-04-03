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
            alert("FSPF Project registered successfully!");
            location.reload();
          } else {
            alert("Error: " + data.message);
          }
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
            alert("IDP Project registered successfully!");
            location.reload();
          } else {
            alert("Error: " + data.message);
          }
        })
        .catch((error) => console.error("Error:", error));
    });
  }

  // AFME Form Submission
  const afmeForm = document.getElementById("afmeForm");
  if (afmeForm) {
    afmeForm.addEventListener("submit", function (e) {
      e.preventDefault();
      const formData = new FormData(this);

      fetch("add-afme-machinery.php", {
        method: "POST",
        body: formData,
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.status === "success") {
            alert("Machinery added successfully!");
            location.reload();
          } else {
            alert("Error: " + data.message);
          }
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
