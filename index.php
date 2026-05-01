<?php
session_name('ABED_IDM_HUB');
session_start();
require_once __DIR__ . '/config/database.php';

function tableHasColumn(mysqli $conn, string $table, string $column): bool {
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $safeTable = $conn->real_escape_string($table);
    $safeColumn = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
    $cache[$key] = $res && $res->num_rows > 0;
    return $cache[$key];
}

function formatDateSafe(?string $date): string {
    if (empty($date)) {
        return 'N/A';
    }
    $ts = strtotime($date);
    return $ts ? date('M j, Y', $ts) : 'N/A';
}

function matchesFilter(string $haystack, string $needle): bool {
    if ($needle === '') {
        return true;
    }
    return stripos($haystack, $needle) !== false;
}

function buildFilterUrl(array $overrides = []): string {
    $params = [
        'q' => (string) ($_GET['q'] ?? ''),
        'type' => (string) ($_GET['type'] ?? 'all'),
        'stage' => (string) ($_GET['stage'] ?? 'all'),
        'location' => (string) ($_GET['location'] ?? ''),
    ];
    foreach ($overrides as $key => $value) {
        $params[$key] = $value;
    }
    $params = array_filter($params, static function ($v): bool {
        return $v !== '' && $v !== 'all';
    });
    if (empty($params)) {
        return 'index.php';
    }
    return 'index.php?' . http_build_query($params);
}

$filterQuery = trim((string) ($_GET['q'] ?? ''));
$filterType = strtolower(trim((string) ($_GET['type'] ?? 'all')));
$filterStage = trim((string) ($_GET['stage'] ?? 'all'));
$filterLocation = trim((string) ($_GET['location'] ?? ''));
$allowedTypes = ['all', 'fspf', 'idp', 'afme'];
if (!in_array($filterType, $allowedTypes, true)) {
    $filterType = 'all';
}
$activeChips = [];
if ($filterQuery !== '') {
    $activeChips[] = [
        'label' => 'Search: ' . $filterQuery,
        'remove_url' => buildFilterUrl(['q' => '']),
    ];
}
if ($filterType !== 'all') {
    $activeChips[] = [
        'label' => 'Type: ' . strtoupper($filterType),
        'remove_url' => buildFilterUrl(['type' => 'all']),
    ];
}
if ($filterStage !== 'all') {
    $activeChips[] = [
        'label' => 'Stage: ' . $filterStage,
        'remove_url' => buildFilterUrl(['stage' => 'all']),
    ];
}
if ($filterLocation !== '') {
    $activeChips[] = [
        'label' => 'Location: ' . $filterLocation,
        'remove_url' => buildFilterUrl(['location' => '']),
    ];
}

$hasApprovalStatus = tableHasColumn($conn, 'projects', 'approval_status');
$hasProjectDocuments = tableHasColumn($conn, 'projects', 'documents');
$projectApprovedWhere = $hasApprovalStatus ? "WHERE approval_status = 'Approved'" : '';

$fspf_count = (int) (($conn->query("SELECT COUNT(*) AS total FROM projects WHERE project_type = 'fspf'" . ($projectApprovedWhere ? " AND approval_status = 'Approved'" : ''))->fetch_assoc()['total']) ?? 0);
$idp_count = (int) (($conn->query("SELECT COUNT(*) AS total FROM projects WHERE project_type = 'idp'" . ($projectApprovedWhere ? " AND approval_status = 'Approved'" : ''))->fetch_assoc()['total']) ?? 0);
$afme_count = (int) (($conn->query("SELECT COUNT(*) AS total FROM projects WHERE project_type = 'afme'" . ($projectApprovedWhere ? " AND approval_status = 'Approved'" : ''))->fetch_assoc()['total']) ?? 0);

