<?php
/**
 * API Handler for Notifications & Alerts
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/email.php';
require_once __DIR__ . '/../functions/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name('ABED_IDM_HUB');
    session_start();
}

/**
 * Merge notification rows by created_at descending and cap length.
 *
 * @param array<int, array<string,mixed>> $rows
 * @return array<int, array<string,mixed>>
 */
function notifications_merge_by_date(array $rows, int $limit): array
{
    usort($rows, static function ($a, $b): int {
        $ta = strtotime((string) ($a['created_at'] ?? ''));
        $tb = strtotime((string) ($b['created_at'] ?? ''));
        return $tb <=> $ta;
    });

    return array_slice($rows, 0, max(0, $limit));
}

/**
 * Dashboard notices for Super Admin: accounts awaiting activation + projects awaiting approval.
 * Stored separately from the notifications table (nothing inserts rows there on signup/project submit today).
 *
 * @return array<int, array<string,mixed>>
 */
function notifications_admin_synthetic(mysqli $conn, int $viewerUserId): array
{
    $out = [];

    $qu = $conn->query(
        'SELECT id, username, email, first_name, last_name, created_at
         FROM users
         WHERE is_active = 0
         ORDER BY created_at DESC
         LIMIT 50'
    );
    if ($qu) {
        while ($row = $qu->fetch_assoc()) {
            $name = user_display_name((string) ($row['first_name'] ?? ''), (string) ($row['last_name'] ?? ''));
            if ($name === '') {
                $name = (string) ($row['username'] ?? 'User');
            }
            $email = trim((string) ($row['email'] ?? ''));
            $msg = $name . ($email !== '' ? ' (' . $email . ')' : '') . ' registered and needs activation.';
            $out[] = [
                'id' => -(100_000_000 + (int) $row['id']),
                'user_id' => $viewerUserId,
                'project_id' => null,
                'alert_type' => 'pending_user',
                'title' => 'Account pending activation',
                'message' => $msg,
                'is_read' => 0,
                'created_at' => $row['created_at'],
                'synthetic' => true,
                'synthetic_kind' => 'pending_user',
                'ref_id' => (int) $row['id'],
            ];
        }
    }

    $qp = $conn->query(
        "SELECT id, project_code, title, project_type, created_at
         FROM projects
         WHERE approval_status = 'Pending'
           AND (status IS NULL OR status <> 'Archived')
         ORDER BY created_at DESC
         LIMIT 50"
    );
    if ($qp) {
        while ($row = $qp->fetch_assoc()) {
            $code = strip_tags((string) ($row['project_code'] ?? ''));
            $title = strip_tags((string) ($row['title'] ?? ''));
            $ptype = strtoupper(strip_tags((string) ($row['project_type'] ?? '')));
            $msg = "{$ptype} {$code} — {$title} was added and awaits approval.";
            $out[] = [
                'id' => -(200_000_000 + (int) $row['id']),
                'user_id' => $viewerUserId,
                'project_id' => (int) $row['id'],
                'project_type' => (string) ($row['project_type'] ?? 'fspf'),
                'alert_type' => 'pending_project',
                'title' => 'Project pending approval',
                'message' => $msg,
                'is_read' => 0,
                'created_at' => $row['created_at'],
                'synthetic' => true,
                'synthetic_kind' => 'pending_project',
                'ref_id' => (int) $row['id'],
            ];
        }
    }

    return $out;
}

function admin_pending_activation_count(mysqli $conn): int
{
    $r = $conn->query('SELECT COUNT(*) AS c FROM users WHERE is_active = 0');
    if (!$r) {
        return 0;
    }
    $row = $r->fetch_assoc();

    return (int) ($row['c'] ?? 0);
}

function admin_pending_project_approval_count(mysqli $conn): int
{
    $r = $conn->query(
        "SELECT COUNT(*) AS c FROM projects
         WHERE approval_status = 'Pending'
           AND (status IS NULL OR status <> 'Archived')"
    );
    if (!$r) {
        return 0;
    }
    $row = $r->fetch_assoc();

    return (int) ($row['c'] ?? 0);
}

/**
 * Employee: own project submissions still pending Super Admin approval.
 *
 * @return array<int, array<string,mixed>>
 */
