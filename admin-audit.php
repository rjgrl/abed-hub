<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/functions/helpers.php';
require_once __DIR__ . '/components/layout.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

requireLogin();
requireRoles(['admin', 'coordinator']);

$page_title = 'Audit Log - ABED IDM Hub';

$per_page  = 50;
$page      = max(1, intval($_GET['page'] ?? 1));
$offset    = ($page - 1) * $per_page;
$filter_user   = intval($_GET['user_id'] ?? 0);
$filter_action = $conn->real_escape_string($_GET['action'] ?? '');

$where  = [];
$params = [];
$types  = '';

if ($filter_user) {
    $where[]  = 'a.user_id = ?';
    $params[] = $filter_user;
    $types   .= 'i';
}
if ($filter_action !== '') {
    $where[]  = 'a.action LIKE ?';
    $params[] = '%' . $filter_action . '%';
    $types   .= 's';
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM audit_log a $whereClause");
if ($countStmt && !empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt && $countStmt->execute();
$total      = $countStmt ? (int) $countStmt->get_result()->fetch_assoc()['total'] : 0;
$total_pages = (int) ceil($total / $per_page);

$stmt = $conn->prepare(
    "SELECT a.*, TRIM(CONCAT_WS(' ', NULLIF(TRIM(u.first_name),''), NULLIF(TRIM(u.last_name),''))) AS full_name, u.username
     FROM audit_log a
     LEFT JOIN users u ON a.user_id = u.id
     $whereClause
     ORDER BY a.created_at DESC
     LIMIT ? OFFSET ?"
);
$allParams = array_merge($params, [$per_page, $offset]);
$allTypes  = $types . 'ii';
if ($stmt) {
    $stmt->bind_param($allTypes, ...$allParams);
    $stmt->execute();
    $logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $logs = [];
}

$users = $conn->query("SELECT id, first_name, last_name, username FROM users ORDER BY last_name, first_name")->fetch_all(MYSQLI_ASSOC);

renderAppLayout($page_title);
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Audit Log</h1>
            <p class="text-muted mb-0">Track all user actions across the system (<?php echo number_format($total); ?> entries)</p>
        </div>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Filter by User</label>
                    <select name="user_id" class="form-select">
                        <option value="">All Users</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?php echo $u['id']; ?>" <?php echo $filter_user == $u['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(user_display_name($u['first_name'] ?? '', $u['last_name'] ?? '') . ' (' . $u['username'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Filter by Action</label>
                    <input type="text" name="action" class="form-control"
                           placeholder="e.g. ARCHIVE_PROJECT"
                           value="<?php echo htmlspecialchars($filter_action); ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-primary w-100">
                        <i class="fas fa-search me-1"></i> Filter
                    </button>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <a href="admin-audit.php" class="btn btn-outline-secondary w-100">
                        <i class="fas fa-redo me-1"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Timestamp</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Project Type</th>
                            <th>Project ID</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No audit records found.</td></tr>
                        <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td class="text-nowrap small">
                                <?php echo htmlspecialchars($log['created_at']); ?>
                            </td>
                            <td>
                                <span class="fw-semibold"><?php echo htmlspecialchars($log['full_name'] ?? 'Unknown'); ?></span><br>
                                <small class="text-muted">@<?php echo htmlspecialchars($log['username'] ?? '—'); ?></small>
                            </td>
                            <td>
                                <code class="text-dark"><?php echo htmlspecialchars($log['action']); ?></code>
                            </td>
                            <td>
                                <?php if ($log['project_type']): ?>
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($log['project_type']); ?></span>
                                <?php else: echo '—'; endif; ?>
                            </td>
                            <td><?php echo $log['project_id'] ? '#' . $log['project_id'] : '—'; ?></td>
                            <td><small class="text-muted"><?php echo htmlspecialchars($log['ip_address'] ?? '—'); ?></small></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <nav class="mt-4">
        <ul class="pagination justify-content-center">
            <li class="page-item <?php echo $page === 1 ? 'disabled' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $page - 1; ?>&user_id=<?php echo $filter_user; ?>&action=<?php echo urlencode($filter_action); ?>">Previous</a>
            </li>
            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $i; ?>&user_id=<?php echo $filter_user; ?>&action=<?php echo urlencode($filter_action); ?>"><?php echo $i; ?></a>
            </li>
            <?php endfor; ?>
            <li class="page-item <?php echo $page === $total_pages ? 'disabled' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $page + 1; ?>&user_id=<?php echo $filter_user; ?>&action=<?php echo urlencode($filter_action); ?>">Next</a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>
</div>
<?php renderAppLayoutFooter(); ?>
