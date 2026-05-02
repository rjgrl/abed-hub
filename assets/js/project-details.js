document.addEventListener("DOMContentLoaded", function () {
  // Upload Document Form Submission
  document
    .getElementById("uploadDocumentForm")
    .addEventListener("submit", function (e) {
      e.preventDefault();
      const formData = new FormData(this);

      fetch("api/documents.php?action=upload", {
        method: "POST",
        body: formData,
        credentials: "same-origin",
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.status === "success") {
            return AppModal
              .alert(data.message || "Document uploaded successfully!", { title: "Upload", variant: "success" })
              .then(function () {
                location.reload();
              });
          }
          return AppModal.alert("Error: " + (data.message || "Upload failed"), {
            title: "Upload failed",
            variant: "danger",
          });
        })
        .catch((error) => console.error("Error:", error));
    });

  // Add Milestone Form Submission
  document
    .getElementById("addMilestoneForm")
    .addEventListener("submit", function (e) {
      e.preventDefault();
      const formData = new FormData(this);

      fetch("add-milestone.php", {
        method: "POST",
        body: formData,
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.status === "success") {
            return AppModal
              .alert("Milestone added successfully!", { title: "Milestone", variant: "success" })
              .then(function () {
                location.reload();
              });
          }
          return AppModal.alert("Error: " + data.message, { title: "Error", variant: "danger" });
        })
        .catch((error) => console.error("Error:", error));
    });
});
