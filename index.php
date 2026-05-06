<?php
session_name('ABED_IDM_HUB');
session_start();
require_once 'config/database.php';

// Get overview statistics
$fspf_count = (int) db_query_scalar(
    $conn,
    "SELECT COUNT(*) AS total FROM projects WHERE project_type = 'fspf' AND approval_status = 'Approved'",
    0
);
$idp_count = (int) db_query_scalar(
    $conn,
    "SELECT COUNT(*) AS total FROM projects WHERE project_type = 'idp' AND approval_status = 'Approved'",
    0
);
$afme_count = (int) db_query_scalar($conn, 'SELECT COUNT(*) AS total FROM afme', 0);

// Get funded amounts
$fspf_funded = db_query_scalar(
    $conn,
    "SELECT SUM(allocated_amount) AS total FROM projects WHERE project_type = 'fspf' AND approval_status = 'Approved'",
    0
);
$fspf_funded = $fspf_funded !== null ? (float) $fspf_funded : 0.0;
$idp_funded = db_query_scalar(
    $conn,
    "SELECT SUM(allocated_amount) AS total FROM projects WHERE project_type = 'idp' AND approval_status = 'Approved'",
    0
);
$idp_funded = $idp_funded !== null ? (float) $idp_funded : 0.0;
$afme_funded = db_query_scalar($conn, 'SELECT SUM(amount_allocated) AS total FROM afme', 0);
$afme_funded = $afme_funded !== null ? (float) $afme_funded : 0.0;

// Get recent projects (FSPF)
$fspf_recent = db_query_all_assoc(
    $conn,
    "
    SELECT id, project_code, title AS project_title, allocated_amount, current_stage, physical_progress
    FROM projects
    WHERE project_type = 'fspf' AND approval_status = 'Approved'
    ORDER BY created_at DESC
    LIMIT 5
"
);

// Get recent projects (IDP)
$idp_recent = db_query_all_assoc(
    $conn,
    "
    SELECT id, project_code, title AS project_title, allocated_amount, current_stage, physical_progress
    FROM projects
    WHERE project_type = 'idp' AND approval_status = 'Approved'
    ORDER BY created_at DESC
    LIMIT 5
"
);

// Get recent machinery (AFME)
$afme_recent = db_query_all_assoc(
    $conn,
    "
    SELECT id, machine_name, beneficiary_name, amount_allocated AS allocated_amount, current_status
    FROM afme
    ORDER BY created_at DESC
    LIMIT 5
"
);

// Unified catalog for the default “All projects” tab (approved projects + AFME linked to approved parents)
$all_catalog = [];
$proj_all_rows = db_query_all_assoc(
    $conn,
    "
    SELECT id, project_code, title AS project_title, project_type, allocated_amount, current_stage, physical_progress, created_at
    FROM projects
    WHERE approval_status = 'Approved'
    ORDER BY created_at DESC
"
);
foreach ($proj_all_rows as $row) {
        $all_catalog[] = [
            'kind' => 'project',
            'id' => (int) $row['id'],
            'project_type' => (string) $row['project_type'],
            'code' => (string) $row['project_code'],
            'title' => (string) $row['project_title'],
            'stage' => (string) $row['current_stage'],
            'progress' => (float) $row['physical_progress'],
            'budget' => $row['allocated_amount'],
            'sort_ts' => strtotime((string) $row['created_at']) ?: 0,
        ];
}
$afme_all_rows = db_query_all_assoc(
    $conn,
    "
    SELECT a.id, a.machine_name, a.beneficiary_name, a.amount_allocated, a.current_status, a.created_at, p.project_code
    FROM afme a
    LEFT JOIN projects p ON p.id = a.project_id
    WHERE (p.id IS NULL OR p.approval_status = 'Approved')
    ORDER BY a.created_at DESC
"
);
foreach ($afme_all_rows as $row) {
        $ref = trim((string) ($row['project_code'] ?? ''));
        if ($ref === '') {
            $ref = 'AFME #' . (int) $row['id'];
        }
        $all_catalog[] = [
            'kind' => 'afme',
            'id' => (int) $row['id'],
            'code' => $ref,
            'title' => (string) $row['machine_name'],
            'stage' => (string) $row['current_status'],
            'progress' => null,
            'budget' => $row['amount_allocated'],
            'sort_ts' => strtotime((string) $row['created_at']) ?: 0,
        ];
}
usort($all_catalog, static function (array $a, array $b): int {
    return ($b['sort_ts'] <=> $a['sort_ts']);
});

