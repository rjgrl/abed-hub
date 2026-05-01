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

function isApprovedDoc(array $doc): bool {
    return (string) ($doc['review_status'] ?? '') === 'Approved';
}

$type = strtolower(trim((string) ($_GET['type'] ?? '')));
$id = (int) ($_GET['id'] ?? 0);
if (!in_array($type, ['fspf', 'idp', 'afme'], true) || $id <= 0) {
    http_response_code(404);
    die('Project not found.');
}

$hasApprovalStatus = tableHasColumn($conn, 'projects', 'approval_status');
$hasProjectDocs = tableHasColumn($conn, 'projects', 'documents');

$projectSql = "SELECT id, project_type, project_code, title, current_stage, description, allocated_amount,
                      municipality, province, barangay, latitude, longitude, created_at, updated_at
               FROM projects
               WHERE id = ? AND project_type = ?";
if ($hasApprovalStatus) {
    $projectSql .= " AND approval_status = 'Approved'";
}
$stmt = $conn->prepare($projectSql);
$stmt->bind_param('is', $id, $type);
$stmt->execute();
$project = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$project) {
    http_response_code(404);
    die('Project not found or not approved for public viewing.');
}

$approvedDocs = [];
if ($hasProjectDocs) {
    $docStmt = $conn->prepare("SELECT documents FROM projects WHERE id = ?");
    $docStmt->bind_param('i', $id);
    $docStmt->execute();
    $docRow = $docStmt->get_result()->fetch_assoc();
    $docStmt->close();
    $docs = json_decode((string) ($docRow['documents'] ?? '[]'), true);
    if (is_array($docs)) {
        foreach ($docs as $doc) {
            if (!is_array($doc) || !isApprovedDoc($doc)) {
                continue;
            }
            $approvedDocs[] = [
                'type' => (string) ($doc['document_type'] ?? $doc['doc_type'] ?? 'Document'),
                'name' => (string) ($doc['original_filename'] ?? $doc['file_name'] ?? 'Document'),
                'date' => (string) ($doc['upload_date'] ?? ''),
            ];
        }
    }
}

$afmeMachines = [];
if ($type === 'afme') {
    $mStmt = $conn->prepare("SELECT id, machine_name, beneficiary_name, amount_allocated, current_status, documents FROM afme WHERE project_id = ?");
    $mStmt->bind_param('i', $id);
    $mStmt->execute();
    $afmeMachines = $mStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $mStmt->close();

    foreach ($afmeMachines as $machine) {
        $docs = json_decode((string) ($machine['documents'] ?? '[]'), true);
        if (!is_array($docs)) {
            continue;
        }
        foreach ($docs as $doc) {
            if (!is_array($doc) || !isApprovedDoc($doc)) {
                continue;
            }
            $approvedDocs[] = [
                'type' => (string) ($doc['doc_type'] ?? $doc['document_type'] ?? 'Document'),
                'name' => (string) ($doc['file_name'] ?? $doc['original_filename'] ?? 'Document'),
                'date' => (string) ($doc['upload_date'] ?? ''),
            ];
        }
    }
}

usort($approvedDocs, static function (array $a, array $b): int {
    return strcmp((string) ($b['date'] ?? ''), (string) ($a['date'] ?? ''));
});

$lat = (float) ($project['latitude'] ?? 0);
$lng = (float) ($project['longitude'] ?? 0);
$hasCoords = !($lat == 0.0 && $lng == 0.0);

