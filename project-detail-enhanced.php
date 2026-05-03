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

$table = 'projects';

// Fetch project details
$stmt = $conn->prepare("SELECT *, title AS project_title FROM $table WHERE id = ? AND project_type = ?");
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

$documents = [];
$rawDocs = json_decode((string) ($project['documents'] ?? '[]'), true);
if (is_array($rawDocs)) {
    $uploadUserIds = [];
    foreach ($rawDocs as $d) {
        if (is_array($d)) {
            $uid = (int) ($d['uploaded_by'] ?? 0);
            if ($uid > 0) {
                $uploadUserIds[$uid] = true;
            }
        }
    }
    $uploadUserNames = [];
    if (!empty($uploadUserIds)) {
        $ids = array_values(array_unique(array_map('intval', array_keys($uploadUserIds))));
        $ids = array_filter($ids, static fn ($id) => $id > 0);
        if (!empty($ids)) {
            $inList = implode(',', $ids);
            $uq = $conn->query("SELECT id, full_name FROM users WHERE id IN ($inList)");
            if ($uq) {
                foreach ($uq->fetch_all(MYSQLI_ASSOC) as $ur) {
                    $uploadUserNames[(int) $ur['id']] = (string) $ur['full_name'];
                }
            }
        }
    }
    foreach ($rawDocs as $doc) {
        if (!is_array($doc)) {
            continue;
        }
        $uid = (int) ($doc['uploaded_by'] ?? 0);
        $documents[] = [
            'id' => (int) ($doc['id'] ?? 0),
            'original_filename' => (string) ($doc['original_filename'] ?? $doc['file_name'] ?? 'Document'),
            'document_type' => (string) ($doc['document_type'] ?? $doc['doc_type'] ?? 'General'),
            'upload_date' => (string) ($doc['upload_date'] ?? ''),
            'uploaded_by' => $uid > 0 ? ($uploadUserNames[$uid] ?? ('User #' . $uid)) : 'Unknown',
        ];
    }
    usort($documents, static function ($a, $b) {
        return strcmp((string) ($b['upload_date'] ?? ''), (string) ($a['upload_date'] ?? ''));
    });
}

$financial_records = [];
$rawFinancial = json_decode((string) ($project['financial_entries'] ?? '[]'), true);
if (is_array($rawFinancial)) {
    foreach ($rawFinancial as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $financial_records[] = [
            'record_type' => (string) ($entry['record_type'] ?? 'Other'),
            'amount' => (float) ($entry['amount'] ?? 0),
            'reference_number' => (string) ($entry['reference_number'] ?? ''),
            'record_date' => (string) ($entry['record_date'] ?? ''),
            'record_status' => (string) ($entry['record_status'] ?? 'Active'),
        ];
    }
    usort($financial_records, static function ($a, $b) {
        return strcmp((string) ($b['record_date'] ?? ''), (string) ($a['record_date'] ?? ''));
    });
}