// Collect only admin-approved uploads for public display.
$approved_uploads = [];
$approvedProjectDocs = db_query_all_assoc(
    $conn,
    "
    SELECT id, project_code, title AS project_title, project_type, documents
    FROM projects
    WHERE approval_status = 'Approved'
      AND documents IS NOT NULL AND documents <> '' AND documents <> '[]'
"
);
foreach ($approvedProjectDocs as $row) {
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
$approvedAfmeDocs = db_query_all_assoc(
    $conn,
    "
    SELECT a.id, a.machine_name, a.documents, p.project_code
    FROM afme a
    INNER JOIN projects p ON p.id = a.project_id AND p.approval_status = 'Approved'
    WHERE a.documents IS NOT NULL AND a.documents <> '' AND a.documents <> '[]'
"
);
foreach ($approvedAfmeDocs as $row) {
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
usort($approved_uploads, static function (array $a, array $b): int {
    return strcmp((string) ($b['upload_date'] ?? ''), (string) ($a['upload_date'] ?? ''));
});
$uploads_all = array_slice($approved_uploads, 0, 12);
$uploads_fspf = array_slice(array_values(array_filter($approved_uploads, static function (array $u): bool {
    return strtoupper((string) ($u['module'] ?? '')) === 'FSPF';
})), 0, 12);
$uploads_idp = array_slice(array_values(array_filter($approved_uploads, static function (array $u): bool {
    return strtoupper((string) ($u['module'] ?? '')) === 'IDP';
})), 0, 12);
$uploads_afme = array_slice(array_values(array_filter($approved_uploads, static function (array $u): bool {
    return strtoupper((string) ($u['module'] ?? '')) === 'AFME';
})), 0, 12);

// Get stage breakdown
$stage_breakdown = db_query_all_assoc(
    $conn,
    "
    SELECT UPPER(project_type) AS project_type, current_stage, COUNT(*) AS count
    FROM projects
    WHERE approval_status = 'Approved'
    GROUP BY project_type, current_stage
"
);

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
        <nav class="navbar navbar-expand-lg py-3 align-items-lg-center public-navbar" aria-label="Public navigation">
          <a class="navbar-brand d-flex align-items-center gap-2 gap-sm-3 public-navbar-brand" href="index.php">
            <img src="logos/abed_logo.png" alt="ABED IDM Hub" class="public-brand-logo flex-shrink-0" />
            <span class="public-brand-text d-flex flex-column lh-sm text-start">
              <span class="public-brand-title fw-bold text-white">ABED Integrated Data Management Hub</span>
              <span class="public-brand-subtitle small text-white-50">Agricultural and Biosystems Engineering Division - LGU Malaybalay City</span>
            </span>
          </a>

          <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#publicNav" aria-controls="publicNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
          </button>

          <div class="collapse navbar-collapse align-items-lg-center" id="publicNav">
            <ul class="navbar-nav ms-auto me-lg-2 mb-3 mb-lg-0 public-navbar-main-nav">
              <li class="nav-item"><a class="nav-link" href="#overview">Overview</a></li>
              <li class="nav-item"><a class="nav-link" href="#projects">Projects</a></li>
              <li class="nav-item"><a class="nav-link" href="#uploads">Uploads</a></li>
              <li class="nav-item"><a class="nav-link" href="team.php">Meet Our Team</a></li>
              <li class="nav-item"><a class="nav-link" href="contact.php">Contact</a></li>
            </ul>
            <?php if (isset($_SESSION['user_id'])): ?>
            <div class="d-flex flex-column flex-lg-row align-items-lg-center gap-2 public-nav-utils">
              <a href="dashboard-enhanced.php" class="btn btn-primary btn-sm public-nav-btn">Dashboard</a>
            </div>
            <?php endif; ?>
          </div>
        </nav>
      </div>
    </header>

    <section class="public-hero-layout" id="overview">
      <div class="container">
        <div class="row g-0 align-items-stretch public-hero-row">
          <div class="col-lg-6 public-hero-copy-wrap">
            <div class="public-hero-copy">
              <span class="public-hero-kicker">Agricultural and Biosystems Engineering Division</span>
              <h1 class="public-hero-title">ABED Integrated Data Management Hub</h1>
              <p class="public-hero-description">
                Track project performance, monitor infrastructure progress, and access verified public records through the ABED IDM Hub.
              </p>
              <?php if (!isset($_SESSION['user_id'])): ?>
              <div class="public-hero-cta d-flex flex-wrap gap-3 mt-4">
                <a href="login.php" class="btn btn-primary">Login</a>
                <a href="signup.php" class="btn btn-outline-primary">Create Account</a>
              </div>
              <?php else: ?>
              <div class="public-hero-cta mt-4">
                <p class="mb-3">Welcome back, <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong></p>
                <a href="dashboard-enhanced.php" class="btn btn-primary">Go to Dashboard</a>
              </div>
              <?php endif; ?>
            </div>
          </div>
          <div class="col-lg-6">
            <div class="public-hero-visual d-flex align-items-center justify-content-center p-4">
              <img src="logos/agriblack.png" alt="Agricultural and Biosystems Engineering" class="img-fluid public-hero-agriblack" width="720" height="440" />
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
                <div class="public-feature-icon public-feature-icon--brand">
                  <img src="logos/verified-records.svg" alt="Verified Records" class="public-feature-brand-logo" width="40" height="40" />
                </div>
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
    <section class="py-5 public-home-overview-section" id="system-overview" aria-labelledby="system-overview-title">
      <div class="container">
        <h2 class="text-center mb-5 public-home-overview-title" id="system-overview-title">System Overview</h2>
        
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
        <h2 class="mb-2">Projects</h2>
        <p class="text-muted mb-4">Approved initiatives and machinery in the public catalog. The <strong>All projects</strong> tab opens by default.</p>

        <!-- Tabs -->
        <ul class="nav nav-tabs mb-4" role="tablist">
          <li class="nav-item">
            <a class="nav-link active" data-bs-toggle="tab" href="#all-projects">All projects</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#fspf-projects">FSPF Projects</a>
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
          <!-- All projects -->
          <div id="all-projects" class="tab-pane fade show active">
            <div class="table-responsive">
              <table class="table table-hover">
                <thead class="table-light">
                  <tr>
                    <th>Type</th>
                    <th>Reference</th>
                    <th>Title</th>
                    <th>Stage / status</th>
                    <th>Progress</th>
                    <th>Budget</th>
                    <th class="text-end">View</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($all_catalog as $row): ?>
                  <tr>
                    <td>
                      <?php if ($row['kind'] === 'project'): ?>
                      <span class="badge bg-<?php echo $row['project_type'] === 'fspf' ? 'success' : ($row['project_type'] === 'idp' ? 'info' : 'warning'); ?>"><?php echo strtoupper(htmlspecialchars($row['project_type'])); ?></span>
                      <?php else: ?>
                      <span class="badge bg-warning text-dark">AFME</span>
                      <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($row['code']); ?></td>
                    <td><?php echo htmlspecialchars(strlen($row['title']) > 48 ? substr($row['title'], 0, 45) . '…' : $row['title']); ?></td>
                    <td>
                      <span class="badge bg-secondary"><?php echo htmlspecialchars($row['stage']); ?></span>
                    </td>
                    <td>
                      <?php if ($row['kind'] === 'project'): ?>
                      <div class="progress progress-small">
                        <div class="progress-bar progress-bar-w" style="--w: <?php echo (float) $row['progress']; ?>%"></div>
                      </div>
                      <small><?php echo htmlspecialchars((string) $row['progress']); ?>%</small>
                      <?php else: ?>
                      <span class="text-muted">—</span>
                      <?php endif; ?>
                    </td>
                    <td>₱<?php echo number_format((float) ($row['budget'] ?? 0), 2); ?></td>
                    <td class="text-end">
                      <?php if ($row['kind'] === 'project'): ?>
                      <a class="btn btn-sm btn-outline-primary" href="public-project-detail.php?type=<?php echo htmlspecialchars(urlencode($row['project_type']), ENT_QUOTES, 'UTF-8'); ?>&amp;id=<?php echo (int) $row['id']; ?>">View</a>
                      <?php else: ?>
                      <a class="btn btn-sm btn-outline-primary" href="public-afme-detail.php?id=<?php echo (int) $row['id']; ?>">View</a>
                      <?php endif; ?>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if (empty($all_catalog)): ?>
                  <tr>
                    <td colspan="7" class="text-center text-muted py-4">No approved projects in the catalog yet.</td>
                  </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>

          <!-- FSPF Tab -->
          <div id="fspf-projects" class="tab-pane fade">
            <div class="table-responsive">
              <table class="table table-hover">
                <thead class="table-light">
                  <tr>
                    <th>Project Code</th>
                    <th>Title</th>
                    <th>Stage</th>
                    <th>Progress</th>
                    <th>Budget</th>
                    <th class="text-end">View</th>
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
                        <div class="progress-bar progress-bar-w" style="--w: <?php echo $project['physical_progress']; ?>%"></div>
                      </div>
                      <small><?php echo $project['physical_progress']; ?>%</small>
                    </td>
                    <td>₱<?php echo number_format($project['allocated_amount'] ?? 0, 2); ?></td>
                    <td class="text-end">
                      <a class="btn btn-sm btn-outline-primary" href="public-project-detail.php?type=fspf&amp;id=<?php echo (int) $project['id']; ?>">View</a>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if (empty($fspf_recent)): ?>
                  <tr>
                    <td colspan="6" class="text-center text-muted py-4">No FSPF projects available</td>
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
                    <th class="text-end">View</th>
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
                        <div class="progress-bar bg-info progress-bar-w" style="--w: <?php echo $project['physical_progress']; ?>%"></div>
                      </div>
                      <small><?php echo $project['physical_progress']; ?>%</small>
                    </td>
                    <td>₱<?php echo number_format($project['allocated_amount'] ?? 0, 2); ?></td>
                    <td class="text-end">
                      <a class="btn btn-sm btn-outline-primary" href="public-project-detail.php?type=idp&amp;id=<?php echo (int) $project['id']; ?>">View</a>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if (empty($idp_recent)): ?>
                  <tr>
                    <td colspan="6" class="text-center text-muted py-4">No IDP projects available</td>
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
                    <th class="text-end">View</th>
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
                    <td class="text-end">
                      <a class="btn btn-sm btn-outline-primary" href="public-afme-detail.php?id=<?php echo (int) $machinery['id']; ?>">View</a>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if (empty($afme_recent)): ?>
                  <tr>
                    <td colspan="5" class="text-center text-muted py-4">No AFME machinery available</td>
                  </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="py-5 bg-white public-section-surface" id="uploads">
      <div class="container">
        <h2 class="mb-2">Verified Uploads</h2>
        <p class="text-muted mb-4">Only admin-approved documents are listed below. The <strong>All uploads</strong> tab opens by default.</p>

        <ul class="nav nav-tabs mb-4" role="tablist">
          <li class="nav-item">
            <a class="nav-link active" data-bs-toggle="tab" href="#all-uploads">All uploads</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#fspf-uploads">FSPF Projects</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#idp-uploads">IDP Projects</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#afme-uploads">AFME Machinery</a>
          </li>
        </ul>

        <div class="tab-content">
          <div id="all-uploads" class="tab-pane fade show active">
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
                  <?php foreach ($uploads_all as $upload): ?>
                  <tr>
                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars($upload['module']); ?></span></td>
                    <td><?php echo htmlspecialchars($upload['project_ref']); ?></td>
                    <td><?php echo htmlspecialchars($upload['project_title']); ?></td>
                    <td><?php echo htmlspecialchars($upload['doc_type']); ?></td>
                    <td><?php echo htmlspecialchars($upload['file_name']); ?></td>
                    <td><?php echo htmlspecialchars($upload['upload_date'] !== '' ? date('M j, Y', strtotime($upload['upload_date'])) : '—'); ?></td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if (empty($uploads_all)): ?>
                  <tr>
                    <td colspan="6" class="text-center text-muted py-4">No verified uploads available yet.</td>
                  </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
          <div id="fspf-uploads" class="tab-pane fade">
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
                  <?php foreach ($uploads_fspf as $upload): ?>
                  <tr>
                    <td><span class="badge bg-success"><?php echo htmlspecialchars($upload['module']); ?></span></td>
                    <td><?php echo htmlspecialchars($upload['project_ref']); ?></td>
                    <td><?php echo htmlspecialchars($upload['project_title']); ?></td>
                    <td><?php echo htmlspecialchars($upload['doc_type']); ?></td>
                    <td><?php echo htmlspecialchars($upload['file_name']); ?></td>
                    <td><?php echo htmlspecialchars($upload['upload_date'] !== '' ? date('M j, Y', strtotime($upload['upload_date'])) : '—'); ?></td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if (empty($uploads_fspf)): ?>
                  <tr>
                    <td colspan="6" class="text-center text-muted py-4">No FSPF verified uploads yet.</td>
                  </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
          <div id="idp-uploads" class="tab-pane fade">
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
                  <?php foreach ($uploads_idp as $upload): ?>
                  <tr>
                    <td><span class="badge bg-info"><?php echo htmlspecialchars($upload['module']); ?></span></td>
                    <td><?php echo htmlspecialchars($upload['project_ref']); ?></td>
                    <td><?php echo htmlspecialchars($upload['project_title']); ?></td>
                    <td><?php echo htmlspecialchars($upload['doc_type']); ?></td>
                    <td><?php echo htmlspecialchars($upload['file_name']); ?></td>
                    <td><?php echo htmlspecialchars($upload['upload_date'] !== '' ? date('M j, Y', strtotime($upload['upload_date'])) : '—'); ?></td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if (empty($uploads_idp)): ?>
                  <tr>
                    <td colspan="6" class="text-center text-muted py-4">No IDP verified uploads yet.</td>
                  </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
          <div id="afme-uploads" class="tab-pane fade">
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
                  <?php foreach ($uploads_afme as $upload): ?>
                  <tr>
                    <td><span class="badge bg-warning text-dark"><?php echo htmlspecialchars($upload['module']); ?></span></td>
                    <td><?php echo htmlspecialchars($upload['project_ref']); ?></td>
                    <td><?php echo htmlspecialchars($upload['project_title']); ?></td>
                    <td><?php echo htmlspecialchars($upload['doc_type']); ?></td>
                    <td><?php echo htmlspecialchars($upload['file_name']); ?></td>
                    <td><?php echo htmlspecialchars($upload['upload_date'] !== '' ? date('M j, Y', strtotime($upload['upload_date'])) : '—'); ?></td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if (empty($uploads_afme)): ?>
                  <tr>
                    <td colspan="6" class="text-center text-muted py-4">No AFME verified uploads yet.</td>
                  </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Footer -->
    <footer class="public-footer py-4">
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
              <li><a href="contact.php" class="text-muted text-decoration-none">Contact</a></li>
              <li><a href="#" class="text-muted text-decoration-none">Privacy Policy</a></li>
            </ul>
          </div>
          <div class="col-md-4">
            <h6 class="fw-bold mb-3">Contact</h6>
            <p class="small text-muted">
              Malaybalay City, Bukidnon<br>
              Email: <a href="mailto:example.idm@gmail.com" class="text-muted">example.abed.idm@example.com</a>
            </p>
          </div>
        </div>
        <hr class="my-3">
        <div class="text-center text-muted small">
          <p>&copy; 2026 ABED IDM Hub. All rights reserved.</p>
        </div>
      </div>
    </footer>

    <script src="assets/bootstrap/js/bootstrap.bundle.js"></script>
  </body>
</html>

