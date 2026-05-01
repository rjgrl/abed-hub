<?php
/**
 * Legacy project document upload (writes to projects.documents JSON).
 * Kept for older UI; new code should prefer api/documents.php?action=upload.
 */
require_once __DIR__ . '/config/database.php';

requireLogin();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$user_id = (int) ($_SESSION['user_id'] ?? 0);
$project_type_raw = sanitize($_POST['project_type'] ?? '');
$project_id = (int) ($_POST['project_id'] ?? 0);
$doc_type = sanitize($_POST['doc_type'] ?? $_POST['document_type'] ?? '');
$stage = sanitize($_POST['stage'] ?? '');

$typeMap = ['FSPF' => 'fspf', 'IDP' => 'idp', 'AFME' => 'afme'];
$project_type = $typeMap[strtoupper($project_type_raw)] ?? strtolower($project_type_raw);
if (!in_array($project_type, ['fspf', 'idp', 'afme'], true) || $project_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid input parameters']);
    exit;
}

if ($doc_type === '') {
    echo json_encode(['status' => 'error', 'message' => 'Document type is required']);
    exit;
}

$stmt = $conn->prepare('SELECT id, project_type, documents, approval_status FROM projects WHERE id = ? AND project_type = ?');
$stmt->bind_param('is', $project_id, $project_type);
$stmt->execute();
$project = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$project) {
    echo json_encode(['status' => 'error', 'message' => 'Project not found']);
    exit;
}

if (($project['approval_status'] ?? 'Approved') !== 'Approved') {
    echo json_encode(['status' => 'error', 'message' => 'This project is pending Super Admin approval; documents cannot be uploaded yet.']);
    exit;
}

if (!isset($_FILES['document_file']) || $_FILES['document_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['status' => 'error', 'message' => 'No file uploaded or upload error']);
    exit;
}

$file = $_FILES['document_file'];
$file_name = $file['name'];
$file_size = (int) $file['size'];
$file_tmp = $file['tmp_name'];

if ($file_size > MAX_FILE_SIZE) {
    echo json_encode(['status' => 'error', 'message' => 'File size exceeds maximum limit of 10MB']);
    exit;
}

$allowed_types = array_merge(ALLOWED_IMAGE_TYPES, ALLOWED_DOCUMENT_TYPES);
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $file_tmp);
finfo_close($finfo);

$ext = strtolower((string) pathinfo((string) $file_name, PATHINFO_EXTENSION));
$allowed_extensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'webp', 'gif', 'heic', 'heif'];
if (!in_array($mime_type, $allowed_types, true) && !in_array($ext, $allowed_extensions, true)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid file type. Allowed: PDF, DOC, DOCX, JPG, PNG, WEBP, GIF, HEIC, HEIF']);
    exit;
}

$uploadRoot = defined('UPLOAD_DIR') ? UPLOAD_DIR : (__DIR__ . '/uploads/');
$folder = rtrim($uploadRoot, '/\\') . DIRECTORY_SEPARATOR . $project_type . DIRECTORY_SEPARATOR . 'documents' . DIRECTORY_SEPARATOR . $project_id . DIRECTORY_SEPARATOR;
if (!is_dir($folder) && !mkdir($folder, 0755, true) && !is_dir($folder)) {
    echo json_encode(['status' => 'error', 'message' => 'Unable to create upload directory']);
    exit;
}

$file_extension = $ext;
$storedName = uniqid('doc_', true) . ($file_extension ? '.' . $file_extension : '');
$file_path = $folder . $storedName;

if (!move_uploaded_file($file_tmp, $file_path)) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to save file']);
    exit;
}

$docs = json_decode((string) ($project['documents'] ?? '[]'), true);
if (!is_array($docs)) {
    $docs = [];
}
$nextId = 1;
foreach ($docs as $d) {
    if (is_array($d) && isset($d['id']) && (int) $d['id'] >= $nextId) {
        $nextId = (int) $d['id'] + 1;
    }
}

$docs[] = [
    'id' => $nextId,
    'project_id' => $project_id,
    'project_type' => $project_type,
    'document_type' => $doc_type,
    'stage' => $stage,
    'description' => '',
    'original_filename' => (string) $file_name,
    'file_path' => $file_path,
    'mime_type' => $mime_type,
    'upload_date' => date('Y-m-d H:i:s'),
    'uploaded_by' => $user_id,
    'review_status' => 'Pending',
    'reviewed_by' => null,
    'reviewed_at' => null,
];

$documentsJson = json_encode($docs);
$up = $conn->prepare('UPDATE projects SET documents = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
$up->bind_param('si', $documentsJson, $project_id);

if ($up->execute()) {
    logAudit('UPLOAD_PROJECT_DOCUMENT', strtoupper($project_type), $project_id, null, [
        'document_type' => $doc_type,
        'file_name' => $file_name,
    ]);
    echo json_encode([
        'status' => 'success',
        'message' => 'Document uploaded successfully',
        'document_id' => $nextId,
    ]);
} else {
    @unlink($file_path);
    echo json_encode(['status' => 'error', 'message' => 'Failed to save document record']);
}

$up->close();
$conn->close();
