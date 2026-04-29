<?php
/**
 * API Handler for Notifications & Alerts
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/email.php';
require_once __DIR__ . '/../functions/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';
$response = [];

try {
    $user_id = $_SESSION['user_id'];

    switch ($action) {
        case 'get':
            // Get user notifications
            $unread_only = isset($_GET['unread']) ? true : false;
            $limit = (int)($_GET['limit'] ?? 20);

            $query = "SELECT * FROM notifications WHERE user_id = ?";
            if ($unread_only) {
                $query .= " AND is_read = 0";
            }
            $query .= " ORDER BY created_at DESC LIMIT ?";

            $stmt = $conn->prepare($query);
            $stmt->bind_param('ii', $user_id, $limit);
            $stmt->execute();

            $notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

            $response = [
                'success' => true,
                'data' => $notifications,
                'count' => count($notifications)
            ];
            break;

        case 'count':
            // Get unread count
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
            $stmt->bind_param('i', $user_id);
            $stmt->execute();

            $count = $stmt->get_result()->fetch_assoc()['count'];

            $response = [
                'success' => true,
                'unread_count' => $count
            ];
            break;

        case 'mark_read':
            $notification_id = $_POST['notification_id'] ?? null;

            if (!$notification_id) {
                throw new Exception('Notification ID required');
            }

            $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
            $stmt->bind_param('ii', $notification_id, $user_id);

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

            $query = "SELECT * FROM project_alerts WHERE is_active = 1";
            $params = [];
            $types = '';

            if ($project_id) {
                $query .= " AND project_id = ?";
                $params[] = $project_id;
                $types .= 'i';
            }

            if ($alert_type) {
                $query .= " AND alert_type = ?";
                $params[] = $alert_type;
                $types .= 's';
            }

            $query .= " ORDER BY created_at DESC";

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
