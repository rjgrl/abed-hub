<?php
session_start();
require_once 'config/database.php';
requireLogin();

$page_title = 'Analytics';

// Filters
$year = $_GET['year'] ?? date('Y');
$type = $_GET['type'] ?? 'all';
$status = $_GET['status'] ?? 'all';

$conditions = [];
$params = [];
$types = '';

if ($year !== 'all') {
    $conditions[] = 'funding_year = ?';
    $params[] = (int)$year;
    $types .= 'i';
}

if ($status !== 'all') {
    $conditions[] = 'current_stage = ?';
    $params[] = $status;
    $types .= 's';
}

$whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Stats per category
$stats = [];

$curYearClause = $whereClause;
if ($type !== 'all') {
    $curYearClause .= ($curYearClause ? ' AND ' : 'WHERE ') . "current_stage = ?";
    // note: this is optional; only for filtering this formula; but we skip for now.
}

// FSPF
$query = "SELECT COUNT(*) as total, SUM(CASE WHEN current_stage = 'Completed' THEN 1 ELSE 0 END) as completed, SUM(allocated_amount) as total_budget, AVG(physical_progress) as avg_progress FROM fspf_projects $whereClause";
if (!empty($params)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $stats['fspf'] = $stmt->get_result()->fetch_assoc();
} else {
    $result = $conn->query($query);
    $stats['fspf'] = $result->fetch_assoc();
}

// IDP
$query = "SELECT COUNT(*) as total, SUM(CASE WHEN current_stage = 'Completed' THEN 1 ELSE 0 END) as completed, SUM(allocated_amount) as total_budget, AVG(physical_progress) as avg_progress FROM idp_projects $whereClause";
if (!empty($params)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $stats['idp'] = $stmt->get_result()->fetch_assoc();
} else {
    $result = $conn->query($query);
    $stats['idp'] = $result->fetch_assoc();
}

// AFME
$query = "SELECT COUNT(*) as total, SUM(CASE WHEN current_status = 'Turned-Over' THEN 1 ELSE 0 END) as completed, SUM(allocated_amount) as total_budget FROM afme_machinery $whereClause";
if (!empty($params)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $stats['afme'] = $stmt->get_result()->fetch_assoc();
} else {
    $result = $conn->query($query);
    $stats['afme'] = $result->fetch_assoc();
}

// Top project stage breakdown by all matched projects (FSPF+IDP+AFME)
$projects = [];

$fspfQuery = "SELECT 'FSPF' as type, current_stage as stage FROM fspf_projects $whereClause";
$idpQuery = "SELECT 'IDP' as type, current_stage as stage FROM idp_projects $whereClause";
$afmeQuery = "SELECT 'AFME' as type, current_status as stage FROM afme_machinery $whereClause";

$projects = [];

if (!empty($params)) {
    $stmt = $conn->prepare($fspfQuery);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $projects = array_merge($projects, $stmt->get_result()->fetch_all(MYSQLI_ASSOC));

    $stmt = $conn->prepare($idpQuery);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $projects = array_merge($projects, $stmt->get_result()->fetch_all(MYSQLI_ASSOC));

    $stmt = $conn->prepare($afmeQuery);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $projects = array_merge($projects, $stmt->get_result()->fetch_all(MYSQLI_ASSOC));
} else {
    $projects = array_merge(
        $conn->query($fspfQuery)->fetch_all(MYSQLI_ASSOC),
        $conn->query($idpQuery)->fetch_all(MYSQLI_ASSOC),
        $conn->query($afmeQuery)->fetch_all(MYSQLI_ASSOC)
    );
}

$typeCounts = ['FSPF' => 0, 'IDP' => 0, 'AFME' => 0, 'Other' => 0];
$stageCounts = [];
foreach ($projects as $project) {
    $t = $project['type'] ?? 'Other';
    if (!array_key_exists($t, $typeCounts)) $t = 'Other';
    $typeCounts[$t]++;

    $st = $project['stage'] ?? 'Unknown';
    if ($st === '') $st = 'Unknown';
    $stageCounts[$st] = ($stageCounts[$st] ?? 0) + 1;
}

$totalProjects = array_sum($typeCounts);
$totalCompleted = ($stats['fspf']['completed'] ?? 0) + ($stats['idp']['completed'] ?? 0) + ($stats['afme']['completed'] ?? 0);
$completionRate = $totalProjects > 0 ? ($totalCompleted / $totalProjects) * 100 : 0;

