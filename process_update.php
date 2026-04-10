<?php
include '../components/db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = $_POST['project_id'];
    $actual_date = $_POST['actual_date'];
    $milestone = $_POST['milestone'];

    $sql = "UPDATE milestones SET actual_date = ? WHERE project_id = ? AND milestone_name = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sis", $actual_date, $id, $milestone);
    
    if ($stmt->execute()) {
        header("Location: dashboard-enhanced.php?msg=success");
    } else {
        echo "Error updating record: " . $conn->error;
    }
}
?>

