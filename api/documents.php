<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../functions/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? 'list';
$method = $_SERVER['REQUEST_METHOD'];
$response = [];

try {
    switch ($action) {
        case 'list':
            $response = listDocuments();
            break;

        case 'upload':
            if ($method !== 'POST') {
                http_response_code(405);
                throw new Exception('Method not allowed');
            }
            $response = uploadDocument();
            break;

        case 'download':
            downloadDocument();
            break;

        case 'delete':
            if ($method !== 'DELETE') {
                http_response_code(405);
                throw new Exception('Method not allowed');
            }
            $response = deleteDocument();
            break;

        case 'preview':
            previewDocument();
            break;

        default:
            http_response_code(400);
            throw new Exception('Invalid action');
    }

    if ($action !== 'download' && $action !== 'preview') {
        http_response_code(200);
        echo json_encode($response);
    }

} catch (Exception $e) {
    if ($action !== 'download' && $action !== 'preview') {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function listDocuments() {
    global $conn;

    $project_id = $_GET['project_id'] ?? null;
    $project_type = $_GET['project_type'] ?? 'fspf';
    $document_type = $_GET['document_type'] ?? null;
    $limit = min((int)($_GET['limit'] ?? 50), 500);
    $offset = (int)($_GET['offset'] ?? 0);

    $type_map = [
        'fspf' => 'fspf_project_id',
        'idp' => 'idp_project_id',
        'afme' => 'afme_project_id'
    ];

    $project_col = $type_map[$project_type] ?? 'fspf_project_id';

    $query = "SELECT * FROM project_documents WHERE 1=1";
    $params = [];

    if ($project_id) {
        $query .= " AND $project_col = ?";
        $params[] = $project_id;
    }

    if ($document_type) {
        $query .= " AND document_type = ?";
        $params[] = $document_type;
    }

    $query .= " ORDER BY upload_date DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        throw new Exception('prepare failed: ' . $conn->error);
    }

    if (!empty($params)) {
        $types = str_repeat('s', count($params) - 2) . 'ii';
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Add file size and exists check
    foreach ($results as &$doc) {
        if (file_exists($doc['file_path'])) {
            $doc['file_size'] = filesize($doc['file_path']);
            $doc['file_exist'] = true;
        } else {
            $doc['file_exist'] = false;
        }
    }

    return $results;
}

function uploadDocument() {
    global $conn;

    // Validate upload
    if (empty($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error');
    }

    $file = $_FILES['document'];
    $project_id = $_POST['project_id'] ?? null;
    $project_type = $_POST['project_type'] ?? 'fspf';
    $document_type = $_POST['document_type'] ?? 'General';
    $description = $_POST['description'] ?? null;

    if (!$project_id) {
        throw new Exception('Project ID required');
    }

    // Get upload directory from config
    require_once __DIR__ . '/../config/database.php';

    $allowed_types = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif', 
                      'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                      'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                      'application/vnd.ms-excel', 'application/msword'];

    if (!in_array($file['type'], $allowed_types)) {
        throw new Exception('File type not allowed');
    }

    if ($file['size'] > MAX_FILE_SIZE) {
        throw new Exception('File size exceeds maximum allowed');
    }

    // Create upload directory if not exists
    $upload_dir = __DIR__ . '/../uploads/' . strtolower($project_type) . '/documents/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Generate unique filename
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('doc_') . '_' . date('Ymd_His') . '.' . $ext;
    $filepath = $upload_dir . $filename;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Failed to save file');
    }

    // Save to database
    $type_map = [
        'fspf' => 'fspf_project_id',
        'idp' => 'idp_project_id',
        'afme' => 'afme_project_id'
    ];

    $project_col = $type_map[$project_type] ?? 'fspf_project_id';

    $columns = [$project_col, "original_filename", "file_path", "document_type", "upload_date", "uploaded_by"];
    $placeholders = ["?", "?", "?", "?", "NOW()", "?"];
    $bind_params = [$project_id, $file['name'], $filepath, $document_type, $_SESSION['user_id']];
    $types = 'issssi';

    if ($description) {
        $columns[] = "description";
        $placeholders[] = "?";
        $bind_params[] = $description;
        $types = 'issssssi';
    }

    $query = "INSERT INTO project_documents (" . implode(", ", $columns) . ") 
              VALUES (" . implode(", ", $placeholders) . ")";

    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        throw new Exception('prepare failed: ' . $conn->error);
    }

    $stmt->bind_param($types, ...$bind_params);
    $stmt->execute();

    $doc_id = $conn->insert_id;

    logActivity($_SESSION['user_id'], "Uploaded document $filename for project $project_id", 'documents', 'UPLOAD');

    return [
        'success' => true,
        'document_id' => $doc_id,
        'filename' => $filename,
        'message' => 'Document uploaded successfully'
    ];
}

function downloadDocument() {
    global $conn;

    $doc_id = $_GET['id'] ?? null;
    if (!$doc_id) {
        http_response_code(400);
        die('Document ID required');
    }

    $stmt = $conn->prepare("SELECT * FROM project_documents WHERE id = ?");
    $stmt->bind_param('i', $doc_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    if (!$result || !file_exists($result['file_path'])) {
        http_response_code(404);
        die('Document not found');
    }

    // Log download
    logActivity($_SESSION['user_id'], "Downloaded document ID $doc_id", 'documents', 'DOWNLOAD');

    // Send file
    header('Content-Type: ' . mime_content_type($result['file_path']));
    header('Content-Disposition: attachment; filename="' . $result['original_filename'] . '"');
    header('Content-Length: ' . filesize($result['file_path']));
    readfile($result['file_path']);
    exit;
}

function previewDocument() {
    global $conn;

    $doc_id = $_GET['id'] ?? null;
    if (!$doc_id) {
        http_response_code(400);
        die('Document ID required');
    }

    $stmt = $conn->prepare("SELECT * FROM project_documents WHERE id = ?");
    $stmt->bind_param('i', $doc_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    if (!$result || !file_exists($result['file_path'])) {
        http_response_code(404);
        die('Document not found');
    }

    $mime = mime_content_type($result['file_path']);

    // Log preview
    logActivity($_SESSION['user_id'], "Previewed document ID $doc_id", 'documents', 'PREVIEW');

    // Send file for preview
    header('Content-Type: ' . $mime);
    readfile($result['file_path']);
    exit;
}

function deleteDocument() {
    global $conn;

    $doc_id = $_GET['id'] ?? null;
    if (!$doc_id) {
        throw new Exception('Document ID required');
    }

    $stmt = $conn->prepare("SELECT file_path FROM project_documents WHERE id = ?");
    $stmt->bind_param('i', $doc_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    if (!$result) {
        throw new Exception('Document not found');
    }

    // Delete file if exists
    if (file_exists($result['file_path'])) {
        unlink($result['file_path']);
    }

    // Delete from database
    $del_stmt = $conn->prepare("DELETE FROM project_documents WHERE id = ?");
    $del_stmt->bind_param('i', $doc_id);
    $del_stmt->execute();

    logActivity($_SESSION['user_id'], "Deleted document ID $doc_id", 'documents', 'DELETE');

    return ['success' => true, 'message' => 'Document deleted successfully'];
}