// Fetch machinery if AFME project
$machinery = [];
if ($project_type === 'afme') {
    $mach_stmt = $conn->prepare("SELECT * FROM afme WHERE project_id = ? ORDER BY created_at DESC");
    $mach_stmt->bind_param('i', $project_id);
    $mach_stmt->execute();
    $machinery = $mach_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get comments/audit log
$audit_stmt = $conn->prepare("SELECT * FROM audit_log WHERE project_type = ? AND project_id = ? ORDER BY created_at DESC LIMIT 20");
$auditProjectType = strtoupper($project_type);
$audit_stmt->bind_param('si', $auditProjectType, $project_id);
$audit_stmt->execute();
$audit_log = $audit_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$physicalProgressRaw = (float) ($project['physical_progress'] ?? 0);
$financialProgressRaw = (float) ($project['financial_progress'] ?? 0);
$physicalProgressDisplay = round($physicalProgressRaw, 1);
$financialProgressDisplay = round($financialProgressRaw, 1);
$physicalProgressWidth = max(0, min(100, $physicalProgressRaw));
$financialProgressWidth = max(0, min(100, $financialProgressRaw));

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
            <?php if ($approval !== 'Approved'): ?>
                <div class="alert alert-warning border-0 shadow-sm mb-3" role="alert">
                    <strong>Awaiting approval.</strong> This registration is not visible in the project catalog or maps until a Super Admin approves it.
                </div>
            <?php endif; ?>
            <!-- Project Header -->
            <div class="row align-items-center mb-4">
                <div class="col">
                    <div class="d-flex align-items-center gap-3">
                        <div>
                            <span class="badge bg-primary badge-fs-md">
                                <?php echo strtoupper($project_type); ?>
                            </span>
                            <span class="badge badge-fs-md <?php echo htmlspecialchars(stage_badge_class((string) $project['current_stage']), ENT_QUOTES, 'UTF-8'); ?>">
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
                        <button type="button" class="btn btn-outline-dark btn-sm" id="printBtn">
                            <i class="fas fa-print"></i> Print
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
                                        <div class="col-md-6">
                                            <strong>Purok:</strong>
                                            <p><?php
                                                $purok = trim((string) ($project['district'] ?? ''));
                                                echo htmlspecialchars($purok !== '' ? $purok : 'N/A');
                                            ?></p>
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
                                        <label class="form-label">Physical Progress: <strong><?php echo $physicalProgressDisplay; ?>%</strong></label>
                                        <div class="progress progress-lg">
                                            <div class="progress-bar progress-bar-physical progress-bar-w" style="--w: <?php echo $physicalProgressWidth; ?>%;">
                                                <?php echo $physicalProgressDisplay; ?>%
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mb-4">
                                        <label class="form-label">Financial Progress: <strong><?php echo $financialProgressDisplay; ?>%</strong></label>
                                        <div class="progress progress-lg">
                                            <div class="progress-bar bg-success progress-bar-w" style="--w: <?php echo $financialProgressWidth; ?>%;">
                                                <?php echo $financialProgressDisplay; ?>%
                                            </div>
                                        </div>
                                    </div>
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
                                            <strong>Created:</strong> <?php echo date('M d, Y', strtotime($project['created_at'])); ?>
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
                                        <?php if (empty($financial_records)): ?>
                                            <tr>
                                                <td colspan="5" class="text-center text-muted py-3">No financial records yet.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($financial_records as $record): ?>
                                                <tr>
                                                    <td>
                                                        <span class="badge <?php echo htmlspecialchars(financial_record_badge_class((string) $record['record_type']), ENT_QUOTES, 'UTF-8'); ?>">
                                                            <?php echo htmlspecialchars($record['record_type']); ?>
                                                        </span>
                                                    </td>
                                                    <td>₱<?php echo number_format((float) $record['amount'], 2); ?></td>
                                                    <td><?php echo htmlspecialchars($record['reference_number'] !== '' ? $record['reference_number'] : '-'); ?></td>
                                                    <td><?php echo $record['record_date'] !== '' ? date('M d, Y', strtotime($record['record_date'])) : '—'; ?></td>
                                                    <td><?php echo htmlspecialchars($record['record_status']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
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
                                                    <td><?php echo $doc['upload_date'] !== '' ? date('M d, Y', strtotime($doc['upload_date'])) : '—'; ?></td>
                                                    <td>
                                                        <a href="api/documents.php?action=preview&amp;project_id=<?php echo (int) $project_id; ?>&amp;project_type=<?php echo htmlspecialchars($project_type, ENT_QUOTES, 'UTF-8'); ?>&amp;id=<?php echo (int) $doc['id']; ?>" class="btn btn-xs btn-outline-secondary" target="_blank" rel="noopener" title="View">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <a href="api/documents.php?action=download&amp;project_id=<?php echo (int) $project_id; ?>&amp;project_type=<?php echo htmlspecialchars($project_type, ENT_QUOTES, 'UTF-8'); ?>&amp;id=<?php echo (int) $doc['id']; ?>" class="btn btn-xs btn-outline-primary">
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
                                            <strong><?php echo htmlspecialchars($entry['action']); ?></strong>
                                            <p class="small text-muted mb-1">
                                                <?php echo htmlspecialchars($entry['action']); ?>
                                            </p>
                                            <small class="text-muted">
                                                <?php echo date('M d, Y H:i', strtotime($entry['created_at'])); ?>
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
    <div class="modal fade" id="uploadDocumentModal" tabindex="-1" aria-labelledby="uploadDocumentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="uploadDocumentModalLabel">Upload document</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="uploadDocumentForm">
                    <div class="modal-body">
                        <input type="hidden" name="project_id" value="<?php echo (int) $project_id; ?>">
                        <input type="hidden" name="project_type" value="<?php echo htmlspecialchars($project_type, ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="mb-3">
                            <label class="form-label">Document type</label>
                            <select class="form-select" name="document_type" required>
                                <option value="">— Select —</option>
                                <option value="Program of Work">Program of Work</option>
                                <option value="Progress photo">Progress photo</option>
                                <option value="Inspection report">Inspection report</option>
                                <option value="Billing / SOA">Billing / SOA</option>
                                <option value="General">General</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">File</label>
                            <input type="file" class="form-control" name="document_file" required accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp,.gif,.heic,.heif">
                            <div class="form-text">Allowed: PDF, DOC, DOCX, JPG, PNG, WEBP, GIF, HEIC, HEIF (max 10MB)</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Upload</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="addFinancialModal" tabindex="-1" aria-labelledby="addFinancialModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addFinancialModalLabel">Add financial record</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="addFinancialForm">
                    <div class="modal-body">
                        <input type="hidden" name="project_id" value="<?php echo (int) $project_id; ?>">
                        <input type="hidden" name="project_type" value="<?php echo htmlspecialchars($project_type, ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="mb-3">
                            <label class="form-label">Type</label>
                            <select class="form-select" name="record_type" required>
                                <option value="">— Select —</option>
                                <option value="Obligation">Obligation</option>
                                <option value="Disbursement">Disbursement</option>
                                <option value="Liquidation">Liquidation</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Amount</label>
                            <input type="number" class="form-control" name="amount" min="0.01" step="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Reference</label>
                            <input type="text" class="form-control" name="reference_number" maxlength="100" placeholder="Optional reference number">
                        </div>
                        <div class="mb-0">
                            <label class="form-label">Particulars</label>
                            <textarea class="form-control" name="particulars" rows="3" maxlength="500" placeholder="Optional details"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save record</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

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

    <script src="assets/bootstrap/js/bootstrap.bundle.js"></script>
    <script src="assets/js/form-validator.js"></script>
    <script>
        const projectType = '<?php echo htmlspecialchars($project_type); ?>';
        const projectId = <?php echo (int)$project_id; ?>;

        // Edit button
        document.getElementById('editBtn').addEventListener('click', function() {
            const modal = new bootstrap.Modal(document.getElementById('editModal'));
            modal.show();
        });
        document.getElementById('printBtn').addEventListener('click', function() {
            const url = 'exports/project-detail-pdf.php?type=' + encodeURIComponent(projectType) + '&id=' + encodeURIComponent(projectId);
            window.open(url, '_blank', 'noopener');
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
                    backgroundColor: ['#9ca3af', '#c694f9', '#f5c57a', '#5b8def', '#5fd4a8']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } }
            }
        });

        const uploadDocumentForm = document.getElementById('uploadDocumentForm');
        if (uploadDocumentForm) {
            uploadDocumentForm.addEventListener('submit', async function (e) {
                e.preventDefault();
                const formData = new FormData(this);
                const submitBtn = this.querySelector('button[type="submit"]');
                const prev = submitBtn ? submitBtn.innerHTML : '';
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
                }
                try {
                    const response = await fetch('api/documents.php?action=upload', {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin',
                    });
                    const data = await response.json().catch(function () { return {}; });
                    if (response.ok && data.status === 'success') {
                        await AppModal.alert(data.message || 'Document uploaded successfully', { title: 'Upload', variant: 'success' });
                        location.reload();
                    } else {
                        await AppModal.alert(data.message || 'Upload failed', { title: 'Upload failed', variant: 'danger' });
                    }
                } catch (err) {
                    console.error(err);
                    await AppModal.alert('Upload failed', { title: 'Error', variant: 'danger' });
                } finally {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = prev;
                    }
                }
            });
        }

        const addFinancialForm = document.getElementById('addFinancialForm');
        if (addFinancialForm) {
            addFinancialForm.addEventListener('submit', async function (e) {
                e.preventDefault();
                const formData = new FormData(this);
                const submitBtn = this.querySelector('button[type="submit"]');
                const prev = submitBtn ? submitBtn.innerHTML : '';
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
                }
                try {
                    const response = await fetch('api/financial.php?action=create', {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin'
                    });
                    const data = await response.json().catch(function () { return {}; });
                    if (response.ok && data.status === 'success') {
                        await AppModal.alert(data.message || 'Financial record added successfully', { title: 'Financial', variant: 'success' });
                        location.reload();
                    } else {
                        await AppModal.alert(data.message || 'Failed to add financial record', { title: 'Error', variant: 'danger' });
                    }
                } catch (err) {
                    console.error(err);
                    await AppModal.alert('Failed to add financial record', { title: 'Error', variant: 'danger' });
                } finally {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = prev;
                    }
                }
            });
        }

        // Save edit
        document.getElementById('saveEditBtn').addEventListener('click', async function() {
            const formData = new FormData(document.getElementById('editForm'));
            const payload = Object.fromEntries(formData.entries());
            try {
                const response = await fetch('api/projects.php?action=update&type=' + encodeURIComponent(projectType) + '&id=' + encodeURIComponent(projectId), {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const result = await response.json().catch(function () { return {}; });
                if (response.ok && result.status === 'success') {
                    await AppModal.alert(result.message || 'Project updated successfully', { title: 'Saved', variant: 'success' });
                    location.reload();
                } else {
                    await AppModal.alert(result.message || 'Failed to update project', { title: 'Error', variant: 'danger' });
                }
            } catch (err) {
                console.error(err);
                await AppModal.alert('Failed to update project', { title: 'Error', variant: 'danger' });
            }
        });
    </script>
</body>
</html>
