document.addEventListener("DOMContentLoaded", function () {
  // FSPF Form Submission
  document.getElementById("fspfForm").addEventListener("submit", function (e) {
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

  // IDP Form Submission
  document.getElementById("idpForm").addEventListener("submit", function (e) {
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

  // AFME Form Submission
  document.getElementById("afmeForm").addEventListener("submit", function (e) {
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
});
