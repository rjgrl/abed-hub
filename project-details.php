<?php
session_start();
require_once 'config/database.php';

requireLogin();

$page_title = 'Project Details';

$type = $_GET['type'] ?? '';
$id = intval($_GET['id'] ?? 0);

if (empty($type) || !in_array($type, ['FSPF', 'IDP']) || $id <= 0) {
    header('Location: dashboard.php');
    exit;
}

$table = strtolower($type) . '_projects';

// Get project details
$stmt = $conn->prepare("SELECT * FROM $table WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$project = $stmt->get_result()->fetch_assoc();

if (!$project) {
    header('Location: dashboard.php');
    exit;
}

// Get project documents
$stmt = $conn->prepare("
    SELECT * FROM project_documents 
    WHERE project_type = ? AND project_id = ? 
    ORDER BY upload_date DESC
");
$stmt->bind_param("si", $type, $id);
$stmt->execute();
$documents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get project milestones
$stmt = $conn->prepare("
    SELECT * FROM project_milestones 
    WHERE project_type = ? AND project_id = ? 
    ORDER BY target_date DESC
");
$stmt->bind_param("si", $id);
$stmt->execute();
$milestones = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get status updates
$stmt = $conn->prepare("
    SELECT * FROM project_status_updates 
    WHERE project_type = ? AND project_id = ? 
    ORDER BY update_time DESC
");
$stmt->bind_param("si", $type, $id);
$stmt->execute();
$status_updates = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'viewer';
?>
<?php
require_once __DIR__ . '/components/layout.php';
renderAppLayout($page_title);
?>
        <div class="container-fluid py-4">
        <!-- Project Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="mb-0"><?php echo htmlspecialchars($project['project_title']); ?></h4>
                                <small><?php echo htmlspecialchars($project['project_code']); ?> | <?php echo $type; ?> Project</small>
                            </div>
                            <div>
                                <span class="badge bg-<?php 
                                    echo match($project['current_stage']) {
                                        'Proposal' => 'warning',
                                        'Pre-Implementation' => 'info',
                                        'Procurement' => 'secondary',
                                        'Implementation' => 'success',
                                        'Completed' => 'success',
                                        default => 'secondary'
                                    };
                                ?> fs-6">
                                    <?php echo htmlspecialchars($project['current_stage']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Progress Bar -->
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted">Physical Progress</span>
                                <span class="fw-bold"><?php echo $project['physical_progress'] ?? 0; ?>%</span>
                            </div>
                            <div class="progress" style="height: 10px;">
                                <div class="progress-bar" role="progressbar" 
                                     style="width: <?php echo $project['physical_progress'] ?? 0; ?>%"
                                     aria-valuenow="<?php echo $project['physical_progress'] ?? 0; ?>" 
                                     aria-valuemin="0" aria-valuemax="100">
                                </div>
                            </div>
                        </div>

                        <!-- Quick Stats -->
                        <div class="row text-center">
                            <div class="col-md-3">
                                <div class="border-end">
                                    <h5 class="text-primary mb-0">₱<?php echo number_format($project['allocated_amount'] ?? 0, 2); ?></h5>
                                    <small class="text-muted">Budget</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border-end">
                                    <h5 class="text-success mb-0"><?php echo $project['physical_progress'] ?? 0; ?>%</h5>
                                    <small class="text-muted">Physical</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border-end">
                                    <h5 class="text-info mb-0"><?php echo $project['financial_progress'] ?? 0; ?>%</h5>
                                    <small class="text-muted">Financial</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <h5 class="text-warning mb-0"><?php echo $project['funding_year'] ?? 'N/A'; ?></h5>
                                <small class="text-muted">Funding Year</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Project Details Tabs -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <ul class="nav nav-tabs card-header-tabs" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" data-bs-toggle="tab" href="#details">Details</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#documents">Documents</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#milestones">Milestones</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#updates">Status Updates</a>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content">
                            <!-- Details Tab -->
                            <div id="details" class="tab-pane fade show active">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="text-primary">Basic Information</h6>
                                        <table class="table table-sm">
                                            <tr>
                                                <td class="fw-bold">Project Code:</td>
                                                <td><?php echo htmlspecialchars($project['project_code']); ?></td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold">Project Title:</td>
                                                <td><?php echo htmlspecialchars($project['project_title']); ?></td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold">Fund Source:</td>
                                                <td><?php echo htmlspecialchars($project['fund_source']); ?></td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold">Funding Year:</td>
                                                <td><?php echo $project['funding_year']; ?></td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold">Scope of Work:</td>
                                                <td><?php echo htmlspecialchars($project['scope_of_work']); ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="text-primary">Location & Implementation</h6>
                                        <table class="table table-sm">
                                            <tr>
                                                <td class="fw-bold">Province:</td>
                                                <td><?php echo htmlspecialchars($project['province']); ?></td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold">Municipality:</td>
                                                <td><?php echo htmlspecialchars($project['municipality']); ?></td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold">Barangay:</td>
                                                <td><?php echo htmlspecialchars($project['barangay']); ?></td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold">Beneficiary:</td>
                                                <td><?php echo htmlspecialchars($project['beneficiary']); ?></td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold">Implementation Days:</td>
                                                <td><?php echo $project['implementation_schedule_days']; ?> days</td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <h6 class="text-primary">Financial Information</h6>
                                        <table class="table table-sm">
                                            <tr>
                                                <td class="fw-bold">Proposed Amount:</td>
                                                <td>₱<?php echo number_format($project['proposed_amount'] ?? 0, 2); ?></td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold">Allocated Amount:</td>
                                                <td>₱<?php echo number_format($project['allocated_amount'] ?? 0, 2); ?></td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold">Quantity:</td>
                                                <td><?php echo $project['quantity']; ?> <?php echo htmlspecialchars($project['unit']); ?></td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold">Households Benefited:</td>
                                                <td><?php echo $project['households_benefited']; ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="text-primary">Progress Information</h6>
                                        <table class="table table-sm">
                                            <tr>
                                                <td class="fw-bold">Current Stage:</td>
                                                <td><?php echo htmlspecialchars($project['current_stage']); ?></td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold">Proposal Status:</td>
                                                <td><?php echo htmlspecialchars($project['proposal_status']); ?></td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold">Physical Progress:</td>
                                                <td><?php echo $project['physical_progress'] ?? 0; ?>%</td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold">Financial Progress:</td>
                                                <td><?php echo $project['financial_progress'] ?? 0; ?>%</td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                                <?php if (!empty($project['description'])): ?>
                                <div class="mt-3">
                                    <h6 class="text-primary">Description</h6>
                                    <p><?php echo nl2br(htmlspecialchars($project['description'])); ?></p>
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- Documents Tab -->
                            <div id="documents" class="tab-pane fade">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="text-primary mb-0">Project Documents</h6>
                                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#uploadDocumentModal">
                                        <i class="fas fa-upload me-1"></i>Upload Document
                                    </button>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Document Type</th>
                                                <th>File Name</th>
                                                <th>Stage</th>
                                                <th>Upload Date</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($documents as $doc): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($doc['doc_type']); ?></td>
                                                <td><?php echo htmlspecialchars($doc['file_name']); ?></td>
                                                <td><?php echo htmlspecialchars($doc['stage']); ?></td>
                                                <td><?php echo date('M d, Y H:i', strtotime($doc['upload_date'])); ?></td>
                                                <td>
                                                    <a href="<?php echo htmlspecialchars($doc['file_path']); ?>" 
                                                       class="btn btn-sm btn-outline-primary" target="_blank">
                                                        <i class="fas fa-download"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                            <?php if (empty($documents)): ?>
                                            <tr>
                                                <td colspan="5" class="text-center text-muted">No documents uploaded yet</td>
                                            </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Milestones Tab -->
                            <div id="milestones" class="tab-pane fade">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="text-primary mb-0">Project Milestones</h6>
                                    <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addMilestoneModal">
                                        <i class="fas fa-plus me-1"></i>Add Milestone
                                    </button>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Milestone</th>
                                                <th>Stage</th>
                                                <th>Target Date</th>
                                                <th>Actual Date</th>
                                                <th>Status</th>
                                                <th>Remarks</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($milestones as $milestone): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($milestone['milestone_name']); ?></td>
                                                <td><?php echo htmlspecialchars($milestone['stage']); ?></td>
                                                <td><?php echo $milestone['target_date'] ? date('M d, Y', strtotime($milestone['target_date'])) : 'N/A'; ?></td>
                                                <td><?php echo $milestone['actual_date'] ? date('M d, Y', strtotime($milestone['actual_date'])) : 'N/A'; ?></td>
                                                <td>
                                                    <?php if ($milestone['actual_date']): ?>
                                                        <span class="badge bg-success">Completed</span>
                                                    <?php elseif (strtotime($milestone['target_date']) < time()): ?>
                                                        <span class="badge bg-danger">Overdue</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">Pending</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($milestone['remarks']); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                            <?php if (empty($milestones)): ?>
                                            <tr>
                                                <td colspan="6" class="text-center text-muted">No milestones set yet</td>
                                            </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Status Updates Tab -->
                            <div id="updates" class="tab-pane fade">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Date</th>
                                                <th>Previous Status</th>
                                                <th>New Status</th>
                                                <th>Remarks</th>
                                                <th>Updated By</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($status_updates as $update): ?>
                                            <tr>
                                                <td><?php echo date('M d, Y H:i', strtotime($update['update_time'])); ?></td>
                                                <td><?php echo htmlspecialchars($update['previous_status']); ?></td>
                                                <td><?php echo htmlspecialchars($update['new_status']); ?></td>
                                                <td><?php echo htmlspecialchars($update['update_remarks']); ?></td>
                                                <td><?php echo htmlspecialchars($update['updated_by']); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                            <?php if (empty($status_updates)): ?>
                                            <tr>
                                                <td colspan="5" class="text-center text-muted">No status updates yet</td>
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
        </div>
    </div>

    <!-- Upload Document Modal -->
    <div class="modal fade" id="uploadDocumentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Upload Document</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="uploadDocumentForm" enctype="multipart/form-data">
                    <input type="hidden" name="project_type" value="<?php echo $type; ?>">
                    <input type="hidden" name="project_id" value="<?php echo $id; ?>">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Document Type</label>
                            <select class="form-control" name="doc_type" required>
                                <option value="Validation Report">Validation Report</option>
                                <option value="Geotagged Photo">Geotagged Photo</option>
                                <option value="KML">KML File</option>
                                <option value="POW">Program of Works</option>
                                <option value="ES">Engineering Survey</option>
                                <option value="DED">Detailed Engineering Design</option>
                                <option value="Feasibility Study">Feasibility Study</option>
                                <option value="Others">Others</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Stage</label>
                            <select class="form-control" name="stage">
                                <option value="Proposal">Proposal</option>
                                <option value="Pre-Implementation">Pre-Implementation</option>
                                <option value="Procurement">Procurement</option>
                                <option value="Implementation">Implementation</option>
                                <option value="Completed">Completed</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">File</label>
                            <input type="file" class="form-control" name="document_file" required>
                            <small class="text-muted">Max file size: 10MB. Allowed: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG</small>
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

    <!-- Add Milestone Modal -->
    <div class="modal fade" id="addMilestoneModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Milestone</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="addMilestoneForm">
                    <input type="hidden" name="project_type" value="<?php echo $type; ?>">
                    <input type="hidden" name="project_id" value="<?php echo $id; ?>">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Milestone Name</label>
                            <input type="text" class="form-control" name="milestone_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Stage</label>
                            <select class="form-control" name="stage" required>
                                <option value="Proposal">Proposal</option>
                                <option value="Pre-Implementation">Pre-Implementation</option>
                                <option value="Procurement">Procurement</option>
                                <option value="Implementation">Implementation</option>
                                <option value="Completed">Completed</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Target Date</label>
                            <input type="date" class="form-control" name="target_date">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Remarks</label>
                            <textarea class="form-control" name="remarks" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Add Milestone</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="assets/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/project-details.js"></script>
<?php renderAppLayoutFooter(); ?>

