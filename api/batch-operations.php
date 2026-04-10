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
            $project_type = $input['project_type'];
            $table = match($project_type) {
                'fspf' => 'fspf_projects',
                'idp' => 'idp_projects',
                'afme' => 'afme_projects',
                default => null
            };

            if (!$table) {
                echo json_encode(['success' => false, 'message' => 'Invalid project type']);
                exit;
            }

            if ($input['action'] === 'delete') {
                // Batch delete
                $ids = $input['ids'];
                if (empty($ids) || !is_array($ids)) {
                    echo json_encode(['success' => false, 'message' => 'No valid IDs provided']);
                    exit;
                }

                $placeholders = str_repeat('?,', count($ids) - 1) . '?';
                $stmt = $conn->prepare("DELETE FROM $table WHERE id IN ($placeholders)");
                $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);

                if ($stmt->execute()) {
                    echo json_encode([
                        'success' => true,
                        'deleted_count' => $stmt->affected_rows,
                        'message' => 'Projects deleted successfully'
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to delete projects']);
                }

            } elseif ($input['action'] === 'update') {
                // Batch update
                $ids = $input['ids'];
                $updates = $input['updates'];

                if (empty($ids) || !is_array($ids) || empty($updates)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
                    exit;
                }

                $set_parts = [];
                $params = [];
                $types = '';

                foreach ($updates as $field => $value) {
                    $set_parts[] = "$field = ?";
                    $params[] = $value;
                    $types .= is_numeric($value) ? 'd' : 's';
                }

                $placeholders = str_repeat('?,', count($ids) - 1) . '?';
                $set_clause = implode(', ', $set_parts);

                $stmt = $conn->prepare("UPDATE $table SET $set_clause, updated_at = CURRENT_TIMESTAMP WHERE id IN ($placeholders)");
                $all_params = array_merge($params, $ids);
                $stmt->bind_param($types . str_repeat('i', count($ids)), ...$all_params);

                if ($stmt->execute()) {
                    echo json_encode([
                        'success' => true,
                        'updated_count' => $stmt->affected_rows,
                        'message' => 'Projects updated successfully'
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to update projects']);
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