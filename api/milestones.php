<?php
/**
 * API Handler for Milestone Operations
 */
require_once __DIR__ . '/../config/database.php';
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
    switch ($action) {
        case 'create':
            $project_id = $_POST['project_id'] ?? null;
            $project_type = $_POST['project_type'] ?? null;
            $milestone_name = $_POST['milestone_name'] ?? null;
            $target_date = $_POST['target_date'] ?? null;
            $description = $_POST['description'] ?? null;

            if (!$project_id || !$project_type || !$milestone_name || !$target_date) {
                throw new Exception('Missing required fields');
            }

            $stmt = $conn->prepare("
                INSERT INTO project_milestones 
                (project_id, project_type, milestone_name, target_date, description, status, progress_percentage, created_by, created_date)
                VALUES (?, ?, ?, ?, ?, 'On Track', 0, ?, NOW())
            ");
            
            $stmt->bind_param('issssi', $project_id, $project_type, $milestone_name, $target_date, $description, $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                $response = ['success' => true, 'message' => 'Milestone created', 'id' => $conn->insert_id];
            } else {
                throw new Exception('Failed to create milestone');
            }
            break;

        case 'update':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('Milestone ID required');

            $status = $_POST['status'] ?? null;
            $progress = $_POST['progress_percentage'] ?? null;
            $target_date = $_POST['target_date'] ?? null;

            $fields = [];
            $params = [];
            $types = '';

            if ($status !== null) {
                $fields[] = "status = ?";
                $params[] = $status;
                $types .= 's';
            }
            if ($progress !== null) {
                $fields[] = "progress_percentage = ?";
                $params[] = $progress;
                $types .= 'i';
            }
            if ($target_date !== null) {
                $fields[] = "target_date = ?";
                $params[] = $target_date;
                $types .= 's';
            }

            if (empty($fields)) {
                throw new Exception('No fields to update');
            }

            $fields[] = "updated_at = NOW()";
            $params[] = $id;
            $types .= 'i';

            $query = "UPDATE project_milestones SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param($types, ...$params);

            if ($stmt->execute()) {
                $response = ['success' => true, 'message' => 'Milestone updated'];
            } else {
                throw new Exception('Failed to update milestone');
            }
            break;

        case 'list':
            $project_id = $_GET['project_id'] ?? null;
            $project_type = $_GET['project_type'] ?? null;

            if (!$project_id || !$project_type) {
                throw new Exception('Project ID and type required');
            }

            $stmt = $conn->prepare("
                SELECT * FROM project_milestones 
                WHERE project_id = ? AND project_type = ?
                ORDER BY target_date ASC
            ");
            $stmt->bind_param('is', $project_id, $project_type);
            $stmt->execute();
            
            $milestones = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $response = ['success' => true, 'data' => $milestones, 'count' => count($milestones)];
            break;

        case 'get':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('Milestone ID required');

            $stmt = $conn->prepare("SELECT * FROM project_milestones WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            
            $milestone = $stmt->get_result()->fetch_assoc();
            if (!$milestone) {
                throw new Exception('Milestone not found');
            }

            $response = ['success' => true, 'data' => $milestone];
            break;

        case 'delete':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('Milestone ID required');

            $stmt = $conn->prepare("DELETE FROM project_milestones WHERE id = ?");
            $stmt->bind_param('i', $id);
            
            if ($stmt->execute()) {
                $response = ['success' => true, 'message' => 'Milestone deleted'];
            } else {
                throw new Exception('Failed to delete milestone');
            }
            break;

        default:
            throw new Exception('Invalid action');
    }

} catch (Exception $e) {
    http_response_code(400);
    $response = ['error' => $e->getMessage()];
}

echo json_encode($response);
