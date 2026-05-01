<?php
require_once __DIR__ . '/components/layout.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/functions/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

requireLogin();

$page_title = 'Projects - ABED IDM Hub';
$page_extra_head = '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />'
    . '<style>#newProjectMap{height:240px;width:100%;border-radius:.375rem;border:1px solid #dee2e6;z-index:0;}</style>';

// Get filter parameters
$project_type = $_GET['type'] ?? 'fspf';
$stage_filter = $_GET['stage'] ?? '';
$search = $_GET['search'] ?? '';
$year = $_GET['year'] ?? date('Y');
$page = (int)($_GET['page'] ?? 1);
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Unified table in refactored schema
$table = 'projects';

// Build query
$query = "SELECT *, title AS project_title FROM $table WHERE project_type = ? AND approval_status = 'Approved' AND (status IS NULL OR status <> 'Archived')";
$count_query = "SELECT COUNT(*) as total FROM $table WHERE project_type = ? AND approval_status = 'Approved' AND (status IS NULL OR status <> 'Archived')";
$params = [];
$types = 's';
$params[] = $project_type;

if ($stage_filter) {
    $query .= " AND current_stage = ?";
    $count_query .= " AND current_stage = ?";
    $params[] = $stage_filter;
    $types .= 's';
}

if ($search) {
    $search_term = "%{$search}%";
    $query .= " AND (project_code LIKE ? OR title LIKE ?)";
    $count_query .= " AND (project_code LIKE ? OR title LIKE ?)";
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= 'ss';
}

if ($year) {
    $query .= " AND YEAR(created_at) = ?";
    $count_query .= " AND YEAR(created_at) = ?";
    $params[] = $year;
    $types .= 'i';
}

// Get total count
$count_stmt = $conn->prepare($count_query);
if ($params) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $per_page);

// Get projects
$query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
$limit_params = $params;
$limit_params[] = $per_page;
$limit_params[] = $offset;
$limit_types = $types . 'ii';

$stmt = $conn->prepare($query);
if ($limit_params) {
    $stmt->bind_param($limit_types, ...$limit_params);
}
$stmt->execute();
$projects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get saved views for current user
$saved_views_query = $conn->prepare("SELECT id, view_name, filters FROM saved_views WHERE user_id = ? AND project_type = ? ORDER BY updated_at DESC");
$saved_views_query->bind_param("is", $_SESSION['user_id'], $project_type);
$saved_views_query->execute();
$saved_views = $saved_views_query->get_result()->fetch_all(MYSQLI_ASSOC);

// Get available stages
$stages_stmt = $conn->prepare("SELECT DISTINCT current_stage FROM $table WHERE project_type = ? AND approval_status = 'Approved' AND (status IS NULL OR status <> 'Archived') ORDER BY current_stage");
$stages_stmt->bind_param('s', $project_type);
$stages_stmt->execute();
$available_stages = $stages_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

