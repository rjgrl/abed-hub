<?php
session_start();
require_once 'config/database.php';

requireLogin();

$page_title = 'Reports';

// Get filter parameters
$year = $_GET['year'] ?? date('Y');
$type = $_GET['type'] ?? 'all';
$status = $_GET['status'] ?? 'all';

// Build query conditions
$conditions = [];
$params = [];
$types = '';

if ($year !== 'all') {
    $conditions[] = "funding_year = ?";
    $params[] = $year;
    $types .= 'i';
}

if ($status !== 'all') {
    $conditions[] = "current_stage = ?";
    $params[] = $status;
    $types .= 's';
}

$whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Get project statistics
$stats = [];

// FSPF Statistics
$query = "SELECT
    COUNT(*) as total,
    SUM(CASE WHEN current_stage = 'Completed' THEN 1 ELSE 0 END) as completed,
    SUM(allocated_amount) as total_budget,
    AVG(physical_progress) as avg_progress
    FROM fspf_projects $whereClause";

if (!empty($params)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $stats['fspf'] = $stmt->get_result()->fetch_assoc();
} else {
    $result = $conn->query($query);
    $stats['fspf'] = $result->fetch_assoc();
}

// IDP Statistics
$query = "SELECT
    COUNT(*) as total,
    SUM(CASE WHEN current_stage = 'Completed' THEN 1 ELSE 0 END) as completed,
    SUM(allocated_amount) as total_budget,
    AVG(physical_progress) as avg_progress
    FROM idp_projects $whereClause";

if (!empty($params)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $stats['idp'] = $stmt->get_result()->fetch_assoc();
} else {
    $result = $conn->query($query);
    $stats['idp'] = $result->fetch_assoc();
}

// AFME Statistics
$query = "SELECT
    COUNT(*) as total,
    SUM(CASE WHEN current_status = 'Turned-Over' THEN 1 ELSE 0 END) as completed,
    SUM(allocated_amount) as total_budget
    FROM afme_machinery $whereClause";

if (!empty($params)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $stats['afme'] = $stmt->get_result()->fetch_assoc();
} else {
    $result = $conn->query($query);
    $stats['afme'] = $result->fetch_assoc();
}

// Get projects list for export
$projects = [];

// FSPF Projects
$query = "SELECT 'FSPF' as type, project_code, project_title, fund_source, funding_year,
          current_stage, allocated_amount, municipality, province, physical_progress
          FROM fspf_projects $whereClause ORDER BY project_code";

if (!empty($params)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $fspf_projects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $result = $conn->query($query);
    $fspf_projects = $result->fetch_all(MYSQLI_ASSOC);
}

// IDP Projects
$query = "SELECT 'IDP' as type, project_code, project_title, fund_source, funding_year,
          current_stage, allocated_amount, municipality, province, physical_progress
          FROM idp_projects $whereClause ORDER BY project_code";

if (!empty($params)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $idp_projects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $result = $conn->query($query);
    $idp_projects = $result->fetch_all(MYSQLI_ASSOC);
}

// AFME Machinery
$query = "SELECT 'AFME' as type, machine_name as project_title, fund_source, funding_year,
          current_status as current_stage, allocated_amount, farm_location as municipality,
          beneficiary_name as province
          FROM afme_machinery $whereClause ORDER BY machine_name";

if (!empty($params)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $afme_projects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $result = $conn->query($query);
    $afme_projects = $result->fetch_all(MYSQLI_ASSOC);
}

$projects = array_merge($fspf_projects, $idp_projects, $afme_projects);

// Calculate totals
$total_projects = ($stats['fspf']['total'] ?? 0) + ($stats['idp']['total'] ?? 0) + ($stats['afme']['total'] ?? 0);
$total_completed = ($stats['fspf']['completed'] ?? 0) + ($stats['idp']['completed'] ?? 0) + ($stats['afme']['completed'] ?? 0);
$total_budget = ($stats['fspf']['total_budget'] ?? 0) + ($stats['idp']['total_budget'] ?? 0) + ($stats['afme']['total_budget'] ?? 0);
$completion_rate = $total_projects > 0 ? ($total_completed / $total_projects) * 100 : 0;

// Build analytics data for charts
$typeCounts = ['FSPF' => 0, 'IDP' => 0, 'AFME' => 0, 'Other' => 0];
$stageCounts = [];

