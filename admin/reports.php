<?php include '../includes/db.php'; ?>
<!doctype html>
<html lang="en">
<head>
    <title>Reports | ABED Hub</title>
    <link rel="stylesheet" href="../bootstrap/css/bootstrap.min.css">
</head>
<body class="admin-body d-flex">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-wrapper flex-grow-1">
        <main class="p-4">
            <h2 class="fw-bold mb-4">Project Reports</h2>
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Project Name</th>
                                <th>Category</th>
                                <th>Status</th>
                                <th>Actual Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $result = $conn->query("SELECT * FROM projects ORDER BY date_created DESC");
                            while($row = $result->fetch_assoc()) {
                                echo "<tr>
                                    <td>{$row['name']}</td>
                                    <td>{$row['category']}</td>
                                    <td><span class='badge bg-primary'>{$row['status']}</span></td>
                                    <td>{$row['actual_date']}</td>
                                </tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>
</html>