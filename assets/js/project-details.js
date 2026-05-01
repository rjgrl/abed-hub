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
            alert(data.message || "Document uploaded successfully!");
            location.reload();
          } else {
            alert("Error: " + (data.message || "Upload failed"));
          }
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
            alert("Milestone added successfully!");
            location.reload();
          } else {
            alert("Error: " + data.message);
          }
        })
        .catch((error) => console.error("Error:", error));
    });
});
