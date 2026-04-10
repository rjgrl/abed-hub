<?php
require_once __DIR__ . '/components/layout.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/functions/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

requireLogin();

$page_title = 'AFME Dashboard - ABED IDM Hub';

// AFME-specific statistics
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'user';

// AFME projects count
$afme_count = $conn->query("SELECT COUNT(*) as cnt FROM afme_projects")->fetch_assoc()['cnt'];

// AFME projects by stage
$stages_query = $conn->query("SELECT current_stage, COUNT(*) as count FROM afme_projects GROUP BY current_stage");
$stages = [];
while ($row = $stages_query->fetch_assoc()) {
    $stages[$row['current_stage']] = $row['count'];
}

// AFME financial overview
$financial = $conn->query("
    SELECT
        SUM(proposed_amount) as total_proposed,
        SUM(allocated_amount) as total_allocated,
        SUM(allocated_amount) as total_contract,
        0 as total_disbursed
    FROM afme_projects
")->fetch_assoc();

// AFME progress averages
$progress = [
    'avg_physical' => 0,
    'avg_financial' => 0
];

// Recent AFME projects
$recent_projects = $conn->query("
    SELECT id, project_code, project_title, current_stage, 0 as physical_progress, 0 as financial_progress,
           allocated_amount, updated_at, 'afme' as type
    FROM afme_projects
    ORDER BY updated_at DESC LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

// Pending approvals for AFME
$pending_count = $conn->query("SELECT COUNT(*) as cnt FROM afme_projects WHERE current_stage IN ('Proposal', 'Pre-Implementation')")->fetch_assoc()['cnt'];

// Top performers
$performers = $conn->query("
    SELECT project_code, 0 as physical_progress, 0 as financial_progress
    FROM afme_projects
    ORDER BY project_code DESC LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// AFME machinery statistics
$machinery_stats = $conn->query("
    SELECT
        COUNT(*) as total_machinery,
        SUM(CASE WHEN current_status IN ('Delivered', 'Turned-Over') THEN 1 ELSE 0 END) as delivered_machinery,
        SUM(CASE WHEN current_status IN ('Proposal Validated', 'Pre-Implementation', 'Procurement', 'Implementation', 'Delivered', 'Turned-Over') THEN 1 ELSE 0 END) as validated_machinery
    FROM afme_machinery
")->fetch_assoc();

renderAppLayout($page_title, '<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>');
?>
        <div class="container-fluid py-4">
            <!-- Welcome Section -->
            <div class="row mb-4">
                <div class="col">
                    <h1 class="h3 mb-0">
                        <i class="fas fa-cogs text-warning me-2"></i>AFME Dashboard
                    </h1>
                    <p class="text-muted">Agricultural Farm Mechanization Equipment - Project Overview</p>
                </div>
                <div class="col-auto">
                    <a href="projects-advanced.php?type=afme" class="btn btn-outline-warning">
                        <i class="fas fa-list"></i> View All AFME Projects
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
                                    <p class="text-muted small mb-1">Total AFME Projects</p>
                                    <h2 class="mb-0"><?php echo $afme_count; ?></h2>
                                </div>
                                <div class="rounded-circle p-3" style="background-color: rgba(255, 193, 7, 0.1);">
                                    <i class="fas fa-cogs fa-lg text-warning"></i>
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
                                    <p class="text-muted small mb-1">Total Machinery</p>
                                    <h2 class="mb-0"><?php echo $machinery_stats['total_machinery'] ?? 0; ?></h2>
                                </div>
                                <div class="rounded-circle p-3" style="background-color: rgba(255, 193, 7, 0.1);">
                                    <i class="fas fa-tractor fa-lg text-warning"></i>
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

            <!-- Machinery Status Row -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="rounded-circle p-3 mx-auto mb-3" style="background-color: rgba(40, 167, 69, 0.1); width: fit-content;">
                                <i class="fas fa-check-circle fa-2x text-success"></i>
                            </div>
                            <h4 class="mb-1"><?php echo $machinery_stats['delivered_machinery'] ?? 0; ?></h4>
                            <p class="text-muted small mb-0">Delivered Machinery</p>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="rounded-circle p-3 mx-auto mb-3" style="background-color: rgba(23, 162, 184, 0.1); width: fit-content;">
                                <i class="fas fa-clipboard-check fa-2x text-info"></i>
                            </div>
                            <h4 class="mb-1"><?php echo $machinery_stats['validated_machinery'] ?? 0; ?></h4>
                            <p class="text-muted small mb-0">Validated Machinery</p>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="rounded-circle p-3 mx-auto mb-3" style="background-color: rgba(255, 193, 7, 0.1); width: fit-content;">
                                <i class="fas fa-clock fa-2x text-warning"></i>
                            </div>
                            <h4 class="mb-1"><?php echo ($machinery_stats['total_machinery'] ?? 0) - ($machinery_stats['delivered_machinery'] ?? 0); ?></h4>
                            <p class="text-muted small mb-0">Pending Delivery</p>
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
                            <h6 class="mb-0">AFME Projects by Stage</h6>
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
                                            <small class="badge bg-warning">
                                                0
                                            </small>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-warning" style="width: <?php echo $progress['avg_physical'] ?? 0; ?>%;"></div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between mb-2">
                                            <span>Financial Progress</span>
                                            <small class="badge bg-warning">
                                                0
                                            </small>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-warning" style="width: <?php echo $progress['avg_financial'] ?? 0; ?>%;"></div>
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
                <!-- Recent AFME Projects -->
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header">
                            <h6 class="mb-0">Recent AFME Projects</h6>
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
                                                    <span class="badge bg-warning"><?php echo htmlspecialchars($project['current_stage']); ?></span>
                                                </td>
                                                <td><?php echo round($project['physical_progress'], 0); ?>%</td>
                                                <td><?php echo round($project['financial_progress'], 0); ?>%</td>
                                                <td>₱<?php echo number_format($project['allocated_amount'], 0); ?></td>
                                                <td>
                                                    <a href="project-detail-enhanced.php?type=afme&id=<?php echo $project['id']; ?>" class="btn btn-xs btn-warning">View</a>
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
                                            <small class="badge bg-warning"><?php echo round($perf['physical_progress'], 0); ?>%</small>
                                        </div>
                                        <div class="progress" style="height: 6px;">
                                            <div class="progress-bar bg-warning" style="width: <?php echo $perf['physical_progress']; ?>%;"></div>
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
                                <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#registerAfmeModal">
                                    <i class="fas fa-plus"></i> Register AFME Project
                                </button>
                                <button class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#addMachineryModal">
                                    <i class="fas fa-cog"></i> Add Machinery
                                </button>
                                <a href="projects-advanced.php?type=afme" class="btn btn-outline-warning btn-sm">
                                    <i class="fas fa-list"></i> Browse All Projects
                                </a>
                                <a href="analytics-reports.php?type=afme" class="btn btn-outline-info btn-sm">
                                    <i class="fas fa-chart-bar"></i> View Analytics
                                </a>
                                <a href="view-afme-inventory.php" class="btn btn-outline-warning btn-sm">
                                    <i class="fas fa-tractor"></i> Machinery Inventory
                                </a>
                                <a href="scurve-monitoring.php?type=afme" class="btn btn-outline-success btn-sm">
                                    <i class="fas fa-chart-line"></i> S-Curve Monitoring
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Register AFME Modal -->
    <div class="modal fade" id="registerAfmeModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Register AFME Project</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="afmeProjectForm">
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
                                    <label class="form-label">Beneficiary</label>
                                    <input type="text" class="form-control" name="beneficiary" />
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
                        <button type="submit" class="btn btn-warning">Register AFME Project</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Machinery Modal -->
    <div class="modal fade" id="addMachineryModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add AFME Machinery</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="afmeMachineryForm">
                    <div class="modal-body">
                        <!-- Project Selection -->
                        <div class="mb-3">
                            <label class="form-label">Select AFME Project *</label>
                            <select class="form-control" name="afme_project_id" required>
                                <option value="">Select Project</option>
                                <?php
                                $projects = $conn->query("SELECT id, project_code, project_title FROM afme_projects ORDER BY project_code");
                                while ($project = $projects->fetch_assoc()) {
                                    echo "<option value='{$project['id']}'>{$project['project_code']} - {$project['project_title']}</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <!-- Basic Information -->
                        <h6 class="text-primary mb-3">Machinery Information</h6>
                        <div class="mb-3">
                            <label class="form-label">Machine Name *</label>
                            <input type="text" class="form-control" name="machine_name" required />
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Farm Operation *</label>
                                    <select class="form-control" name="farm_operation" required>
                                        <option value="">Select Operation</option>
                                        <option value="Crops">Crops</option>
                                        <option value="Livestock">Livestock</option>
                                        <option value="Fisheries">Fisheries</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Funding Year</label>
                                    <input type="number" class="form-control" name="funding_year" value="2026" />
                                </div>
                            </div>
                        </div>

                        <!-- Beneficiary Information -->
                        <h6 class="text-primary mb-3 mt-4">Beneficiary Information</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Beneficiary Name *</label>
                                    <input type="text" class="form-control" name="beneficiary_name" required />
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Contact Information</label>
                                    <input type="text" class="form-control" name="beneficiary_contact" />
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Recipient Type</label>
                                    <select class="form-control" name="recipient_type">
                                        <option value="Farmers Cooperative">Farmers Cooperative</option>
                                        <option value="Registered Farmers Organization">Registered Farmers Organization</option>
                                        <option value="Agrarian Reform Beneficiary Organization">Agrarian Reform Beneficiary Organization</option>
                                        <option value="Rural-based Organization">Rural-based Organization</option>
                                        <option value="Local Government Unit">Local Government Unit</option>
                                        <option value="Agricultural School">Agricultural School</option>
                                        <option value="University or College">University or College</option>
                                        <option value="Others">Others</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Beneficiary Households</label>
                                    <input type="number" class="form-control" name="beneficiary_households" />
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Farm Location</label>
                            <input type="text" class="form-control" name="farm_location" />
                        </div>

                        <!-- Financial Information -->
                        <h6 class="text-primary mb-3 mt-4">Financial Information</h6>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Fund Source</label>
                                    <select class="form-control" name="fund_source">
                                        <option value="">Select Fund Source</option>
                                        <option>20% City Development Fund (CDF)</option>
                                        <option>Supplemental Budget</option>
                                        <option>LDRRM - QRF</option>
                                        <option>Other</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Proposed Amount (₱)</label>
                                    <input type="number" step="0.01" class="form-control" name="proposed_amount" />
                                </div>
                            </div>
                            <div class="col-md-4">
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
                        <button type="submit" class="btn btn-warning">Add Machinery</button>
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