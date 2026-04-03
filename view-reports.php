<?php
include 'db_connect.php';

// Get the category from the URL (e.g., view_projects.php?category=FSPF)
$category = isset($_GET['category']) ? $_GET['category'] : 'FSPF';

$sql = "SELECT * FROM projects WHERE project_category = '$category'";
$result = $conn->query($sql);
?>

<h2>Viewing <?php echo $category; ?> Projects</h2>

<table class="table table-hover">
    <thead>
        <tr>
            <th>Project Name</th>
            <th>Location</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php while($row = $result->fetch_assoc()): ?>
        <tr>
            <td><?php echo $row['project_name']; ?></td>
            <td><?php echo $row['location']; ?></td>
            <td><span class="badge bg-info"><?php echo $row['current_status']; ?></span></td>
            <td><a href="update_status.php?id=<?php echo $row['project_id']; ?>" class="btn btn-sm btn-primary">Update Status</a></td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>

