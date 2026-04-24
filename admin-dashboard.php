<?php
require_once __DIR__ . '/components/layout.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/functions/helpers.php';

requireLogin();
requireRoles(['admin']);

$page_title = 'Super Admin Dashboard';

$pendingUsers = $conn->query("
    SELECT id, full_name, username, email, office_unit, created_at
    FROM users
    WHERE is_active = 0
    ORDER BY created_at ASC
    LIMIT 50
")->fetch_all(MYSQLI_ASSOC);

$recentUploads = $conn->query("
    SELECT pd.id, pd.project_type, pd.project_id, pd.doc_type, pd.file_name, pd.upload_date, u.full_name AS uploaded_by_name
    FROM project_documents pd
    LEFT JOIN users u ON u.id = pd.uploaded_by
    ORDER BY pd.upload_date DESC
    LIMIT 50
")->fetch_all(MYSQLI_ASSOC);

renderAppLayout($page_title);
?>
<div class="container-fluid py-4">
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
                    <p class="text-muted small mb-1">Recent Uploads</p>
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
                    <h5 class="mb-0">Recent Employee Uploads</h5>
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
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($upload['file_name'] ?? 'Unnamed file'); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($upload['doc_type'] ?? 'Document'); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars(($upload['project_type'] ?? 'N/A') . ' #' . ($upload['project_id'] ?? '')); ?></td>
                                        <td><?php echo htmlspecialchars($upload['uploaded_by_name'] ?? 'Unknown'); ?></td>
                                        <td>
                                            <form method="POST" action="handlers/admin-upload-action.php" onsubmit="return confirm('Archive this upload record?');">
                                                <input type="hidden" name="document_id" value="<?php echo (int) $upload['id']; ?>">
                                                <button class="btn btn-sm btn-outline-warning" type="submit">Archive</button>
                                            </form>
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
