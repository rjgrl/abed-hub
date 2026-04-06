<?php
require_once __DIR__ . '/components/layout.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/functions/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Fetch statistics
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'user';

// Total projects by type
$fspf_count = $conn->query("SELECT COUNT(*) as cnt FROM fspf_projects")->fetch_assoc()['cnt'];
$idp_count = $conn->query("SELECT COUNT(*) as cnt FROM idp_projects")->fetch_assoc()['cnt'];
$afme_count = $conn->query("SELECT COUNT(*) as cnt FROM afme_projects")->fetch_assoc()['cnt'];
$total_projects = $fspf_count + $idp_count + $afme_count;

// Projects by stage
$stages_query = $conn->query("
    SELECT current_stage, COUNT(*) as count FROM (
        SELECT current_stage FROM fspf_projects
        UNION ALL
        SELECT current_stage FROM idp_projects
        UNION ALL
        SELECT current_stage FROM afme_projects
    ) AS combined
    GROUP BY current_stage
");
$stages = [];
while ($row = $stages_query->fetch_assoc()) {
    $stages[$row['current_stage']] = $row['count'];
}

// Financial overview
$financial_query = $conn->query("
    SELECT 
        SUM(proposed_amount) as total_proposed,
        SUM(allocated_amount) as total_allocated
    FROM (
        SELECT proposed_amount, allocated_amount FROM fspf_projects
        UNION ALL
        SELECT proposed_amount, allocated_amount FROM idp_projects
        UNION ALL
        SELECT proposed_amount, allocated_amount FROM afme_projects
    ) AS combined
");
$financial = $financial_query->fetch_assoc();

// Recent projects
$recent_query = $conn->query("
    SELECT id, project_code, project_title, current_stage, 'fspf' as type FROM fspf_projects
    UNION ALL
    SELECT id, project_code, project_title, current_stage, 'idp' as type FROM idp_projects
    UNION ALL
    SELECT id, project_code, project_title, current_stage, 'afme' as type FROM afme_projects
    ORDER BY created_date DESC LIMIT 10
");
$recent_projects = $recent_query->fetch_all(MYSQLI_ASSOC);

// Pending approvals (if user is admin)
$pending_count = 0;
$pending_projects = [];
if ($user_role === 'admin' || $user_role === 'coordinator') {
    $pending_query = $conn->query("
        SELECT id, project_code, project_title, 'fspf' as type FROM fspf_projects WHERE approval_status = 'Pending'
        UNION ALL
        SELECT id, project_code, project_title, 'idp' as type FROM idp_projects WHERE approval_status = 'Pending'
        UNION ALL
        SELECT id, project_code, project_title, 'afme' as type FROM afme_projects WHERE approval_status = 'Pending'
        LIMIT 5
    ");
    $pending_projects = $pending_query->fetch_all(MYSQLI_ASSOC);
    $pending_count = count($pending_projects);
}

// Get high/low performers
$performers_query = $conn->query("
    SELECT project_code, project_title, physical_progress, financial_progress, current_stage, 'fspf' as type
    FROM fspf_projects WHERE current_stage IN ('Implementation', 'Completed')
    ORDER BY physical_progress DESC LIMIT 5
");
$performers = $performers_query->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - ABED IDM Hub</title>
    <link rel="stylesheet" href="assets/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
</head>
<body>
    <?php include 'components/sidebar.php'; ?>
    <?php include 'components/topbar.php'; ?>
    <?php include 'components/navbar.php'; ?>

    <main class="app-main">
        <div class="container-fluid py-4">
            <!-- Welcome Section -->
            <div class="row mb-4">
                <div class="col">
                    <h1 class="h3 mb-0">Dashboard</h1>
                    <p class="text-muted">Overview of all projects and activities</p>
                </div>
            </div>

            <!-- Key Metrics -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <p class="text-muted small mb-1">Total Projects</p>
                                    <h2 class="mb-0"><?php echo $total_projects; ?></h2>
                                </div>
                                <div class="rounded-circle p-3" style="background-color: rgba(13, 110, 253, 0.1);">
                                    <i class="fas fa-project-diagram fa-lg text-primary"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <p class="text-muted small mb-1">Total Allocated</p>
                                    <h2 class="mb-0">₱<?php echo number_format($financial['total_allocated'] ?? 0, 0); ?></h2>
                                </div>
                                <div class="rounded-circle p-3" style="background-color: rgba(40, 167, 69, 0.1);">
                                    <i class="fas fa-money-bill fa-lg text-success"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <p class="text-muted small mb-1">Avg. Physical Progress</p>
                                    <h2 class="mb-0"><?php 
                                        $avg_physical = $conn->query("
                                            SELECT AVG(physical_progress) as avg FROM (
                                                SELECT physical_progress FROM fspf_projects
                                                UNION ALL
                                                SELECT physical_progress FROM idp_projects
                                                UNION ALL
                                                SELECT physical_progress FROM afme_projects
                                            ) as combined
                                        ")->fetch_assoc()['avg'];
                                        echo round($avg_physical, 1);
                                    ?>%</h2>
                                </div>
                                <div class="rounded-circle p-3" style="background-color: rgba(23, 162, 184, 0.1);">
                                    <i class="fas fa-chart-pie fa-lg text-info"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($pending_count > 0): ?>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm h-100 border-warning">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <p class="text-muted small mb-1">Pending Approvals</p>
                                        <h2 class="mb-0 text-warning"><?php echo $pending_count; ?></h2>
                                    </div>
                                    <div class="rounded-circle p-3" style="background-color: rgba(255, 193, 7, 0.1);">
                                        <i class="fas fa-clock fa-lg text-warning"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Charts Row -->
            <div class="row g-3 mb-4">
                <!-- Projects by Type -->
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header">
                            <h6 class="mb-0">Projects by Type</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="typeChart" height="100"></canvas>
                        </div>
                        <div class="card-footer bg-light small">
                            <div class="row text-center">
                                <div class="col">
                                    <strong><?php echo $fspf_count; ?></strong><br>FSPF
                                </div>
                                <div class="col">
                                    <strong><?php echo $idp_count; ?></strong><br>IDP
                                </div>
                                <div class="col">
                                    <strong><?php echo $afme_count; ?></strong><br>AFME
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Projects by Stage -->
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header">
                            <h6 class="mb-0">Projects by Stage</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="stageChart" height="100"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Progress Distribution -->
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header">
                            <h6 class="mb-0">Progress Distribution</h6>
                        </div>
                        <div class="card-body">
                            <div class="ps-2">
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <small>0-25%</small>
                                        <small class="badge bg-danger">
                                            <?php echo $conn->query("
                                                SELECT COUNT(*) as cnt FROM (
                                                    SELECT physical_progress FROM fspf_projects WHERE physical_progress < 25
                                                    UNION ALL
                                                    SELECT physical_progress FROM idp_projects WHERE physical_progress < 25
                                                    UNION ALL
                                                    SELECT physical_progress FROM afme_projects WHERE physical_progress < 25
                                                ) as combined
                                            ")->fetch_assoc()['cnt']; ?>
                                        </small>
                                    </div>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar bg-danger" style="width: 25%;"></div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <small>25-50%</small>
                                        <small class="badge bg-warning">
                                            <?php echo $conn->query("
                                                SELECT COUNT(*) as cnt FROM (
                                                    SELECT physical_progress FROM fspf_projects WHERE physical_progress >= 25 AND physical_progress < 50
                                                    UNION ALL
                                                    SELECT physical_progress FROM idp_projects WHERE physical_progress >= 25 AND physical_progress < 50
                                                    UNION ALL
                                                    SELECT physical_progress FROM afme_projects WHERE physical_progress >= 25 AND physical_progress < 50
                                                ) as combined
                                            ")->fetch_assoc()['cnt']; ?>
                                        </small>
                                    </div>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar bg-warning" style="width: 50%;"></div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <small>50-75%</small>
                                        <small class="badge bg-info">
                                            <?php echo $conn->query("
                                                SELECT COUNT(*) as cnt FROM (
                                                    SELECT physical_progress FROM fspf_projects WHERE physical_progress >= 50 AND physical_progress < 75
                                                    UNION ALL
                                                    SELECT physical_progress FROM idp_projects WHERE physical_progress >= 50 AND physical_progress < 75
                                                    UNION ALL
                                                    SELECT physical_progress FROM afme_projects WHERE physical_progress >= 50 AND physical_progress < 75
                                                ) as combined
                                            ")->fetch_assoc()['cnt']; ?>
                                        </small>
                                    </div>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar bg-info" style="width: 75%;"></div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <small>75-100%</small>
                                        <small class="badge bg-success">
                                            <?php echo $conn->query("
                                                SELECT COUNT(*) as cnt FROM (
                                                    SELECT physical_progress FROM fspf_projects WHERE physical_progress >= 75
                                                    UNION ALL
                                                    SELECT physical_progress FROM idp_projects WHERE physical_progress >= 75
                                                    UNION ALL
                                                    SELECT physical_progress FROM afme_projects WHERE physical_progress >= 75
                                                ) as combined
                                            ")->fetch_assoc()['cnt']; ?>
                                        </small>
                                    </div>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar bg-success" style="width: 100%;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Projects & Pending -->
            <div class="row g-3">
                <!-- Recent Projects -->
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header">
                            <h6 class="mb-0">Recent Projects</h6>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Code</th>
                                            <th>Title</th>
                                            <th>Type</th>
                                            <th>Stage</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (array_slice($recent_projects, 0, 8) as $project): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars(substr($project['project_code'], 0, 15)); ?></td>
                                                <td class="text-truncate" style="max-width: 200px;" title="<?php echo htmlspecialchars($project['project_title']); ?>">
                                                    <?php echo htmlspecialchars(substr($project['project_title'], 0, 30)); ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary"><?php echo strtoupper($project['type']); ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge" style="background-color: 
                                                        <?php echo match($project['current_stage']) {
                                                            'Proposal' => '#6c757d',
                                                            'Pre-Implementation' => '#17a2b8',
                                                            'Procurement' => '#ffc107',
                                                            'Implementation' => '#0d6efd',
                                                            'Completed', 'Turned-Over' => '#28a745',
                                                            default => '#e3e3e3'
                                                        }; ?>; color: <?php echo match($project['current_stage']) {
                                                            'Procurement' => 'black',
                                                            default => 'white'
                                                        }; ?>">
                                                        <?php echo htmlspecialchars($project['current_stage']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="project-detail-enhanced.php?type=<?php echo $project['type']; ?>&id=<?php echo $project['id']; ?>" 
                                                       class="btn btn-xs btn-outline-primary">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="card-footer bg-light">
                            <a href="projects.php" class="btn btn-sm btn-outline-primary">View All Projects</a>
                        </div>
                    </div>
                </div>

                <!-- Pending Approvals / Top Performers -->
                <div class="col-lg-4">
                    <?php if ($pending_count > 0): ?>
                        <div class="card border-0 shadow-sm mb-3">
                            <div class="card-header bg-warning-subtle">
                                <h6 class="mb-0 text-warning"><i class="fas fa-exclamation-circle"></i> Pending Approvals</h6>
                            </div>
                            <div class="card-body p-2">
                                <?php foreach ($pending_projects as $proj): ?>
                                    <div class="p-2 border-bottom d-flex justify-content-between align-items-center">
                                        <div>
                                            <small class="fw-bold"><?php echo htmlspecialchars($proj['project_code']); ?></small>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars(substr($proj['project_title'], 0, 25)); ?></small>
                                        </div>
                                        <a href="project-detail-enhanced.php?type=<?php echo $proj['type']; ?>&id=<?php echo $proj['id']; ?>" 
                                           class="btn btn-xs btn-primary">Review</a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="card border-0 shadow-sm">
                        <div class="card-header">
                            <h6 class="mb-0">Top Performers</h6>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <?php foreach (array_slice($performers, 0, 5) as $perf): ?>
                                    <div class="list-group-item p-3">
                                        <div class="d-flex justify-content-between mb-2">
                                            <small class="fw-bold"><?php echo htmlspecialchars($perf['project_code']); ?></small>
                                            <small class="badge bg-success"><?php echo round($perf['physical_progress'], 0); ?>%</small>
                                        </div>
                                        <div class="progress" style="height: 6px;">
                                            <div class="progress-bar bg-success" style="width: <?php echo $perf['physical_progress']; ?>%;"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="assets/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        // Type Chart
        new Chart(document.getElementById('typeChart'), {
            type: 'doughnut',
            data: {
                labels: ['FSPF', 'IDP', 'AFME'],
                datasets: [{
                    data: [<?php echo $fspf_count; ?>, <?php echo $idp_count; ?>, <?php echo $afme_count; ?>],
                    backgroundColor: ['#0d6efd', '#17a2b8', '#28a745']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } }
            }
        });

        // Stage Chart
        const stages = <?php echo json_encode(array_keys($stages)); ?>;
        const stageCounts = <?php echo json_encode(array_values($stages)); ?>;
        
        new Chart(document.getElementById('stageChart'), {
            type: 'bar',
            data: {
                labels: stages,
                datasets: [{
                    label: 'Count',
                    data: stageCounts,
                    backgroundColor: ['#6c757d', '#17a2b8', '#ffc107', '#0d6efd', '#28a745']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                scales: { x: { beginAtZero: true } }
            }
        });
    </script>
</body>
</html>
