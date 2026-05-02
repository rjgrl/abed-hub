<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/functions/helpers.php';
require_once __DIR__ . '/components/layout.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

requireLogin();

$page_title  = 'Change Password - ABED IDM Hub';
$success_msg = '';
$error_msg   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current   = $_POST['current_password'] ?? '';
    $new_pass  = $_POST['new_password'] ?? '';
    $confirm   = $_POST['confirm_password'] ?? '';

    if (!$current || !$new_pass || !$confirm) {
        $error_msg = 'All fields are required.';
    } elseif ($new_pass !== $confirm) {
        $error_msg = 'New passwords do not match.';
    } elseif (strlen($new_pass) < 8) {
        $error_msg = 'New password must be at least 8 characters.';
    } else {
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param('i', $_SESSION['user_id']);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if (!$row || !password_verify($current, $row['password'])) {
            $error_msg = 'Current password is incorrect.';
        } else {
            $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
            $upd = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $upd->bind_param('si', $hashed, $_SESSION['user_id']);

            if ($upd->execute()) {
                $success_msg = 'Password updated successfully.';
                logAudit('CHANGE_PASSWORD');
            } else {
                $error_msg = 'Failed to update password. Please try again.';
            }
        }
    }
}

renderAppLayout($page_title);
?>
<div class="container py-4 form-narrow">
    <div class="d-flex align-items-center mb-4 gap-3">
        <a href="my-account.php" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left"></i>
        </a>
        <h1 class="h4 mb-0"><i class="fas fa-lock me-2"></i>Change Password</h1>
    </div>

    <?php if ($success_msg): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success_msg); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error_msg); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <form method="POST" autocomplete="off">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Current Password</label>
                    <input type="password" name="current_password" class="form-control" required autocomplete="current-password">
                </div>
                <hr>
                <div class="mb-3">
                    <label class="form-label fw-semibold">New Password</label>
                    <input type="password" name="new_password" id="newPass" class="form-control"
                           required minlength="8" autocomplete="new-password"
                           oninput="checkStrength(this.value)">
                    <div class="progress progress-strength-track mt-2">
                        <div id="strengthBar" class="progress-bar progress-bar-strength"></div>
                    </div>
                    <small id="strengthLabel" class="text-muted">Enter a password</small>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold">Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control" required autocomplete="new-password">
                </div>
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-save me-2"></i>Update Password
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function checkStrength(pw) {
    const bar   = document.getElementById('strengthBar');
    const label = document.getElementById('strengthLabel');
    let score = 0;
    if (pw.length >= 8)  score++;
    if (/[A-Z]/.test(pw)) score++;
    if (/[0-9]/.test(pw)) score++;
    if (/[^A-Za-z0-9]/.test(pw)) score++;

    const levels = [
        { pct: '25%', cls: 'bg-danger',  txt: 'Weak' },
        { pct: '50%', cls: 'bg-warning', txt: 'Fair' },
        { pct: '75%', cls: 'bg-info',    txt: 'Good' },
        { pct: '100%',cls: 'bg-success', txt: 'Strong' },
    ];
    const lvl = levels[score - 1] ?? { pct: '0%', cls: '', txt: 'Enter a password' };
    bar.style.width          = lvl.pct;
    bar.className            = 'progress-bar progress-bar-strength ' + lvl.cls;
    label.textContent        = lvl.txt;
}
</script>
<?php renderAppLayoutFooter(); ?>
