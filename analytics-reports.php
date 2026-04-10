<?php
require_once __DIR__ . '/components/layout.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/functions/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

requireLogin();

$page_title = 'Analytics & Reports - ABED IDM Hub';

$year = intval($_GET['year'] ?? date('Y'));
$month = $_GET['month'] ?? date('m');
$report_type = $_GET['report'] ?? 'summary';

// Get overall statistics
$all_projects = $conn->query("
    SELECT COUNT(*) as count FROM (
        SELECT id FROM fspf_projects
        UNION ALL
        SELECT id FROM idp_projects
        UNION ALL
        SELECT id FROM afme_projects
    ) as combined
")->fetch_assoc()['count'];

$total_proposed = $conn->query("
    SELECT SUM(proposed_amount) as total FROM (
        SELECT proposed_amount FROM fspf_projects
        UNION ALL
        SELECT proposed_amount FROM idp_projects
        UNION ALL
        SELECT proposed_amount FROM afme_projects
    ) as combined
")->fetch_assoc()['total'] ?? 0;

$total_allocated = $conn->query("
    SELECT SUM(allocated_amount) as total FROM (
        SELECT allocated_amount FROM fspf_projects
        UNION ALL
        SELECT allocated_amount FROM idp_projects
        UNION ALL
        SELECT allocated_amount FROM afme_projects
    ) as combined
")->fetch_assoc()['total'] ?? 0;

// Get project distribution
$fspf_count = $conn->query("SELECT COUNT(*) as count FROM fspf_projects")->fetch_assoc()['count'];
$idp_count = $conn->query("SELECT COUNT(*) as count FROM idp_projects")->fetch_assoc()['count'];
$afme_count = $conn->query("SELECT COUNT(*) as count FROM afme_projects")->fetch_assoc()['count'];

// Get stage distribution
$stage_dist = $conn->query("
    SELECT current_stage, COUNT(*) as count FROM (
        SELECT current_stage FROM fspf_projects
        UNION ALL
        SELECT current_stage FROM idp_projects
        UNION ALL
        SELECT current_stage FROM afme_projects
    ) as combined
    GROUP BY current_stage
")->fetch_all(MYSQLI_ASSOC);

// Get monthly data for the selected year
$monthly_query = $conn->query("
    SELECT 
        MONTH(created_at) as month,
        COUNT(*) as count,
        ROUND(AVG(physical_progress), 1) as avg_physical,
        ROUND(AVG(financial_progress), 1) as avg_financial
    FROM (
        SELECT created_at, physical_progress, financial_progress FROM fspf_projects WHERE YEAR(created_at) = $year
        UNION ALL
        SELECT created_at, physical_progress, financial_progress FROM idp_projects WHERE YEAR(created_at) = $year
        UNION ALL
        SELECT created_at, 0 as physical_progress, 0 as financial_progress FROM afme_projects WHERE YEAR(created_at) = $year
    ) as combined
    GROUP BY MONTH(created_at)
    ORDER BY month
");

$monthly_data = [];
while ($row = $monthly_query->fetch_assoc()) {
    $monthly_data[$row['month']] = $row;
}

// Get projects by stage for selected year
$stage_yearly = $conn->query("
    SELECT current_stage, COUNT(*) as count FROM (
        SELECT current_stage FROM fspf_projects WHERE YEAR(created_at) = $year
        UNION ALL
        SELECT current_stage FROM idp_projects WHERE YEAR(created_at) = $year
        UNION ALL
        SELECT current_stage FROM afme_projects WHERE YEAR(created_at) = $year
    ) as combined
    GROUP BY current_stage
")->fetch_all(MYSQLI_ASSOC);

// Get top performers
$top_performers = $conn->query("
    SELECT project_code, project_title, physical_progress, financial_progress, 'fspf' as type
    FROM fspf_projects WHERE current_stage IN ('Implementation', 'Completed')
    UNION ALL
    SELECT project_code, project_title, physical_progress, financial_progress, 'idp' as type
    FROM idp_projects WHERE current_stage IN ('Implementation', 'Completed')
    UNION ALL
    SELECT project_code, project_title, 0 as physical_progress, 0 as financial_progress, 'afme' as type
    FROM afme_projects WHERE current_stage IN ('Implementation', 'Delivered', 'Turned-Over', 'Operation and Maintenance')
    ORDER BY physical_progress DESC
    LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

// Get at-risk projects
$at_risk = $conn->query("
    SELECT project_code, project_title, physical_progress, financial_progress, (physical_progress - financial_progress) as variance, 'fspf' as type
    FROM fspf_projects WHERE (physical_progress - financial_progress) < -15 OR (physical_progress - financial_progress) > 20
    UNION ALL
    SELECT project_code, project_title, physical_progress, financial_progress, (physical_progress - financial_progress) as variance, 'idp' as type
    FROM idp_projects WHERE (physical_progress - financial_progress) < -15 OR (physical_progress - financial_progress) > 20
    UNION ALL
    SELECT project_code, project_title, 0 as physical_progress, 0 as financial_progress, 0 as variance, 'afme' as type
    FROM afme_projects WHERE current_stage IN ('Implementation', 'Delivered', 'Turned-Over')
    ORDER BY ABS(variance) DESC
    LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

renderAppLayout($page_title, '<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>');
?>
        <div class="container-fluid py-4">
            <!-- Header -->
            <div class="row align-items-center mb-4">
                <div class="col">
                    <h1 class="h3 mb-0"><i class="fas fa-chart-bar"></i> Analytics & Reports</h1>
                    <p class="text-muted">Comprehensive project performance analysis</p>
                </div>
                <div class="col-auto">
                    <button class="btn btn-outline-primary" onclick="window.print()">
                        <i class="fas fa-print"></i> Print
                    </button>
                </div>
            </div>

            <!-- Filters -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label">Year</label>
                            <select name="year" class="form-select" onchange="this.form.submit()">
                                <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                                    <option value="<?php echo $y; ?>" <?php echo $year == $y ? 'selected' : ''; ?>>
                                        <?php echo $y; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Report Type</label>
                            <select name="report" class="form-select" onchange="this.form.submit()">
                                <option value="summary" <?php echo $report_type === 'summary' ? 'selected' : ''; ?>>Summary</option>
                                <option value="detailed" <?php echo $report_type === 'detailed' ? 'selected' : ''; ?>>Detailed</option>
                                <option value="performance" <?php echo $report_type === 'performance' ? 'selected' : ''; ?>>Performance</option>
                            </select>
                        </div>
                    </form>
                </div>
            </div>

            <!-- KPI Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <h3 class="text-primary"><?php echo $all_projects; ?></h3>
                            <small class="text-muted">Total Projects</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <h3 class="text-success">₱<?php echo number_format($total_allocated, 0); ?></h3>
                            <small class="text-muted">Total Allocated</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <?php
                            $avg_physical = $conn->query("
                                SELECT AVG(physical_progress) as avg FROM (
                                    SELECT physical_progress FROM fspf_projects
                                    UNION ALL
                                    SELECT physical_progress FROM idp_projects
                                    UNION ALL
                                    SELECT 0 as physical_progress FROM afme_projects
                                ) as combined
                            ")->fetch_assoc()['avg'] ?? 0;
                            ?>
                            <h3 class="text-info"><?php echo round($avg_physical, 1); ?>%</h3>
                            <small class="text-muted">Avg Physical Progress</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <?php
                            $completed = $conn->query("
                                SELECT COUNT(*) as count FROM (
                                    SELECT id FROM fspf_projects WHERE current_stage IN ('Completed', 'Turned-Over')
                                    UNION ALL
                                    SELECT id FROM idp_projects WHERE current_stage IN ('Completed', 'Turned-Over')
                                    UNION ALL
                                    SELECT id FROM afme_projects WHERE current_stage IN ('Completed', 'Turned-Over')
                                ) as combined
                            ")->fetch_assoc()['count'];
                            ?>
                            <h3 class="text-warning"><?php echo $completed; ?></h3>
                            <small class="text-muted">Completed Projects</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="row g-3 mb-4">
                <!-- Distribution by Type -->
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header">
                            <h6 class="mb-0">Distribution by Type</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="typeChart" height="100"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Distribution by Stage -->
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header">
                            <h6 class="mb-0">Distribution by Stage</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="stageChart" height="100"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Monthly Trend -->
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header">
                            <h6 class="mb-0">Monthly Trend <?php echo $year; ?></h6>
                        </div>
                        <div class="card-body">
                            <canvas id="monthlyChart" height="100"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Performance Analysis -->
            <div class="row g-3 mb-4">
                <!-- Top Performers -->
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header">
                            <h6 class="mb-0">Top Performers</h6>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Project</th>
                                            <th>Progress</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (array_slice($top_performers, 0, 8) as $proj): ?>
                                            <tr>
                                                <td>
                                                    <small>
                                                        <strong><?php echo htmlspecialchars($proj['project_code']); ?></strong><br>
                                                        <span class="text-muted"><?php echo htmlspecialchars(substr($proj['project_title'], 0, 30)); ?></span>
                                                    </small>
                                                </td>
                                                <td>
                                                    <div class="progress" style="width: 80px; height: 20px;">
                                                        <div class="progress-bar bg-success" style="width: <?php echo $proj['physical_progress']; ?>%;">
                                                            <small><?php echo round($proj['physical_progress'], 0); ?>%</small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-success">On Track</span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- At-Risk Projects -->
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header">
                            <h6 class="mb-0">Projects with Significant Variance</h6>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Project</th>
                                            <th>Variance</th>
                                            <th>Alert</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (array_slice($at_risk, 0, 8) as $proj): ?>
                                            <tr>
                                                <td>
                                                    <small>
                                                        <strong><?php echo htmlspecialchars($proj['project_code']); ?></strong><br>
                                                        <span class="text-muted"><?php echo htmlspecialchars(substr($proj['project_title'], 0, 30)); ?></span>
                                                    </small>
                                                </td>
                                                <td>
                                                    <span class="badge" style="background-color: <?php echo $proj['variance'] < 0 ? '#dc3545' : '#ffc107'; ?>">
                                                        <?php echo $proj['variance'] > 0 ? '+' : ''; ?><?php echo round($proj['variance'], 1); ?>%
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($proj['variance'] < -15): ?>
                                                        <small class="badge bg-danger">Must Act</small>
                                                    <?php elseif ($proj['variance'] < -5): ?>
                                                        <small class="badge bg-warning text-dark">Monitor</small>
                                                    <?php else: ?>
                                                        <small class="badge bg-info">Watch</small>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detailed Tables -->
            <div class="row g-3">
                <!-- Projects by Stage -->
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header">
                            <h6 class="mb-0">Projects by Stage (<?php echo $year; ?>)</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <tbody>
                                        <?php foreach ($stage_yearly as $stage): ?>
                                            <tr>
                                                <td>
                                                    <span class="badge" style="background-color: 
                                                        <?php echo match($stage['current_stage']) {
                                                            'Proposal' => '#6c757d',
                                                            'Pre-Implementation' => '#17a2b8',
                                                            'Procurement' => '#ffc107',
                                                            'Implementation' => '#0d6efd',
                                                            'Completed', 'Turned-Over' => '#28a745',
                                                            default => '#e3e3e3'
                                                        }; ?>;  color: <?php echo match($stage['current_stage']) {
                                                            'Procurement' => 'black',
                                                            default => 'white'
                                                        }; ?>">
                                                        <?php echo htmlspecialchars($stage['current_stage']); ?>
                                                    </span>
                                                </td>
                                                <td class="text-end">
                                                    <strong><?php echo $stage['count']; ?></strong>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Summary by Type -->
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header">
                            <h6 class="mb-0">Summary by Project Type</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <tbody>
                                        <tr>
                                            <td><span class="badge bg-primary">FSPF</span></td>
                                            <td class="text-end"><strong><?php echo $fspf_count; ?></strong> projects</td>
                                        </tr>
                                        <tr>
                                            <td><span class="badge bg-info">IDP</span></td>
                                            <td class="text-end"><strong><?php echo $idp_count; ?></strong> projects</td>
                                        </tr>
                                        <tr>
                                            <td><span class="badge bg-success">AFME</span></td>
                                            <td class="text-end"><strong><?php echo $afme_count; ?></strong> projects</td>
                                        </tr>
                                        <tr class="table-active">
                                            <td><strong>Total</strong></td>
                                            <td class="text-end"><strong><?php echo $all_projects; ?></strong> projects</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="assets/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        // Type Distribution Chart
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

        // Stage Distribution Chart
        const stages = <?php echo json_encode(array_column($stage_dist, 'current_stage')); ?>;
        const stageCounts = <?php echo json_encode(array_column($stage_dist, 'count')); ?>;
        
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
                scales: { y: { beginAtZero: true } },
                plugins: { legend: { display: false } }
            }
        });

        // Monthly Trend Chart
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const monthlyCount = [];
        const monthlyPhysical = [];
        
        for (let i = 1; i <= 12; i++) {
            const data = <?php echo json_encode($monthly_data); ?>;
            monthlyCount.push(data[i]?.count || 0);
            monthlyPhysical.push(data[i]?.avg_physical || 0);
        }

        new Chart(document.getElementById('monthlyChart'), {
            type: 'line',
            data: {
                labels: months,
                datasets: [{
                    label: 'Projects Created',
                    data: monthlyCount,
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true } }
            }
        });
    </script>

<?php
renderAppLayoutFooter();
?>
