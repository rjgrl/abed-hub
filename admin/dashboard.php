<?php 
include '../includes/db.php'; 

// Fetch real-time counts
$fspf = $conn->query("SELECT COUNT(*) as total FROM projects WHERE category='FSPF'")->fetch_assoc();
$idp = $conn->query("SELECT COUNT(*) as total FROM projects WHERE category='IDP'")->fetch_assoc();
$machinery = $conn->query("SELECT COUNT(*) as total FROM machinery")->fetch_assoc();
?>
<!doctype html>
<html lang="en">
<head>
    <title>Dashboard | ABED Hub</title>
    <link rel="stylesheet" href="../bootstrap/css/bootstrap.min.css">
</head>
<body class="admin-body d-flex">
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="main-wrapper flex-grow-1">
        <?php include '../includes/topbar.php'; ?>
        <main class="p-4">
            <h2 class="fw-bold mb-4">System Overview</h2>
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="card p-3 border-start border-primary border-4">
                        <div class="text-muted small fw-bold">FSPF PROJECTS</div>
                        <h3><?php echo $fspf['total']; ?></h3>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card p-3 border-start border-success border-4">
                        <div class="text-muted small fw-bold">IDP PROJECTS</div>
                        <h3><?php echo $idp['total']; ?></h3>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card p-3 border-start border-warning border-4">
                        <div class="text-muted small fw-bold">AFME MACHINES</div>
                        <h3><?php echo $machinery['total']; ?></h3>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>