<?php
/**
 * Public read-only AFME machinery profile (linked parent project must be approved).
 */
declare(strict_types=1);

session_name('ABED_IDM_HUB');
session_start();

require_once __DIR__ . '/config/database.php';

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(404);
    exit('Not found');
}

$stmt = $conn->prepare('
    SELECT a.*,
           p.title AS parent_project_title,
           p.project_code,
           p.approval_status AS parent_approval,
           p.id AS parent_project_id
    FROM afme a
    LEFT JOIN projects p ON p.id = a.project_id
    WHERE a.id = ?
    LIMIT 1
');
$stmt->bind_param('i', $id);
$stmt->execute();
$m = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$m) {
    http_response_code(404);
    exit('Not found');
}

$pid = (int) ($m['project_id'] ?? 0);
if ($pid > 0 && (string) ($m['parent_approval'] ?? '') !== 'Approved') {
    http_response_code(404);
    exit('Not found');
}

$documents = [];
$rawDocs = json_decode((string) ($m['documents'] ?? '[]'), true);
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
            'file_name' => (string) ($doc['file_name'] ?? 'Document'),
            'doc_type' => (string) ($doc['doc_type'] ?? 'Document'),
            'upload_date' => (string) ($doc['upload_date'] ?? ''),
        ];
    }
    usort($documents, static function ($a, $b) {
        return strcmp((string) ($b['upload_date'] ?? ''), (string) ($a['upload_date'] ?? ''));
    });
}

$conn->close();

$code = (string) ($m['project_code'] ?? '');
if ($code === '') {
    $code = 'AFME #' . $id;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($m['machine_name'] ?? 'Machinery'); ?> — ABED IDM Hub</title>
    <link rel="stylesheet" href="assets/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="public-home public-catalog-detail">
    <header class="public-header">
      <div class="container">
        <nav class="navbar navbar-expand-lg py-3 align-items-lg-center public-navbar" aria-label="Public navigation">
          <a class="navbar-brand d-flex align-items-center gap-2 public-navbar-brand" href="index.php">
            <img src="logos/abed_logo.png" alt="" class="public-brand-logo flex-shrink-0" width="40" height="40" />
            <span class="public-brand-title fw-bold text-white small">AFME · Public profile</span>
          </a>
          <div class="ms-auto d-flex gap-2">
            <a href="index.php#projects" class="btn btn-outline-light btn-sm">← Projects</a>
            <?php if ($pid > 0): ?>
            <a href="public-project-detail.php?type=afme&amp;id=<?php echo $pid; ?>" class="btn btn-light btn-sm">Parent project</a>
            <?php endif; ?>
          </div>
        </nav>
      </div>
    </header>

    <main class="py-4">
        <div class="container">
            <div class="public-catalog-detail-hero mb-4">
                <span class="badge bg-warning text-dark">AFME</span>
                <span class="badge bg-secondary"><?php echo htmlspecialchars((string) ($m['current_status'] ?? '')); ?></span>
                <h1 class="h3 mt-2 mb-1 public-catalog-detail-title"><?php echo htmlspecialchars((string) ($m['machine_name'] ?? '')); ?></h1>
                <p class="public-catalog-detail-subtitle mb-0">
                    <strong><?php echo htmlspecialchars($code); ?></strong>
                    <?php if (!empty($m['parent_project_title'])): ?>
                    · <?php echo htmlspecialchars((string) $m['parent_project_title']); ?>
                    <?php endif; ?>
                </p>
            </div>

            <div class="row g-3">
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white"><h6 class="mb-0">Beneficiary &amp; specifications</h6></div>
                        <div class="card-body">
                            <div class="row g-3 small">
                                <div class="col-md-6"><strong>Beneficiary</strong><p class="mb-0"><?php echo htmlspecialchars((string) ($m['beneficiary_name'] ?? '—')); ?></p></div>
                                <div class="col-md-6"><strong>Machinery type</strong><p class="mb-0"><?php echo htmlspecialchars((string) ($m['machinery_type'] ?? '—')); ?></p></div>
                                <div class="col-md-6"><strong>Brand</strong><p class="mb-0"><?php echo htmlspecialchars((string) ($m['brand'] ?? '—')); ?></p></div>
                                <div class="col-md-6"><strong>Serial</strong><p class="mb-0"><?php echo htmlspecialchars((string) ($m['serial_number'] ?? '—')); ?></p></div>
                                <div class="col-12"><strong>Specifications</strong><p class="mb-0"><?php echo nl2br(htmlspecialchars((string) ($m['specifications'] ?? '—'))); ?></p></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body text-center">
                            <div class="h4 text-success mb-0">₱<?php echo number_format((float) ($m['amount_allocated'] ?? 0), 2); ?></div>
                            <small class="text-muted">Amount allocated</small>
                        </div>
                    </div>
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white"><h6 class="mb-0">Document privacy</h6></div>
                        <div class="card-body">
                            <p class="text-muted small mb-0">Supporting documents are private and available only to authorized internal staff.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer class="public-footer py-4">
      <div class="container text-center text-muted small">
        <p class="mb-0">&copy; <?php echo date('Y'); ?> ABED IDM Hub</p>
      </div>
    </footer>

    <script src="assets/bootstrap/js/bootstrap.bundle.js"></script>
</body>
</html>
