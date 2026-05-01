<?php
session_name('ABED_IDM_HUB');
session_start();
require_once 'config/database.php';

// Get overview statistics
$fspf_count = $conn->query("SELECT COUNT(*) as total FROM projects WHERE project_type = 'fspf' AND approval_status = 'Approved'")->fetch_assoc()['total'];
$idp_count = $conn->query("SELECT COUNT(*) as total FROM projects WHERE project_type = 'idp' AND approval_status = 'Approved'")->fetch_assoc()['total'];
$afme_count = $conn->query("SELECT COUNT(*) as total FROM afme")->fetch_assoc()['total'];

// Get funded amounts
$fspf_funded = $conn->query("SELECT SUM(allocated_amount) as total FROM projects WHERE project_type = 'fspf' AND approval_status = 'Approved'")->fetch_assoc()['total'] ?? 0;
$idp_funded = $conn->query("SELECT SUM(allocated_amount) as total FROM projects WHERE project_type = 'idp' AND approval_status = 'Approved'")->fetch_assoc()['total'] ?? 0;
$afme_funded = $conn->query("SELECT SUM(amount_allocated) as total FROM afme")->fetch_assoc()['total'] ?? 0;

// Get recent projects (FSPF)
$fspf_recent = $conn->query("
    SELECT id, project_code, title AS project_title, allocated_amount, current_stage, physical_progress
    FROM projects
    WHERE project_type = 'fspf' AND approval_status = 'Approved'
    ORDER BY created_at DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// Get recent projects (IDP)
$idp_recent = $conn->query("
    SELECT id, project_code, title AS project_title, allocated_amount, current_stage, physical_progress
    FROM projects
    WHERE project_type = 'idp' AND approval_status = 'Approved'
    ORDER BY created_at DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// Get recent machinery (AFME)
$afme_recent = $conn->query("
    SELECT id, machine_name, beneficiary_name, amount_allocated AS allocated_amount, current_status
    FROM afme
    ORDER BY created_at DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// Collect only admin-approved uploads for public display.
$approved_uploads = [];
$approvedProjectDocs = $conn->query("
    SELECT id, project_code, title AS project_title, project_type, documents
    FROM projects
    WHERE approval_status = 'Approved'
      AND documents IS NOT NULL AND documents <> '' AND documents <> '[]'
");
if ($approvedProjectDocs) {
    foreach ($approvedProjectDocs->fetch_all(MYSQLI_ASSOC) as $row) {
        $docs = json_decode((string) ($row['documents'] ?? '[]'), true);
        if (!is_array($docs)) {
            continue;
        }
        foreach ($docs as $doc) {
            if (!is_array($doc) || (string) ($doc['review_status'] ?? 'Pending') !== 'Approved') {
                continue;
            }
            $approved_uploads[] = [
                'module' => strtoupper((string) ($row['project_type'] ?? '')),
                'project_ref' => (string) ($row['project_code'] ?? ('#' . (int) $row['id'])),
                'project_title' => (string) ($row['project_title'] ?? ''),
                'doc_type' => (string) ($doc['document_type'] ?? ($doc['doc_type'] ?? 'Document')),
                'file_name' => (string) ($doc['original_filename'] ?? ($doc['file_name'] ?? 'Unnamed file')),
                'upload_date' => (string) ($doc['upload_date'] ?? ''),
            ];
        }
    }
}
$approvedAfmeDocs = $conn->query("
    SELECT a.id, a.machine_name, a.documents, p.project_code
    FROM afme a
    INNER JOIN projects p ON p.id = a.project_id AND p.approval_status = 'Approved'
    WHERE a.documents IS NOT NULL AND a.documents <> '' AND a.documents <> '[]'
");
if ($approvedAfmeDocs) {
    foreach ($approvedAfmeDocs->fetch_all(MYSQLI_ASSOC) as $row) {
        $docs = json_decode((string) ($row['documents'] ?? '[]'), true);
        if (!is_array($docs)) {
            continue;
        }
        foreach ($docs as $doc) {
            if (!is_array($doc) || (string) ($doc['review_status'] ?? 'Pending') !== 'Approved') {
                continue;
            }
            $approved_uploads[] = [
                'module' => 'AFME',
                'project_ref' => (string) ($row['project_code'] ?? ('AFME #' . (int) $row['id'])),
                'project_title' => (string) ($row['machine_name'] ?? 'AFME Machinery'),
                'doc_type' => (string) ($doc['doc_type'] ?? 'Document'),
                'file_name' => (string) ($doc['file_name'] ?? 'Unnamed file'),
                'upload_date' => (string) ($doc['upload_date'] ?? ''),
            ];
        }
    }
}
usort($approved_uploads, static function (array $a, array $b): int {
    return strcmp((string) ($b['upload_date'] ?? ''), (string) ($a['upload_date'] ?? ''));
});
$approved_uploads = array_slice($approved_uploads, 0, 12);

// Get stage breakdown
$stage_breakdown = $conn->query("
    SELECT UPPER(project_type) AS project_type, current_stage, COUNT(*) as count
    FROM projects
    WHERE approval_status = 'Approved'
    GROUP BY project_type, current_stage
")->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>ABED IDM Hub - Homepage</title>
    <link rel="stylesheet" href="assets/bootstrap/css/bootstrap.min.css" />
    <link rel="stylesheet" href="assets/css/style.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
  </head>
  <body class="public-home">
    <header class="public-header">
      <div class="container">
        <nav class="navbar navbar-expand-lg py-3 public-navbar" aria-label="Public navigation">
          <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
            <img src="logos/abed_logo.png" alt="ABED IDM Hub" class="public-brand-logo" />
            <span class="fw-bold d-none d-sm-inline">ABED IDM Hub</span>
          </a>

          <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#publicNav" aria-controls="publicNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
          </button>

          <div class="collapse navbar-collapse" id="publicNav">
            <ul class="navbar-nav mx-auto mb-3 mb-lg-0">
              <li class="nav-item"><a class="nav-link" href="#overview">Overview</a></li>
              <li class="nav-item"><a class="nav-link" href="#projects">Projects</a></li>
              <li class="nav-item"><a class="nav-link" href="#uploads">Uploads</a></li>
              <li class="nav-item"><a class="nav-link" href="#contact">Contact</a></li>
            </ul>
            <div class="d-flex flex-column flex-lg-row align-items-lg-center gap-2 gap-lg-3 public-nav-utils">
              <?php if (!isset($_SESSION['user_id'])): ?>
              <a href="login.php" class="btn btn-outline-primary btn-sm">Login</a>
              <a href="signup.php" class="btn btn-primary btn-sm">Sign Up</a>
              <?php else: ?>
              <a href="dashboard-enhanced.php" class="btn btn-primary btn-sm">Dashboard</a>
              <?php endif; ?>
              <a href="#contact" class="nav-link p-0">Contact</a>
              <button type="button" class="btn btn-light btn-sm px-3" aria-label="Current language">EN</button>
            </div>
          </div>
        </nav>
      </div>
    </header>

    <section class="public-hero-layout" id="overview">
      <div class="container">
        <div class="row g-0 align-items-stretch public-hero-row">
          <div class="col-lg-6 public-hero-copy-wrap">
            <div class="public-hero-copy">
              <span class="public-hero-kicker">Overview of Agricultural Support Programs</span>
              <h1 class="public-hero-title">Infrastructure Management Made Easier.</h1>
              <p class="public-hero-description">
                Track project performance, monitor infrastructure progress, and access verified public records through the ABED IDM Hub.
              </p>
              <?php if (!isset($_SESSION['user_id'])): ?>
              <div class="d-flex flex-wrap gap-2 mt-4">
                <a href="login.php" class="btn btn-primary px-4">Login</a>
                <a href="signup.php" class="btn btn-outline-primary px-4">Create Account</a>
              </div>
              <?php else: ?>
              <div class="mt-4">
                <p class="mb-2">Welcome back, <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong></p>
                <a href="dashboard-enhanced.php" class="btn btn-primary px-4">Go to Dashboard</a>
              </div>
              <?php endif; ?>
            </div>
          </div>
          <div class="col-lg-6">
            <div class="public-hero-visual">
            </div>
          </div>
        </div>

        <div class="row g-3 public-feature-cards">
          <div class="col-12 col-sm-6 col-lg-3">
            <article class="card h-100 card-hover public-feature-card">
              <div class="card-body">
                <div class="public-feature-icon"><i class="fas fa-chart-line"></i></div>
                <h3 class="h5 mb-2">Project Visibility</h3>
                <p class="mb-0 text-muted">Get a quick view of approved initiatives and current implementation stages.</p>
              </div>
            </article>
          </div>
          <div class="col-12 col-sm-6 col-lg-3">
            <article class="card h-100 card-hover public-feature-card">
              <div class="card-body">
                <div class="public-feature-icon"><i class="fas fa-file-circle-check"></i></div>
                <h3 class="h5 mb-2">Verified Records</h3>
                <p class="mb-0 text-muted">Browse uploads that have been reviewed and approved by administrators.</p>
              </div>
            </article>
          </div>
          <div class="col-12 col-sm-6 col-lg-3">
            <article class="card h-100 card-hover public-feature-card public-feature-card--featured">
              <div class="card-body">
                <div class="public-feature-icon"><i class="fas fa-water"></i></div>
                <h3 class="h5 mb-2">Infrastructure Focus</h3>
                <p class="mb-0 text-muted">Monitor irrigation and farm-to-market development priorities in one place.</p>
              </div>
            </article>
          </div>
          <div class="col-12 col-sm-6 col-lg-3">
            <article class="card h-100 card-hover public-feature-card">
              <div class="card-body">
                <div class="public-feature-icon"><i class="fas fa-users"></i></div>
                <h3 class="h5 mb-2">Program Access</h3>
                <p class="mb-0 text-muted">Support field staff and stakeholders with centralized reporting tools.</p>
              </div>
            </article>
          </div>
        </div>
      </div>
    </section>

    <!-- Statistics Section -->
    <section class="py-5">
      <div class="container">
        <h2 class="text-center mb-5">System Overview</h2>
        
        <div class="row g-4">
          <!-- FSPF Stats -->
          <div class="col-md-4">
            <div class="card card-hover stat-card fspf">
              <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                  <div>
                    <h6 class="card-title text-muted">FSPF Projects</h6>
                    <h2 class="mb-2"><?php echo $fspf_count; ?></h2>
                    <small class="text-success">₱<?php echo number_format($fspf_funded, 2); ?></small>
                  </div>
                  <div class="stat-icon text-success">
                    <i class="fas fa-tractor"></i>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- IDP Stats -->
          <div class="col-md-4">
            <div class="card card-hover stat-card idp">
              <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                  <div>
                    <h6 class="card-title text-muted">IDP Projects</h6>
                    <h2 class="mb-2"><?php echo $idp_count; ?></h2>
                    <small class="text-info">₱<?php echo number_format($idp_funded, 2); ?></small>
                  </div>
                  <div class="stat-icon text-primary">
                    <i class="fas fa-water"></i>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- AFME Stats -->
          <div class="col-md-4">
            <div class="card card-hover stat-card afme">
              <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                  <div>
                    <h6 class="card-title text-muted">AFME Machinery</h6>
                    <h2 class="mb-2"><?php echo $afme_count; ?></h2>
                    <small class="text-warning">₱<?php echo number_format($afme_funded, 2); ?></small>
                  </div>
                  <div class="stat-icon text-warning">
                    <i class="fas fa-cog"></i>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Recent Projects Section -->
    <section class="py-5 bg-white public-section-surface" id="projects">
      <div class="container">
        <h2 class="mb-5">Recent Projects</h2>

        <!-- Tabs -->
        <ul class="nav nav-tabs mb-4" role="tablist">
          <li class="nav-item">
            <a class="nav-link active" data-bs-toggle="tab" href="#fspf-projects">FSPF Projects</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#idp-projects">IDP Projects</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#afme-projects">AFME Machinery</a>
          </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content">
          <!-- FSPF Tab -->
          <div id="fspf-projects" class="tab-pane fade show active">
            <div class="table-responsive">
              <table class="table table-hover">
                <thead class="table-light">
                  <tr>
                    <th>Project Code</th>
                    <th>Title</th>
                    <th>Stage</th>
                    <th>Progress</th>
                    <th>Budget</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($fspf_recent as $project): ?>
                  <tr>
                    <td><span class="badge bg-success"><?php echo htmlspecialchars($project['project_code']); ?></span></td>
                    <td><?php echo htmlspecialchars(substr($project['project_title'], 0, 40)); ?></td>
                    <td>
                      <span class="badge bg-<?php
                        echo match($project['current_stage']) {
                          'Proposal' => 'warning',
                          'Pre-Implementation' => 'info',
                          'Procurement' => 'secondary',
                          'Implementation' => 'primary',
                          'Completed' => 'success',
                          default => 'secondary'
                        };
                      ?>"><?php echo $project['current_stage']; ?></span>
                    </td>
                    <td>
                      <div class="progress progress-small">
                        <div class="progress-bar" style="width: <?php echo $project['physical_progress']; ?>%"></div>
                      </div>
                      <small><?php echo $project['physical_progress']; ?>%</small>
                    </td>
                    <td>₱<?php echo number_format($project['allocated_amount'] ?? 0, 2); ?></td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if (empty($fspf_recent)): ?>
                  <tr>
                    <td colspan="5" class="text-center text-muted py-4">No FSPF projects available</td>
                  </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>

          <!-- IDP Tab -->
          <div id="idp-projects" class="tab-pane fade">
            <div class="table-responsive">
              <table class="table table-hover">
                <thead class="table-light">
                  <tr>
                    <th>Project Code</th>
                    <th>Title</th>
                    <th>Stage</th>
                    <th>Progress</th>
                    <th>Budget</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($idp_recent as $project): ?>
                  <tr>
                    <td><span class="badge bg-info"><?php echo htmlspecialchars($project['project_code']); ?></span></td>
                    <td><?php echo htmlspecialchars(substr($project['project_title'], 0, 40)); ?></td>
                    <td>
                      <span class="badge bg-<?php
                        echo match($project['current_stage']) {
                          'Proposal' => 'warning',
                          'Pre-Implementation' => 'info',
                          'Procurement' => 'secondary',
                          'Implementation' => 'primary',
                          'Completed' => 'success',
                          default => 'secondary'
                        };
                      ?>"><?php echo $project['current_stage']; ?></span>
                    </td>
                    <td>
                      <div class="progress progress-small">
                        <div class="progress-bar bg-info" style="width: <?php echo $project['physical_progress']; ?>%"></div>
                      </div>
                      <small><?php echo $project['physical_progress']; ?>%</small>
                    </td>
                    <td>₱<?php echo number_format($project['allocated_amount'] ?? 0, 2); ?></td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if (empty($idp_recent)): ?>
                  <tr>
                    <td colspan="5" class="text-center text-muted py-4">No IDP projects available</td>
                  </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>

          <!-- AFME Tab -->
          <div id="afme-projects" class="tab-pane fade">
            <div class="table-responsive">
              <table class="table table-hover">
                <thead class="table-light">
                  <tr>
                    <th>Machinery Name</th>
                    <th>Beneficiary</th>
                    <th>Status</th>
                    <th>Budget</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($afme_recent as $machinery): ?>
                  <tr>
                    <td><?php echo htmlspecialchars(substr($machinery['machine_name'], 0, 30)); ?></td>
                    <td><?php echo htmlspecialchars(substr($machinery['beneficiary_name'], 0, 25)); ?></td>
                    <td>
                      <span class="badge bg-<?php
                        echo match($machinery['current_status']) {
                          'For Validation' => 'warning',
                          'Pre-Implementation' => 'info',
                          'Procurement' => 'secondary',
                          'Implementation' => 'primary',
                          'Delivered' => 'success',
                          'Turned-Over' => 'dark',
                          default => 'secondary'
                        };
                      ?>"><?php echo $machinery['current_status']; ?></span>
                    </td>
                    <td>₱<?php echo number_format($machinery['allocated_amount'] ?? 0, 2); ?></td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if (empty($afme_recent)): ?>
                  <tr>
                    <td colspan="4" class="text-center text-muted py-4">No AFME machinery available</td>
                  </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="py-5" id="uploads">
      <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <h2 class="mb-0">Verified Uploads</h2>
          <small class="text-muted">Only admin-approved documents are shown</small>
        </div>
        <div class="table-responsive">
          <table class="table table-hover">
            <thead class="table-light">
              <tr>
                <th>Module</th>
                <th>Project Reference</th>
                <th>Title</th>
                <th>Document Type</th>
                <th>File</th>
                <th>Uploaded</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($approved_uploads as $upload): ?>
              <tr>
                <td><span class="badge bg-secondary"><?php echo htmlspecialchars($upload['module']); ?></span></td>
                <td><?php echo htmlspecialchars($upload['project_ref']); ?></td>
                <td><?php echo htmlspecialchars($upload['project_title']); ?></td>
                <td><?php echo htmlspecialchars($upload['doc_type']); ?></td>
                <td><?php echo htmlspecialchars($upload['file_name']); ?></td>
                <td><?php echo htmlspecialchars($upload['upload_date'] !== '' ? date('M j, Y', strtotime($upload['upload_date'])) : '—'); ?></td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($approved_uploads)): ?>
              <tr>
                <td colspan="6" class="text-center text-muted py-4">No verified uploads available yet.</td>
              </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- Footer -->
    <footer class="public-footer py-4 mt-5" id="contact">
      <div class="container">
        <div class="row">
          <div class="col-md-4">
            <h6 class="fw-bold mb-3">ABED IDM Hub</h6>
            <p class="small text-muted">Infrastructure Development Management System</p>
          </div>
          <div class="col-md-4">
            <h6 class="fw-bold mb-3">Quick Links</h6>
            <ul class="list-unstyled small">
              <li><a href="#" class="text-muted text-decoration-none">About Us</a></li>
              <li><a href="#" class="text-muted text-decoration-none">Contact</a></li>
              <li><a href="#" class="text-muted text-decoration-none">Privacy Policy</a></li>
            </ul>
          </div>
          <div class="col-md-4">
            <h6 class="fw-bold mb-3">Contact</h6>
            <p class="small text-muted">
              Malaybalay City, Bukidnon<br>
              Email: info@abed.gov.ph
            </p>
          </div>
        </div>
        <hr class="my-3">
        <div class="text-center text-muted small">
          <p>&copy; 2026 ABED IDM Hub. All rights reserved.</p>
        </div>
      </div>
    </footer>

    <script src="assets/bootstrap/js/bootstrap.bundle.min.js"></script>
  </body>
</html>