$fspf_funded = (float) (($conn->query("SELECT COALESCE(SUM(allocated_amount),0) AS total FROM projects WHERE project_type = 'fspf'" . ($projectApprovedWhere ? " AND approval_status = 'Approved'" : ''))->fetch_assoc()['total']) ?? 0);
$idp_funded = (float) (($conn->query("SELECT COALESCE(SUM(allocated_amount),0) AS total FROM projects WHERE project_type = 'idp'" . ($projectApprovedWhere ? " AND approval_status = 'Approved'" : ''))->fetch_assoc()['total']) ?? 0);
$afme_funded = (float) (($conn->query("SELECT COALESCE(SUM(allocated_amount),0) AS total FROM projects WHERE project_type = 'afme'" . ($projectApprovedWhere ? " AND approval_status = 'Approved'" : ''))->fetch_assoc()['total']) ?? 0);

$featuredProjects = [];
$featuredRes = $conn->query("
    SELECT id, project_type, project_code, title, current_stage, allocated_amount,
           municipality, province, latitude, longitude, created_at
    FROM projects
    " . $projectApprovedWhere . "
    ORDER BY created_at DESC
    LIMIT 24
");
if ($featuredRes) {
    foreach ($featuredRes->fetch_all(MYSQLI_ASSOC) as $row) {
        $lat = (float) ($row['latitude'] ?? 0);
        $lng = (float) ($row['longitude'] ?? 0);
        $featuredProjects[] = [
            'id' => (int) $row['id'],
            'type' => strtolower((string) ($row['project_type'] ?? 'fspf')),
            'project_code' => (string) ($row['project_code'] ?? ''),
            'title' => (string) ($row['title'] ?? 'Untitled Project'),
            'current_stage' => (string) ($row['current_stage'] ?? 'Unknown'),
            'allocated_amount' => (float) ($row['allocated_amount'] ?? 0),
            'municipality' => (string) ($row['municipality'] ?? ''),
            'province' => (string) ($row['province'] ?? ''),
            'latitude' => $lat,
            'longitude' => $lng,
            'has_coords' => !($lat == 0.0 && $lng == 0.0),
            'created_at' => (string) ($row['created_at'] ?? ''),
        ];
    }
}
$filteredProjects = [];
foreach ($featuredProjects as $project) {
    if ($filterType !== 'all' && $project['type'] !== $filterType) {
        continue;
    }
    if ($filterStage !== 'all' && strcasecmp((string) ($project['current_stage'] ?? ''), $filterStage) !== 0) {
        continue;
    }
    $locationText = trim(((string) ($project['municipality'] ?? '')) . ' ' . ((string) ($project['province'] ?? '')));
    if (!matchesFilter($locationText, $filterLocation)) {
        continue;
    }
    $searchText = ((string) ($project['project_code'] ?? '')) . ' ' . ((string) ($project['title'] ?? '')) . ' ' . ((string) ($project['current_stage'] ?? ''));
    if (!matchesFilter($searchText, $filterQuery)) {
        continue;
    }
    $filteredProjects[] = $project;
}
$featuredProjects = array_slice($filteredProjects, 0, 12);

$approvedUploads = [];
if ($hasProjectDocuments) {
    $projectDocsQuery = "
        SELECT id, project_type, project_code, title, documents
        FROM projects
        " . ($hasApprovalStatus ? "WHERE approval_status = 'Approved' AND" : "WHERE") . "
        documents IS NOT NULL AND documents <> '' AND documents <> '[]'
    ";
    $projectDocsRes = $conn->query($projectDocsQuery);
    if ($projectDocsRes) {
        foreach ($projectDocsRes->fetch_all(MYSQLI_ASSOC) as $row) {
            $docs = json_decode((string) ($row['documents'] ?? '[]'), true);
            if (!is_array($docs)) {
                continue;
            }
            foreach ($docs as $doc) {
                if (!is_array($doc)) {
                    continue;
                }
                if ((string) ($doc['review_status'] ?? '') !== 'Approved') {
                    continue;
                }
                $approvedUploads[] = [
                    'project_id' => (int) $row['id'],
                    'type' => strtolower((string) ($row['project_type'] ?? 'fspf')),
                    'project_code' => (string) ($row['project_code'] ?? ''),
                    'project_title' => (string) ($row['title'] ?? 'Untitled Project'),
                    'doc_type' => (string) ($doc['document_type'] ?? $doc['doc_type'] ?? 'Document'),
                    'file_name' => (string) ($doc['original_filename'] ?? $doc['file_name'] ?? 'Unnamed file'),
                    'upload_date' => (string) ($doc['upload_date'] ?? ''),
                    'source' => 'project',
                ];
            }
        }
    }
}

$afmeDocsQuery = "
    SELECT a.project_id, a.documents, p.project_type, p.project_code, p.title
    FROM afme a
    INNER JOIN projects p ON p.id = a.project_id
    " . ($hasApprovalStatus ? "WHERE p.approval_status = 'Approved' AND" : "WHERE") . "
    a.documents IS NOT NULL AND a.documents <> '' AND a.documents <> '[]'
";
$afmeDocsRes = $conn->query($afmeDocsQuery);
if ($afmeDocsRes) {
    foreach ($afmeDocsRes->fetch_all(MYSQLI_ASSOC) as $row) {
        $docs = json_decode((string) ($row['documents'] ?? '[]'), true);
        if (!is_array($docs)) {
            continue;
        }
        foreach ($docs as $doc) {
            if (!is_array($doc)) {
                continue;
            }
            if ((string) ($doc['review_status'] ?? '') !== 'Approved') {
                continue;
            }
            $approvedUploads[] = [
                'project_id' => (int) $row['project_id'],
                'type' => strtolower((string) ($row['project_type'] ?? 'afme')),
                'project_code' => (string) ($row['project_code'] ?? ''),
                'project_title' => (string) ($row['title'] ?? 'AFME Project'),
                'doc_type' => (string) ($doc['doc_type'] ?? $doc['document_type'] ?? 'Document'),
                'file_name' => (string) ($doc['file_name'] ?? $doc['original_filename'] ?? 'Unnamed file'),
                'upload_date' => (string) ($doc['upload_date'] ?? ''),
                'source' => 'afme',
            ];
        }
    }
}

usort($approvedUploads, static function (array $a, array $b): int {
    return strcmp((string) ($b['upload_date'] ?? ''), (string) ($a['upload_date'] ?? ''));
});
$filteredUploads = [];
foreach ($approvedUploads as $upload) {
    if ($filterType !== 'all' && strtolower((string) ($upload['type'] ?? '')) !== $filterType) {
        continue;
    }
    $searchText = ((string) ($upload['project_code'] ?? '')) . ' '
        . ((string) ($upload['project_title'] ?? '')) . ' '
        . ((string) ($upload['doc_type'] ?? '')) . ' '
        . ((string) ($upload['file_name'] ?? ''));
    if (!matchesFilter($searchText, $filterQuery)) {
        continue;
    }
    $filteredUploads[] = $upload;
}
$approvedUploads = array_slice($filteredUploads, 0, 20);

$conn->close();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ABED IDM Hub - Public Transparency Portal</title>
  <link rel="stylesheet" href="assets/bootstrap/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <style>
    body { background: #f5f7fb; color: #1f2a37; }
    .hero { background: linear-gradient(135deg, #0f4c81 0%, #1f6fb2 100%); color: #fff; padding: 4rem 0; }
    .stat-card { border: 0; box-shadow: 0 8px 24px rgba(0,0,0,0.08); border-radius: 12px; }
    .panel { border: 0; box-shadow: 0 6px 20px rgba(0,0,0,0.06); border-radius: 12px; }
    .project-card { border: 0; box-shadow: 0 4px 14px rgba(0,0,0,0.06); border-radius: 12px; height: 100%; }
    .project-meta { font-size: 0.85rem; color: #6b7280; }
    .readonly-badge { font-size: .75rem; }
  </style>
</head>
<body>
  <header class="hero">
    <div class="container">
      <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div>
          <h1 class="display-6 fw-bold mb-2">ABED Public Transparency Portal</h1>
          <p class="mb-0">Public view of approved projects and verified uploads for accountability.</p>
        </div>
        <div class="d-flex gap-2">
          <?php if (!isset($_SESSION['user_id'])): ?>
            <a href="login.php" class="btn btn-light"><i class="fas fa-sign-in-alt me-1"></i> Login</a>
          <?php else: ?>
            <a href="dashboard.php" class="btn btn-light"><i class="fas fa-gauge-high me-1"></i> Dashboard</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </header>

  <main class="py-5">
    <div class="container">
      <section class="mb-4">
        <div class="card panel mb-3">
          <div class="card-header bg-white">
            <h5 class="mb-0">Public Search & Filters</h5>
          </div>
          <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
              <div class="col-md-4">
                <label class="form-label mb-1">Search</label>
                <input type="text" name="q" class="form-control" placeholder="Project code, title, document..." value="<?php echo htmlspecialchars($filterQuery); ?>">
              </div>
              <div class="col-md-2">
                <label class="form-label mb-1">Type</label>
                <select name="type" class="form-select">
                  <option value="all" <?php echo $filterType === 'all' ? 'selected' : ''; ?>>All</option>
                  <option value="fspf" <?php echo $filterType === 'fspf' ? 'selected' : ''; ?>>FSPF</option>
                  <option value="idp" <?php echo $filterType === 'idp' ? 'selected' : ''; ?>>IDP</option>
                  <option value="afme" <?php echo $filterType === 'afme' ? 'selected' : ''; ?>>AFME</option>
                </select>
              </div>
              <div class="col-md-3">
                <label class="form-label mb-1">Stage</label>
                <select name="stage" class="form-select">
                  <option value="all" <?php echo $filterStage === 'all' ? 'selected' : ''; ?>>All Stages</option>
                  <option value="Proposal" <?php echo $filterStage === 'Proposal' ? 'selected' : ''; ?>>Proposal</option>
                  <option value="Pre-Implementation" <?php echo $filterStage === 'Pre-Implementation' ? 'selected' : ''; ?>>Pre-Implementation</option>
                  <option value="Procurement" <?php echo $filterStage === 'Procurement' ? 'selected' : ''; ?>>Procurement</option>
                  <option value="Implementation" <?php echo $filterStage === 'Implementation' ? 'selected' : ''; ?>>Implementation</option>
                  <option value="Completed" <?php echo $filterStage === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                </select>
              </div>
              <div class="col-md-3">
                <label class="form-label mb-1">Location</label>
                <input type="text" name="location" class="form-control" placeholder="Municipality or province" value="<?php echo htmlspecialchars($filterLocation); ?>">
              </div>
              <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="fas fa-filter me-1"></i> Apply</button>
                <a href="index.php" class="btn btn-outline-secondary">Reset</a>
              </div>
            </form>
            <?php if (!empty($activeChips)): ?>
              <div class="mt-3 d-flex align-items-center flex-wrap gap-2">
                <?php foreach ($activeChips as $chip): ?>
                  <a href="<?php echo htmlspecialchars($chip['remove_url']); ?>" class="badge rounded-pill text-bg-primary text-decoration-none">
                    <?php echo htmlspecialchars($chip['label']); ?> <i class="fas fa-times ms-1"></i>
                  </a>
                <?php endforeach; ?>
                <a href="index.php" class="badge rounded-pill text-bg-secondary text-decoration-none">Clear All</a>
              </div>
            <?php endif; ?>
          </div>
        </div>
        <div class="row g-3">
          <div class="col-md-4">
            <div class="card stat-card">
              <div class="card-body">
                <div class="text-muted small">Approved FSPF Projects</div>
                <div class="h3 mb-0"><?php echo $fspf_count; ?></div>
                <div class="small text-success">₱<?php echo number_format($fspf_funded, 2); ?></div>
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="card stat-card">
              <div class="card-body">
                <div class="text-muted small">Approved IDP Projects</div>
                <div class="h3 mb-0"><?php echo $idp_count; ?></div>
                <div class="small text-primary">₱<?php echo number_format($idp_funded, 2); ?></div>
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="card stat-card">
              <div class="card-body">
                <div class="text-muted small">Approved AFME Projects</div>
                <div class="h3 mb-0"><?php echo $afme_count; ?></div>
                <div class="small text-warning">₱<?php echo number_format($afme_funded, 2); ?></div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section class="mb-4">
        <div class="card panel">
          <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Approved Public Projects</h5>
            <span class="badge bg-primary"><?php echo count($featuredProjects); ?> result(s)</span>
          </div>
          <div class="card-body">
            <div class="row g-3">
              <?php foreach ($featuredProjects as $project): ?>
                <div class="col-md-6 col-lg-4">
                  <div class="card project-card">
                    <div class="card-body">
                      <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="badge bg-primary"><?php echo strtoupper(htmlspecialchars($project['type'])); ?></span>
                        <span class="badge bg-light text-dark"><?php echo htmlspecialchars($project['current_stage']); ?></span>
                      </div>
                      <h6 class="mb-1"><?php echo htmlspecialchars($project['project_code']); ?></h6>
                      <p class="mb-2"><?php echo htmlspecialchars($project['title']); ?></p>
                      <div class="project-meta mb-2">
                        <?php echo htmlspecialchars(trim(($project['municipality'] ? $project['municipality'] . ', ' : '') . $project['province'])); ?>
                      </div>
                      <div class="project-meta mb-3">Allocated: ₱<?php echo number_format((float) $project['allocated_amount'], 2); ?></div>
                      <a href="public-project.php?type=<?php echo urlencode($project['type']); ?>&id=<?php echo (int) $project['id']; ?>" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-map-marker-alt me-1"></i> View Public Details
                      </a>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
              <?php if (empty($featuredProjects)): ?>
                <div class="col-12">
                  <div class="text-center text-muted py-4">No approved projects available for public display.</div>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </section>

      <section>
        <div class="card panel">
          <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Verified Uploads</h5>
            <small class="text-muted"><?php echo count($approvedUploads); ?> record(s)</small>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-hover mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Project</th>
                    <th>Type</th>
                    <th>Document</th>
                    <th>File</th>
                    <th>Approved Upload Date</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($approvedUploads as $upload): ?>
                    <tr>
                      <td>
                        <strong><?php echo htmlspecialchars($upload['project_code']); ?></strong><br>
                        <small class="text-muted"><?php echo htmlspecialchars($upload['project_title']); ?></small>
                      </td>
                      <td><span class="badge bg-secondary"><?php echo strtoupper(htmlspecialchars($upload['type'])); ?></span></td>
                      <td><?php echo htmlspecialchars($upload['doc_type']); ?></td>
                      <td><?php echo htmlspecialchars($upload['file_name']); ?></td>
                      <td><?php echo htmlspecialchars(formatDateSafe($upload['upload_date'])); ?></td>
                      <td>
                        <a href="public-project.php?type=<?php echo urlencode($upload['type']); ?>&id=<?php echo (int) $upload['project_id']; ?>" class="btn btn-sm btn-outline-primary">
                          View
                        </a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  <?php if (empty($approvedUploads)): ?>
                    <tr>
                      <td colspan="6" class="text-center text-muted py-4">No verified uploads available yet.</td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </section>
    </div>
  </main>

  <footer class="bg-dark text-white py-4 mt-3">
    <div class="container d-flex justify-content-between flex-wrap gap-2">
      <span>ABED IDM Hub Transparency Portal</span>
      <span class="text-white-50">Public view for verified data</span>
    </div>
  </footer>

  <script src="assets/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