foreach ($projects as $project) {
    $t = $project['type'] ?? 'Other';
    if (!isset($typeCounts[$t])) $t = 'Other';
    $typeCounts[$t]++;

    $stage = $project['current_stage'] ?? $project['current_status'] ?? 'Unknown';
    if ($stage === '') $stage = 'Unknown';
    if (!isset($stageCounts[$stage])) $stageCounts[$stage] = 0;
    $stageCounts[$stage]++;
}
?>
<?php
require_once __DIR__ . '/components/layout.php';
renderAppLayout($page_title);
?>
        <div class="container-fluid py-4">
        <!-- Reports Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="mb-0"><i class="fas fa-chart-line me-2"></i><?php echo htmlspecialchars($page_title ?? 'Reports'); ?></h4>
                                <small>Generate comprehensive reports and view project analytics</small>
                            </div>
                            <div>
                                <button class="btn btn-light" onclick="window.print()">
                                    <i class="fas fa-print me-1"></i>Print Report
                                </button>
                                <button class="btn btn-light ms-2" onclick="exportToExcel()">
                                    <i class="fas fa-download me-1"></i>Export Excel
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Filters -->
                        <form method="GET" class="row g-3 mb-4">
                            <div class="col-md-3">
                                <label class="form-label">Funding Year</label>
                                <select class="form-control" name="year">
                                    <option value="all">All Years</option>
                                    <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                                    <option value="<?php echo $y; ?>" <?php echo $year == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Project Type</label>
                                <select class="form-control" name="type">
                                    <option value="all">All Types</option>
                                    <option value="FSPF" <?php echo $type == 'FSPF' ? 'selected' : ''; ?>>FSPF Projects</option>
                                    <option value="IDP" <?php echo $type == 'IDP' ? 'selected' : ''; ?>>IDP Projects</option>
                                    <option value="AFME" <?php echo $type == 'AFME' ? 'selected' : ''; ?>>AFME Machinery</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Status</label>
                                <select class="form-control" name="status">
                                    <option value="all">All Status</option>
                                    <option value="Proposal" <?php echo $status == 'Proposal' ? 'selected' : ''; ?>>Proposal</option>
                                    <option value="Pre-Implementation" <?php echo $status == 'Pre-Implementation' ? 'selected' : ''; ?>>Pre-Implementation</option>
                                    <option value="Procurement" <?php echo $status == 'Procurement' ? 'selected' : ''; ?>>Procurement</option>
                                    <option value="Implementation" <?php echo $status == 'Implementation' ? 'selected' : ''; ?>>Implementation</option>
                                    <option value="Completed" <?php echo $status == 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-filter me-1"></i>Apply Filters
                                </button>
                            </div>
                        </form>

                        <!-- Summary Statistics -->
                        <div class="row text-center mb-4">
                            <div class="col-md-3">
                                <div class="card border-primary">
                                    <div class="card-body">
                                        <h3 class="text-primary"><?php echo $total_projects; ?></h3>
                                        <p class="mb-0">Total Projects</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border-success">
                                    <div class="card-body">
                                        <h3 class="text-success"><?php echo $total_completed; ?></h3>
                                        <p class="mb-0">Completed</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border-info">
                                    <div class="card-body">
                                        <h3 class="text-info"><?php echo number_format($completion_rate, 1); ?>%</h3>
                                        <p class="mb-0">Completion Rate</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border-warning">
                                    <div class="card-body">
                                        <h3 class="text-warning">₱<?php echo number_format($total_budget, 2); ?></h3>
                                        <p class="mb-0">Total Budget</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Project Type Breakdown -->
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-header bg-primary text-white">
                                        <h6 class="mb-0">FSPF Projects</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row text-center">
                                            <div class="col-6">
                                                <h4 class="text-primary"><?php echo $stats['fspf']['total'] ?? 0; ?></h4>
                                                <small>Total</small>
                                            </div>
                                            <div class="col-6">
                                                <h4 class="text-success"><?php echo $stats['fspf']['completed'] ?? 0; ?></h4>
                                                <small>Completed</small>
                                            </div>
                                        </div>
                                        <hr>
                                        <p class="mb-1"><strong>Budget:</strong> ₱<?php echo number_format($stats['fspf']['total_budget'] ?? 0, 2); ?></p>
                                        <p class="mb-0"><strong>Avg Progress:</strong> <?php echo number_format($stats['fspf']['avg_progress'] ?? 0, 1); ?>%</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-header bg-success text-white">
                                        <h6 class="mb-0">IDP Projects</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row text-center">
                                            <div class="col-6">
                                                <h4 class="text-success"><?php echo $stats['idp']['total'] ?? 0; ?></h4>
                                                <small>Total</small>
                                            </div>
                                            <div class="col-6">
                                                <h4 class="text-success"><?php echo $stats['idp']['completed'] ?? 0; ?></h4>
                                                <small>Completed</small>
                                            </div>
                                        </div>
                                        <hr>
                                        <p class="mb-1"><strong>Budget:</strong> ₱<?php echo number_format($stats['idp']['total_budget'] ?? 0, 2); ?></p>
                                        <p class="mb-0"><strong>Avg Progress:</strong> <?php echo number_format($stats['idp']['avg_progress'] ?? 0, 1); ?>%</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-header bg-warning text-white">
                                        <h6 class="mb-0">AFME Machinery</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row text-center">
                                            <div class="col-6">
                                                <h4 class="text-warning"><?php echo $stats['afme']['total'] ?? 0; ?></h4>
                                                <small>Total</small>
                                            </div>
                                            <div class="col-6">
                                                <h4 class="text-warning"><?php echo $stats['afme']['completed'] ?? 0; ?></h4>
                                                <small>Turned-Over</small>
                                            </div>
                                        </div>
                                        <hr>
                                        <p class="mb-1"><strong>Budget:</strong> ₱<?php echo number_format($stats['afme']['total_budget'] ?? 0, 2); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Projects Table -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Project Details Report</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="reportsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Type</th>
                                        <th>Project Code</th>
                                        <th>Project Title</th>
                                        <th>Fund Source</th>
                                        <th>Year</th>
                                        <th>Status</th>
                                        <th>Budget</th>
                                        <th>Location</th>
                                        <th>Progress</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($projects as $project): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo match($project['type']) {
                                                    'FSPF' => 'primary',
                                                    'IDP' => 'success',
                                                    'AFME' => 'warning',
                                                    default => 'secondary'
                                                };
                                            ?>">
                                                <?php echo $project['type']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($project['project_code'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($project['project_title']); ?></td>
                                        <td><?php echo htmlspecialchars($project['fund_source']); ?></td>
                                        <td><?php echo $project['funding_year']; ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo match($project['current_stage']) {
                                                    'Proposal' => 'warning',
                                                    'Pre-Implementation' => 'info',
                                                    'Procurement' => 'secondary',
                                                    'Implementation' => 'success',
                                                    'Completed' => 'success',
                                                    'Turned-Over' => 'dark',
                                                    default => 'secondary'
                                                };
                                            ?>">
                                                <?php echo htmlspecialchars($project['current_stage']); ?>
                                            </span>
                                        </td>
                                        <td>₱<?php echo number_format($project['allocated_amount'] ?? 0, 2); ?></td>
                                        <td><?php echo htmlspecialchars(($project['municipality'] ?? '') . ', ' . ($project['province'] ?? '')); ?></td>
                                        <td>
                                            <?php if (isset($project['physical_progress'])): ?>
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar" role="progressbar" 
                                                         style="width: <?php echo $project['physical_progress']; ?>%"
                                                         aria-valuenow="<?php echo $project['physical_progress']; ?>" 
                                                         aria-valuemin="0" aria-valuemax="100">
                                                        <?php echo $project['physical_progress']; ?>%
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                N/A
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($projects)): ?>
                                    <tr>
                                        <td colspan="9" class="text-center text-muted">No projects found matching the criteria</td>
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

    <script src="assets/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        function exportToExcel() {
            // Create a simple CSV export
            const table = document.getElementById('reportsTable');
            let csv = [];

            // Get headers
            const headers = [];
            for (let i = 0; i < table.rows[0].cells.length; i++) {
                headers.push(table.rows[0].cells[i].textContent.trim());
            }
            csv.push(headers.join(','));

            // Get data rows
            for (let i = 1; i < table.rows.length; i++) {
                const row = [];
                for (let j = 0; j < table.rows[i].cells.length; j++) {
                    let cellText = table.rows[i].cells[j].textContent.trim();
                    // Remove badges and clean up text
                    cellText = cellText.replace(/[\r\n]+/g, ' ').replace(/\s+/g, ' ');
                    row.push('"' + cellText + '"');
                }
                csv.push(row.join(','));
            }

            // Download CSV
            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', 'abed_projects_report_<?php echo date('Y-m-d'); ?>.csv');
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>
<?php renderAppLayoutFooter(); ?>

