<?php
/**
 * ABED IDM Hub - Projects API Controller
 * Handles all CRUD operations for FSPF, IDP, and AFME projects
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

require_once '../config/database.php';
require_once '../functions/helpers.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die(json_encode(['status' => 'error', 'message' => 'Unauthorized']));
}

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : null;
$project_type = isset($_GET['type']) ? strtolower($_GET['type']) : null; // fspf, idp, afme
$project_id = isset($_GET['id']) ? intval($_GET['id']) : null;

try {
    switch ($method) {
        case 'GET':
            if ($action === 'list') {
                listProjects($conn, $project_type);
            } elseif ($action === 'detail' && $project_id) {
                getProjectDetail($conn, $project_type, $project_id);
            } elseif ($action === 'duplicates') {
                detectDuplicates($conn, $project_type);
            } else {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
            }
            break;

        case 'POST':
            if ($action === 'create') {
                createProject($conn, $project_type, $_POST, $_FILES ?? []);
            } elseif ($action === 'update-progress') {
                updateProgress($conn, $project_type, $project_id, $_POST);
            } else {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
            }
            break;

        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            if ($action === 'update' && $project_id) {
                updateProject($conn, $project_type, $project_id, $data);
            } elseif ($action === 'stage' && $project_id) {
                updateProjectStage($conn, $project_type, $project_id, $data);
            } else {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
            }
            break;

        case 'DELETE':
            if ($project_id) {
                archiveProject($conn, $project_type, $project_id);
            } else {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Project ID required']);
            }
            break;

        case 'OPTIONS':
            http_response_code(200);
            break;

        default:
            http_response_code(405);
            echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server error: ' . $e->getMessage()]);
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

function listProjects($conn, $project_type) {
    $filters = [];
    $params = [];
    $types = '';

    // Build WHERE clause from GET parameters
    if (isset($_GET['year'])) {
        $filters[] = 'funding_year = ?';
        $params[] = intval($_GET['year']);
        $types .= 'i';
    }

    if (isset($_GET['status'])) {
        $filters[] = 'proposal_status = ?';
        $params[] = $_GET['status'];
        $types .= 's';
    }

    if (isset($_GET['stage'])) {
        $filters[] = 'current_stage = ?';
        $params[] = $_GET['stage'];
        $types .= 's';
    }

    if (isset($_GET['location'])) {
        $filters[] = 'municipality LIKE ?';
        $params[] = '%' . $_GET['location'] . '%';
        $types .= 's';
    }

    // Determine table and base query
    if ($project_type === 'fspf') {
        $table = 'fspf_projects';
    } elseif ($project_type === 'idp') {
        $table = 'idp_projects';
    } elseif ($project_type === 'afme') {
        $table = 'afme_projects';
    } else {
        http_response_code(400);
        die(json_encode(['status' => 'error', 'message' => 'Invalid project type']));
    }

    $where = !empty($filters) ? 'WHERE ' . implode(' AND ', $filters) : '';
    $query = "SELECT * FROM $table $where ORDER BY created_at DESC LIMIT 100";

    if ($stmt = $conn->prepare($query)) {
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $projects = $result->fetch_all(MYSQLI_ASSOC);

        echo json_encode([
            'status' => 'success',
            'count' => count($projects),
            'data' => $projects
        ]);
        $stmt->close();
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database error']);
    }
}

function getProjectDetail($conn, $project_type, $project_id) {
    if ($project_type === 'fspf') {
        $table = 'fspf_projects';
    } elseif ($project_type === 'idp') {
        $table = 'idp_projects';
    } elseif ($project_type === 'afme') {
        $table = 'afme_projects';
    } else {
        http_response_code(400);
        die(json_encode(['status' => 'error', 'message' => 'Invalid project type']));
    }

    $query = "SELECT * FROM $table WHERE id = ?";

    if ($stmt = $conn->prepare($query)) {
        $stmt->bind_param('i', $project_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $project = $result->fetch_assoc();

        if ($project) {
            echo json_encode([
                'status' => 'success',
                'data' => $project
            ]);
        } else {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Project not found']);
        }
        $stmt->close();
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database error']);
    }
}

function createProject($conn, $project_type, $data, $files) {
    if ($project_type === 'fspf') {
        $table = 'fspf_projects';
    } elseif ($project_type === 'idp') {
        $table = 'idp_projects';
    } elseif ($project_type === 'afme') {
        $table = 'afme_projects';
    } else {
        http_response_code(400);
        die(json_encode(['status' => 'error', 'message' => 'Invalid project type']));
    }

    // Validate required fields
    $required_fields = ['project_code', 'project_title', 'funding_year'];
    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            http_response_code(400);
            die(json_encode(['status' => 'error', 'message' => "Missing required field: $field"]));
        }
    }

    // Prepare insert statement
    $fields = array_keys($data);
    $fields[] = 'created_by';
    $placeholders = array_fill(0, count($fields), '?');
    $values = array_values($data);
    $values[] = $_SESSION['user_id'];

    $query = "INSERT INTO $table (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";

    if ($stmt = $conn->prepare($query)) {
        $types = str_repeat('s', count($values) - 1) . 'i';
        $stmt->bind_param($types, ...$values);

        if ($stmt->execute()) {
            $project_id = $stmt->insert_id;
            echo json_encode([
                'status' => 'success',
                'message' => 'Project created successfully',
                'project_id' => $project_id
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to create project: ' . $stmt->error]);
        }
        $stmt->close();
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
    }
}

function updateProject($conn, $project_type, $project_id, $data) {
    if ($project_type === 'fspf') {
        $table = 'fspf_projects';
    } elseif ($project_type === 'idp') {
        $table = 'idp_projects';
    } elseif ($project_type === 'afme') {
        $table = 'afme_projects';
    } else {
        http_response_code(400);
        die(json_encode(['status' => 'error', 'message' => 'Invalid project type']));
    }

    $updates = [];
    $params = [];
    foreach ($data as $key => $value) {
        $updates[] = "$key = ?";
        $params[] = $value;
    }
    $params[] = $project_id;

    $query = "UPDATE $table SET " . implode(', ', $updates) . " WHERE id = ?";

    if ($stmt = $conn->prepare($query)) {
        $types = str_repeat('s', count($params) - 1) . 'i';
        $stmt->bind_param($types, ...$params);

        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Project updated successfully']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to update project']);
        }
        $stmt->close();
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database error']);
    }
}

function updateProjectStage($conn, $project_type, $project_id, $data) {
    if ($project_type === 'fspf') {
        $table = 'fspf_projects';
    } elseif ($project_type === 'idp') {
        $table = 'idp_projects';
    } elseif ($project_type === 'afme') {
        $table = 'afme_projects';
    } else {
        http_response_code(400);
        die(json_encode(['status' => 'error', 'message' => 'Invalid project type']));
    }

    if (empty($data['stage'])) {
        http_response_code(400);
        die(json_encode(['status' => 'error', 'message' => 'Stage is required']));
    }

    $query = "UPDATE $table SET current_stage = ? WHERE id = ?";

    if ($stmt = $conn->prepare($query)) {
        $stmt->bind_param('si', $data['stage'], $project_id);

        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Stage updated successfully']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to update stage']);
        }
        $stmt->close();
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database error']);
    }
}

function updateProgress($conn, $project_type, $project_id, $data) {
    if ($project_type === 'fspf' || $project_type === 'idp') {
        $table = ($project_type === 'fspf') ? 'fspf_projects' : 'idp_projects';

        $physical = isset($data['physical_progress']) ? intval($data['physical_progress']) : 0;
        $financial = isset($data['financial_progress']) ? intval($data['financial_progress']) : 0;

        $query = "UPDATE $table SET physical_progress = ?, financial_progress = ? WHERE id = ?";

        if ($stmt = $conn->prepare($query)) {
            $stmt->bind_param('iii', $physical, $financial, $project_id);

            if ($stmt->execute()) {
                echo json_encode(['status' => 'success', 'message' => 'Progress updated']);
            } else {
                http_response_code(500);
                echo json_encode(['status' => 'error', 'message' => 'Update failed']);
            }
            $stmt->close();
        }
    } else {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Progress tracking not available for this project type']);
    }
}

function archiveProject($conn, $project_type, $project_id) {
    if ($project_type === 'fspf') {
        $table = 'fspf_projects';
    } elseif ($project_type === 'idp') {
        $table = 'idp_projects';
    } elseif ($project_type === 'afme') {
        $table = 'afme_projects';
    } else {
        http_response_code(400);
        die(json_encode(['status' => 'error', 'message' => 'Invalid project type']));
    }

    $query = "UPDATE $table SET proposal_status = 'Archived' WHERE id = ?";

    if ($stmt = $conn->prepare($query)) {
        $stmt->bind_param('i', $project_id);

        if ($stmt->execute()) {
            // Log the action
            logActivity($conn, $_SESSION['user_id'], 'ARCHIVE_PROJECT', "Archived {$project_type} project ID: {$project_id}");
            echo json_encode(['status' => 'success', 'message' => 'Project archived']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to archive project']);
        }
        $stmt->close();
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database error']);
    }
}

function detectDuplicates($conn, $project_type) {
    if ($project_type === 'fspf') {
        $table = 'fspf_projects';
    } elseif ($project_type === 'idp') {
        $table = 'idp_projects';
    } elseif ($project_type === 'afme') {
        $table = 'afme_projects';
    } else {
        http_response_code(400);
        die(json_encode(['status' => 'error', 'message' => 'Invalid project type']));
    }

    // Get similar projects based on title + location
    $search_term = isset($_GET['search']) ? $_GET['search'] : '';

    if (strlen($search_term) < 3) {
        echo json_encode(['status' => 'error', 'message' => 'Search term must be at least 3 characters']);
        return;
    }

    $query = "SELECT id, project_code, project_title, municipality, created_at 
              FROM $table 
              WHERE project_title LIKE ? OR project_code LIKE ?
              ORDER BY LEVENSHTEIN(project_title, ?) LIMIT 10";

    // If database doesn't have LEVENSHTEIN, use LIKE instead
    $query = "SELECT id, project_code, project_title, municipality, created_at 
              FROM $table 
              WHERE (project_title LIKE ? OR project_code LIKE ?)
              ORDER BY created_at DESC LIMIT 10";

    if ($stmt = $conn->prepare($query)) {
        $search_pattern = '%' . $search_term . '%';
        $stmt->bind_param('ss', $search_pattern, $search_pattern);
        $stmt->execute();
        $result = $stmt->get_result();
        $duplicates = $result->fetch_all(MYSQLI_ASSOC);

        echo json_encode([
            'status' => 'success',
            'count' => count($duplicates),
            'data' => $duplicates
        ]);
        $stmt->close();
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database error']);
    }
}

function logActivity($conn, $user_id, $action, $details) {
    $query = "INSERT INTO audit_log (user_id, action, details) VALUES (?, ?, ?)";
    if ($stmt = $conn->prepare($query)) {
        $stmt->bind_param('iss', $user_id, $action, $details);
        $stmt->execute();
        $stmt->close();
    }
}
?>
