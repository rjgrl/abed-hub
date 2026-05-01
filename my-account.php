<?php
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$page_title = 'My Account';
$errors = [];
$success_message = '';

function ensureProfileColumns(mysqli $conn): void
{
    $required_columns = [
        'address' => "ALTER TABLE users ADD COLUMN address VARCHAR(255) NULL AFTER office_unit",
        'contact_number' => "ALTER TABLE users ADD COLUMN contact_number VARCHAR(50) NULL AFTER address",
        'profile_picture' => "ALTER TABLE users ADD COLUMN profile_picture VARCHAR(500) NULL AFTER contact_number",
    ];

    foreach ($required_columns as $column => $sql) {
        $check_stmt = $conn->prepare("
            SELECT COUNT(*)
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'users'
              AND column_name = ?
        ");
        $check_stmt->bind_param("s", $column);
        $check_stmt->execute();
        $check_stmt->bind_result($exists);
        $check_stmt->fetch();
        $check_stmt->close();

        if ((int) $exists === 0) {
            $conn->query($sql);
        }
    }
}

ensureProfileColumns($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');

    if ($full_name === '') {
        $errors[] = 'Full name is required.';
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email address is required.';
    }

    if ($contact_number !== '' && !preg_match('/^[0-9+\-\s()]{7,20}$/', $contact_number)) {
        $errors[] = 'Contact number format is invalid.';
    }

    $profile_picture_path = null;
    if (isset($_FILES['profile_picture']) && (int) $_FILES['profile_picture']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ((int) $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Profile picture upload failed.';
        } else {
            $allowed_mime = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'image/gif' => 'gif',
            ];
            $max_size = 2 * 1024 * 1024;
            $tmp_name = $_FILES['profile_picture']['tmp_name'];
            $file_size = (int) $_FILES['profile_picture']['size'];
            $mime_type = mime_content_type($tmp_name);

            if (!isset($allowed_mime[$mime_type])) {
                $errors[] = 'Profile picture must be JPG, PNG, WEBP, or GIF.';
            } elseif ($file_size > $max_size) {
                $errors[] = 'Profile picture must be 2MB or smaller.';
            } else {
                $upload_dir = __DIR__ . '/uploads/profile_pictures';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0775, true);
                }

                $new_file_name = 'user_' . $user_id . '_' . bin2hex(random_bytes(8)) . '.' . $allowed_mime[$mime_type];
                $destination = $upload_dir . '/' . $new_file_name;

                if (!move_uploaded_file($tmp_name, $destination)) {
                    $errors[] = 'Unable to save profile picture.';
                } else {
                    $profile_picture_path = 'uploads/profile_pictures/' . $new_file_name;
                }
            }
        }
    }

    if (empty($errors)) {
        $email_check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id <> ?");
        $email_check_stmt->bind_param("si", $email, $user_id);
        $email_check_stmt->execute();
        $email_exists = $email_check_stmt->get_result()->num_rows > 0;
        $email_check_stmt->close();

        if ($email_exists) {
            $errors[] = 'Email is already used by another account.';
        } else {
            if ($profile_picture_path !== null) {
                $update_stmt = $conn->prepare("
                    UPDATE users
                    SET full_name = ?, email = ?, address = ?, contact_number = ?, profile_picture = ?
                    WHERE id = ?
                ");
                $update_stmt->bind_param("sssssi", $full_name, $email, $address, $contact_number, $profile_picture_path, $user_id);
            } else {
                $update_stmt = $conn->prepare("
                    UPDATE users
                    SET full_name = ?, email = ?, address = ?, contact_number = ?
                    WHERE id = ?
                ");
                $update_stmt->bind_param("ssssi", $full_name, $email, $address, $contact_number, $user_id);
            }

            if ($update_stmt->execute()) {
                $_SESSION['full_name'] = $full_name;
                $_SESSION['email'] = $email;
                if ($profile_picture_path !== null) {
                    $_SESSION['profile_picture'] = $profile_picture_path;
                }
                $success_message = 'Profile updated successfully.';
            } else {
                $errors[] = 'Failed to update profile. Please try again.';
            }
            $update_stmt->close();
        }
    }
}

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

$dashboard_link = ($user['role'] ?? '') === 'admin' ? 'admin-dashboard.php' : 'dashboard.php';
$profile_picture = $user['profile_picture'] ?? '';
?>
<?php
require_once __DIR__ . '/components/layout.php';
renderAppLayout($page_title);
?>
<div class="container py-5">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">My Account</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($success_message)): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
                    <?php endif; ?>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger mb-3">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-4 d-flex align-items-center gap-3">
                            <div class="rounded-circle overflow-hidden border d-flex align-items-center justify-content-center bg-light" style="width: 88px; height: 88px;">
                                <?php if (!empty($profile_picture)): ?>
                                    <img src="<?php echo htmlspecialchars($profile_picture); ?>" alt="Profile picture" style="width: 100%; height: 100%; object-fit: cover;">
                                <?php else: ?>
                                    <span class="fw-bold text-secondary" style="font-size: 1.4rem;">
                                        <?php echo htmlspecialchars(strtoupper(substr((string) ($user['full_name'] ?? 'U'), 0, 2))); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="flex-grow-1">
                                <label for="profile_picture" class="form-label fw-semibold mb-1">Profile Picture</label>
                                <input type="file" class="form-control" id="profile_picture" name="profile_picture" accept=".jpg,.jpeg,.png,.gif,.webp">
                                <small class="text-muted">Accepted: JPG, PNG, GIF, WEBP. Max 2MB.</small>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="full_name" class="form-label fw-semibold">Full Name</label>
                                    <input type="text" class="form-control" id="full_name" name="full_name" required value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Username</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" readonly>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label fw-semibold">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" required value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="contact_number" class="form-label fw-semibold">Contact Number</label>
                                    <input type="text" class="form-control" id="contact_number" name="contact_number" value="<?php echo htmlspecialchars($user['contact_number'] ?? ''); ?>" placeholder="+63...">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="address" class="form-label fw-semibold">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3" placeholder="Enter your complete address"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Role</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars(ucfirst((string) ($user['role'] ?? ''))); ?>" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Office/Unit</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['office_unit'] ?? 'N/A'); ?>" readonly>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <div class="d-flex flex-wrap gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i>Save Profile
                            </button>
                            <a href="<?php echo htmlspecialchars($dashboard_link); ?>" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                            </a>
                            <a href="change-password.php" class="btn btn-outline-primary">
                                <i class="fas fa-key me-1"></i>Change Password
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</main>

<script src="assets/bootstrap/js/bootstrap.bundle.min.js"></script>
<?php renderAppLayoutFooter(); ?>

