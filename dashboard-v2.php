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

// Get summary statistics from database
$stats = [
    'total_projects' => 0,
    'fspf_projects' => 0,
    'idp_projects' => 0,
    'afme_projects' => 0,
    'completed_projects' => 0,
    'total_proposed' => 0,
    'total_allocated' => 0
];

// Count projects by type and status
$query = "
    (SELECT 'FSPF' as type, COUNT(*) as count, SUM(proposed_amount) as proposed, SUM(allocated_amount) as allocated FROM fspf_projects)
    UNION
    (SELECT 'IDP' as type, COUNT(*) as count, SUM(proposed_amount) as proposed, SUM(allocated_amount) as allocated FROM idp_projects)
    UNION
    (SELECT 'AFME' as type, COUNT(*) as count, SUM(proposed_amount) as proposed, SUM(allocated_amount) as allocated FROM afme_projects)
";

$result = $conn->query($query);
$project_stats = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $project_stats[$row['type']] = [
            'count' => $row['count'],
            'proposed' => floatval($row['proposed'] ?? 0),
            'allocated' => floatval($row['allocated'] ?? 0)
        ];
    }
}

// Get recently updated projects
$recent_projects = $conn->query("
    SELECT 'FSPF' as type, id, project_code, project_title, municipality, current_stage, updated_at FROM fspf_projects ORDER BY updated_at DESC LIMIT 5
    UNION
    SELECT 'IDP' as type, id, project_code, project_title, municipality, current_stage, updated_at FROM idp_projects ORDER BY updated_at DESC LIMIT 5
    UNION
    SELECT 'AFME' as type, id, project_code, project_title, municipality, current_stage, updated_at FROM afme_projects ORDER BY updated_at DESC LIMIT 5
    ORDER BY updated_at DESC LIMIT 5
");

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
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h2">Dashboard</h1>
                <div class="btn-group" role="group">
                    <input type="radio" class="btn-check" name="year_filter" id="year_current" value="current" checked>
                    <label class="btn btn-outline-primary" for="year_current">Current Year</label>

                    <input type="radio" class="btn-check" name="year_filter" id="year_all" value="all">
                    <label class="btn btn-outline-primary" for="year_all">All Time</label>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="row g-3 mb-4">
                <?php
                $cards = [
                    ['icon' => 'fas fa-folder', 'color' => 'primary', 'title' => 'FSPF Projects', 'value' => $project_stats['FSPF']['count'] ?? 0],
                    ['icon' => 'fas fa-water', 'color' => 'info', 'title' => 'IDP Projects', 'value' => $project_stats['IDP']['count'] ?? 0],
                    ['icon' => 'fas fa-cog', 'color' => 'warning', 'title' => 'AFME Equipment', 'value' => $project_stats['AFME']['count'] ?? 0],
                    ['icon' => 'fas fa-check-circle', 'color' => 'success', 'title' => 'Completed', 'value' => 0],
                ];

                foreach ($cards as $card):
                ?>
                <div class="col-md-6 col-lg-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="text-muted small mb-1"><?php echo $card['title']; ?></h6>
                                    <h2 class="mb-0 text-<?php echo $card['color']; ?>"><?php echo $card['value']; ?></h2>
                                </div>
                                <div class="text-<?php echo $card['color']; ?> opacity-50">
                                    <i class="<?php echo $card['icon']; ?> fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Financial Summary -->
            <div class="row g-3 mb-4">
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light border-bottom">
                            <h6 class="mb-0">Proposed vs Allocated Budget</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="budgetChart" height="300"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light border-bottom">
                            <h6 class="mb-0">Projects by Stage</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="stageChart" height="300"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Projects -->
            <div class="row g-3">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light border-bottom d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">Recently Updated Projects</h6>
                            <a href="projects.php" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Type</th>
                                            <th>Code</th>
                                            <th>Title</th>
                                            <th>Location</th>
                                            <th>Stage</th>
                                            <th>Updated</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        if ($recent_projects) {
                                            while ($project = $recent_projects->fetch_assoc()) {
                                                $stage_color = match($project['current_stage']) {
                                                    'Proposal' => 'secondary',
                                                    'Pre-Implementation' => 'info',
                                                    'Procurement' => 'warning',
                                                    'Implementation' => 'primary',
                                                    'Completed', 'Turned-Over' => 'success',
                                                    default => 'light'
                                                };
                                                ?>
                                                <tr>
                                                    <td><span class="badge bg-secondary"><?php echo $project['type']; ?></span></td>
                                                    <td><code><?php echo $project['project_code']; ?></code></td>
                                                    <td><?php echo substr($project['project_title'], 0, 40); ?></td>
                                                    <td><?php echo $project['municipality']; ?></td>
                                                    <td><span class="badge bg-<?php echo $stage_color; ?>"><?php echo $project['current_stage']; ?></span></td>
                                                    <td><small class="text-muted"><?php echo date('M d, Y', strtotime($project['updated_at'])); ?></small></td>
                                                    <td>
                                                        <a href="project-details.php?type=<?php echo strtolower($project['type']); ?>&id=<?php echo $project['id']; ?>" 
                                                           class="btn btn-xs btn-outline-primary">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                    </td>
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
                </div>
            </div>
        </div>
    </main>

    <script src="assets/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        // Chart Data
        const budgetData = {
            labels: ['FSPF', 'IDP', 'AFME'],
            datasets: [
                {
                    label: 'Proposed',
                    data: [
                        <?php echo $project_stats['FSPF']['proposed'] ?? 0 ?>,
                        <?php echo $project_stats['IDP']['proposed'] ?? 0 ?>,
                        <?php echo $project_stats['AFME']['proposed'] ?? 0 ?>
                    ],
                    backgroundColor: 'rgba(102, 126, 234, 0.3)',
                    borderColor: 'rgba(102, 126, 234, 1)',
                    borderWidth: 2
                },
                {
                    label: 'Allocated',
                    data: [
                        <?php echo $project_stats['FSPF']['allocated'] ?? 0 ?>,
                        <?php echo $project_stats['IDP']['allocated'] ?? 0 ?>,
                        <?php echo $project_stats['AFME']['allocated'] ?? 0 ?>
                    ],
                    backgroundColor: 'rgba(118, 75, 162, 0.3)',
                    borderColor: 'rgba(118, 75, 162, 1)',
                    borderWidth: 2
                }
            ]
        };

        new Chart(document.getElementById('budgetChart'), {
            type: 'bar',
            data: budgetData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: true, position: 'top' }
                },
                scales: {
                    y: { beginAtZero: true, ticks: { callback: v => '₱' + (v/1e6).toFixed(1) + 'M' } }
                }
            }
        });

        // Stage pie chart
        new Chart(document.getElementById('stageChart'), {
            type: 'doughnut',
            data: {
                labels: ['Proposal', 'Pre-Implementation', 'Procurement', 'Implementation', 'Completed'],
                datasets: [{
                    data: [5, 3, 2, 8, 2],
                    backgroundColor: [
                        'rgba(108, 117, 125, 0.5)',
                        'rgba(23, 162, 184, 0.5)',
                        'rgba(255, 193, 7, 0.5)',
                        'rgba(102, 126, 234, 0.5)',
                        'rgba(40, 167, 69, 0.5)'
                    ],
                    borderColor: [
                        'rgba(108, 117, 125, 1)',
                        'rgba(23, 162, 184, 1)',
                        'rgba(255, 193, 7, 1)',
                        'rgba(102, 126, 234, 1)',
                        'rgba(40, 167, 69, 1)'
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: true, position: 'bottom' } }
            }
        });
    </script>
</body>
</html>
