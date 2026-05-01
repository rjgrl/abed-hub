<?php
require_once __DIR__ . '/components/layout.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/functions/helpers.php';

requireLogin();
requireRoles(['admin']);

$page_title = 'Super Admin Dashboard';
$success_msg = '';
$error_msg = '';
$uploadStatusFilter = (string) ($_GET['upload_status'] ?? 'all');
$uploadSourceFilter = (string) ($_GET['upload_source'] ?? 'all');
$allowedUploadStatus = ['all', 'Pending', 'Approved', 'Rejected'];
$allowedUploadSource = ['all', 'project', 'afme'];
if (!in_array($uploadStatusFilter, $allowedUploadStatus, true)) {
    $uploadStatusFilter = 'all';
}
if (!in_array($uploadSourceFilter, $allowedUploadSource, true)) {
    $uploadSourceFilter = 'all';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'review_pending_project') {
    $recordId = (int) ($_POST['record_id'] ?? 0);
    $decision = (string) ($_POST['decision'] ?? '');
    $allowedDecisions = ['Approved', 'Rejected'];
    if ($recordId <= 0 || !in_array($decision, $allowedDecisions, true)) {
        $error_msg = 'Invalid project review request.';
    } else {
        $chk = $conn->prepare('SELECT id, approval_status FROM projects WHERE id = ? LIMIT 1');
        $chk->bind_param('i', $recordId);
        $chk->execute();
        $prow = $chk->get_result()->fetch_assoc();
        $chk->close();
        if (!$prow || (string) ($prow['approval_status'] ?? '') !== 'Pending') {
            $error_msg = 'Project not found or not pending approval.';
        } else {
            $up = $conn->prepare('UPDATE projects SET approval_status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND approval_status = ?');
            $pending = 'Pending';
            $up->bind_param('sis', $decision, $recordId, $pending);
            if ($up->execute() && $up->affected_rows > 0) {
                $success_msg = $decision === 'Approved'
                    ? 'Project approved and is now visible in the catalog.'
                    : 'Project registration rejected.';
                logAudit('ADMIN_REVIEW_PROJECT_REGISTRATION', null, $recordId, null, [
                    'decision' => $decision,
                ]);
            } else {
                $error_msg = 'Failed to update project approval status.';
            }
            $up->close();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'review_upload') {
    $source = (string) ($_POST['source'] ?? '');
    $recordId = (int) ($_POST['record_id'] ?? 0);
    $docId = (int) ($_POST['doc_id'] ?? 0);
    $decision = (string) ($_POST['decision'] ?? '');
    $allowedDecisions = ['Approved', 'Rejected'];

    if (!in_array($source, ['project', 'afme'], true) || $recordId <= 0 || $docId <= 0 || !in_array($decision, $allowedDecisions, true)) {
        $error_msg = 'Invalid review request.';
    } else {
        $column = $source === 'project' ? 'documents' : 'documents';
        $table = $source === 'project' ? 'projects' : 'afme';
        $idCol = 'id';

        $stmt = $conn->prepare("SELECT {$column} FROM {$table} WHERE {$idCol} = ?");
        $stmt->bind_param('i', $recordId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            $error_msg = 'Record not found.';
        } else {
            $docs = json_decode((string) ($row['documents'] ?? '[]'), true);
            if (!is_array($docs)) {
                $docs = [];
            }

            $updated = false;
            foreach ($docs as &$doc) {
                if (!is_array($doc) || (int) ($doc['id'] ?? 0) !== $docId) {
                    continue;
                }
                $doc['review_status'] = $decision;
                $doc['reviewed_by'] = (int) ($_SESSION['user_id'] ?? 0);
                $doc['reviewed_at'] = date('Y-m-d H:i:s');
                $updated = true;
                break;
            }
            unset($doc);

            if (!$updated) {
                $error_msg = 'Document not found.';
            } else {
                $docsJson = json_encode($docs);
                $up = $conn->prepare("UPDATE {$table} SET {$column} = ?, updated_at = CURRENT_TIMESTAMP WHERE {$idCol} = ?");
                $up->bind_param('si', $docsJson, $recordId);
                if ($up->execute()) {
                    $success_msg = "Document marked as {$decision}.";
                    logAudit('ADMIN_REVIEW_UPLOAD', null, null, null, [
                        'source' => $source,
                        'record_id' => $recordId,
                        'doc_id' => $docId,
                        'decision' => $decision
                    ]);
                } else {
                    $error_msg = 'Failed to save review decision.';
                }
                $up->close();
            }
        }
    }
}

$pendingUsers = $conn->query("
    SELECT id, full_name, username, email, office_unit, created_at
    FROM users
    WHERE is_active = 0
    ORDER BY created_at ASC
    LIMIT 50
")->fetch_all(MYSQLI_ASSOC);

$recentUploads = [];
$allUploads = [];
$userNames = [];
$uRes = $conn->query("SELECT id, full_name FROM users");
if ($uRes) {
    foreach ($uRes->fetch_all(MYSQLI_ASSOC) as $u) {
        $userNames[(int) $u['id']] = (string) $u['full_name'];
    }
}

$hasProjectDocumentsColumn = false;
$colCheck = $conn->query("SHOW COLUMNS FROM projects LIKE 'documents'");
if ($colCheck && $colCheck->num_rows > 0) {
    $hasProjectDocumentsColumn = true;
}

$projectRegs = $conn->query("
    SELECT id, project_type, project_code, title, created_at, updated_at, user_id, approval_status
    FROM projects
    WHERE approval_status IN ('Pending', 'Approved', 'Rejected')
    ORDER BY created_at DESC
    LIMIT 50
");
if ($projectRegs) {
    foreach ($projectRegs->fetch_all(MYSQLI_ASSOC) as $row) {
        $uploaderId = (int) ($row['user_id'] ?? 0);
        $allUploads[] = [
            'source' => 'project_registration',
            'record_id' => (int) $row['id'],
            'doc_id' => 0,
            'project_type' => strtoupper((string) ($row['project_type'] ?? '')),
            'project_id' => (int) $row['id'],
            'doc_type' => 'New project registration',
            'file_name' => (string) ($row['title'] ?? 'Untitled'),
            'upload_date' => $row['created_at'] ?? $row['updated_at'],
            'uploaded_by_name' => $userNames[$uploaderId] ?? 'Unknown',
            'review_status' => (string) ($row['approval_status'] ?? 'Pending'),
            'project_code' => (string) ($row['project_code'] ?? ''),
        ];
    }
}

$projectUploads = false;
if ($hasProjectDocumentsColumn) {
    $projectUploads = $conn->query("
        SELECT id, project_type, documents, updated_at
        FROM projects
        WHERE approval_status = 'Approved'
          AND documents IS NOT NULL AND documents <> '' AND documents <> '[]'
        ORDER BY updated_at DESC
        LIMIT 100
    ");
}

if ($projectUploads) {
    foreach ($projectUploads->fetch_all(MYSQLI_ASSOC) as $row) {
        $docs = json_decode((string) ($row['documents'] ?? ''), true);
        if (!is_array($docs)) {
            continue;
        }
        foreach ($docs as $doc) {
            if (!is_array($doc)) {
                continue;
            }
            $uploaderId = (int) ($doc['uploaded_by'] ?? 0);
            $allUploads[] = [
                'source' => 'project',
                'record_id' => (int) $row['id'],
                'doc_id' => (int) ($doc['id'] ?? 0),
                'project_type' => strtoupper((string) ($row['project_type'] ?? '')),
                'project_id' => (int) $row['id'],
                'doc_type' => $doc['document_type'] ?? ($doc['doc_type'] ?? 'Document'),
                'file_name' => $doc['original_filename'] ?? ($doc['file_name'] ?? 'Unnamed file'),
                'upload_date' => $doc['upload_date'] ?? $row['updated_at'],
                'uploaded_by_name' => $userNames[$uploaderId] ?? 'Unknown',
                'review_status' => $doc['review_status'] ?? 'Pending',
            ];
        }
    }
}

$afmeUploads = $conn->query("
    SELECT a.id AS afme_id, a.project_id, p.project_type, a.documents, a.updated_at
    FROM afme a
    LEFT JOIN projects p ON p.id = a.project_id
    WHERE a.documents IS NOT NULL AND a.documents <> '' AND a.documents <> '[]'
    ORDER BY a.updated_at DESC
    LIMIT 100
");

if ($afmeUploads) {
    foreach ($afmeUploads->fetch_all(MYSQLI_ASSOC) as $row) {
        $docs = json_decode((string) ($row['documents'] ?? ''), true);
        if (!is_array($docs)) {
            continue;
        }
        foreach ($docs as $doc) {
            if (!is_array($doc)) {
                continue;
            }
            $uploaderId = (int) ($doc['uploaded_by'] ?? 0);
            $allUploads[] = [
                'source' => 'afme',
                'record_id' => (int) $row['afme_id'],
                'doc_id' => (int) ($doc['id'] ?? 0),
                'project_type' => strtoupper((string) ($row['project_type'] ?? 'afme')),
                'project_id' => (int) $row['project_id'],
                'doc_type' => $doc['doc_type'] ?? 'Document',
                'file_name' => $doc['file_name'] ?? ($doc['name'] ?? 'Unnamed file'),
                'upload_date' => $doc['upload_date'] ?? $row['updated_at'],
                'uploaded_by_name' => $userNames[$uploaderId] ?? 'Unknown',
                'review_status' => $doc['review_status'] ?? 'Pending',
            ];
        }
    }
}

if (!empty($allUploads)) {
    usort($allUploads, static function ($a, $b) {
        return strcmp((string) ($b['upload_date'] ?? ''), (string) ($a['upload_date'] ?? ''));
    });
}

foreach ($allUploads as $upload) {
    $status = (string) ($upload['review_status'] ?? 'Pending');
    $source = (string) ($upload['source'] ?? 'project');
    if ($uploadStatusFilter !== 'all' && $status !== $uploadStatusFilter) {
        continue;
    }
    if ($uploadSourceFilter !== 'all') {
        if ($uploadSourceFilter === 'project') {
            if (!in_array($source, ['project', 'project_registration'], true)) {
                continue;
            }
        } elseif ($source !== $uploadSourceFilter) {
            continue;
        }
    }
    $recentUploads[] = $upload;
}
if (!empty($recentUploads)) {
    $recentUploads = array_slice($recentUploads, 0, 50);
}

renderAppLayout($page_title);
?>
<div class="container-fluid py-4">
    <?php if ($success_msg): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php echo htmlspecialchars($success_msg); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?php echo htmlspecialchars($error_msg); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row mb-4">
        <div class="col">
            <h1 class="h3 mb-0">Super Admin Dashboard</h1>
            <p class="text-muted mb-0">Approve accounts and manage employee uploads from one place.</p>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <p class="text-muted small mb-1">Pending Accounts</p>
                    <h2 class="mb-0"><?php echo count($pendingUsers); ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <p class="text-muted small mb-1">Uploads (Filtered)</p>
                    <h2 class="mb-0"><?php echo count($recentUploads); ?></h2>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0">Account Approval Queue</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Name</th>
                                    <th>Office</th>
                                    <th>Created</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendingUsers as $user): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($user['full_name']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($user['username']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($user['office_unit'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($user['created_at']))); ?></td>
                                        <td>
                                            <form method="POST" action="handlers/admin-user-approval.php" class="d-inline">
                                                <input type="hidden" name="user_id" value="<?php echo (int) $user['id']; ?>">
                                                <input type="hidden" name="decision" value="approve">
                                                <button class="btn btn-sm btn-success" type="submit">Approve</button>
                                            </form>
                                            <form method="POST" action="handlers/admin-user-approval.php" class="d-inline">
                                                <input type="hidden" name="user_id" value="<?php echo (int) $user['id']; ?>">
                                                <input type="hidden" name="decision" value="reject">
                                                <button class="btn btn-sm btn-outline-danger" type="submit">Reject</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($pendingUsers)): ?>
                                    <tr><td colspan="4" class="text-center text-muted">No pending account approvals.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h5 class="mb-0">Recent Employee Uploads</h5>
                        <form method="GET" class="d-flex align-items-center gap-2">
                            <select name="upload_status" class="form-select form-select-sm" style="min-width: 140px;">
                                <option value="all" <?php echo $uploadStatusFilter === 'all' ? 'selected' : ''; ?>>All Status</option>
                                <option value="Pending" <?php echo $uploadStatusFilter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="Approved" <?php echo $uploadStatusFilter === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                                <option value="Rejected" <?php echo $uploadStatusFilter === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                            <select name="upload_source" class="form-select form-select-sm" style="min-width: 130px;">
                                <option value="all" <?php echo $uploadSourceFilter === 'all' ? 'selected' : ''; ?>>All Sources</option>
                                <option value="project" <?php echo $uploadSourceFilter === 'project' ? 'selected' : ''; ?>>Project</option>
                                <option value="afme" <?php echo $uploadSourceFilter === 'afme' ? 'selected' : ''; ?>>AFME</option>
                            </select>
                            <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                            <a href="admin-dashboard.php?upload_status=Pending&upload_source=all" class="btn btn-sm btn-outline-secondary">Reset</a>
                        </form>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>File</th>
                                    <th>Project</th>
                                    <th>Uploaded By</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentUploads as $upload): ?>
                                    <?php
                                    $isRegistration = (($upload['source'] ?? '') === 'project_registration');
                                    $projLabel = ($upload['project_type'] ?? 'N/A')
                                        . ' '
                                        . ($upload['project_code'] ?? ('#' . (int) ($upload['project_id'] ?? 0)));
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($upload['file_name'] ?? 'Unnamed file'); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($upload['doc_type'] ?? 'Document'); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($projLabel); ?></td>
                                        <td><?php echo htmlspecialchars($upload['uploaded_by_name'] ?? 'Unknown'); ?></td>
                                        <td>
                                            <?php $reviewStatus = (string) ($upload['review_status'] ?? 'Pending'); ?>
                                            <span class="badge <?php echo $reviewStatus === 'Approved' ? 'bg-success' : ($reviewStatus === 'Rejected' ? 'bg-danger' : 'bg-warning text-dark'); ?>">
                                                <?php echo htmlspecialchars($reviewStatus); ?>
                                            </span>
                                            <div class="btn-group btn-group-sm ms-2">
                                                <?php if ($isRegistration && $reviewStatus === 'Pending'): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="review_pending_project">
                                                        <input type="hidden" name="record_id" value="<?php echo (int) $upload['record_id']; ?>">
                                                        <input type="hidden" name="decision" value="Approved">
                                                        <button type="submit" class="btn btn-outline-success" title="Approve project"><i class="fas fa-check"></i></button>
                                                    </form>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="review_pending_project">
                                                        <input type="hidden" name="record_id" value="<?php echo (int) $upload['record_id']; ?>">
                                                        <input type="hidden" name="decision" value="Rejected">
                                                        <button type="submit" class="btn btn-outline-danger" title="Reject registration"><i class="fas fa-times"></i></button>
                                                    </form>
                                                <?php elseif (!$isRegistration): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="review_upload">
                                                        <input type="hidden" name="source" value="<?php echo htmlspecialchars((string) $upload['source']); ?>">
                                                        <input type="hidden" name="record_id" value="<?php echo (int) $upload['record_id']; ?>">
                                                        <input type="hidden" name="doc_id" value="<?php echo (int) $upload['doc_id']; ?>">
                                                        <input type="hidden" name="decision" value="Approved">
                                                        <button type="submit" class="btn btn-outline-success" title="Approve"><i class="fas fa-check"></i></button>
                                                    </form>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="review_upload">
                                                        <input type="hidden" name="source" value="<?php echo htmlspecialchars((string) $upload['source']); ?>">
                                                        <input type="hidden" name="record_id" value="<?php echo (int) $upload['record_id']; ?>">
                                                        <input type="hidden" name="doc_id" value="<?php echo (int) $upload['doc_id']; ?>">
                                                        <input type="hidden" name="decision" value="Rejected">
                                                        <button type="submit" class="btn btn-outline-danger" title="Reject"><i class="fas fa-times"></i></button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($recentUploads)): ?>
                                    <tr><td colspan="4" class="text-center text-muted">No uploads found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php renderAppLayoutFooter(); ?>