renderAppLayout($page_title, $page_extra_head);
?>

        <div class="container-fluid py-4">
            <!-- Header -->
            <div class="row align-items-center mb-4">
                <div class="col">
                    <h1 class="h3 mb-0">Projects</h1>
                    <p class="text-muted">Browse and manage all projects</p>
                </div>
                <div class="col-auto">
                    <button class="btn btn-success me-2" type="button" id="openNewProjectModalBtn" data-bs-toggle="modal" data-bs-target="#newProjectModal">
                        <i class="fas fa-plus"></i> New Project
                    </button>
                    <div class="btn-group" role="group">
                        <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#exportModal">
                            <i class="fas fa-download"></i> Export
                        </button>
                        <button class="btn btn-outline-secondary" id="selectAllBtn">
                            <i class="fas fa-check-square"></i> Select All
                        </button>
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-outline-warning dropdown-toggle" data-bs-toggle="dropdown" id="batchActionsBtn" disabled>
                                <i class="fas fa-tasks"></i> Batch Actions
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#" id="batchExportBtn">
                                    <i class="fas fa-file-export"></i> Export Selected
                                </a></li>
                                <li><a class="dropdown-item" href="#" id="batchUpdateBtn">
                                    <i class="fas fa-edit"></i> Bulk Update
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="#" id="batchDeleteBtn">
                                    <i class="fas fa-trash"></i> Delete Selected
                                </a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <!-- Type Tabs -->
                        <div class="col-12">
                            <div class="btn-group" role="group">
                                <input type="radio" class="btn-check" name="type" id="type_fspf" value="fspf" <?php echo $project_type === 'fspf' ? 'checked' : ''; ?> onchange="this.form.submit()">
                                <label class="btn btn-outline-primary" for="type_fspf">FSPF</label>

                                <input type="radio" class="btn-check" name="type" id="type_idp" value="idp" <?php echo $project_type === 'idp' ? 'checked' : ''; ?> onchange="this.form.submit()">
                                <label class="btn btn-outline-primary" for="type_idp">IDP</label>

                                <input type="radio" class="btn-check" name="type" id="type_afme" value="afme" <?php echo $project_type === 'afme' ? 'checked' : ''; ?> onchange="this.form.submit()">
                                <label class="btn btn-outline-primary" for="type_afme">AFME</label>
                            </div>
                        </div>

                        <!-- Saved Views -->
                        <div class="col-12">
                            <div class="d-flex gap-2 align-items-center">
                                <select id="savedViewsSelect" class="form-select" style="max-width: 250px;">
                                    <option value="">Load Saved View...</option>
                                    <?php foreach ($saved_views as $view): ?>
                                        <option value="<?php echo $view['id']; ?>" data-filters='<?php echo htmlspecialchars($view['filters']); ?>'>
                                            <?php echo htmlspecialchars($view['view_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="btn btn-outline-secondary" id="saveViewBtn" data-bs-toggle="modal" data-bs-target="#saveViewModal">
                                    <i class="fas fa-save"></i> Save View
                                </button>
                                <button type="button" class="btn btn-outline-danger" id="deleteViewBtn" style="display: none;">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </div>
                        </div>

                        <!-- Search -->
                        <div class="col-md-4">
                            <input type="text" name="search" class="form-control" placeholder="Search by code or title" 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>

                        <!-- Stage Filter -->
                        <div class="col-md-3">
                            <select name="stage" class="form-select">
                                <option value="">All Stages</option>
                                <?php foreach ($available_stages as $s): ?>
                                    <option value="<?php echo htmlspecialchars($s['current_stage']); ?>" 
                                            <?php echo $stage_filter === $s['current_stage'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($s['current_stage']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Year Filter -->
                        <div class="col-md-2">
                            <select name="year" class="form-select">
                                <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                                    <option value="<?php echo $y; ?>" <?php echo $year == $y ? 'selected' : ''; ?>>
                                        <?php echo $y; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <!-- Submit -->
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search"></i> Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Results Info -->
            <div class="alert alert-info mb-4">
                Showing <strong><?php echo count($projects); ?></strong> of <strong><?php echo $total_records; ?></strong> 
                <?php echo strtoupper($project_type); ?> projects
                <?php if ($search): ?> matching "<strong><?php echo htmlspecialchars($search); ?></strong>"<?php endif; ?>
            </div>

            <!-- Projects Table -->
            <div class="card border-0 shadow-sm">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th width="40">
                                    <input type="checkbox" class="form-check-input" id="masterCheckbox">
                                </th>
                                <th>Code</th>
                                <th>Title</th>
                                <th>Stage</th>
                                <th>Physical Progress</th>
                                <th>Financial Progress</th>
                                <th>Allocated Amount</th>
                                <th>Modified</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($projects)): ?>
                                <tr>
                                    <td colspan="9" class="text-center py-4 text-muted">No projects found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($projects as $project): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" class="form-check-input row-checkbox" value="<?php echo $project['id']; ?>">
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($project['project_code']); ?></strong>
                                        </td>
                                        <td>
                                            <span title="<?php echo htmlspecialchars($project['project_title']); ?>">
                                                <?php echo htmlspecialchars(substr($project['project_title'], 0, 50)); ?>
                                            </span>
                                        </td>
                                        <td>
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
                                                }; ?>">
                                                <?php echo htmlspecialchars($project['current_stage']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="progress" style="height: 20px; width: 100px;">
                                                <div class="progress-bar" style="width: <?php echo $project['physical_progress']; ?>%; background-color: #0d6efd;">
                                                    <small><?php echo round($project['physical_progress'], 0); ?>%</small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="progress" style="height: 20px; width: 100px;">
                                                <div class="progress-bar bg-success" style="width: <?php echo $project['financial_progress']; ?>%;">
                                                    <small><?php echo round($project['financial_progress'], 0); ?>%</small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            ₱<?php echo number_format($project['allocated_amount'], 0); ?>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?php echo date('M d, Y', strtotime($project['updated_at'])); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="project-detail-enhanced.php?type=<?php echo $project_type; ?>&id=<?php echo $project['id']; ?>" 
                                                   class="btn btn-info" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="scurve-monitoring.php?type=<?php echo $project_type; ?>&id=<?php echo $project['id']; ?>" 
                                                   class="btn btn-secondary" title="S-Curve">
                                                    <i class="fas fa-chart-line"></i>
                                                </a>
                                                <button type="button"
                                                        class="btn btn-danger single-delete-btn"
                                                        data-project-id="<?php echo (int) $project['id']; ?>"
                                                        data-project-code="<?php echo htmlspecialchars($project['project_code'], ENT_QUOTES); ?>"
                                                        title="Delete Project">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="card-footer bg-light">
                        <nav aria-label="Page navigation">
                            <ul class="pagination mb-0">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?type=<?php echo $project_type; ?>&page=1&stage=<?php echo htmlspecialchars($stage_filter); ?>&search=<?php echo htmlspecialchars($search); ?>">First</a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="?type=<?php echo $project_type; ?>&page=<?php echo $page - 1; ?>&stage=<?php echo htmlspecialchars($stage_filter); ?>&search=<?php echo htmlspecialchars($search); ?>">Previous</a>
                                    </li>
                                <?php endif; ?>

                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?type=<?php echo $project_type; ?>&page=<?php echo $i; ?>&stage=<?php echo htmlspecialchars($stage_filter); ?>&search=<?php echo htmlspecialchars($search); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?type=<?php echo $project_type; ?>&page=<?php echo $page + 1; ?>&stage=<?php echo htmlspecialchars($stage_filter); ?>&search=<?php echo htmlspecialchars($search); ?>">Next</a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="?type=<?php echo $project_type; ?>&page=<?php echo $total_pages; ?>&stage=<?php echo htmlspecialchars($stage_filter); ?>&search=<?php echo htmlspecialchars($search); ?>">Last</a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Save View Modal -->
        <div class="modal fade" id="saveViewModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Save Current View</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="viewNameInput" class="form-label">View Name</label>
                            <input type="text" class="form-control" id="viewNameInput" placeholder="Enter a name for this view">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="confirmSaveView">Save View</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bulk Update Modal -->
        <div class="modal fade" id="bulkUpdateModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Bulk Update Projects</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="bulkUpdateForm">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Current Stage</label>
                                        <select class="form-select" name="current_stage">
                                            <option value="">No Change</option>
                                            <option value="Proposal">Proposal</option>
                                            <option value="Pre-Implementation">Pre-Implementation</option>
                                            <option value="Procurement">Procurement</option>
                                            <option value="Implementation">Implementation</option>
                                            <option value="Completed">Completed</option>
                                            <option value="Turned-Over">Turned-Over</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Physical Progress (%)</label>
                                        <input type="number" class="form-control" name="physical_progress" min="0" max="100" placeholder="Leave empty for no change">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Financial Progress (%)</label>
                                        <input type="number" class="form-control" name="financial_progress" min="0" max="100" placeholder="Leave empty for no change">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Allocated Amount</label>
                                        <input type="number" class="form-control" name="allocated_amount" step="0.01" placeholder="Leave empty for no change">
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="confirmBulkUpdate">Update Projects</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- New Project Modal -->
        <div class="modal fade" id="newProjectModal" tabindex="-1">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Register New Project</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="newProjectForm">
                            <!-- Project Type Selection -->
                            <div class="mb-3">
                                <label class="form-label">Project Type <span class="text-danger">*</span></label>
                                <div class="btn-group w-100" role="group">
                                    <input type="radio" class="btn-check" name="project_type" id="type_fspf_form" value="fspf" onclick="updateFormFields()">
                                    <label class="btn btn-outline-primary" for="type_fspf_form">FSPF</label>

                                    <input type="radio" class="btn-check" name="project_type" id="type_idp_form" value="idp" onclick="updateFormFields()">
                                    <label class="btn btn-outline-primary" for="type_idp_form">IDP</label>

                                    <input type="radio" class="btn-check" name="project_type" id="type_afme_form" value="afme" onclick="updateFormFields()">
                                    <label class="btn btn-outline-primary" for="type_afme_form">AFME</label>
                                </div>
                            </div>

                            <div class="row g-3">
                                <!-- Project Code -->
                                <div class="col-md-6">
                                    <label class="form-label">Project Code <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="project_code" placeholder="e.g., FSPF-2026-001" required>
                                </div>

                                <!-- Funding Year -->
                                <div class="col-md-6">
                                    <label class="form-label">Funding Year <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" name="funding_year" value="<?php echo date('Y'); ?>" required>
                                </div>

                                <!-- Project Title -->
                                <div class="col-12">
                                    <label class="form-label">Project Title <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="project_title" placeholder="Enter project title" required>
                                </div>

                                <!-- Location (Philippines) — PSGC-backed cascade -->
                                <div class="col-12">
                                    <label class="form-label" for="locationDropdownBtn">Location <span class="text-muted fw-normal">(Philippines)</span></label>
                                    <div class="dropdown w-100">
                                        <button class="btn btn-outline-secondary dropdown-toggle w-100 text-start d-flex justify-content-between align-items-center" type="button"
                                            id="locationDropdownBtn" data-bs-toggle="dropdown" data-bs-auto-close="outside" data-bs-display="static"
                                            aria-expanded="false" aria-haspopup="true">
                                            <span class="text-truncate me-2" id="locationSummaryText">Click to choose province, city/municipality, barangay…</span>
                                        </button>
                                        <div class="dropdown-menu shadow-sm p-3 w-100 border" style="max-width: 100%; min-width: 280px;" id="locationDropdownPanel" onclick="event.stopPropagation();">
                                            <p class="small text-muted mb-3 mb-md-2">Select in order: province → city or municipality → barangay; then optional purok, zone, or sitio.</p>
                                            <div class="mb-2">
                                                <label class="form-label small mb-0" for="locProvinceSelect">Province</label>
                                                <select class="form-select form-select-sm" id="locProvinceSelect" name="province">
                                                    <option value="">— Select province —</option>
                                                </select>
                                            </div>
                                            <div class="mb-2">
                                                <label class="form-label small mb-0" for="locCitySelect">City / Municipality</label>
                                                <select class="form-select form-select-sm" id="locCitySelect" name="municipality" disabled>
                                                    <option value="">— Select city/municipality —</option>
                                                </select>
                                            </div>
                                            <div class="mb-2">
                                                <label class="form-label small mb-0" for="locBarangaySelect">Barangay</label>
                                                <select class="form-select form-select-sm" id="locBarangaySelect" name="barangay" disabled>
                                                    <option value="">— Select barangay —</option>
                                                </select>
                                            </div>
                                            <div class="mb-0">
                                                <label class="form-label small mb-0" for="locPurokInput">Purok / Zone / Sitio</label>
                                                <input type="text" class="form-control form-control-sm" name="district" id="locPurokInput" placeholder="Optional (e.g. Purok 4, Zone II, Sitio Malinao)" autocomplete="address-level4">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- GeoMap: pin inside Philippines (saved with project) -->
                                <div class="col-12">
                                    <label class="form-label">Map location <span class="text-danger">*</span> <span class="text-muted fw-normal">(GeoMap / legend)</span></label>
                                    <p class="small text-muted mb-2">Click the map to drop a pin. It is stored as latitude and longitude and shown on <a href="geomap.php" target="_blank" rel="noopener">GeoMap</a> by project type.</p>
                                    <input type="hidden" name="latitude" id="newProjectLatitude" value="">
                                    <input type="hidden" name="longitude" id="newProjectLongitude" value="">
                                    <div id="newProjectMap" role="application" aria-label="Philippines map, click to set coordinates"></div>
                                </div>

                                <!-- Fund Source -->
                                <div class="col-md-6">
                                    <label class="form-label">Fund Source <span class="text-danger">*</span></label>
                                    <select class="form-select" name="fund_source" required>
                                        <option value="">Select fund source</option>
                                        <option value="National Government">National Government</option>
                                        <option value="Local Government Unit">Local Government Unit</option>
                                        <option value="Private">Private</option>
                                        <option value="Donors">Donors</option>
                                    </select>
                                </div>

                                <!-- Beneficiary -->
                                <div class="col-md-6">
                                    <label class="form-label">Beneficiary</label>
                                    <input type="text" class="form-control" name="beneficiary" placeholder="Enter beneficiary name">
                                </div>

                                <!-- Scope of Work (FSPF/IDP only) -->
                                <div class="col-md-6" id="scopeOfWorkField" style="display: none;">
                                    <label class="form-label">Scope of Work</label>
                                    <select class="form-select" name="scope_of_work">
                                        <option value="">Select scope</option>
                                        <option value="Construction">Construction</option>
                                        <option value="Rehabilitation">Rehabilitation</option>
                                        <option value="Upgrading">Upgrading</option>
                                        <option value="Additional Work">Additional Work</option>
                                    </select>
                                </div>

                                <!-- Proposed Amount -->
                                <div class="col-md-6">
                                    <label class="form-label">Proposed Amount (₱)</label>
                                    <input type="number" class="form-control" name="proposed_amount" placeholder="0.00" step="0.01" value="0">
                                </div>

                                <!-- Allocated Amount -->
                                <div class="col-md-6">
                                    <label class="form-label">Allocated Amount (₱)</label>
                                    <input type="number" class="form-control" name="allocated_amount" placeholder="0.00" step="0.01" value="0">
                                </div>

                                <!-- Description -->
                                <div class="col-12">
                                    <label class="form-label">Project Description</label>
                                    <textarea class="form-control" name="description" rows="3" placeholder="Enter project description"></textarea>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="submitNewProjectBtn" onclick="submitNewProject()">
                            <i class="fas fa-save me-2"></i>Register Project
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Export Modal -->
        <div class="modal fade" id="exportModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Export Projects</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2">Choose what to export for <strong><?php echo strtoupper($project_type); ?></strong>.</p>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="exportScope" id="exportAllScope" value="all" checked>
                            <label class="form-check-label" for="exportAllScope">All filtered rows on this tab</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="exportScope" id="exportSelectedScope" value="selected">
                            <label class="form-check-label" for="exportSelectedScope">Only selected rows</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="confirmExportBtn">Export CSV</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div class="modal fade" id="deleteProjectModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Delete Project</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2" id="deleteProjectSummaryText"></p>
                        <p class="small text-muted mb-3">This action archives the project and requires your account password.</p>
                        <div class="mb-0">
                            <label for="deletePasswordInput" class="form-label">Confirm password</label>
                            <input type="password" class="form-control" id="deletePasswordInput" autocomplete="current-password" placeholder="Enter your password">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger" id="confirmDeleteProjectBtn">
                            <i class="fas fa-trash me-1"></i>Delete
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <script src="assets/bootstrap/js/bootstrap.bundle.min.js"></script>
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <script>
            const currentProjectType = '<?php echo $project_type; ?>';

            const PH_REG_BOUNDS = L.latLngBounds([4.2, 116.0], [21.7, 127.6]);
            const PH_REG_CENTER = [12.5, 122.5];
            let newProjectRegMap = null;
            let newProjectRegMarker = null;

            function destroyNewProjectRegMap() {
                if (newProjectRegMap) {
                    newProjectRegMap.remove();
                    newProjectRegMap = null;
                    newProjectRegMarker = null;
                }
            }

            function syncNewProjectLatLngInputs(lat, lng) {
                const la = document.getElementById('newProjectLatitude');
                const lo = document.getElementById('newProjectLongitude');
                if (la) la.value = String(lat);
                if (lo) lo.value = String(lng);
            }

            function initNewProjectRegMap() {
                const el = document.getElementById('newProjectMap');
                if (!el || typeof L === 'undefined') return;
                destroyNewProjectRegMap();
                syncNewProjectLatLngInputs('', '');
                newProjectRegMap = L.map(el, {
                    maxBounds: PH_REG_BOUNDS.pad(0.1),
                    minZoom: 5,
                    maxZoom: 18,
                    maxBoundsViscosity: 0.9
                }).setView(PH_REG_CENTER, 6);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap'
                }).addTo(newProjectRegMap);
                newProjectRegMap.on('click', function (e) {
                    const ll = e.latlng;
                    if (!PH_REG_BOUNDS.contains(ll)) return;
                    if (!newProjectRegMarker) {
                        newProjectRegMarker = L.marker(ll, { draggable: true }).addTo(newProjectRegMap);
                        newProjectRegMarker.on('dragend', function (ev) {
                            const p = ev.target.getLatLng();
                            syncNewProjectLatLngInputs(p.lat, p.lng);
                        });
                    } else {
                        newProjectRegMarker.setLatLng(ll);
                    }
                    syncNewProjectLatLngInputs(ll.lat, ll.lng);
                });
                setTimeout(function () {
                    if (newProjectRegMap) newProjectRegMap.invalidateSize();
                }, 200);
            }

            function validateNewProjectMapPin() {
                const la = document.getElementById('newProjectLatitude');
                const lo = document.getElementById('newProjectLongitude');
                const lat = parseFloat(la && la.value ? la.value : '');
                const lng = parseFloat(lo && lo.value ? lo.value : '');
                if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
                    alert('Please click the map to set a location pin (Philippines). It is required for GeoMap.');
                    return false;
                }
                if (lat < PH_REG_BOUNDS.getSouth() || lat > PH_REG_BOUNDS.getNorth()
                    || lng < PH_REG_BOUNDS.getWest() || lng > PH_REG_BOUNDS.getEast()) {
                    alert('The pin must be inside the Philippines.');
                    return false;
                }
                return true;
            }

            function syncNewProjectTypeWithActiveTab() {
                const current = (new URLSearchParams(window.location.search).get('type') || currentProjectType || 'fspf').toLowerCase();
                const radio = document.querySelector(`input[name="project_type"][value="${current}"]`);
                if (radio) {
                    radio.checked = true;
                }
                updateFormFields();
            }

            // Global function for submitting new project
            async function submitNewProject() {
                const form = document.getElementById('newProjectForm');
                const submitBtn = document.getElementById('submitNewProjectBtn');
                const projectType = document.querySelector('input[name="project_type"]:checked').value;

                // Validate form
                if (!form.checkValidity()) {
                    form.reportValidity();
                    return;
                }

                if (!validateNewProjectMapPin()) {
                    return;
                }

                if (submitBtn && submitBtn.disabled) {
                    return;
                }

                const formData = new FormData(form);
                const handler = 'register-' + projectType + '-project.php';
                const originalBtnHtml = submitBtn ? submitBtn.innerHTML : '';

                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Submitting...';
                }

                try {
                    const response = await fetch(handler, {
                        method: 'POST',
                        body: formData
                    });

                    const raw = await response.text();
                    let data = null;
                    try {
                        data = JSON.parse(raw);
                    } catch (parseError) {
                        throw new Error('Unexpected server response. Please check server logs.');
                    }

                    console.log('Response data:', data);
                    if (data.status === 'success') {
                        alert(data.message);
                        form.reset();
                        updateFormFields();
                        const modal = bootstrap.Modal.getInstance(document.getElementById('newProjectModal'));
                        if (modal) modal.hide();
                        setTimeout(() => location.reload(), 500);
                    } else {
                        alert('Error: ' + (data.message || 'Failed to register project'));
                    }
                } catch (error) {
                    console.error('Fetch error:', error);
                    alert('An error occurred: ' + error.message);
                } finally {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalBtnHtml;
                    }
                }
            }

            function updateFormFields() {
                const selectedType = document.querySelector('input[name="project_type"]:checked');
                const scopeField = document.getElementById('scopeOfWorkField');
                if (!scopeField || !selectedType) return;
                scopeField.style.display = (selectedType.value === 'fspf' || selectedType.value === 'idp') ? '' : 'none';
            }

            /** Philippine location (PSGC public API) for Register New Project */
            const PSGC_API_BASE = 'https://psgc.gitlab.io/api';
            let phProvincesLoaded = false;

            function phLoc$(id) {
                return document.getElementById(id);
            }

            function updateLocationSummary() {
                const summary = phLoc$('locationSummaryText');
                if (!summary) return;
                const p = phLoc$('locProvinceSelect')?.value?.trim() || '';
                const c = phLoc$('locCitySelect')?.value?.trim() || '';
                const b = phLoc$('locBarangaySelect')?.value?.trim() || '';
                const z = phLoc$('locPurokInput')?.value?.trim() || '';
                if (!p && !c && !b && !z) {
                    summary.textContent = 'Click to choose province, city/municipality, barangay…';
                    summary.classList.add('text-muted');
                    return;
                }
                summary.classList.remove('text-muted');
                const parts = [p, c, b].filter(Boolean);
                if (z) parts.push(z);
                summary.textContent = parts.join(' · ');
            }

            function phLocSelectedCode(selectEl) {
                const opt = selectEl && selectEl.selectedOptions && selectEl.selectedOptions[0];
                return (opt && opt.dataset && opt.dataset.code) ? String(opt.dataset.code) : '';
            }

            function loadPhProvinceOptions() {
                const sel = phLoc$('locProvinceSelect');
                if (!sel || phProvincesLoaded) {
                    return Promise.resolve();
                }
                return fetch(PSGC_API_BASE + '/provinces')
                    .then(function (res) {
                        if (!res.ok) throw new Error('provinces');
                        return res.json();
                    })
                    .then(function (rows) {
                        rows.sort(function (a, b) { return a.name.localeCompare(b.name); });
                        sel.innerHTML = '<option value="">— Select province —</option>';
                        rows.forEach(function (row) {
                            const opt = new Option(row.name, row.name);
                            opt.dataset.code = row.code;
                            sel.add(opt);
                        });
                        phProvincesLoaded = true;
                    })
                    .catch(function (err) {
                        console.error(err);
                        sel.innerHTML = '<option value="">Unable to load provinces (network)</option>';
                    });
            }

            async function onPhProvinceChange() {
                const pSel = phLoc$('locProvinceSelect');
                const cSel = phLoc$('locCitySelect');
                const bSel = phLoc$('locBarangaySelect');
                const code = phLocSelectedCode(pSel);
                cSel.innerHTML = '<option value="">— Select city/municipality —</option>';
                bSel.innerHTML = '<option value="">— Select barangay —</option>';
                bSel.disabled = true;
                if (!code) {
                    cSel.disabled = true;
                    updateLocationSummary();
                    return;
                }
                cSel.disabled = false;
                cSel.options[0].textContent = 'Loading…';
                try {
                    const res = await fetch(PSGC_API_BASE + '/provinces/' + encodeURIComponent(code) + '/cities-municipalities');
                    if (!res.ok) throw new Error('cities-municipalities');
                    const rows = await res.json();
                    rows.sort(function (a, b) { return a.name.localeCompare(b.name); });
                    cSel.innerHTML = '<option value="">— Select city/municipality —</option>';
                    rows.forEach(function (row) {
                        const opt = new Option(row.name, row.name);
                        opt.dataset.code = row.code;
                        cSel.add(opt);
                    });
                } catch (err) {
                    console.error(err);
                    cSel.innerHTML = '<option value="">Could not load cities/municipalities</option>';
                }
                updateLocationSummary();
            }

            async function onPhCityChange() {
                const cSel = phLoc$('locCitySelect');
                const bSel = phLoc$('locBarangaySelect');
                const code = phLocSelectedCode(cSel);
                bSel.innerHTML = '<option value="">— Select barangay —</option>';
                if (!code) {
                    bSel.disabled = true;
                    updateLocationSummary();
                    return;
                }
                bSel.disabled = false;
                bSel.options[0].textContent = 'Loading…';
                try {
                    const res = await fetch(PSGC_API_BASE + '/cities-municipalities/' + encodeURIComponent(code) + '/barangays');
                    if (!res.ok) throw new Error('barangays');
                    const rows = await res.json();
                    rows.sort(function (a, b) { return a.name.localeCompare(b.name); });
                    bSel.innerHTML = '<option value="">— Select barangay —</option>';
                    rows.forEach(function (row) {
                        bSel.add(new Option(row.name, row.name));
                    });
                } catch (err) {
                    console.error(err);
                    bSel.innerHTML = '<option value="">Could not load barangays</option>';
                }
                updateLocationSummary();
            }

            function resetNewProjectLocationUI() {
                const cSel = phLoc$('locCitySelect');
                const bSel = phLoc$('locBarangaySelect');
                const pIn = phLoc$('locPurokInput');
                const pSel = phLoc$('locProvinceSelect');
                if (pIn) pIn.value = '';
                if (cSel) {
                    cSel.innerHTML = '<option value="">— Select city/municipality —</option>';
                    cSel.disabled = true;
                }
                if (bSel) {
                    bSel.innerHTML = '<option value="">— Select barangay —</option>';
                    bSel.disabled = true;
                }
                if (pSel) pSel.value = '';
                syncNewProjectLatLngInputs('', '');
                loadPhProvinceOptions().then(updateLocationSummary);
            }

            document.addEventListener('DOMContentLoaded', function() {
                // Move modals to <body> so Bootstrap backdrop stacking works reliably.
                ['saveViewModal', 'bulkUpdateModal', 'newProjectModal', 'exportModal', 'deleteProjectModal'].forEach(function (id) {
                    const el = document.getElementById(id);
                    if (el && el.parentElement !== document.body) {
                        document.body.appendChild(el);
                    }
                });
                
                // Saved Views - only if element exists
                const savedViewsSelect = document.getElementById('savedViewsSelect');
                if (savedViewsSelect) {
                    savedViewsSelect.addEventListener('change', function() {
                        const selectedOption = this.options[this.selectedIndex];
                        if (selectedOption.value) {
                            const filters = JSON.parse(selectedOption.getAttribute('data-filters'));
                            if (filters.search) document.querySelector('input[name="search"]').value = filters.search;
                            if (filters.stage) document.querySelector('select[name="stage"]').value = filters.stage;
                            if (filters.year) document.querySelector('select[name="year"]').value = filters.year;
                            document.querySelector('form').submit();
                        }
                    });
                }

                // Save View Button
                const saveViewBtn = document.getElementById('saveViewBtn');
                if (saveViewBtn) {
                    saveViewBtn.addEventListener('click', function() {
                        const search = document.querySelector('input[name="search"]').value;
                        const stage = document.querySelector('select[name="stage"]').value;
                        const year = document.querySelector('select[name="year"]').value;
                        window.currentFilters = { search, stage, year };
                    });
                }

                // Confirm Save View
                const confirmSaveView = document.getElementById('confirmSaveView');
                if (confirmSaveView) {
                    confirmSaveView.addEventListener('click', function() {
                        const viewName = document.getElementById('viewNameInput').value.trim();
                        if (!viewName) {
                            alert('Please enter a view name');
                            return;
                        }

                        fetch('api/saved-views.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                action: 'save',
                                view_name: viewName,
                                project_type: '<?php echo $project_type; ?>',
                                filters: window.currentFilters
                            })
                        })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                alert('View saved successfully!');
                                location.reload();
                            } else {
                                alert('Error: ' + data.message);
                            }
                        });

                        bootstrap.Modal.getInstance(document.getElementById('saveViewModal')).hide();
                    });
                }

                // Delete View Button
                const deleteViewBtn = document.getElementById('deleteViewBtn');
                if (deleteViewBtn && savedViewsSelect) {
                    savedViewsSelect.addEventListener('change', function() {
                        if (this.value) {
                            deleteViewBtn.style.display = 'inline-block';
                            deleteViewBtn.onclick = function() {
                                if (confirm('Delete this saved view?')) {
                                    fetch('api/saved-views.php', {
                                        method: 'POST',
                                        headers: { 'Content-Type': 'application/json' },
                                        body: JSON.stringify({
                                            action: 'delete',
                                            view_id: savedViewsSelect.value
                                        })
                                    })
                                    .then(r => r.json())
                                    .then(data => {
                                        if (data.success) {
                                            alert('View deleted!');
                                            location.reload();
                                        }
                                    });
                                }
                            };
                        } else {
                            deleteViewBtn.style.display = 'none';
                        }
                    });
                }

                // Batch Operations
                const masterCheckbox = document.getElementById('masterCheckbox');
                const rowCheckboxes = document.querySelectorAll('.row-checkbox');
                const batchActionsBtn = document.getElementById('batchActionsBtn');
                const selectAllBtn = document.getElementById('selectAllBtn');
                const batchExportBtn = document.getElementById('batchExportBtn');
                const batchDeleteBtn = document.getElementById('batchDeleteBtn');
                const batchUpdateBtn = document.getElementById('batchUpdateBtn');
                const confirmBulkUpdate = document.getElementById('confirmBulkUpdate');
                const confirmExportBtn = document.getElementById('confirmExportBtn');
                const deleteProjectModalEl = document.getElementById('deleteProjectModal');
                const deletePasswordInput = document.getElementById('deletePasswordInput');
                const confirmDeleteProjectBtn = document.getElementById('confirmDeleteProjectBtn');
                const deleteProjectSummaryText = document.getElementById('deleteProjectSummaryText');
                const singleDeleteButtons = document.querySelectorAll('.single-delete-btn');
                let pendingDeleteIds = [];

                function updateBatchActionsState() {
                    const checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
                    batchActionsBtn.disabled = checkedBoxes.length === 0;
                }

                function openDeleteModal(ids, label) {
                    pendingDeleteIds = Array.isArray(ids) ? ids : [];
                    if (pendingDeleteIds.length === 0 || !deleteProjectModalEl) {
                        return;
                    }
                    if (deleteProjectSummaryText) {
                        deleteProjectSummaryText.textContent = label;
                    }
                    if (deletePasswordInput) {
                        deletePasswordInput.value = '';
                    }
                    const modal = bootstrap.Modal.getOrCreateInstance(deleteProjectModalEl);
                    modal.show();
                    if (deletePasswordInput) {
                        setTimeout(() => deletePasswordInput.focus(), 120);
                    }
                }

                async function submitDeleteWithPassword() {
                    if (!pendingDeleteIds.length) return;
                    const password = (deletePasswordInput?.value || '').trim();
                    if (!password) {
                        alert('Password is required.');
                        if (deletePasswordInput) deletePasswordInput.focus();
                        return;
                    }
                    if (confirmDeleteProjectBtn?.disabled) {
                        return;
                    }

                    const originalHtml = confirmDeleteProjectBtn.innerHTML;
                    confirmDeleteProjectBtn.disabled = true;
                    confirmDeleteProjectBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Deleting...';

                    try {
                        const response = await fetch('api/batch.php?action=delete-projects', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                project_type: '<?php echo $project_type; ?>',
                                ids: pendingDeleteIds,
                                password: password
                            })
                        });
                        const data = await response.json();
                        if (data.status === 'success') {
                            alert(`Deleted ${data.data.deleted_count} project(s).`);
                            location.reload();
                            return;
                        }
                        alert('Error: ' + (data.message || 'Unknown error'));
                    } catch (error) {
                        alert('Failed to delete project(s): ' + error.message);
                    } finally {
                        confirmDeleteProjectBtn.disabled = false;
                        confirmDeleteProjectBtn.innerHTML = originalHtml;
                    }
                }

                if (masterCheckbox) {
                    masterCheckbox.addEventListener('change', function() {
                        rowCheckboxes.forEach(cb => cb.checked = this.checked);
                        updateBatchActionsState();
                    });
                }

                rowCheckboxes.forEach(checkbox => {
                    checkbox.addEventListener('change', function() {
                        const allChecked = Array.from(rowCheckboxes).every(cb => cb.checked);
                        const someChecked = Array.from(rowCheckboxes).some(cb => cb.checked);
                        if (masterCheckbox) {
                            masterCheckbox.checked = allChecked;
                            masterCheckbox.indeterminate = someChecked && !allChecked;
                        }
                        updateBatchActionsState();
                    });
                });

                if (selectAllBtn) {
                    selectAllBtn.addEventListener('click', function() {
                        const allChecked = Array.from(rowCheckboxes).every(cb => cb.checked);
                        const newState = !allChecked;
                        if (masterCheckbox) masterCheckbox.checked = newState;
                        rowCheckboxes.forEach(cb => cb.checked = newState);
                        updateBatchActionsState();
                        this.innerHTML = newState ? '<i class="fas fa-square"></i> Deselect All' : '<i class="fas fa-check-square"></i> Select All';
                    });
                }

                // Batch Export
                if (batchExportBtn) {
                    batchExportBtn.addEventListener('click', function() {
                        const selectedIds = Array.from(document.querySelectorAll('.row-checkbox:checked')).map(cb => cb.value);
                        if (selectedIds.length === 0) return;
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = 'api/export-projects.php';
                        form.appendChild(Object.assign(document.createElement('input'), {type: 'hidden', name: 'type', value: '<?php echo $project_type; ?>'}));
                        form.appendChild(Object.assign(document.createElement('input'), {type: 'hidden', name: 'selected_ids', value: JSON.stringify(selectedIds)}));
                        document.body.appendChild(form);
                        form.submit();
                        document.body.removeChild(form);
                    });
                }

                // Batch Delete
                if (batchDeleteBtn) {
                    batchDeleteBtn.addEventListener('click', function() {
                        const selectedIds = Array.from(document.querySelectorAll('.row-checkbox:checked')).map(cb => cb.value);
                        if (selectedIds.length === 0) return;
                        openDeleteModal(selectedIds, `Delete ${selectedIds.length} selected project(s)?`);
                    });
                }

                singleDeleteButtons.forEach((btn) => {
                    btn.addEventListener('click', function () {
                        const id = this.getAttribute('data-project-id');
                        const code = this.getAttribute('data-project-code') || 'this project';
                        if (!id) return;
                        openDeleteModal([id], `Delete project ${code}?`);
                    });
                });

                if (confirmDeleteProjectBtn) {
                    confirmDeleteProjectBtn.addEventListener('click', submitDeleteWithPassword);
                }
                if (deletePasswordInput) {
                    deletePasswordInput.addEventListener('keydown', function (event) {
                        if (event.key === 'Enter') {
                            event.preventDefault();
                            submitDeleteWithPassword();
                        }
                    });
                }

                // Batch Update
                if (batchUpdateBtn) {
                    batchUpdateBtn.addEventListener('click', function() {
                        const selectedIds = Array.from(document.querySelectorAll('.row-checkbox:checked')).map(cb => cb.value);
                        if (selectedIds.length === 0) return;
                        window.selectedIdsForUpdate = selectedIds;
                        new bootstrap.Modal(document.getElementById('bulkUpdateModal')).show();
                    });
                }

                if (confirmBulkUpdate) {
                    confirmBulkUpdate.addEventListener('click', function() {
                        const formData = new FormData(document.getElementById('bulkUpdateForm'));
                        const updates = {};
                        for (let [key, value] of formData.entries()) {
                            if (value.trim() !== '') {
                                updates[key] = key.includes('progress') || key.includes('amount') ? parseFloat(value) : value;
                            }
                        }
                        if (Object.keys(updates).length === 0) {
                            alert('Specify at least one field to update');
                            return;
                        }
                        fetch('api/batch.php?action=bulk-update', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                project_type: '<?php echo $project_type; ?>',
                                ids: window.selectedIdsForUpdate,
                                updates: updates
                            })
                        })
                        .then(r => r.json())
                        .then(data => {
                            if (data.status === 'success') {
                                alert(`Updated ${data.data.updated_count} projects`);
                                location.reload();
                            }
                        });
                        bootstrap.Modal.getInstance(document.getElementById('bulkUpdateModal')).hide();
                    });
                }

                // Reset form on modal open
                const newProjectModal = document.getElementById('newProjectModal');
                if (newProjectModal) {
                    newProjectModal.addEventListener('show.bs.modal', function() {
                        destroyNewProjectRegMap();
                        const form = document.getElementById('newProjectForm');
                        if (form) form.reset();
                        const yearInput = document.querySelector('input[name="funding_year"]');
                        if (yearInput) yearInput.value = new Date().getFullYear();
                        syncNewProjectTypeWithActiveTab();
                        resetNewProjectLocationUI();
                    });
                    newProjectModal.addEventListener('shown.bs.modal', function() {
                        initNewProjectRegMap();
                    });
                    newProjectModal.addEventListener('hidden.bs.modal', function() {
                        destroyNewProjectRegMap();
                    });
                }

                const locProv = phLoc$('locProvinceSelect');
                const locCity = phLoc$('locCitySelect');
                const locBrgy = phLoc$('locBarangaySelect');
                const locPurok = phLoc$('locPurokInput');
                if (locProv) locProv.addEventListener('change', onPhProvinceChange);
                if (locCity) locCity.addEventListener('change', onPhCityChange);
                if (locBrgy) locBrgy.addEventListener('change', updateLocationSummary);
                if (locPurok) locPurok.addEventListener('input', updateLocationSummary);
                loadPhProvinceOptions();

                if (confirmExportBtn) {
                    confirmExportBtn.addEventListener('click', function () {
                        const scope = document.querySelector('input[name="exportScope"]:checked')?.value || 'all';
                        const selectedIds = Array.from(document.querySelectorAll('.row-checkbox:checked')).map(cb => cb.value);
                        if (scope === 'selected' && selectedIds.length === 0) {
                            alert('Select at least one row before exporting selected items.');
                            return;
                        }
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = 'api/export-projects.php';
                        form.appendChild(Object.assign(document.createElement('input'), {type: 'hidden', name: 'type', value: currentProjectType}));
                        if (scope === 'selected') {
                            form.appendChild(Object.assign(document.createElement('input'), {type: 'hidden', name: 'selected_ids', value: JSON.stringify(selectedIds)}));
                        }
                        document.body.appendChild(form);
                        form.submit();
                        document.body.removeChild(form);
                        const exportModal = bootstrap.Modal.getInstance(document.getElementById('exportModal'));
                        if (exportModal) exportModal.hide();
                    });
                }

                document.querySelectorAll('input[name="project_type"]').forEach(function (radio) {
                    radio.addEventListener('change', updateFormFields);
                });

                const newProjectForm = document.getElementById('newProjectForm');
                if (newProjectForm) {
                    newProjectForm.addEventListener('submit', function (event) {
                        event.preventDefault();
                        submitNewProject();
                    });
                }

                syncNewProjectTypeWithActiveTab();

            }); // End DOMContentLoaded
        </script>

<?php
renderAppLayoutFooter();
?>