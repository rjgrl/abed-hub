document.addEventListener("DOMContentLoaded", function () {
  // Upload Machinery Document Form Submission
  document
    .getElementById("uploadMachineryDocumentForm")
    .addEventListener("submit", function (e) {
      e.preventDefault();
      const formData = new FormData(this);

      fetch("upload-machinery-document.php", {
        method: "POST",
        body: formData,
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.status === "success") {
            alert("Document uploaded successfully!");
            location.reload();
          } else {
            alert("Error: " + data.message);
          }
        })
        .catch((error) => console.error("Error:", error));
    });

  // Add Machinery Milestone Form Submission
  document
    .getElementById("addMachineryMilestoneForm")
    .addEventListener("submit", function (e) {
      e.preventDefault();
      const formData = new FormData(this);

      fetch("add-machinery-milestone.php", {
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
