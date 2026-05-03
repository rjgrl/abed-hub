<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/functions/helpers.php';
require_once __DIR__ . '/components/layout.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

requireLogin();
requireRoles(['admin']);

$page_title = 'System Settings - ABED IDM Hub';

$success_msg = '';
$error_msg   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'update_user_role') {
        $target_id = intval($_POST['user_id'] ?? 0);
        $new_role  = $_POST['role'] ?? '';
        $allowed   = ['admin', 'coordinator', 'operator', 'viewer'];

        if (!$target_id || !in_array($new_role, $allowed, true)) {
            $error_msg = 'Invalid user or role.';
        } elseif ($target_id === (int) $_SESSION['user_id']) {
            $error_msg = 'You cannot change your own role.';
        } else {
            $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmt->bind_param('si', $new_role, $target_id);
            $stmt->execute() ? $success_msg = 'User role updated.' : $error_msg = 'Update failed.';
        }
        logAudit('ADMIN_UPDATE_USER_ROLE', null, null, null, [
            'target_user_id' => $target_id,
            'new_role' => $new_role
        ]);
    }

    if ($_POST['action'] === 'toggle_user_active') {
        $target_id = intval($_POST['user_id'] ?? 0);
        $active    = intval($_POST['is_active'] ?? 1);

        if (!$target_id) {
            $error_msg = 'Invalid user.';
        } elseif ($target_id === (int) $_SESSION['user_id']) {
            $error_msg = 'You cannot deactivate your own account.';
        } else {
            $stmt = $conn->prepare("UPDATE users SET is_active = ? WHERE id = ?");
            $stmt->bind_param('ii', $active, $target_id);
            $stmt->execute() ? $success_msg = 'User status updated.' : $error_msg = 'Update failed.';
        }
        logAudit('ADMIN_TOGGLE_USER_ACTIVE', null, null, null, [
            'target_user_id' => $target_id,
            'new_is_active' => $active
        ]);
    }
}

$users = $conn->query(
    "SELECT id, username, first_name, last_name, email, role, office_unit, is_active, created_at
     FROM users ORDER BY last_name, first_name"
)->fetch_all(MYSQLI_ASSOC);

renderAppLayout($page_title);
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">System Settings</h1>
            <p class="text-muted mb-0">Manage users, roles, and system configuration</p>
        </div>
    </div>

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

    <!-- User Management Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-users me-2"></i>User Accounts (<?php echo count($users); ?>)</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Office</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td class="fw-semibold"><?php echo htmlspecialchars(user_display_name($u['first_name'] ?? '', $u['last_name'] ?? '')); ?></td>
                            <td><small class="text-muted">@<?php echo htmlspecialchars($u['username']); ?></small></td>
                            <td><small><?php echo htmlspecialchars($u['email']); ?></small></td>
                            <td><small><?php echo htmlspecialchars($u['office_unit'] ?? '—'); ?></small></td>
                            <td>
                                <form method="POST" class="d-inline-flex gap-1 align-items-center">
                                    <input type="hidden" name="action" value="update_user_role">
                                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                    <select name="role" class="form-select form-select-sm form-select-width-role">
                                        <?php foreach (['admin','coordinator','operator','viewer'] as $r): ?>
                                            <option value="<?php echo $r; ?>" <?php echo $u['role'] === $r ? 'selected' : ''; ?>><?php echo ucfirst($r); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if ($u['id'] !== (int)$_SESSION['user_id']): ?>
                                    <button class="btn btn-sm btn-outline-primary" title="Save role">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <?php endif; ?>
                                </form>
                            </td>
                            <td>
                                <?php if ($u['is_active']): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td><small class="text-muted"><?php echo date('M j, Y', strtotime($u['created_at'])); ?></small></td>
                            <td>
                                <?php if ($u['id'] !== (int)$_SESSION['user_id']): ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="toggle_user_active">
                                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                    <input type="hidden" name="is_active" value="<?php echo $u['is_active'] ? 0 : 1; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-<?php echo $u['is_active'] ? 'warning' : 'success'; ?>"
                                            data-app-confirm="<?php echo htmlspecialchars(($u['is_active'] ? 'Deactivate' : 'Activate') . ' this user?', ENT_QUOTES, 'UTF-8'); ?>">
                                        <i class="fas fa-<?php echo $u['is_active'] ? 'user-slash' : 'user-check'; ?>"></i>
                                        <?php echo $u['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                    </button>
                                </form>
                                <?php else: ?>
                                    <span class="text-muted small">Current user</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php renderAppLayoutFooter(); ?>
