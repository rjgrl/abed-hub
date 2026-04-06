<?php
session_start();
require_once 'config/database.php';

$page_title = 'Dashboard';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'viewer';

// Get user information
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Get project statistics
$stats = [];

// FSPF Statistics
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN current_stage = 'Completed' THEN 1 ELSE 0 END) as completed,
        SUM(physical_progress) / COUNT(*) as avg_progress
    FROM fspf_projects
");
$stmt->execute();
$stats['fspf'] = $stmt->get_result()->fetch_assoc();

// IDP Statistics
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN current_stage = 'Completed' THEN 1 ELSE 0 END) as completed,
        SUM(physical_progress) / COUNT(*) as avg_progress
    FROM idp_projects
");
$stmt->execute();
$stats['idp'] = $stmt->get_result()->fetch_assoc();

// AFME Statistics
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN current_status = 'Turned-Over' THEN 1 ELSE 0 END) as delivered,
        SUM(CASE WHEN current_status = 'Operational' THEN 1 ELSE 0 END) as operational
    FROM afme_machinery
");
$stmt->execute();
$stats['afme'] = $stmt->get_result()->fetch_assoc();

// Get recent projects
$recent_projects = [];

// Recent FSPF
$stmt = $conn->prepare("
    SELECT 'FSPF' as type, id, project_code, project_title, current_stage as status, 
           physical_progress, allocated_amount, updated_at
    FROM fspf_projects
    ORDER BY updated_at DESC
    LIMIT 5
");
$stmt->execute();
$recent_projects['fspf'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Recent IDP
$stmt = $conn->prepare("
    SELECT 'IDP' as type, id, project_code, project_title, current_stage as status,
           physical_progress, allocated_amount, updated_at
    FROM idp_projects
    ORDER BY updated_at DESC
    LIMIT 5
");
$stmt->execute();
$recent_projects['idp'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Recent AFME
$stmt = $conn->prepare("
    SELECT 'AFME' as type, id, machine_name as project_title, current_status as status,
           allocated_amount, updated_at
    FROM afme_machinery
    ORDER BY updated_at DESC
    LIMIT 5
");
$stmt->execute();
$recent_projects['afme'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get project status breakdown
$stmt = $conn->prepare("
    SELECT current_stage, COUNT(*) as count FROM fspf_projects GROUP BY current_stage
    UNION ALL
    SELECT current_stage, COUNT(*) as count FROM idp_projects GROUP BY current_stage
");
$stmt->execute();
$status_breakdown = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>
<?php
require_once __DIR__ . '/components/layout.php';
renderAppLayout($page_title);
?>

        <div class="container-fluid">
      <!-- Welcome Section -->
      <div class="row mb-4">
        <div class="col-md-12">
          <div class="card bg-gradient" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <div class="card-body dashboard-welcome-text">
              <h4 class="card-title"><?php echo htmlspecialchars($page_title ?? 'Dashboard'); ?> - Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h4>
              <p class="card-text mb-0">ABED IDM Hub - Malaybalay City Infrastructure Development Management System</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Statistics Cards -->
      <div class="row mb-4">
        <!-- FSPF Card -->
        <div class="col-md-3 mb-3">
          <div class="card border-left-primary">
            <div class="card-body">
              <div class="d-flex align-items-center">
                <div class="flex-grow-1">
                  <h6 class="text-primary text-uppercase mb-1">FSPF Projects</h6>
                  <h3 class="mb-0"><?php echo $stats['fspf']['total'] ?? 0; ?></h3>
                  <small class="text-muted">
                    <?php echo $stats['fspf']['completed'] ?? 0; ?> Completed
                  </small>
                </div>
                <div class="text-primary" style="font-size: 2rem;">
                  <i class="fas fa-tractor"></i>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- IDP Card -->
        <div class="col-md-3 mb-3">
          <div class="card border-left-success">
            <div class="card-body">
              <div class="d-flex align-items-center">
                <div class="flex-grow-1">
                  <h6 class="text-success text-uppercase mb-1">IDP Projects</h6>
                  <h3 class="mb-0"><?php echo $stats['idp']['total'] ?? 0; ?></h3>
                  <small class="text-muted">
                    <?php echo $stats['idp']['completed'] ?? 0; ?> Completed
                  </small>
                </div>
                <div class="text-success" style="font-size: 2rem;">
                  <i class="fas fa-water"></i>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- AFME Card -->
        <div class="col-md-3 mb-3">
          <div class="card border-left-warning">
            <div class="card-body">
              <div class="d-flex align-items-center">
                <div class="flex-grow-1">
                  <h6 class="text-warning text-uppercase mb-1">AFME Machinery</h6>
                  <h3 class="mb-0"><?php echo $stats['afme']['total'] ?? 0; ?></h3>
                  <small class="text-muted">
                    <?php echo $stats['afme']['delivered'] ?? 0; ?> Turned-Over
                  </small>
                </div>
                <div class="text-warning" style="font-size: 2rem;">
                  <i class="fas fa-cog"></i>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- GeoMap Card -->
        <div class="col-md-3 mb-3">
          <div class="card border-left-info">
            <div class="card-body">
              <div class="d-flex align-items-center">
                <div class="flex-grow-1">
                  <h6 class="text-info text-uppercase mb-1">GeoMap</h6>
                  <p class="mb-0">View all projects on map</p>
                </div>
                <div>
                  <a href="geomap.php" class="btn btn-sm btn-info">
                    <i class="fas fa-map"></i>
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Quick Actions -->
      <div class="row mb-4">
        <div class="col-md-12">
          <div class="card">
            <div class="card-header bg-secondary text-white">
              <h5 class="mb-0">Quick Actions</h5>
            </div>
            <div class="card-body">
              <div class="d-grid gap-2 d-md-flex flex-wrap">
                <button id="quickRegisterFspf" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#registerFspfModal">
                  <i class="fas fa-plus me-2"></i>Register FSPF Project
                </button>
                <button id="quickRegisterIdp" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#registerIdpModal">
                  <i class="fas fa-plus me-2"></i>Register IDP Project
                </button>
                <button id="quickRegisterAfme" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#registerAfmeModal">
                  <i class="fas fa-plus me-2"></i>Add AFME Machinery
                </button>
                <a href="analytics.php" class="btn btn-info">
                  <i class="fas fa-chart-line me-2"></i>View Analytics
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Recent Projects Tabs -->
      <div class="row">
        <div class="col-md-12">
          <div class="card">
            <div class="card-header">
              <ul class="nav nav-tabs card-header-tabs" role="tablist">
                <li class="nav-item">
                  <a class="nav-link active" data-bs-toggle="tab" href="#recentFspf">Recent FSPF Projects</a>
                </li>
                <li class="nav-item">
                  <a class="nav-link" data-bs-toggle="tab" href="#recentIdp">Recent IDP Projects</a>
                </li>
                <li class="nav-item">
                  <a class="nav-link" data-bs-toggle="tab" href="#recentAfme">Recent AFME Machinery</a>
                </li>
              </ul>
            </div>
            <div class="card-body">
              <div class="tab-content">
                <!-- FSPF Recent Projects -->
                <div id="recentFspf" class="tab-pane fade show active">
                  <div class="table-responsive">
                    <table class="table table-hover">
                      <thead class="table-light">
                        <tr>
                          <th>Project Code</th>
                          <th>Project Title</th>
                          <th>Status</th>
                          <th>Progress</th>
                          <th>Budget</th>
                          <th>Last Updated</th>
                          <th>Action</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($recent_projects['fspf'] as $project): ?>
                        <tr>
                          <td><span class="badge bg-primary"><?php echo htmlspecialchars($project['project_code']); ?></span></td>
                          <td><?php echo htmlspecialchars($project['project_title']); ?></td>
                          <td>
                            <span class="badge bg-<?php 
                              echo match($project['status']) {
                                'Proposal' => 'warning',
                                'Pre-Implementation' => 'info',
                                'Procurement' => 'secondary',
                                'Implementation' => 'success',
                                'Completed' => 'success',
                                default => 'secondary'
                              };
                            ?>">
                              <?php echo htmlspecialchars($project['status']); ?>
                            </span>
                          </td>
                          <td>
                            <div class="progress" style="height: 20px;">
                              <div class="progress-bar" role="progressbar" 
                                   style="width: <?php echo $project['physical_progress'] ?? 0; ?>%"
                                   aria-valuenow="<?php echo $project['physical_progress'] ?? 0; ?>" 
                                   aria-valuemin="0" aria-valuemax="100">
                                <?php echo $project['physical_progress'] ?? 0; ?>%
                              </div>
                            </div>
                          </td>
                          <td>₱<?php echo number_format($project['allocated_amount'] ?? 0, 2); ?></td>
                          <td><?php echo date('M d, Y', strtotime($project['updated_at'])); ?></td>
                          <td>
                            <a href="project-details.php?type=FSPF&id=<?php echo $project['id']; ?>" 
                               class="btn btn-sm btn-outline-primary">
                              <i class="fas fa-eye"></i>
                            </a>
                          </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($recent_projects['fspf'])): ?>
                        <tr>
                          <td colspan="7" class="text-center text-muted">No FSPF projects yet</td>
                        </tr>
                        <?php endif; ?>
                      </tbody>
                    </table>
                  </div>
                </div>

                <!-- IDP Recent Projects -->
                <div id="recentIdp" class="tab-pane fade">
                  <div class="table-responsive">
                    <table class="table table-hover">
                      <thead class="table-light">
                        <tr>
                          <th>Project Code</th>
                          <th>Project Title</th>
                          <th>Status</th>
                          <th>Progress</th>
                          <th>Budget</th>
                          <th>Last Updated</th>
                          <th>Action</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($recent_projects['idp'] as $project): ?>
                        <tr>
                          <td><span class="badge bg-success"><?php echo htmlspecialchars($project['project_code']); ?></span></td>
                          <td><?php echo htmlspecialchars($project['project_title']); ?></td>
                          <td>
                            <span class="badge bg-<?php 
                              echo match($project['status']) {
                                'Proposal' => 'warning',
                                'Pre-Implementation' => 'info',
                                'Procurement' => 'secondary',
                                'Implementation' => 'success',
                                'Completed' => 'success',
                                default => 'secondary'
                              };
                            ?>">
                              <?php echo htmlspecialchars($project['status']); ?>
                            </span>
                          </td>
                          <td>
                            <div class="progress" style="height: 20px;">
                              <div class="progress-bar bg-success" role="progressbar" 
                                   style="width: <?php echo $project['physical_progress'] ?? 0; ?>%"
                                   aria-valuenow="<?php echo $project['physical_progress'] ?? 0; ?>" 
                                   aria-valuemin="0" aria-valuemax="100">
                                <?php echo $project['physical_progress'] ?? 0; ?>%
                              </div>
                            </div>
                          </td>
                          <td>₱<?php echo number_format($project['allocated_amount'] ?? 0, 2); ?></td>
                          <td><?php echo date('M d, Y', strtotime($project['updated_at'])); ?></td>
                          <td>
                            <a href="project-details.php?type=IDP&id=<?php echo $project['id']; ?>" 
                               class="btn btn-sm btn-outline-success">
                              <i class="fas fa-eye"></i>
                            </a>
                          </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($recent_projects['idp'])): ?>
                        <tr>
                          <td colspan="7" class="text-center text-muted">No IDP projects yet</td>
                        </tr>
                        <?php endif; ?>
                      </tbody>
                    </table>
                  </div>
                </div>

                <!-- AFME Recent Machinery -->
                <div id="recentAfme" class="tab-pane fade">
                  <div class="table-responsive">
                    <table class="table table-hover">
                      <thead class="table-light">
                        <tr>
                          <th>Machinery Name</th>
                          <th>Status</th>
                          <th>Budget</th>
                          <th>Last Updated</th>
                          <th>Action</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($recent_projects['afme'] as $machinery): ?>
                        <tr>
                          <td><?php echo htmlspecialchars($machinery['project_title']); ?></td>
                          <td>
                            <span class="badge bg-<?php 
                              echo match($machinery['status']) {
                                'Proposal' => 'warning',
                                'Pre-Implementation' => 'info',
                                'Procurement' => 'secondary',
                                'Implementation' => 'success',
                                'Delivered' => 'success',
                                'Turned-Over' => 'dark',
                                default => 'secondary'
                              };
                            ?>">
                              <?php echo htmlspecialchars($machinery['status']); ?>
                            </span>
                          </td>
                          <td>₱<?php echo number_format($machinery['allocated_amount'] ?? 0, 2); ?></td>
                          <td><?php echo date('M d, Y', strtotime($machinery['updated_at'])); ?></td>
                          <td>
                            <a href="afme-machinery-details.php?id=<?php echo $machinery['id']; ?>" 
                               class="btn btn-sm btn-outline-warning">
                              <i class="fas fa-eye"></i>
                            </a>
                          </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($recent_projects['afme'])): ?>
                        <tr>
                          <td colspan="5" class="text-center text-muted">No AFME machinery yet</td>
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

    <!-- Register FSPF Modal -->
    <div class="modal fade" id="registerFspfModal" tabindex="-1">
      <div class="modal-dialog modal-xl">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Register FSPF Project</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <form id="fspfForm">
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
              <button type="submit" class="btn btn-primary">Register FSPF Project</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Register IDP Modal -->
    <div class="modal fade" id="registerIdpModal" tabindex="-1">
      <div class="modal-dialog modal-xl">
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

    <!-- Register AFME Modal -->
    <div class="modal fade" id="registerAfmeModal" tabindex="-1">
      <div class="modal-dialog modal-xl">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Add AFME Machinery</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <form id="afmeForm">
            <div class="modal-body">
              <!-- Basic Information -->
              <h6 class="text-primary mb-3">Basic Information</h6>
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
              <button type="submit" class="btn btn-warning">Add AFME Machinery</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </main>

    <script src="assets/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
      // Fallback to CDN if local bootstrap JS is not available
      if (typeof bootstrap === "undefined") {
        document.write('<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"><\/script>');
      }
    </script>
    <script src="assets/js/modal-helper.js"></script>
    <script src="assets/js/dashboard.js"></script>
  </body>
</html>

