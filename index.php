<?php
session_name('ABED_IDM_HUB');
session_start();
require_once 'config/database.php';

// Get overview statistics
$fspf_count = $conn->query("SELECT COUNT(*) as total FROM projects WHERE project_type = 'fspf'")->fetch_assoc()['total'];
$idp_count = $conn->query("SELECT COUNT(*) as total FROM projects WHERE project_type = 'idp'")->fetch_assoc()['total'];
$afme_count = $conn->query("SELECT COUNT(*) as total FROM afme")->fetch_assoc()['total'];

// Get funded amounts
$fspf_funded = $conn->query("SELECT SUM(allocated_amount) as total FROM projects WHERE project_type = 'fspf'")->fetch_assoc()['total'] ?? 0;
$idp_funded = $conn->query("SELECT SUM(allocated_amount) as total FROM projects WHERE project_type = 'idp'")->fetch_assoc()['total'] ?? 0;
$afme_funded = $conn->query("SELECT SUM(amount_allocated) as total FROM afme")->fetch_assoc()['total'] ?? 0;

// Get recent projects (FSPF)
$fspf_recent = $conn->query("
    SELECT id, project_code, title AS project_title, allocated_amount, current_stage, physical_progress
    FROM projects
    WHERE project_type = 'fspf'
    ORDER BY created_at DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// Get recent projects (IDP)
$idp_recent = $conn->query("
    SELECT id, project_code, title AS project_title, allocated_amount, current_stage, physical_progress
    FROM projects
    WHERE project_type = 'idp'
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

// Get stage breakdown
$stage_breakdown = $conn->query("
    SELECT UPPER(project_type) AS project_type, current_stage, COUNT(*) as count
    FROM projects
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
    <style>
      body {
        background: #f5f7fa;
      }
      .hero {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 80px 0;
        text-align: center;
      }
      .stat-card {
        border-left: 4px solid;
        transition: all 0.3s ease;
      }
      .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 5px 20px rgba(0,0,0,0.1);
      }
      .stat-card.fspf { border-left-color: #28a745; }
      .stat-card.idp { border-left-color: #007bff; }
      .stat-card.afme { border-left-color: #ffc107; }
      .project-badge {
        font-size: 0.75rem;
        padding: 0.4rem 0.6rem;
      }
      .progress-small {
        height: 5px;
      }
      .navbar {
        background: white;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
      }
      .card-hover {
        transition: all 0.3s ease;
        border: none;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      }
      .card-hover:hover {
        box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        transform: translateY(-3px);
      }
    </style>
  </head>
  <body>
    <!-- Hero Section -->
    <section class="hero">
      <div class="container">
        <h1 class="display-4 fw-bold mb-3">ABED IDM Hub</h1>
        <p class="lead mb-4">Agricultural and Bioenterprise Enhancement Division<br>Infrastructure Development Management System</p>
        <?php if (!isset($_SESSION['user_id'])): ?>
        <div class="d-grid gap-2 d-sm-flex justify-content-sm-center">
          <a href="login.php" class="btn btn-light btn-lg px-4">
            <i class="fas fa-sign-in-alt me-2"></i>Login
          </a>
          <a href="signup.php" class="btn btn-outline-light btn-lg px-4">
            <i class="fas fa-user-plus me-2"></i>Register
          </a>
        </div>
        <?php else: ?>
        <p class="lead">Welcome back, <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong></p>
        <a href="dashboard-enhanced.php" class="btn btn-light btn-lg">Go to Dashboard</a>
        <?php endif; ?>
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
                  <div style="font-size: 3rem; color: #28a745; opacity: 0.2;">
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
                  <div style="font-size: 3rem; color: #007bff; opacity: 0.2;">
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
                  <div style="font-size: 3rem; color: #ffc107; opacity: 0.2;">
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
    <section class="py-5 bg-white" id="projects">
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

    <!-- Footer -->
    <footer class="bg-dark text-white py-4 mt-5">
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

