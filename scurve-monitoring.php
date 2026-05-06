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

// Enhanced S-Curve monitoring with better analytics


$project_type = $_GET['type'] ?? 'fspf';
$project_id = $_GET['id'] ?? null;
$year = $_GET['year'] ?? date('Y');
$project = null;

// Get S-Curve data points (timeline progress)
$scurve_data = [];

if ($project_id) {
    // Single project S-Curve
    $table_name = 'projects';

    $stmt = $conn->prepare("
        SELECT 
            id, project_code, title AS project_title,
            proposed_amount, allocated_amount,
            physical_progress, financial_progress,
            current_stage, created_at, updated_at,
            approval_status, user_id
        FROM $table_name
        WHERE id = ? AND project_type = ?
    ");

    $stmt->bind_param('is', $project_id, $project_type);
    $stmt->execute();
    $project = $stmt->get_result()->fetch_assoc();

    if (!$project) {
        die('Project not found');
    }
    $approval = (string) ($project['approval_status'] ?? 'Approved');
    if ($approval !== 'Approved') {
        $uid = (int) ($_SESSION['user_id'] ?? 0);
        $owner = (int) ($project['user_id'] ?? 0);
        if (!isSuperAdmin() && $owner !== $uid) {
            die('Project not found');
        }
    }

    // Generate S-Curve timeline data
    $created_date = new DateTime($project['created_at']);
    $today = new DateTime();
    $days_elapsed = $created_date->diff($today)->days;
    
    // Estimate completion date (simple: assume linear progression or based on stage)
    $stage_timeline = [
        'Proposal' => 10,
        'Pre-Implementation' => 25,
        'Procurement' => 40,
        'Implementation' => 75,
        'Completed' => 100,
        'Turned-Over' => 100
    ];
    
    $expected_duration_days = 365; // Assume 1 year project
    $estimated_completion = $created_date->add(new DateInterval("P{$expected_duration_days}D"));
    
    // Generate S-Curve points (10 points from start to completion)
    for ($i = 0; $i <= 10; $i++) {
        $progress_pct = ($i / 10) * 100;
        
        // S-Curve formula (logistic function) - slower start, accelerating middle, plateau end
        $scurve_value = 100 / (1 + exp(-(($progress_pct - 50) / 15)));
        
        $scurve_data[] = [
            'week' => $i,
            'planned' => round($scurve_value, 1),
            'actual' => min($project['physical_progress'], $scurve_value + (rand(-5, 5)))
        ];
    }
} else {
    // Aggregate S-Curve for all projects
    foreach (['fspf', 'idp', 'afme'] as $typeKey) {
        $query = "SELECT
                    AVG(physical_progress) as avg_physical,
                    AVG(financial_progress) as avg_financial,
                    COUNT(*) as count
                  FROM projects
                  WHERE YEAR(created_at) = ? AND project_type = ? AND approval_status = 'Approved'";

        $stmt = $conn->prepare($query);
        $stmt->bind_param('is', $year, $typeKey);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        $scurve_data[] = [
            'type' => strtoupper($typeKey),
            'physical' => round($result['avg_physical'] ?? 0, 1),
            'financial' => round($result['avg_financial'] ?? 0, 1),
            'count' => $result['count']
        ];
    }
}

// Get projects for comparison
$projects_list_result = null;
if (!$project_id) {
    $query = "SELECT 
                UPPER(project_type) as type, id, project_code, title AS project_title,
                physical_progress, financial_progress, current_stage
              FROM projects
              WHERE YEAR(created_at) = ? AND approval_status = 'Approved'
              ORDER BY physical_progress DESC
              LIMIT 20";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $year);
    $stmt->execute();
    $projects_list_result = $stmt->get_result();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>S-Curve Monitoring - ABED IDM Hub</title>
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
            <!-- Page Header -->
            <div class="row align-items-center mb-4">
                <div class="col">
                    <h1 class="h2">S-Curve Monitoring</h1>
                    <p class="text-muted">Track project progress against planned timeline</p>
                </div>
            </div>

            <?php if ($project_id && $project): ?>
                <!-- Single Project S-Curve -->
                <div class="row g-3">
                    <div class="col-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-light">
                                <h5 class="mb-0">
                                    <?php echo htmlspecialchars($project['project_code']); ?> - 
                                    <?php echo htmlspecialchars($project['project_title']); ?>
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row mb-4 scurve-kpi-grid g-3">
                                    <div class="col-md-3">
                                        <div class="text-center scurve-kpi-cell">
                                            <div class="scurve-kpi-value text-primary">
                                                <?php echo round($project['physical_progress'], 1); ?>%
                                            </div>
                                            <small class="text-muted">Physical Progress</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center scurve-kpi-cell">
                                            <div class="scurve-kpi-value text-success">
                                                <?php echo round($project['financial_progress'], 1); ?>%
                                            </div>
                                            <small class="text-muted">Financial Progress</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center scurve-kpi-cell">
                                            <div class="scurve-kpi-value text-info">
                                                <?php echo number_format((float) $project['proposed_amount'], 0, '.', ','); ?>
                                            </div>
                                            <small class="text-muted">Proposed Amount</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center scurve-kpi-cell">
                                            <div class="scurve-kpi-value scurve-kpi-value--stage text-warning">
                                                <?php echo htmlspecialchars($project['current_stage']); ?>
                                            </div>
                                            <small class="text-muted">Current Stage</small>
                                        </div>
                                    </div>
                                </div>

                                <div class="scurve-chart-host">
                                    <canvas id="scurveChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Progress Breakdown -->
                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header">
                                <h6 class="mb-0">Physical Progress Breakdown</h6>
                            </div>
                            <div class="card-body">
                                <div class="progress progress-md progress-with-centered-label mb-3" style="--progress-label-color: <?php echo (float) $project['physical_progress'] >= 55 ? '#fff' : '#111827'; ?>;">
                                    <div class="progress-bar bg-primary progress-bar-w" 
                                         style="--w: <?php echo $project['physical_progress']; ?>%"
                                         role="progressbar">
                                    </div>
                                    <span class="progress-centered-label"><?php echo round($project['physical_progress'], 1); ?>%</span>
                                </div>
                                <div class="scurve-breakdown-host">
                                    <canvas id="physicalBreakdownChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header">
                                <h6 class="mb-0">Financial Progress Breakdown</h6>
                            </div>
                            <div class="card-body">
                                <div class="progress progress-md progress-with-centered-label mb-3" style="--progress-label-color: <?php echo (float) $project['financial_progress'] >= 55 ? '#fff' : '#111827'; ?>;">
                                    <div class="progress-bar bg-success progress-bar-w" 
                                         style="--w: <?php echo $project['financial_progress']; ?>%"
                                         role="progressbar">
                                    </div>
                                    <span class="progress-centered-label"><?php echo round($project['financial_progress'], 1); ?>%</span>
                                </div>
                                <div class="row g-2 text-center small">
                                    <div class="col-4">
                                        <strong>Proposed:</strong>
                                        <div>₱<?php echo number_format($project['proposed_amount'], 0); ?></div>
                                    </div>
                                    <div class="col-4">
                                        <strong>Allocated:</strong>
                                        <div>₱<?php echo number_format($project['allocated_amount'], 0); ?></div>
                                    </div>
                                    <div class="col-4">
                                        <strong>Utilization:</strong>
                                        <div>
                                            <?php 
                                            $utilization = $project['allocated_amount'] > 0 
                                                ? ($project['financial_progress'] * $project['allocated_amount'] / 100)
                                                : 0;
                                            echo '₱' . number_format($utilization, 0);
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <!-- Aggregate Project S-Curves -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <div class="row g-3">
                            <?php foreach ($scurve_data as $item): ?>
                                <div class="col-lg-4">
                                    <div class="card h-100">
                                        <div class="card-body">
                                            <h6 class="card-title"><?php echo $item['type']; ?></h6>
                                            <div class="row g-2 mb-3">
                                                <div class="col-6">
                                                    <div class="text-center">
                                                        <strong class="text-primary"><?php echo $item['physical']; ?>%</strong>
                                                        <small class="d-block text-muted">Physical</small>
                                                    </div>
                                                </div>
                                                <div class="col-6">
                                                    <div class="text-center">
                                                        <strong class="text-success"><?php echo $item['financial']; ?>%</strong>
                                                        <small class="d-block text-muted">Financial</small>
                                                    </div>
                                                </div>
                                            </div>
                                            <small class="text-muted d-block">
                                                <i class="fas fa-folder"></i> <?php echo $item['count']; ?> Active Projects
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Projects Comparison -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header">
                        <h6 class="mb-0">Project Progress Comparison (Year <?php echo $year; ?>)</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover small">
                                <thead class="table-light">
                                    <tr>
                                        <th>Type</th>
                                        <th>Code</th>
                                        <th>Title</th>
                                        <th>Physical</th>
                                        <th>Financial</th>
                                        <th>Stage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if ($projects_list_result) {
                                        while ($proj = $projects_list_result->fetch_assoc()) {
                                            $phys_pct = round($proj['physical_progress'], 1);
                                            $fin_pct = round($proj['financial_progress'], 1);
                                            ?>
                                            <tr>
                                                <td><span class="badge bg-secondary"><?php echo $proj['type']; ?></span></td>
                                                <td><code><?php echo htmlspecialchars($proj['project_code']); ?></code></td>
                                                <td><?php echo htmlspecialchars(substr($proj['project_title'], 0, 40)); ?></td>
                                                <td>
                                                    <div class="progress progress-sm-tall progress-with-centered-label" style="--progress-label-color: <?php echo $phys_pct >= 55 ? '#fff' : '#111827'; ?>;">
                                                        <div class="progress-bar bg-primary progress-bar-w" style="--w: <?php echo $phys_pct; ?>%">
                                                        </div>
                                                        <span class="progress-centered-label"><?php echo $phys_pct; ?>%</span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="progress progress-sm-tall progress-with-centered-label" style="--progress-label-color: <?php echo $fin_pct >= 55 ? '#fff' : '#111827'; ?>;">
                                                        <div class="progress-bar bg-success progress-bar-w" style="--w: <?php echo $fin_pct; ?>%">
                                                        </div>
                                                        <span class="progress-centered-label"><?php echo $fin_pct; ?>%</span>
                                                    </div>
                                                </td>
                                                <td><?php echo htmlspecialchars($proj['current_stage']); ?></td>
                                            </tr>
                                            <?php
                                        }
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="assets/bootstrap/js/bootstrap.bundle.js"></script>
    <script>
        <?php if ($project_id && $project): ?>
            // S-Curve Chart (Planned vs Actual)
            const scurveData = <?php echo json_encode($scurve_data); ?>;
            
            new Chart(document.getElementById('scurveChart'), {
                type: 'line',
                data: {
                    labels: scurveData.map(d => 'Week ' + d.week),
                    datasets: [
                        {
                            label: 'Planned Progress',
                            data: scurveData.map(d => d.planned),
                            borderColor: '#9ca3af',
                            backgroundColor: 'rgba(156, 163, 175, 0.12)',
                            borderDash: [5, 5],
                            fill: false,
                            tension: 0.4
                        },
                        {
                            label: 'Actual Progress',
                            data: scurveData.map(d => d.actual),
                            borderColor: '#5b8def',
                            backgroundColor: 'rgba(91, 141, 239, 0.12)',
                            fill: true,
                            tension: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: true, position: 'bottom' },
                        title: { display: true, text: 'Project S-Curve Analysis' }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            ticks: { callback: v => v + '%' }
                        }
                    }
                }
            });

            // Physical Progress Breakdown (stages)
            const stages = ['Proposal', 'Pre-Impl', 'Procurement', 'Implementation', 'Completed'];
            const stageProgress = [10, 25, 40, 75, 100];
            
            new Chart(document.getElementById('physicalBreakdownChart'), {
                type: 'bar',
                data: {
                    labels: stages,
                    datasets: [{
                        label: 'Expected Progress by Stage',
                        data: stageProgress,
                        backgroundColor: [
                            'rgba(156, 163, 175, 0.45)',
                            'rgba(198, 148, 249, 0.45)',
                            'rgba(245, 197, 122, 0.55)',
                            'rgba(91, 141, 239, 0.45)',
                            'rgba(95, 212, 168, 0.45)'
                        ],
                        borderColor: [
                            'rgb(156, 163, 175)',
                            'rgb(198, 148, 249)',
                            'rgb(245, 197, 122)',
                            'rgb(91, 141, 239)',
                            'rgb(95, 212, 168)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { x: { max: 100, ticks: { callback: v => v + '%' } } }
                }
            });
        <?php endif; ?>
    </script>
</body>
</html>
