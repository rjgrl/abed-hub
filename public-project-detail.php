<?php
/**
 * Read-only project detail for approved catalog entries (public homepage / maps links).
 */
declare(strict_types=1);

session_name('ABED_IDM_HUB');
session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/functions/helpers.php';

$project_type = strtolower(trim((string) ($_GET['type'] ?? 'fspf')));
$project_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$allowed = ['fspf', 'idp', 'afme'];
if ($project_id <= 0 || !in_array($project_type, $allowed, true)) {
    http_response_code(404);
    exit('Project not found');
}

$table = 'projects';
$stmt = $conn->prepare("SELECT *, title AS project_title FROM $table WHERE id = ? AND project_type = ? AND approval_status = 'Approved'");
$stmt->bind_param('is', $project_id, $project_type);
$stmt->execute();
$project = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$project) {
    http_response_code(404);
    exit('Project not found');
}

$documents = [];
$rawDocs = json_decode((string) ($project['documents'] ?? '[]'), true);
if (is_array($rawDocs)) {
    foreach ($rawDocs as $doc) {
        if (!is_array($doc)) {
            continue;
        }
        if ((string) ($doc['review_status'] ?? 'Pending') !== 'Approved') {
            continue;
        }
        $documents[] = [
            'id' => (int) ($doc['id'] ?? 0),
            'original_filename' => (string) ($doc['original_filename'] ?? $doc['file_name'] ?? 'Document'),
            'document_type' => (string) ($doc['document_type'] ?? $doc['doc_type'] ?? 'General'),
            'upload_date' => (string) ($doc['upload_date'] ?? ''),
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

$machinery = [];
if ($project_type === 'afme') {
    $mach_stmt = $conn->prepare('SELECT * FROM afme WHERE project_id = ? ORDER BY created_at DESC');
    $mach_stmt->bind_param('i', $project_id);
    $mach_stmt->execute();
    $machinery = $mach_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$physicalProgressRaw = (float) ($project['physical_progress'] ?? 0);
$financialProgressRaw = (float) ($project['financial_progress'] ?? 0);
$physicalProgressDisplay = round($physicalProgressRaw, 1);
$financialProgressDisplay = round($financialProgressRaw, 1);
$physicalProgressWidth = max(0, min(100, $physicalProgressRaw));
$financialProgressWidth = max(0, min(100, $financialProgressRaw));

$conn->close();

$stageShort = (string) ($project['current_stage'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($project['project_code']); ?> — ABED IDM Hub</title>
    <link rel="stylesheet" href="assets/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
</head>
<body class="public-home public-catalog-detail">
    <header class="public-header">
      <div class="container">
        <nav class="navbar navbar-expand-lg py-3 align-items-lg-center public-navbar" aria-label="Public navigation">
          <a class="navbar-brand d-flex align-items-center gap-2 gap-sm-3 public-navbar-brand" href="index.php">
            <img src="logos/abed_logo.png" alt="ABED IDM Hub" class="public-brand-logo flex-shrink-0" />
            <span class="public-brand-text d-flex flex-column lh-sm text-start">
              <span class="public-brand-title fw-bold text-white">ABED Integrated Data Management Hub</span>
              <span class="public-brand-subtitle small text-white-50">Public project profile</span>
            </span>
          </a>
          <div class="ms-auto d-flex gap-2 align-items-center">
            <a href="index.php#projects" class="btn btn-outline-light btn-sm">← Back to projects</a>
            <?php if (!isset($_SESSION['user_id'])): ?>
            <a href="login.php" class="btn btn-primary btn-sm">Login</a>
            <?php else: ?>
            <a href="project-detail-enhanced.php?type=<?php echo htmlspecialchars($project_type, ENT_QUOTES, 'UTF-8'); ?>&amp;id=<?php echo (int) $project_id; ?>" class="btn btn-light btn-sm">Staff view</a>
            <?php endif; ?>
          </div>
        </nav>
      </div>
    </header>

    <main class="py-4">
        <div class="container">
            <div class="public-catalog-detail-hero">
            <div class="row align-items-center mb-3">
                <div class="col">
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <div>
                            <span class="badge bg-primary"><?php echo strtoupper(htmlspecialchars($project_type)); ?></span>
                            <span class="badge <?php echo htmlspecialchars(stage_badge_class((string) $project['current_stage']), ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars((string) $project['current_stage']); ?>
                            </span>
                        </div>
                        <div>
                            <h1 class="h3 mb-1 public-catalog-detail-title"><?php echo htmlspecialchars((string) $project['project_code']); ?></h1>
                            <p class="public-catalog-detail-subtitle mb-0"><?php echo htmlspecialchars((string) $project['project_title']); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <ul class="nav nav-tabs public-catalog-nav-tabs mb-4" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="pub-overview-tab" data-bs-toggle="tab" data-bs-target="#pub-overview" type="button" role="tab">
                        <i class="fas fa-info-circle"></i> Overview
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="pub-financial-tab" data-bs-toggle="tab" data-bs-target="#pub-financial" type="button" role="tab">
                        <i class="fas fa-money-bill"></i> Financial
                    </button>
                </li>
                <?php if ($project_type === 'afme'): ?>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="pub-machinery-tab" data-bs-toggle="tab" data-bs-target="#pub-machinery" type="button" role="tab">
                        <i class="fas fa-cog"></i> Machinery
                    </button>
                </li>
                <?php endif; ?>
            </ul>
            </div>

            <div class="tab-content">
                <div class="tab-pane fade show active" id="pub-overview" role="tabpanel">
                    <div class="row g-3">
                        <div class="col-lg-8">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-white"><h6 class="mb-0">Project information</h6></div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-6"><strong>Province</strong><p class="mb-0"><?php echo htmlspecialchars((string) ($project['province'] ?? 'N/A')); ?></p></div>
                                        <div class="col-md-6"><strong>Municipality</strong><p class="mb-0"><?php echo htmlspecialchars((string) ($project['municipality'] ?? 'N/A')); ?></p></div>
                                        <div class="col-md-6"><strong>Barangay</strong><p class="mb-0"><?php echo htmlspecialchars((string) ($project['barangay'] ?? 'N/A')); ?></p></div>
                                        <div class="col-md-6"><strong>Purok</strong><p class="mb-0"><?php $pu = trim((string) ($project['district'] ?? '')); echo htmlspecialchars($pu !== '' ? $pu : 'N/A'); ?></p></div>
                                        <div class="col-12"><strong>Description</strong><p class="mb-0"><?php echo htmlspecialchars((string) ($project['description'] ?? 'No description')); ?></p></div>
                                    </div>
                                </div>
                            </div>
                            <div class="card border-0 shadow-sm mt-3">
                                <div class="card-header bg-white"><h6 class="mb-0">Progress</h6></div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Physical: <strong><?php echo $physicalProgressDisplay; ?>%</strong></label>
                                        <div class="progress progress-lg progress-with-centered-label" style="--progress-label-color: <?php echo $physicalProgressWidth >= 55 ? '#fff' : '#111827'; ?>;">
                                            <div class="progress-bar progress-bar-physical progress-bar-w" style="--w: <?php echo $physicalProgressWidth; ?>%;"></div>
                                            <span class="progress-centered-label"><?php echo $physicalProgressDisplay; ?>%</span>
                                        </div>
                                    </div>
                                    <div class="mb-0">
                                        <label class="form-label">Financial: <strong><?php echo $financialProgressDisplay; ?>%</strong></label>
                                        <div class="progress progress-lg progress-with-centered-label" style="--progress-label-color: <?php echo $financialProgressWidth >= 55 ? '#fff' : '#111827'; ?>;">
                                            <div class="progress-bar bg-success progress-bar-w" style="--w: <?php echo $financialProgressWidth; ?>%;"></div>
                                            <span class="progress-centered-label"><?php echo $financialProgressDisplay; ?>%</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="card border-0 shadow-sm mb-3">
                                <div class="card-body text-center">
                                    <div class="display-6 text-primary">₱<?php echo number_format((float) ($project['proposed_amount'] ?? 0), 0); ?></div>
                                    <small class="text-muted">Proposed amount</small>
                                </div>
                            </div>
                            <div class="card border-0 shadow-sm mb-3">
                                <div class="card-body text-center">
                                    <div class="display-6 text-success">₱<?php echo number_format((float) ($project['allocated_amount'] ?? 0), 0); ?></div>
                                    <small class="text-muted">Allocated amount</small>
                                </div>
                            </div>
                            <div class="card border-0 shadow-sm mb-3">
                                <div class="card-body small">
                                    <strong>Timeline</strong>
                                    <div class="mt-2">
                                        <div class="mb-2"><strong>Created:</strong> <?php echo date('M d, Y', strtotime((string) $project['created_at'])); ?></div>
                                        <div><strong>Updated:</strong> <?php echo date('M d, Y', strtotime((string) $project['updated_at'])); ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="card border-0 shadow-sm">
                                <div class="card-body">
                                    <strong>Implementation stage</strong>
                                    <div class="mt-2">
                                        <canvas id="pubStageChart" height="120"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="pub-financial" role="tabpanel">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white"><h6 class="mb-0">Financial records</h6></div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead class="table-light">
                                        <tr><th>Type</th><th>Amount</th><th>Reference</th><th>Date</th><th>Status</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($financial_records)): ?>
                                        <tr><td colspan="5" class="text-center text-muted py-3">No financial records published.</td></tr>
                                        <?php else: ?>
                                        <?php foreach ($financial_records as $record): ?>
                                        <tr>
                                            <td><span class="badge <?php echo htmlspecialchars(financial_record_badge_class((string) $record['record_type']), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($record['record_type']); ?></span></td>
                                            <td>₱<?php echo number_format((float) $record['amount'], 2); ?></td>
                                            <td><?php echo htmlspecialchars($record['reference_number'] !== '' ? $record['reference_number'] : '—'); ?></td>
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

                <?php if ($project_type === 'afme'): ?>
                <div class="tab-pane fade" id="pub-machinery" role="tabpanel">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white"><h6 class="mb-0">Equipment &amp; machinery</h6></div>
                        <div class="card-body">
                            <?php if (empty($machinery)): ?>
                            <div class="alert alert-light border mb-0">No machinery rows linked to this project.</div>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead class="table-light">
                                        <tr><th>Type</th><th>Qty</th><th>Unit cost</th><th>Total</th><th>Status</th><th></th></tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $total_machinery_cost = 0;
                                        foreach ($machinery as $mach):
                                            $cost = (float) ($mach['unit_quantity'] ?? 0) * (float) ($mach['unit_cost'] ?? 0);
                                            $total_machinery_cost += $cost;
                                            ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars((string) ($mach['machinery_type'] ?? '')); ?></td>
                                            <td><?php echo htmlspecialchars((string) ($mach['unit_quantity'] ?? '')); ?></td>
                                            <td>₱<?php echo number_format((float) ($mach['unit_cost'] ?? 0), 2); ?></td>
                                            <td>₱<?php echo number_format($cost, 2); ?></td>
                                            <td><?php echo htmlspecialchars((string) ($mach['machinery_status'] ?? $mach['current_status'] ?? '')); ?></td>
                                            <td><a class="btn btn-sm btn-outline-secondary" href="public-afme-detail.php?id=<?php echo (int) $mach['id']; ?>">Machinery profile</a></td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <tr class="table-active">
                                            <td colspan="4"><strong>Total machinery cost</strong></td>
                                            <td colspan="2"><strong>₱<?php echo number_format($total_machinery_cost, 2); ?></strong></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

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
              <li><a href="index.php#projects" class="text-muted text-decoration-none">Projects</a></li>
              <li><a href="team.php" class="text-muted text-decoration-none">Meet Our Team</a></li>
              <li><a href="contact.php" class="text-muted text-decoration-none">Contact</a></li>
              <li><a href="#" class="text-muted text-decoration-none">Privacy Policy</a></li>
            </ul>
          </div>
          <div class="col-md-4">
            <h6 class="fw-bold mb-3">Contact</h6>
            <p class="small text-muted">
              Malaybalay City, Bukidnon<br>
              Email: <a href="mailto:placeholder.abed.idm@example.com" class="text-muted">placeholder.abed.idm@example.com</a>
            </p>
          </div>
        </div>
        <hr class="my-3 border-secondary border-opacity-25" />
        <div class="text-center text-muted small">
          <p class="mb-0">&copy; <?php echo date('Y'); ?> ABED IDM Hub. All rights reserved.</p>
        </div>
      </div>
    </footer>

    <script src="assets/bootstrap/js/bootstrap.bundle.js"></script>
    <script>
        const stageLabel = <?php echo json_encode($stageShort, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const stages = ['Proposal', 'Pre-Implementation', 'Procurement', 'Implementation', 'Completed', 'Turned-Over'];
        let idx = stages.indexOf(stageLabel);
        if (idx < 0) { idx = 0; }
        const weights = stages.map(function (_, i) { return i <= idx ? 1 : 0.15; });
        new Chart(document.getElementById('pubStageChart'), {
            type: 'doughnut',
            data: {
                labels: stages,
                datasets: [{
                    data: weights,
                    backgroundColor: ['#f5c57a', '#7eb8d9', '#9ca3af', '#5b8def', '#5fd4a8', '#475569']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 10 } } } }
            }
        });
    </script>
</body>
</html>