function notifications_employee_submission_synthetic(mysqli $conn, int $viewerUserId): array
{
    $out = [];
    $st = $conn->prepare(
        "SELECT id, project_code, title, project_type, created_at
         FROM projects
         WHERE user_id = ?
           AND approval_status = 'Pending'
           AND (status IS NULL OR status <> 'Archived')
         ORDER BY created_at DESC
         LIMIT 30"
    );
    $st->bind_param('i', $viewerUserId);
    $st->execute();
    $res = $st->get_result();
    while ($row = $res->fetch_assoc()) {
        $code = strip_tags((string) ($row['project_code'] ?? ''));
        $title = strip_tags((string) ($row['title'] ?? ''));
        $ptype = strtoupper(strip_tags((string) ($row['project_type'] ?? '')));
        $out[] = [
            'id' => -(300_000_000 + (int) $row['id']),
            'user_id' => $viewerUserId,
            'project_id' => (int) $row['id'],
            'project_type' => (string) ($row['project_type'] ?? 'fspf'),
            'alert_type' => 'my_pending_project',
            'title' => 'Project awaiting approval',
            'message' => "Your {$ptype} project {$code} — {$title} is pending Super Admin review.",
            'is_read' => 0,
            'created_at' => $row['created_at'],
            'synthetic' => true,
            'synthetic_kind' => 'my_pending_project',
            'ref_id' => (int) $row['id'],
        ];
    }
    $st->close();

    return $out;
}

function employee_own_pending_project_count(mysqli $conn, int $viewerUserId): int
{
    $st = $conn->prepare(
        "SELECT COUNT(*) AS c FROM projects
         WHERE user_id = ?
           AND approval_status = 'Pending'
           AND (status IS NULL OR status <> 'Archived')"
    );
    $st->bind_param('i', $viewerUserId);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();

    return (int) ($row['c'] ?? 0);
}

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = (string) ($_GET['action'] ?? $_POST['action'] ?? '');
$response = [];

