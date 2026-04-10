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

$project_type = $_GET['type'] ?? 'fspf';
$project_id = $_GET['id'] ?? null;

if (!$project_id) {
    die('Project ID required');
}

// Map type to table
$type_map = [
    'fspf' => 'fspf_projects',
    'idp' => 'idp_projects',
    'afme' => 'afme_projects'
];

$table = $type_map[$project_type] ?? 'fspf_projects';

// Fetch project details
$stmt = $conn->prepare("SELECT * FROM $table WHERE id = ?");
$stmt->bind_param('i', $project_id);
$stmt->execute();
$project = $stmt->get_result()->fetch_assoc();

if (!$project) {
    die('Project not found');
}

// Fetch related documents
$docs_stmt = $conn->prepare("SELECT * FROM project_documents WHERE project_type = ? AND project_id = ? ORDER BY upload_date DESC");
$docs_stmt->bind_param('si', strtoupper($project_type), $project_id);
$docs_stmt->execute();
$documents = $docs_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch financial records
$fin_column = match($project_type) {
    'fspf' => 'fspf_project_id',
    'idp' => 'idp_project_id',
    'afme' => 'afme_project_id'
};

$fin_stmt = $conn->prepare("SELECT * FROM project_financial_tracker WHERE $fin_column = ? ORDER BY record_date DESC LIMIT 10");
$fin_stmt->bind_param('i', $project_id);
$fin_stmt->execute();
$financial_records = $fin_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch machinery if AFME project
$machinery = [];
if ($project_type === 'afme') {
    $mach_stmt = $conn->prepare("SELECT * FROM afme_machinery WHERE afme_project_id = ? ORDER BY created_date DESC");
    $mach_stmt->bind_param('i', $project_id);
    $mach_stmt->execute();
    $machinery = $mach_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get comments/audit log
$audit_stmt = $conn->prepare("SELECT * FROM audit_log WHERE project_type = ? AND project_id = ? ORDER BY created_at DESC LIMIT 20");
$audit_stmt->bind_param('si', strtoupper($project_type), $project_id);
$audit_stmt->execute();
$audit_log = $audit_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Details - ABED IDM Hub</title>
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
            <!-- Project Header -->
            <div class="row align-items-center mb-4">
                <div class="col">
                    <div class="d-flex align-items-center gap-3">
                        <div>
                            <span class="badge bg-primary" style="font-size: 0.9rem;">
                                <?php echo strtoupper($project_type); ?>
                            </span>
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
                                }; ?>; font-size: 0.9rem;">
                                <?php echo htmlspecialchars($project['current_stage']); ?>
                            </span>
                        </div>
                        <div>
                            <h1 class="h3 mb-1"><?php echo htmlspecialchars($project['project_code']); ?></h1>
                            <p class="text-muted mb-0"><?php echo htmlspecialchars($project['project_title']); ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-auto">
                    <div class="btn-group">
                        <button type="button" class="btn btn-outline-primary btn-sm" id="editBtn">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <a href="scurve-monitoring.php?type=<?php echo $project_type; ?>&id=<?php echo $project_id; ?>" class="btn btn-outline-info btn-sm">
                            <i class="fas fa-chart-line"></i> S-Curve
                        </a>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#shareModal">
                            <i class="fas fa-share"></i> Share
                        </button>
                    </div>
                </div>
            </div>

            <!-- Nav Tabs -->
            <ul class="nav nav-tabs mb-4" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview" type="button" role="tab">
                        <i class="fas fa-info-circle"></i> Overview
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="financial-tab" data-bs-toggle="tab" data-bs-target="#financial" type="button" role="tab">
                        <i class="fas fa-money-bill"></i> Financial
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="documents-tab" data-bs-toggle="tab" data-bs-target="#documents" type="button" role="tab">
                        <i class="fas fa-file"></i> Documents
                    </button>
                </li>
                <?php if ($project_type === 'afme'): ?>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="machinery-tab" data-bs-toggle="tab" data-bs-target="#machinery" type="button" role="tab">
                            <i class="fas fa-cog"></i> Machinery
                        </button>
                    </li>
                <?php endif; ?>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="activity-tab" data-bs-toggle="tab" data-bs-target="#activity" type="button" role="tab">
                        <i class="fas fa-history"></i> Activity Log
                    </button>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content">
                <!-- Overview Tab -->
                <div class="tab-pane fade show active" id="overview" role="tabpanel">
                    <div class="row g-3">
                        <!-- Project Info -->
                        <div class="col-lg-8">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header">
                                    <h6 class="mb-0">Project Information</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <strong>Code:</strong>
                                            <p><?php echo htmlspecialchars($project['project_code']); ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <strong>Province:</strong>
                                            <p><?php echo htmlspecialchars($project['province'] ?? 'N/A'); ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <strong>Municipality:</strong>
                                            <p><?php echo htmlspecialchars($project['municipality'] ?? 'N/A'); ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <strong>Barangay:</strong>
                                            <p><?php echo htmlspecialchars($project['barangay'] ?? 'N/A'); ?></p>
                                        </div>
                                        <div class="col-12">
                                            <strong>Description:</strong>
                                            <p><?php echo htmlspecialchars($project['description'] ?? 'No description'); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Progress -->
                            <div class="card border-0 shadow-sm mt-3">
                                <div class="card-header">
                                    <h6 class="mb-0">Progress Tracking</h6>
                                </div>
                                <div class="card-body">
                                    <div class="mb-4">
                                        <label class="form-label">Physical Progress: <strong><?php echo round($project['physical_progress'], 1); ?>%</strong></label>
                                        <div class="progress" style="height: 30px;">
                                            <div class="progress-bar" style="width: <?php echo $project['physical_progress']; ?>%; background-color: #0d6efd;">
                                                <?php echo round($project['physical_progress'], 1); ?>%
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mb-4">
                                        <label class="form-label">Financial Progress: <strong><?php echo round($project['financial_progress'], 1); ?>%</strong></label>
                                        <div class="progress" style="height: 30px;">
                                            <div class="progress-bar bg-success" style="width: <?php echo $project['financial_progress']; ?>%;">
                                                <?php echo round($project['financial_progress'], 1); ?>%
                                            </div>
                                        </div>
                                    </div>
                                    <canvas id="progressChart" height="80"></canvas>
                                </div>
                            </div>
                        </div>

                        <!-- Sidebar Stats -->
                        <div class="col-lg-4">
                            <div class="card border-0 shadow-sm mb-3">
                                <div class="card-body text-center">
                                    <div class="display-6 text-primary">₱<?php echo number_format($project['proposed_amount'], 0); ?></div>
                                    <small class="text-muted">Proposed Amount</small>
                                </div>
                            </div>

                            <div class="card border-0 shadow-sm mb-3">
                                <div class="card-body text-center">
                                    <div class="display-6 text-success">₱<?php echo number_format($project['allocated_amount'], 0); ?></div>
                                    <small class="text-muted">Allocated Amount</small>
                                </div>
                            </div>

                            <div class="card border-0 shadow-sm mb-3">
                                <div class="card-body">
                                    <strong>Timeline</strong>
                                    <div class="mt-2 small">
                                        <div class="mb-2">
                                            <strong>Created:</strong> <?php echo date('M d, Y', strtotime($project['created_date'])); ?>
                                        </div>
                                        <div>
                                            <strong>Updated:</strong> <?php echo date('M d, Y', strtotime($project['updated_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card border-0 shadow-sm">
                                <div class="card-body">
                                    <strong>Stage Progress</strong>
                                    <div class="mt-2">
                                        <canvas id="stageChart" height="120"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Financial Tab -->
                <div class="tab-pane fade" id="financial" role="tabpanel">
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">Financial Records</h6>
                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addFinancialModal">
                                    <i class="fas fa-plus"></i> Add Record
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Type</th>
                                            <th>Amount</th>
                                            <th>Reference</th>
                                            <th>Date</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($financial_records as $record): ?>
                                            <tr>
                                                <td>
                                                    <span class="badge" style="background-color: 
                                                        <?php echo match($record['record_type']) {
                                                            'Obligation' => '#0d6efd',
                                                            'Disbursement' => '#28a745',
                                                            'Liquidation' => '#17a2b8',
                                                            default => '#6c757d'
                                                        }; ?>">
                                                        <?php echo htmlspecialchars($record['record_type']); ?>
                                                    </span>
                                                </td>
                                                <td>₱<?php echo number_format($record['amount'], 2); ?></td>
                                                <td><?php echo htmlspecialchars($record['reference_number'] ?? '-'); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($record['record_date'])); ?></td>
                                                <td><?php echo htmlspecialchars($record['record_status']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Documents Tab -->
                <div class="tab-pane fade" id="documents" role="tabpanel">
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">Project Documents</h6>
                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#uploadDocumentModal">
                                    <i class="fas fa-upload"></i> Upload
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (empty($documents)): ?>
                                <div class="alert alert-info">No documents uploaded yet.</div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Name</th>
                                                <th>Type</th>
                                                <th>Uploaded By</th>
                                                <th>Date</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($documents as $doc): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($doc['original_filename']); ?></td>
                                                    <td><?php echo htmlspecialchars($doc['document_type']); ?></td>
                                                    <td><?php echo htmlspecialchars($doc['uploaded_by']); ?></td>
                                                    <td><?php echo date('M d, Y', strtotime($doc['upload_date'])); ?></td>
                                                    <td>
                                                        <a href="/api/documents.php?action=download&id=<?php echo $doc['id']; ?>" class="btn btn-xs btn-outline-primary">
                                                            <i class="fas fa-download"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Machinery Tab (AFME only) -->
                <?php if ($project_type === 'afme'): ?>
                    <div class="tab-pane fade" id="machinery" role="tabpanel">
                        <div class="card border-0 shadow-sm mb-3">
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0">Equipment & Machinery</h6>
                                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addMachineryModal">
                                        <i class="fas fa-plus"></i> Add Equipment
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (empty($machinery)): ?>
                                    <div class="alert alert-info">No machinery records yet.</div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Type</th>
                                                    <th>Quantity</th>
                                                    <th>Unit Cost</th>
                                                    <th>Total Cost</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                $total_machinery_cost = 0;
                                                foreach ($machinery as $mach):
                                                    $cost = $mach['unit_quantity'] * $mach['unit_cost'];
                                                    $total_machinery_cost += $cost;
                                                    ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($mach['machinery_type']); ?></td>
                                                        <td><?php echo $mach['unit_quantity']; ?></td>
                                                        <td>₱<?php echo number_format($mach['unit_cost'], 2); ?></td>
                                                        <td>₱<?php echo number_format($cost, 2); ?></td>
                                                        <td><?php echo htmlspecialchars($mach['machinery_status']); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                <tr class="table-active">
                                                    <td colspan="3"><strong>Total Machinery Cost:</strong></td>
                                                    <td><strong>₱<?php echo number_format($total_machinery_cost, 2); ?></strong></td>
                                                    <td></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Activity Log Tab -->
                <div class="tab-pane fade" id="activity" role="tabpanel">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header">
                            <h6 class="mb-0">Activity Log</h6>
                        </div>
                        <div class="card-body">
                            <div class="timeline">
                                <?php foreach ($audit_log as $entry): ?>
                                    <div class="timeline-item mb-3">
                                        <div class="timeline-marker"></div>
                                        <div class="timeline-content">
                                            <strong><?php echo htmlspecialchars($entry['action_type']); ?></strong>
                                            <p class="small text-muted mb-1">
                                                <?php echo htmlspecialchars($entry['description']); ?>
                                            </p>
                                            <small class="text-muted">
                                                <?php echo date('M d, Y H:i', strtotime($entry['timestamp'])); ?>
                                            </small>
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

    <!-- Modals -->
    <!-- Edit Project Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Project</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editForm">
                        <div class="mb-3">
                            <label class="form-label">Physical Progress (%)</label>
                            <input type="number" class="form-control" name="physical_progress" 
                                   min="0" max="100" step="0.1" value="<?php echo $project['physical_progress']; ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Financial Progress (%)</label>
                            <input type="number" class="form-control" name="financial_progress" 
                                   min="0" max="100" step="0.1" value="<?php echo $project['financial_progress']; ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Current Stage</label>
                            <select class="form-select" name="current_stage">
                                <option value="Proposal" <?php echo $project['current_stage'] === 'Proposal' ? 'selected' : ''; ?>>Proposal</option>
                                <option value="Pre-Implementation" <?php echo $project['current_stage'] === 'Pre-Implementation' ? 'selected' : ''; ?>>Pre-Implementation</option>
                                <option value="Procurement" <?php echo $project['current_stage'] === 'Procurement' ? 'selected' : ''; ?>>Procurement</option>
                                <option value="Implementation" <?php echo $project['current_stage'] === 'Implementation' ? 'selected' : ''; ?>>Implementation</option>
                                <option value="Completed" <?php echo $project['current_stage'] === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="Turned-Over" <?php echo $project['current_stage'] === 'Turned-Over' ? 'selected' : ''; ?>>Turned-Over</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveEditBtn">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/form-validator.js"></script>
    <script>
        const projectType = '<?php echo htmlspecialchars($project_type); ?>';
        const projectId = <?php echo (int)$project_id; ?>;

        // Edit button
        document.getElementById('editBtn').addEventListener('click', function() {
            const modal = new bootstrap.Modal(document.getElementById('editModal'));
            modal.show();
        });

        // Progress chart
        new Chart(document.getElementById('progressChart'), {
            type: 'bar',
            data: {
                labels: ['Physical', 'Financial'],
                datasets: [{
                    label: 'Progress (%)',
                    data: [<?php echo round($project['physical_progress'], 1); ?>, <?php echo round($project['financial_progress'], 1); ?>],
                    backgroundColor: ['#0d6efd', '#28a745']
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                scales: { x: { max: 100, ticks: { callback: v => v + '%' } } }
            }
        });

        // Stage chart
        const stages = ['Proposal', 'Pre-Impl', 'Procurement', 'Implementation', 'Completed'];
        const currentStageIndex = stages.indexOf('<?php echo substr($project['current_stage'], 0, strrpos($project['current_stage'], '-') ?: strlen($project['current_stage'])); ?>');
        
        new Chart(document.getElementById('stageChart'), {
            type: 'doughnut',
            data: {
                labels: stages,
                datasets: [{
                    data: [20, 20, 20, 20, 20],
                    backgroundColor: ['#6c757d', '#17a2b8', '#ffc107', '#0d6efd', '#28a745']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } }
            }
        });

        // Save edit
        document.getElementById('saveEditBtn').addEventListener('click', async function() {
            const formData = new FormData(document.getElementById('editForm'));
            const response = await fetch(`/api/projects.php?action=update&type=${projectType}&id=${projectId}`, {
                method: 'PUT',
                body: formData
            });

            if (response.ok) {
                alert('Project updated successfully');
                location.reload();
            } else {
                alert('Failed to update project');
            }
        });
    </script>
    <style>
        .timeline {
            position: relative;
        }
        .timeline-item {
            display: flex;
            gap: 1rem;
        }
        .timeline-marker {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background-color: #0d6efd;
            margin-top: 3px;
            flex-shrink: 0;
        }
        .timeline-item:not(:last-child) .timeline-marker::after {
            content: '';
            position: absolute;
            width: 2px;
            height: 40px;
            background-color: #dee2e6;
            left: 5px;
            top: 20px;
        }
    </style>
</body>
</html>
