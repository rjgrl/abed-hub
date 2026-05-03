<?php
session_name('ABED_IDM_HUB');
session_start();
require_once 'config/database.php';

requireLogin();

$page_title = 'AFME Machinery Details';

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: dashboard-enhanced.php');
    exit;
}

// Get machinery details from consolidated afme table
$stmt = $conn->prepare("
    SELECT a.*,
           p.title as afme_project_title,
           a.amount_proposed AS proposed_amount,
           a.amount_allocated AS allocated_amount
    FROM afme a
    LEFT JOIN projects p ON a.project_id = p.id
    WHERE a.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$machinery = $stmt->get_result()->fetch_assoc();

if (!$machinery) {
    header('Location: dashboard-enhanced.php');
    exit;
}

// Materialize legacy views from afme single-row structure
$specs = [
    'machinery_type' => $machinery['machinery_type'] ?? null,
    'mode_of_procurement' => $machinery['mode_of_procurement'] ?? null,
    'brand' => $machinery['brand'] ?? null,
    'engine_type' => $machinery['engine_type'] ?? null,
    'serial_number' => $machinery['serial_number'] ?? null,
    'chassis_serial_number' => $machinery['chassis_serial_number'] ?? null,
    'specifications' => $machinery['specifications'] ?? null,
];
$validation = [
    'date_validation_start' => $machinery['date_validation_start'] ?? null,
    'date_validation_end' => $machinery['date_validation_end'] ?? null,
    'implementation_type' => $machinery['implementation_type'] ?? null,
    'service_area' => $machinery['service_area'] ?? null,
    'validation_status' => $machinery['validation_status'] ?? null,
    'latitude' => $machinery['latitude'] ?? null,
    'longitude' => $machinery['longitude'] ?? null,
];
$milestones = json_decode((string)($machinery['milestones'] ?? '[]'), true);
if (!is_array($milestones)) {
    $milestones = [];
}
$documents = json_decode((string)($machinery['documents'] ?? '[]'), true);
if (!is_array($documents)) {
    $documents = [];
}
$delivery = [
    'notice_to_proceed_date' => $machinery['notice_to_proceed_date'] ?? null,
    'delivery_date' => $machinery['delivery_date'] ?? null,
    'delivery_status' => $machinery['delivery_status'] ?? null,
    'delivery_location' => $machinery['delivery_location'] ?? null,
    'delivery_remarks' => $machinery['delivery_remarks'] ?? null,
];
$turnover = [
    'turnover_date' => $machinery['turnover_date'] ?? null,
    'turnover_status' => $machinery['turnover_status'] ?? null,
    'documentary_requirements_met' => (int)($machinery['documentary_requirements_met'] ?? 0),
];

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'viewer';
?>
<?php
require_once __DIR__ . '/components/layout.php';
renderAppLayout($page_title);
?>
        <div class="container-fluid py-4">
        <!-- Machinery Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="mb-0"><?php echo htmlspecialchars($machinery['machine_name']); ?></h4>
                                <small><?php echo htmlspecialchars($machinery['machine_id'] ?? 'No ID'); ?> | AFME Machinery</small>
                            </div>
                            <div>
                                <span class="badge bg-<?php 
                                    echo match($machinery['current_status']) {
                                        'Proposal' => 'warning',
                                        'Pre-Implementation' => 'info',
                                        'Procurement' => 'secondary',
                                        'Implementation' => 'success',
                                        'Delivered' => 'success',
                                        'Turned-Over' => 'dark',
                                        'Operational' => 'success',
                                        default => 'secondary'
                                    };
                                ?> fs-6">
                                    <?php echo htmlspecialchars($machinery['current_status']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Quick Stats -->
                        <div class="row text-center">
                            <div class="col-md-3">
                                <div class="border-end">
                                    <h5 class="text-primary mb-0">₱<?php echo number_format($machinery['allocated_amount'] ?? 0, 2); ?></h5>
                                    <small class="text-muted">Allocated Amount</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border-end">
                                    <h5 class="text-success mb-0"><?php echo htmlspecialchars($machinery['farm_operation']); ?></h5>
                                    <small class="text-muted">Farm Operation</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border-end">
                                    <h5 class="text-info mb-0"><?php echo $machinery['funding_year'] ?? 'N/A'; ?></h5>
                                    <small class="text-muted">Funding Year</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <h5 class="text-warning mb-0"><?php echo $machinery['beneficiary_households'] ?? 0; ?></h5>
                                <small class="text-muted">Households</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Machinery Details Tabs -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <ul class="nav nav-tabs card-header-tabs" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" data-bs-toggle="tab" href="#details">Details</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#specs">Specifications</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#validation">Validation</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#delivery">Delivery</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#documents">Documents</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#milestones">Milestones</a>
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
                                                <td class="fw-bold">Machine Name:</td>
                                                <td><?php echo htmlspecialchars($machinery['machine_name']); ?></td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold">Machine ID:</td>
                                                <td><?php echo htmlspecialchars($machinery['machine_id'] ?? 'N/A'); ?></td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold">Farm Operation:</td>
                                                <td><?php echo htmlspecialchars($machinery['farm_operation']); ?></td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold">Fund Source:</td>
                                                <td><?php echo htmlspecialchars($machinery['fund_source']); ?></td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold">Funding Year:</td>
                                                <td><?php echo $machinery['funding_year']; ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="text-primary">Beneficiary Information</h6>
                                        <table class="table table-sm">
                                            <tr>
                                                <td class="fw-bold">Beneficiary Name:</td>
                                                <td><?php echo htmlspecialchars($machinery['beneficiary_name']); ?></td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold">Contact:</td>
                                                <td><?php echo htmlspecialchars($machinery['beneficiary_contact']); ?></td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold">Recipient Type:</td>
                                                <td><?php echo htmlspecialchars($machinery['recipient_type']); ?></td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold">Farm Location:</td>
                                                <td><?php echo htmlspecialchars($machinery['farm_location']); ?></td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold">Households:</td>
                                                <td><?php echo $machinery['beneficiary_households']; ?></td>
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
                                                <td>₱<?php echo number_format($machinery['proposed_amount'] ?? 0, 2); ?></td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold">Allocated Amount:</td>
                                                <td>₱<?php echo number_format($machinery['allocated_amount'] ?? 0, 2); ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="text-primary">Status Information</h6>
                                        <table class="table table-sm">
                                            <tr>
                                                <td class="fw-bold">Current Status:</td>
                                                <td><?php echo htmlspecialchars($machinery['current_status']); ?></td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold">Date Received:</td>
                                                <td><?php echo $machinery['date_receipt'] ? date('M d, Y', strtotime($machinery['date_receipt'])) : 'N/A'; ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                                <?php if (!empty($machinery['description'])): ?>
                                <div class="mt-3">
                                    <h6 class="text-primary">Description</h6>
                                    <p><?php echo nl2br(htmlspecialchars($machinery['description'])); ?></p>
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- Specifications Tab -->
                            <div id="specs" class="tab-pane fade">
                                <?php if ($specs): ?>
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="text-primary">Technical Specifications</h6>
                                        <table class="table table-sm">
                                            <tr>
                                                <td class="fw-bold">Machinery Type:</td>
                                                <td><?php echo htmlspecialchars($specs['machinery_type']); ?></td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold">Mode of Procurement:</td>
                                                <td><?php echo htmlspecialchars($specs['mode_of_procurement']); ?></td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold">Brand:</td>
                                                <td><?php echo htmlspecialchars($specs['brand']); ?></td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold">Engine Type:</td>
                                                <td><?php echo htmlspecialchars($specs['engine_type']); ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="text-primary">Serial Numbers</h6>
                                        <table class="table table-sm">
                                            <tr>
                                                <td class="fw-bold">Serial Number:</td>
                                                <td><?php echo htmlspecialchars($specs['serial_number']); ?></td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold">Chassis Serial:</td>
                                                <td><?php echo htmlspecialchars($specs['chassis_serial_number']); ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                                <?php if (!empty($specs['specifications'])): ?>
                                <div class="mt-3">
                                    <h6 class="text-primary">Detailed Specifications</h6>
                                    <p><?php echo nl2br(htmlspecialchars($specs['specifications'])); ?></p>
                                </div>
                                <?php endif; ?>
                                <?php else: ?>
                                <p class="text-muted">No specifications recorded yet.</p>
                                <?php endif; ?>
                            </div>

                            <!-- Validation Tab -->
                            <div id="validation" class="tab-pane fade">
                                <?php if ($validation): ?>
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="text-primary">Validation Details</h6>
                                        <table class="table table-sm">
                                            <tr>
                                                <td class="fw-bold">Validation Start:</td>
                                                <td><?php echo $validation['date_validation_start'] ? date('M d, Y', strtotime($validation['date_validation_start'])) : 'N/A'; ?></td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold">Validation End:</td>
                                                <td><?php echo $validation['date_validation_end'] ? date('M d, Y', strtotime($validation['date_validation_end'])) : 'N/A'; ?></td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold">Implementation Type:</td>
                                                <td><?php echo htmlspecialchars($validation['implementation_type']); ?></td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold">Service Area:</td>
                                                <td><?php echo htmlspecialchars($validation['service_area']); ?></td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold">Status:</td>
                                                <td><?php echo htmlspecialchars($validation['validation_status']); ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="text-primary">Location</h6>
                                        <table class="table table-sm">
                                            <tr>
                                                <td class="fw-bold">Latitude:</td>
                                                <td><?php echo $validation['latitude']; ?></td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold">Longitude:</td>
                                                <td><?php echo $validation['longitude']; ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                                <?php else: ?>
                                <p class="text-muted">No validation details recorded yet.</p>
                                <?php endif; ?>
                            </div>

                            <!-- Delivery Tab -->
                            <div id="delivery" class="tab-pane fade">
                                <?php if ($delivery): ?>
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="text-primary">Delivery Information</h6>
                                        <table class="table table-sm">
                                            <tr>
                                                <td class="fw-bold">Notice to Proceed:</td>
                                                <td><?php echo $delivery['notice_to_proceed_date'] ? date('M d, Y', strtotime($delivery['notice_to_proceed_date'])) : 'N/A'; ?></td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold">Delivery Date:</td>
                                                <td><?php echo $delivery['delivery_date'] ? date('M d, Y', strtotime($delivery['delivery_date'])) : 'N/A'; ?></td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold">Delivery Status:</td>
                                                <td><?php echo htmlspecialchars($delivery['delivery_status']); ?></td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold">Delivery Location:</td>
                                                <td><?php echo htmlspecialchars($delivery['delivery_location']); ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                                <?php if (!empty($delivery['delivery_remarks'])): ?>
                                <div class="mt-3">
                                    <h6 class="text-primary">Delivery Remarks</h6>
                                    <p><?php echo nl2br(htmlspecialchars($delivery['delivery_remarks'])); ?></p>
                                </div>
                                <?php endif; ?>
                                <?php else: ?>
                                <p class="text-muted">No delivery information recorded yet.</p>
                                <?php endif; ?>

                                <!-- Turnover Information -->
                                <?php if ($turnover): ?>
                                <div class="mt-4">
                                    <h6 class="text-primary">Turnover Information</h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <table class="table table-sm">
                                                <tr>
                                                    <td class="fw-bold">Turnover Date:</td>
                                                    <td><?php echo $turnover['turnover_date'] ? date('M d, Y', strtotime($turnover['turnover_date'])) : 'N/A'; ?></td>
                                                </tr>
                                                <tr>
                                                    <td class="fw-bold">Turnover Status:</td>
                                                    <td><?php echo htmlspecialchars($turnover['turnover_status']); ?></td>
                                                </tr>
                                                <tr>
                                                    <td class="fw-bold">Requirements Met:</td>
                                                    <td><?php echo $turnover['documentary_requirements_met'] ? 'Yes' : 'No'; ?></td>
                                                </tr>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- Documents Tab -->
                            <div id="documents" class="tab-pane fade">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="text-primary mb-0">Machinery Documents</h6>
                                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#uploadMachineryDocumentModal">
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
                                    <h6 class="text-primary mb-0">Machinery Milestones</h6>
                                    <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addMachineryMilestoneModal">
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
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload Machinery Document Modal -->
    <div class="modal fade" id="uploadMachineryDocumentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Upload Machinery Document</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="uploadMachineryDocumentForm" enctype="multipart/form-data">
                    <input type="hidden" name="machinery_id" value="<?php echo $id; ?>">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Document Type</label>
                            <select class="form-control" name="doc_type" required>
                                <option value="LGU Deed of Donation">LGU Deed of Donation</option>
                                <option value="Proof of Shed">Proof of Shed</option>
                                <option value="Accreditation Certificate">Accreditation Certificate</option>
                                <option value="Proof of Organization Structure">Proof of Organization Structure</option>
                                <option value="Validation Report">Validation Report</option>
                                <option value="Geotagged Photo">Geotagged Photo</option>
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
                                <option value="Delivered">Delivered</option>
                                <option value="Turned-Over">Turned-Over</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">File</label>
                            <input type="file" class="form-control" name="document_file" required>
                            <small class="text-muted">Max file size: 10MB. Allowed: PDF, DOC, DOCX, JPG, PNG</small>
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

    <!-- Add Machinery Milestone Modal -->
    <div class="modal fade" id="addMachineryMilestoneModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Machinery Milestone</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="addMachineryMilestoneForm">
                    <input type="hidden" name="machinery_id" value="<?php echo $id; ?>">
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
                                <option value="Delivered">Delivered</option>
                                <option value="Turned-Over">Turned-Over</option>
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
    <script src="assets/bootstrap/js/bootstrap.bundle.js"></script>
    <script src="assets/js/app-modal.js"></script>
    <script src="assets/js/afme-machinery-details.js"></script>
    <?php renderAppLayoutFooter(); ?>