?>
<?php
require_once __DIR__ . '/components/layout.php';
renderAppLayout($page_title);
?>
        <div class="container-fluid">
            <div class="page-header mb-4">
            <h3><i class="fas fa-chart-pie me-2"></i><?php echo htmlspecialchars($page_title ?? 'Analytics'); ?></h3>
            <p class="text-muted">Visualize project KPIs and performance indicators.</p>
        </div>

        <form class="row g-3 mb-4" method="GET">
            <div class="col-md-3">
                <label class="form-label">Funding Year</label>
                <select name="year" class="form-control">
                    <option value="all"<?php echo $year === 'all' ? ' selected' : ''; ?>>All Years</option>
                    <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                        <option value="<?php echo $y; ?>"<?php echo $year == $y ? ' selected' : ''; ?>><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Project Type</label>
                <select name="type" class="form-control">
                    <option value="all"<?php echo $type === 'all' ? ' selected' : ''; ?>>All Types</option>
                    <option value="FSPF"<?php echo $type === 'FSPF' ? ' selected' : ''; ?>>FSPF</option>
                    <option value="IDP"<?php echo $type === 'IDP' ? ' selected' : ''; ?>>IDP</option>
                    <option value="AFME"<?php echo $type === 'AFME' ? ' selected' : ''; ?>>AFME</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-control">
                    <option value="all"<?php echo $status === 'all' ? ' selected' : ''; ?>>All Status</option>
                    <option value="Proposal"<?php echo $status === 'Proposal' ? ' selected' : ''; ?>>Proposal</option>
                    <option value="Pre-Implementation"<?php echo $status === 'Pre-Implementation' ? ' selected' : ''; ?>>Pre-Implementation</option>
                    <option value="Procurement"<?php echo $status === 'Procurement' ? ' selected' : ''; ?>>Procurement</option>
                    <option value="Implementation"<?php echo $status === 'Implementation' ? ' selected' : ''; ?>>Implementation</option>
                    <option value="Completed"<?php echo $status === 'Completed' ? ' selected' : ''; ?>>Completed</option>
                </select>
            </div>
            <div class="col-md-3 align-self-end">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i>Apply</button>
            </div>
        </form>

        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-primary text-center">
                    <div class="card-body">
                        <h4 class="text-primary"><?php echo $totalProjects; ?></h4>
                        <p class="mb-0">Total Projects</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-success text-center">
                    <div class="card-body">
                        <h4 class="text-success"><?php echo $totalCompleted; ?></h4>
                        <p class="mb-0">Completed/Turned-over</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-info text-center">
                    <div class="card-body">
                        <h4 class="text-info"><?php echo number_format($completionRate, 1); ?>%</h4>
                        <p class="mb-0">Completion Rate</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-warning text-center">
                    <div class="card-body">
                        <h4 class="text-warning"><?php echo '₱' . number_format(($stats['fspf']['total_budget'] ?? 0) + ($stats['idp']['total_budget'] ?? 0) + ($stats['afme']['total_budget'] ?? 0), 2); ?></h4>
                        <p class="mb-0">Total Budget</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-info text-white">Project Type Share</div>
                    <div class="card-body">
                        <canvas id="typeChart" height="220"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-secondary text-white">Status Breakdown</div>
                    <div class="card-body">
                        <canvas id="stageChart" height="220"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </main>

    <script src="assets/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        const typeLabels = <?php echo json_encode(array_keys($typeCounts)); ?>;
        const typeValues = <?php echo json_encode(array_values($typeCounts)); ?>;
        const stageLabels = <?php echo json_encode(array_keys($stageCounts)); ?>;
        const stageValues = <?php echo json_encode(array_values($stageCounts)); ?>;

        new Chart(document.getElementById('typeChart'), {
            type: 'pie',
            data: {
                labels: typeLabels,
                datasets: [{
                    data: typeValues,
                    backgroundColor: ['#0d6efd', '#198754', '#ffc107', '#6c757d']
                }]
            }
        });

        new Chart(document.getElementById('stageChart'), {
            type: 'bar',
            data: {
                labels: stageLabels,
                datasets: [{
                    label: 'Projects',
                    data: stageValues,
                    backgroundColor: '#0dcaf0'
                }]
            },
            options: {
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    </script>
<?php renderAppLayoutFooter(); ?>

