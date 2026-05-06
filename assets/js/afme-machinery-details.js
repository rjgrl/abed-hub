document.addEventListener("DOMContentLoaded", function () {
  // AFME profile/specs form submission (Super Admin)
  const updateAfmeProfileForm = document.getElementById("updateAfmeProfileForm");
  if (updateAfmeProfileForm) {
    updateAfmeProfileForm.addEventListener("submit", function (e) {
      e.preventDefault();
      const formData = new FormData(this);

      fetch("update-afme-machinery-profile.php", {
        method: "POST",
        body: formData,
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.status === "success") {
            return AppModal
              .alert(data.message || "AFME profile updated successfully!", { title: "Saved", variant: "success" })
              .then(function () {
                location.reload();
              });
          }
          return AppModal.alert("Error: " + (data.message || "Unable to update AFME fields"), { title: "Error", variant: "danger" });
        })
        .catch((error) => {
          console.error("Error:", error);
          return AppModal.alert("An unexpected error occurred while saving AFME fields.", { title: "Error", variant: "danger" });
        });
    });
  }

  // Upload Machinery Document Form Submission
  const uploadMachineryDocumentForm = document.getElementById("uploadMachineryDocumentForm");
  if (uploadMachineryDocumentForm) {
    uploadMachineryDocumentForm.addEventListener("submit", function (e) {
      e.preventDefault();
      const formData = new FormData(this);

      fetch("upload-machinery-document.php", {
        method: "POST",
        body: formData,
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.status === "success") {
            return AppModal
              .alert("Document uploaded successfully!", { title: "Upload", variant: "success" })
              .then(function () {
                location.reload();
              });
          }
          return AppModal.alert("Error: " + data.message, { title: "Error", variant: "danger" });
        })
        .catch((error) => console.error("Error:", error));
    });
  }

  // Add Machinery Milestone Form Submission
  const addMachineryMilestoneForm = document.getElementById("addMachineryMilestoneForm");
  if (addMachineryMilestoneForm) {
    addMachineryMilestoneForm.addEventListener("submit", function (e) {
      e.preventDefault();
      const formData = new FormData(this);

      fetch("add-machinery-milestone.php", {
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
  }
});