try {
    $user_id = $_SESSION['user_id'];

    switch ($action) {
        case 'get':
            // Stored notifications + (Super Admin) live queue: pending activations & pending project approvals
            $unread_only = isset($_GET['unread']);
            $limit = max(1, min(100, (int) ($_GET['limit'] ?? 20)));
            $fetchCap = 100;

            $query = 'SELECT n.*, p.project_type AS project_type FROM notifications n
                LEFT JOIN projects p ON p.id = n.project_id
                WHERE n.user_id = ?';
            if ($unread_only) {
                $query .= ' AND n.is_read = 0';
            }
            $query .= ' ORDER BY n.created_at DESC LIMIT ?';

            $stmt = $conn->prepare($query);
            $stmt->bind_param('ii', $user_id, $fetchCap);
            $stmt->execute();

            $notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

            if (isSuperAdmin()) {
                $merged = array_merge($notifications, notifications_admin_synthetic($conn, $user_id));
                $notifications = notifications_merge_by_date($merged, $limit);
            } else {
                $merged = array_merge($notifications, notifications_employee_submission_synthetic($conn, $user_id));
                $notifications = notifications_merge_by_date($merged, $limit);
            }

            $response = [
                'success' => true,
                'data' => $notifications,
                'count' => count($notifications),
            ];
            break;

        case 'count':
            // Unread rows in DB + (Super Admin) items that still need action in Admin Dashboard
            $stmt = $conn->prepare('SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0');
            $stmt->bind_param('i', $user_id);
            $stmt->execute();

            $count = (int) ($stmt->get_result()->fetch_assoc()['count'] ?? 0);

            if (isSuperAdmin()) {
                $count += admin_pending_activation_count($conn);
                $count += admin_pending_project_approval_count($conn);
            } else {
                $count += employee_own_pending_project_count($conn, $user_id);
            }

            $response = [
                'success' => true,
                'unread_count' => $count,
            ];
            break;

        case 'mark_read':
            $notification_id = $_POST['notification_id'] ?? null;

            if ($notification_id === null || $notification_id === '') {
                throw new Exception('Notification ID required');
            }

            // Synthetic dashboard notices use negative ids; they disappear when the underlying row is approved.
            if (is_numeric((string) $notification_id) && (int) $notification_id < 0) {
                $response = ['success' => true, 'message' => 'OK'];
                break;
            }

            $nid = (int) $notification_id;
            $stmt = $conn->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
            $stmt->bind_param('ii', $nid, $user_id);

            if ($stmt->execute()) {
                $response = ['success' => true, 'message' => 'Marked as read'];
            } else {
                throw new Exception('Failed to update notification');
            }
            break;

        case 'mark_all_read':
            $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
            $stmt->bind_param('i', $user_id);

            if ($stmt->execute()) {
                $response = ['success' => true, 'message' => 'All marked as read'];
            } else {
                throw new Exception('Failed to update notifications');
            }
            break;

        case 'delete':
            $notification_id = $_GET['id'] ?? null;

            if (!$notification_id) {
                throw new Exception('Notification ID required');
            }

            $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
            $stmt->bind_param('ii', $notification_id, $user_id);

            if ($stmt->execute()) {
                $response = ['success' => true, 'message' => 'Notification deleted'];
            } else {
                throw new Exception('Failed to delete notification');
            }
            break;

        case 'create_alert':
            // Create system alert (admin only)
            $project_id = $_POST['project_id'] ?? null;
            $alert_type = $_POST['alert_type'] ?? null; // 'variance', 'delay', 'budget', 'milestone'
            $severity = $_POST['severity'] ?? 'medium'; // 'low', 'medium', 'high', 'critical'
            $message = $_POST['message'] ?? null;
            $target_users = $_POST['target_users'] ?? []; // array of user IDs
            $send_email = isset($_POST['send_email']) ? (bool)$_POST['send_email'] : true;

            if (!$project_id || !$alert_type || !$message) {
                throw new Exception('Missing required fields');
            }

            // Get project code for email
            $project_query = $conn->prepare("SELECT project_code FROM projects WHERE id = ? LIMIT 1");
            $project_query->bind_param('i', $project_id);
            $project_query->execute();
            $project_result = $project_query->get_result()->fetch_assoc();
            $project_code = $project_result ? $project_result['project_code'] : 'Unknown Project';

            // Create alert record
            $stmt = $conn->prepare("
                INSERT INTO project_alerts
                (project_id, alert_type, severity, message, created_by, created_at, is_active)
                VALUES (?, ?, ?, ?, ?, NOW(), 1)
            ");

            $stmt->bind_param('isssi', $project_id, $alert_type, $severity, $message, $user_id);

            if ($stmt->execute()) {
                $alert_id = $conn->insert_id;

                // Distribute to target users
                if (is_array($target_users) && count($target_users) > 0) {
                    foreach ($target_users as $target_user) {
                        // Get user email for notifications
                        $user_query = $conn->prepare("SELECT email FROM users WHERE id = ?");
                        $user_query->bind_param('i', $target_user);
                        $user_query->execute();
                        $user_result = $user_query->get_result()->fetch_assoc();

                        // Create notification record
                        $notif_stmt = $conn->prepare("
                            INSERT INTO notifications
                            (user_id, project_id, alert_type, title, message, is_read, created_at)
                            VALUES (?, ?, ?, ?, ?, 0, NOW())
                        ");

                        $title = "Alert: " . ucfirst(str_replace('_', ' ', $alert_type));
                        $notif_stmt->bind_param('iisss', $target_user, $project_id, $alert_type, $title, $message);
                        $notif_stmt->execute();

                        // Send email if requested and user has email
                        if ($send_email && $user_result && $user_result['email']) {
                            sendAlertEmail($user_result['email'], $alert_type, $project_code, $message, $severity);
                        }
                    }
                }

                $response = ['success' => true, 'message' => 'Alert created and notifications sent', 'id' => $alert_id];
            } else {
                throw new Exception('Failed to create alert');
            }
            break;

        case 'get_alerts':
            // Get project alerts
            $project_id = $_GET['project_id'] ?? null;
            $alert_type = $_GET['type'] ?? null;

            $query = "SELECT a.*, p.project_code, p.project_type, p.title AS project_title
                FROM project_alerts a
                LEFT JOIN projects p ON p.id = a.project_id
                WHERE a.is_active = 1";
            $params = [];
            $types = '';

            if ($project_id) {
                $query .= " AND a.project_id = ?";
                $params[] = $project_id;
                $types .= 'i';
            }

            if ($alert_type) {
                $query .= " AND a.alert_type = ?";
                $params[] = $alert_type;
                $types .= 's';
            }

            $query .= " ORDER BY a.created_at DESC";

            $stmt = $conn->prepare($query);
            if ($params) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();

            $alerts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

            $response = [
                'success' => true,
                'data' => $alerts,
                'count' => count($alerts)
            ];
            break;

        case 'send_milestone_reminder':
            throw new Exception('Milestone reminders are unavailable in the refactored schema.');
            break;

        default:
            throw new Exception('Invalid action');
    }

} catch (Exception $e) {
    http_response_code(400);
    $response = ['error' => $e->getMessage()];
}

echo json_encode($response);