$conn->close();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Public Project View - <?php echo htmlspecialchars((string) ($project['project_code'] ?? 'Project')); ?></title>
  <link rel="stylesheet" href="assets/bootstrap/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <style>
    body { background: #f5f7fb; }
    .hero { background: #0f4c81; color: #fff; }
    #map { height: 360px; border-radius: 10px; }
    .panel { border: 0; border-radius: 12px; box-shadow: 0 6px 20px rgba(0,0,0,.06); }
  </style>
</head>
<body>
  <header class="hero py-3">
    <div class="container d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div>
        <strong>ABED Public Transparency Portal</strong>
        <div class="small text-white-50">Read-only project disclosure page</div>
      </div>
      <a href="index.php" class="btn btn-light btn-sm"><i class="fas fa-arrow-left me-1"></i> Back to Public List</a>
    </div>
  </header>

  <main class="py-4">
    <div class="container">
      <div class="card panel mb-3">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
            <div>
              <div class="d-flex align-items-center gap-2 mb-2">
                <span class="badge bg-primary"><?php echo strtoupper(htmlspecialchars((string) $project['project_type'])); ?></span>
                <span class="badge bg-light text-dark"><?php echo htmlspecialchars((string) ($project['current_stage'] ?? 'Unknown')); ?></span>
                <span class="badge bg-success">Approved</span>
              </div>
              <h3 class="mb-1"><?php echo htmlspecialchars((string) ($project['project_code'] ?? 'N/A')); ?></h3>
              <p class="mb-2"><?php echo htmlspecialchars((string) ($project['title'] ?? 'Untitled Project')); ?></p>
              <div class="text-muted small">
                <?php echo htmlspecialchars(trim((($project['barangay'] ?? '') ? ($project['barangay'] . ', ') : '') . (($project['municipality'] ?? '') ? ($project['municipality'] . ', ') : '') . ($project['province'] ?? ''))); ?>
              </div>
            </div>
            <div class="text-end">
              <div class="text-muted small">Allocated Budget</div>
              <div class="h5 mb-0">₱<?php echo number_format((float) ($project['allocated_amount'] ?? 0), 2); ?></div>
            </div>
          </div>
          <hr>
          <p class="mb-0"><?php echo nl2br(htmlspecialchars((string) ($project['description'] ?? 'No description provided.'))); ?></p>
        </div>
      </div>

      <div class="row g-3">
        <div class="col-lg-7">
          <div class="card panel h-100">
            <div class="card-header bg-white">
              <h6 class="mb-0">Pinned Project Location (Public View)</h6>
            </div>
            <div class="card-body">
              <?php if ($hasCoords): ?>
                <div id="map"></div>
                <div class="small text-muted mt-2">Coordinates: <?php echo htmlspecialchars((string) $lat); ?>, <?php echo htmlspecialchars((string) $lng); ?></div>
              <?php else: ?>
                <div class="alert alert-secondary mb-0">No map coordinates available for this project.</div>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <div class="col-lg-5">
          <div class="card panel h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
              <h6 class="mb-0">Verified Uploads</h6>
              <span class="badge bg-secondary">Read-only</span>
            </div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>Type</th>
                      <th>File</th>
                      <th>Date</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($approvedDocs as $doc): ?>
                      <tr>
                        <td><?php echo htmlspecialchars($doc['type']); ?></td>
                        <td><?php echo htmlspecialchars($doc['name']); ?></td>
                        <td><?php echo htmlspecialchars($doc['date'] ? date('M j, Y', strtotime($doc['date'])) : 'N/A'); ?></td>
                      </tr>
                    <?php endforeach; ?>
                    <?php if (empty($approvedDocs)): ?>
                      <tr>
                        <td colspan="3" class="text-center text-muted py-3">No admin-approved uploads for this project.</td>
                      </tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>

      <?php if ($type === 'afme' && !empty($afmeMachines)): ?>
        <div class="card panel mt-3">
          <div class="card-header bg-white">
            <h6 class="mb-0">AFME Equipment (Public Information)</h6>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Machine</th>
                    <th>Beneficiary</th>
                    <th>Status</th>
                    <th>Amount</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($afmeMachines as $machine): ?>
                    <tr>
                      <td><?php echo htmlspecialchars((string) ($machine['machine_name'] ?? 'N/A')); ?></td>
                      <td><?php echo htmlspecialchars((string) ($machine['beneficiary_name'] ?? 'N/A')); ?></td>
                      <td><?php echo htmlspecialchars((string) ($machine['current_status'] ?? 'N/A')); ?></td>
                      <td>₱<?php echo number_format((float) ($machine['amount_allocated'] ?? 0), 2); ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </main>

  <script src="assets/bootstrap/js/bootstrap.bundle.min.js"></script>
  <?php if ($hasCoords): ?>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
      const map = L.map('map').setView([<?php echo $lat; ?>, <?php echo $lng; ?>], 13);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
      }).addTo(map);
      L.marker([<?php echo $lat; ?>, <?php echo $lng; ?>]).addTo(map)
        .bindPopup('<?php echo htmlspecialchars(addslashes((string) ($project['title'] ?? 'Project'))); ?>')
        .openPopup();
    </script>
  <?php endif; ?>
</body>
</html>
