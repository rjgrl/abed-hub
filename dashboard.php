<?php
require_once __DIR__ . '/components/layout.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/functions/helpers.php';
require_once __DIR__ . '/services/ProjectRepository.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id   = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'user';
$module = 'all';
if (isset($_GET['module']) && in_array(strtolower((string) $_GET['module']), ['fspf', 'idp', 'afme'], true)) {
    // Legacy module dashboards are deprecated; route everyone to main dashboard.
    header('Location: dashboard.php');
    exit;
}

$moduleLabels = [
    'all' => 'Dashboard',
];
$moduleSubtitle = 'Overview of all projects and activities';

$repo = new ProjectRepository($conn);

$counts         = $repo->counts($module);
$fspf_count     = $counts['fspf'];
$idp_count      = $counts['idp'];
$afme_count     = $counts['afme'];
$total_projects = $counts['total'];

$stages   = $repo->stageDistribution($module);
$financial = $repo->financialSummary($module);
$recent_projects = $repo->recent($module, 10);
$recent_deleted_projects = $repo->recentDeleted($module, 10);

$pending_count    = 0;
$pending_projects = [];
$pending_title = 'Pending Approvals';
if ($user_role === 'admin') {
    $pending_projects = $repo->pendingApprovals($module, 5);
} else {
    $pending_projects = $repo->pendingByUser((int) $user_id, $module, 5);
    $pending_title = 'My Pending Approvals';
}
if (!empty($pending_projects)) {
    $pending_count = count($pending_projects);
}

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
                    <h1 class="h3 mb-0"><?php echo htmlspecialchars($moduleLabels[$module]); ?></h1>
                    <p class="text-muted"><?php echo htmlspecialchars($moduleSubtitle); ?></p>
                </div>
            </div>

            <!-- Key Metrics -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center py-4">
                            <div class="rounded-circle p-3 stat-icon-well stat-icon-well--blue mx-auto mb-2 d-inline-flex">
                                <i class="fas fa-project-diagram fa-lg text-primary"></i>
                            </div>
                            <p class="text-muted small mb-1">Total Projects</p>
                            <h2 class="mb-0"><?php echo $total_projects; ?></h2>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center py-4">
                            <div class="rounded-circle p-3 stat-icon-well stat-icon-well--mint mx-auto mb-2 d-inline-flex">
                                <i class="fas fa-money-bill fa-lg text-success"></i>
                            </div>
                            <p class="text-muted small mb-1">Total Allocated</p>
                            <h2 class="mb-0">₱<?php echo number_format($financial['total_allocated'] ?? 0, 0); ?></h2>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center py-4">
                            <div class="rounded-circle p-3 stat-icon-well stat-icon-well--purple mx-auto mb-2 d-inline-flex">
                                <i class="fas fa-chart-pie fa-lg text-info"></i>
                            </div>
                            <p class="text-muted small mb-1">Avg. Physical Progress</p>
                            <?php $avgProgress = $repo->avgProgress($module); ?>
                            <h2 class="mb-0"><?php echo $avgProgress['avg_physical']; ?>%</h2>
                        </div>
                    </div>
                </div>

                <?php if ($pending_count > 0): ?>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm h-100 border-warning">
                            <div class="card-body text-center py-4">
                                <div class="rounded-circle p-3 stat-icon-well stat-icon-well--amber mx-auto mb-2 d-inline-flex">
                                    <i class="fas fa-clock fa-lg text-warning"></i>
                                </div>
                                <p class="text-muted small mb-1"><?php echo htmlspecialchars($pending_title); ?></p>
                                <h2 class="mb-0 text-warning"><?php echo $pending_count; ?></h2>
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
                                            <?php echo $conn->query("SELECT COUNT(*) AS cnt FROM projects WHERE approval_status = 'Approved' AND physical_progress < 25")->fetch_assoc()['cnt']; ?>
                                        </small>
                                    </div>
                                    <div class="progress progress-xs">
                                        <div class="progress-bar bg-danger progress-demo-p25"></div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <small>25-50%</small>
                                        <small class="badge bg-warning">
                                            <?php echo $conn->query("SELECT COUNT(*) AS cnt FROM projects WHERE approval_status = 'Approved' AND physical_progress >= 25 AND physical_progress < 50")->fetch_assoc()['cnt']; ?>
                                        </small>
                                    </div>
                                    <div class="progress progress-xs">
                                        <div class="progress-bar bg-warning progress-demo-p50"></div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <small>50-75%</small>
                                        <small class="badge bg-info">
                                            <?php echo $conn->query("SELECT COUNT(*) AS cnt FROM projects WHERE approval_status = 'Approved' AND physical_progress >= 50 AND physical_progress < 75")->fetch_assoc()['cnt']; ?>
                                        </small>
                                    </div>
                                    <div class="progress progress-xs">
                                        <div class="progress-bar bg-info progress-demo-p75"></div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <small>75-100%</small>
                                        <small class="badge bg-success">
                                            <?php echo $conn->query("SELECT COUNT(*) AS cnt FROM projects WHERE approval_status = 'Approved' AND physical_progress >= 75")->fetch_assoc()['cnt']; ?>
                                        </small>
                                    </div>
                                    <div class="progress progress-xs">
                                        <div class="progress-bar bg-success progress-demo-p100"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Projects -->
            <div class="row g-3">
                <!-- Recent Projects -->
                <div class="col-lg-12">
                    <div class="card border-0 shadow-sm mb-3">
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
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (array_slice($recent_projects, 0, 8) as $project): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars(substr($project['project_code'], 0, 15)); ?></td>
                                                <td class="text-truncate table-title-cell" title="<?php echo htmlspecialchars($project['project_title']); ?>">
                                                    <?php echo htmlspecialchars(substr($project['project_title'], 0, 30)); ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary"><?php echo strtoupper($project['type']); ?></span>
                                                </td>
                                                <td>
                                                    <?php $projectStage = (string) ($project['current_stage'] ?? ''); ?>
                                                    <span class="badge bg-<?php
                                                        echo match($projectStage) {
                                                            'Proposal' => 'warning',
                                                            'Pre-Implementation' => 'info',
                                                            'Procurement' => 'secondary',
                                                            'Implementation' => 'success',
                                                            'Completed', 'Turned-Over' => 'success',
                                                            default => 'secondary'
                                                        };
                                                    ?>">
                                                        <?php echo htmlspecialchars($projectStage !== '' ? $projectStage : 'N/A'); ?>
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
                            <a href="projects-advanced.php" class="btn btn-sm btn-outline-primary">View All Projects</a>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm">
                        <div class="card-header">
                            <h6 class="mb-0">Recently Deleted and Rejected Projects</h6>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Code</th>
                                            <th>Title</th>
                                            <th>Type</th>
                                            <th>Status</th>
                                            <th>Updated</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($recent_deleted_projects)): ?>
                                            <?php foreach (array_slice($recent_deleted_projects, 0, 8) as $project): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars(substr($project['project_code'], 0, 15)); ?></td>
                                                    <td class="text-truncate table-title-cell" title="<?php echo htmlspecialchars($project['project_title']); ?>">
                                                        <?php echo htmlspecialchars(substr($project['project_title'], 0, 30)); ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-secondary"><?php echo strtoupper($project['type']); ?></span>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $isRejected = ((string) ($project['approval_status'] ?? '') === 'Rejected');
                                                        ?>
                                                        <span class="badge <?php echo $isRejected ? 'bg-danger' : 'bg-warning text-dark'; ?>">
                                                            <?php echo $isRejected ? 'Rejected' : 'Deleted'; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <small class="text-muted">
                                                            <?php echo !empty($project['updated_at']) ? htmlspecialchars(date('M d, Y', strtotime((string) $project['updated_at']))) : 'N/A'; ?>
                                                        </small>
                                                    </td>
                                                    <td>
                                                        <a href="project-detail-enhanced.php?type=<?php echo urlencode((string) $project['type']); ?>&id=<?php echo (int) $project['id']; ?>" class="btn btn-xs btn-outline-primary" title="View">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="6" class="text-center text-muted py-3">No recently deleted or rejected projects.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </main>

    <script src="assets/bootstrap/js/bootstrap.bundle.js"></script>
    <script src="assets/js/app-modal.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
        // Type Chart
        new Chart(document.getElementById('typeChart'), {
            type: 'doughnut',
            data: {
                labels: ['FSPF', 'IDP', 'AFME'],
                datasets: [{
                    data: [<?php echo $fspf_count; ?>, <?php echo $idp_count; ?>, <?php echo $afme_count; ?>],
                    backgroundColor: ['#5b8def', '#c694f9', '#5fd4a8']
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
                    backgroundColor: ['#9ca3af', '#c694f9', '#f5c57a', '#5b8def', '#5fd4a8']
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
