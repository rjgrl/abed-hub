<?php 
include '../includes/db_connect.php'; 
$type = isset($_GET['type']) ? $_GET['type'] : 'FSPF';
$stage = isset($_GET['stage']) ? $_GET['stage'] : 'Implementation';

$sql = "SELECT * FROM projects WHERE category = ? AND stage = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $type, $stage);
$stmt->execute();
$result = $stmt->get_result();
?>
<table class="table">
    <thead>
        <tr>
            <th>Project Name</th>
            <th>Location</th>
            <th>Budget</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php while($row = $result->fetch_assoc()): ?>
        <tr>
            <td><?php echo $row['project_name']; ?></td>
            <td><?php echo $row['location']; ?></td>
            <td>₱<?php echo number_format($row['budget'], 2); ?></td>
            <td><a href="update_project.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info">Update Status</a></td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>