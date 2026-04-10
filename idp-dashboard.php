<?php
require_once __DIR__ . '/components/layout.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/functions/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

requireLogin();

$page_title = 'IDP Dashboard - ABED IDM Hub';

// IDP-specific statistics
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'user';

// IDP projects count
$idp_count = $conn->query("SELECT COUNT(*) as cnt FROM idp_projects")->fetch_assoc()['cnt'];

// IDP projects by stage
$stages_query = $conn->query("SELECT current_stage, COUNT(*) as count FROM idp_projects GROUP BY current_stage");
$stages = [];
while ($row = $stages_query->fetch_assoc()) {
    $stages[$row['current_stage']] = $row['count'];
}

// IDP financial overview
$financial = $conn->query("
    SELECT
        SUM(proposed_amount) as total_proposed,
        SUM(allocated_amount) as total_allocated,
        SUM(allocated_amount) as total_contract,
        0 as total_disbursed
    FROM idp_projects
")->fetch_assoc();

// IDP progress averages
$progress = $conn->query("
    SELECT
        AVG(physical_progress) as avg_physical,
        AVG(financial_progress) as avg_financial
    FROM idp_projects
")->fetch_assoc();

// Recent IDP projects
$recent_projects = $conn->query("
    SELECT id, project_code, project_title, current_stage, physical_progress, financial_progress,
           allocated_amount, updated_at, 'idp' as type
    FROM idp_projects
    ORDER BY updated_at DESC LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

// Pending approvals for IDP
$pending_count = $conn->query("SELECT COUNT(*) as cnt FROM idp_projects WHERE current_stage IN ('Proposal', 'Pre-Implementation')")->fetch_assoc()['cnt'];

// Top performers
$performers = $conn->query("
    SELECT project_code, physical_progress, financial_progress
    FROM idp_projects
    ORDER BY physical_progress DESC LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

renderAppLayout($page_title, '<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>');
?>
        <div class="container-fluid py-4">
            <!-- Welcome Section -->
            <div class="row mb-4">
                <div class="col">
                    <h1 class="h3 mb-0">
                        <i class="fas fa-home text-success me-2"></i>IDP Dashboard
                    </h1>
                    <p class="text-muted">Internally Displaced Persons - Project Overview</p>
                </div>
                <div class="col-auto">
                    <a href="projects-advanced.php?type=idp" class="btn btn-outline-success">
                        <i class="fas fa-list"></i> View All IDP Projects
                    </a>
                </div>
            </div>

            <!-- Key Metrics -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <p class="text-muted small mb-1">Total IDP Projects</p>
                                    <h2 class="mb-0"><?php echo $idp_count; ?></h2>
                                </div>
                                <div class="rounded-circle p-3" style="background-color: rgba(40, 167, 69, 0.1);">
                                    <i class="fas fa-home fa-lg text-success"></i>
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
                                    <h2 class="mb-0"><?php echo round($progress['avg_physical'] ?? 0, 1); ?>%</h2>
                                </div>
                                <div class="rounded-circle p-3" style="background-color: rgba(23, 162, 184, 0.1);">
                                    <i class="fas fa-chart-pie fa-lg text-info"></i>
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
                                    <p class="text-muted small mb-1">Avg. Financial Progress</p>
                                    <h2 class="mb-0"><?php echo round($progress['avg_financial'] ?? 0, 1); ?>%</h2>
                                </div>
                                <div class="rounded-circle p-3" style="background-color: rgba(255, 193, 7, 0.1);">
                                    <i class="fas fa-coins fa-lg text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="row g-3 mb-4">
                <!-- Projects by Stage -->
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header">
                            <h6 class="mb-0">IDP Projects by Stage</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="stageChart" style="max-height: 300px;"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Financial Overview -->
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header">
                            <h6 class="mb-0">Financial Overview</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="financialChart" style="max-height: 300px;"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Progress Overview -->
            <div class="row g-3 mb-4">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header">
                            <h6 class="mb-0">Progress Overview</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between mb-2">
                                            <span>Physical Progress</span>
                                            <small class="badge bg-success">
                                                <?php echo $conn->query("SELECT COUNT(*) as cnt FROM idp_projects WHERE physical_progress >= 75")->fetch_assoc()['cnt']; ?>
                                            </small>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-success" style="width: <?php echo $progress['avg_physical'] ?? 0; ?>%;"></div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between mb-2">
                                            <span>Financial Progress</span>
                                            <small class="badge bg-success">
                                                <?php echo $conn->query("SELECT COUNT(*) as cnt FROM idp_projects WHERE financial_progress >= 75")->fetch_assoc()['cnt']; ?>
                                            </small>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-success" style="width: <?php echo $progress['avg_financial'] ?? 0; ?>%;"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between mb-2">
                                            <span>Contract vs Allocated</span>
                                            <small class="text-muted">
                                                ₱<?php echo number_format(($financial['total_contract'] ?? 0) - ($financial['total_allocated'] ?? 0), 0); ?> variance
                                            </small>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-warning" style="width: <?php
                                                $contract = $financial['total_contract'] ?? 0;
                                                $allocated = $financial['total_allocated'] ?? 0;
                                                echo $allocated > 0 ? min(100, ($contract / $allocated) * 100) : 0;
                                            ?>%;"></div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between mb-2">
                                            <span>Disbursed vs Contract</span>
                                            <small class="text-muted">
                                                ₱<?php echo number_format(($financial['total_disbursed'] ?? 0) - ($financial['total_contract'] ?? 0), 0); ?> variance
                                            </small>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-info" style="width: <?php
                                                $disbursed = $financial['total_disbursed'] ?? 0;
                                                $contract = $financial['total_contract'] ?? 0;
                                                echo $contract > 0 ? min(100, ($disbursed / $contract) * 100) : 0;
                                            ?>%;"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Projects & Top Performers -->
            <div class="row g-3">
                <!-- Recent IDP Projects -->
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header">
                            <h6 class="mb-0">Recent IDP Projects</h6>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Code</th>
                                            <th>Title</th>
                                            <th>Stage</th>
                                            <th>Physical</th>
                                            <th>Financial</th>
                                            <th>Allocated</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_projects as $project): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars(substr($project['project_code'], 0, 15)); ?></td>
                                                <td class="text-truncate" style="max-width: 200px;" title="<?php echo htmlspecialchars($project['project_title']); ?>">
                                                    <?php echo htmlspecialchars(substr($project['project_title'], 0, 30)); ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-success"><?php echo htmlspecialchars($project['current_stage']); ?></span>
                                                </td>
                                                <td><?php echo round($project['physical_progress'], 0); ?>%</td>
                                                <td><?php echo round($project['financial_progress'], 0); ?>%</td>
                                                <td>₱<?php echo number_format($project['allocated_amount'], 0); ?></td>
                                                <td>
                                                    <a href="project-detail-enhanced.php?type=idp&id=<?php echo $project['id']; ?>" class="btn btn-xs btn-success">View</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Top Performers & Quick Actions -->
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-header">
                            <h6 class="mb-0">Top Performers</h6>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <?php foreach ($performers as $perf): ?>
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

                        <div class="card border-0 shadow-sm">
                        <div class="card-header">
                            <h6 class="mb-0">Quick Actions</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#registerIdpModal">
                                    <i class="fas fa-plus"></i> Register IDP Project
                                </button>
                                <a href="projects-advanced.php?type=idp" class="btn btn-outline-success btn-sm">
                                    <i class="fas fa-list"></i> Browse All Projects
                                </a>
                                <a href="analytics-reports.php?type=idp" class="btn btn-outline-info btn-sm">
                                    <i class="fas fa-chart-bar"></i> View Analytics
                                </a>
                                <a href="scurve-monitoring.php?type=idp" class="btn btn-outline-success btn-sm">
                                    <i class="fas fa-chart-line"></i> S-Curve Monitoring
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Register IDP Modal -->
    <div class="modal fade" id="registerIdpModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Register IDP Project</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="idpForm">
                    <div class="modal-body">
                        <!-- Basic Information -->
                        <h6 class="text-primary mb-3">Basic Information</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Project Code *</label>
                                    <input type="text" class="form-control" name="project_code" required />
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Funding Year *</label>
                                    <input type="number" class="form-control" name="funding_year" value="2026" required />
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Project Title *</label>
                            <input type="text" class="form-control" name="project_title" required />
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Fund Source *</label>
                                    <select class="form-control" name="fund_source" required>
                                        <option value="">Select Fund Source</option>
                                        <option>20% City Development Fund (CDF)</option>
                                        <option>Supplemental Budget</option>
                                        <option>LDRRM - QRF</option>
                                        <option>Other</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Scope of Work</label>
                                    <select class="form-control" name="scope_of_work">
                                        <option value="">Select Scope</option>
                                        <option>Construction</option>
                                        <option>Rehabilitation</option>
                                        <option>Upgrading</option>
                                        <option>Additional Work</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Location Information -->
                        <h6 class="text-primary mb-3 mt-4">Location Information</h6>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Province</label>
                                    <input type="text" class="form-control" name="province" value="Bukidnon" />
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Municipality</label>
                                    <input type="text" class="form-control" name="municipality" value="Malaybalay City" />
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Barangay</label>
                                    <input type="text" class="form-control" name="barangay" />
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Latitude</label>
                                    <input type="number" step="0.000001" class="form-control" name="latitude" />
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Longitude</label>
                                    <input type="number" step="0.000001" class="form-control" name="longitude" />
                                </div>
                            </div>
                        </div>

                        <!-- Project Details -->
                        <h6 class="text-primary mb-3 mt-4">Project Details</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Beneficiary</label>
                                    <input type="text" class="form-control" name="beneficiary" />
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Implementation Days</label>
                                    <input type="number" class="form-control" name="implementation_schedule_days" />
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Quantity</label>
                                    <input type="number" step="0.01" class="form-control" name="quantity" />
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Unit</label>
                                    <input type="text" class="form-control" name="unit" />
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Households Benefited</label>
                                    <input type="number" class="form-control" name="households_benefited" />
                                </div>
                            </div>
                        </div>

                        <!-- Financial Information -->
                        <h6 class="text-primary mb-3 mt-4">Financial Information</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Proposed Amount (₱)</label>
                                    <input type="number" step="0.01" class="form-control" name="proposed_amount" />
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Allocated Amount (₱)</label>
                                    <input type="number" step="0.01" class="form-control" name="allocated_amount" />
                                </div>
                            </div>
                        </div>

                        <!-- Description -->
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Register IDP Project</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="assets/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/dashboard.js"></script>
    <script>
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

        // Financial Chart
        const financialData = {
            labels: ['Proposed', 'Allocated', 'Contract', 'Disbursed'],
            datasets: [{
                label: 'Amount (₱)',
                data: [
                    <?php echo $financial['total_proposed'] ?? 0; ?>,
                    <?php echo $financial['total_allocated'] ?? 0; ?>,
                    <?php echo $financial['total_contract'] ?? 0; ?>,
                    <?php echo $financial['total_disbursed'] ?? 0; ?>
                ],
                backgroundColor: ['#6c757d', '#17a2b8', '#ffc107', '#28a745']
            }]
        };

        new Chart(document.getElementById('financialChart'), {
            type: 'bar',
            data: financialData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₱' + (value / 1000000).toFixed(1) + 'M';
                            }
                        }
                    }
                }
            }
        });
    </script>

<?php
renderAppLayoutFooter();
?>