<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../functions/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

try {
    switch ($method) {
        case 'POST':
            if ($input['action'] === 'save') {
                // Save a new view
                $stmt = $conn->prepare("INSERT INTO saved_views (user_id, view_name, project_type, filters) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE filters = VALUES(filters), updated_at = CURRENT_TIMESTAMP");
                $filters_json = json_encode($input['filters']);
                $stmt->bind_param("isss", $_SESSION['user_id'], $input['view_name'], $input['project_type'], $filters_json);

                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'View saved successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to save view']);
                }
            } elseif ($input['action'] === 'delete') {
                // Delete a view
                $stmt = $conn->prepare("DELETE FROM saved_views WHERE id = ? AND user_id = ?");
                $stmt->bind_param("ii", $input['view_id'], $_SESSION['user_id']);

                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'View deleted successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to delete view']);
                }
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>