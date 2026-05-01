<?php
session_start();
require_once 'config/database.php';

requireLogin();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$user_id = $_SESSION['user_id'];
$machinery_id = intval($_POST['machinery_id'] ?? 0);
$doc_type = sanitize($_POST['doc_type'] ?? '');
$stage = sanitize($_POST['stage'] ?? '');

// Validate inputs
if ($machinery_id <= 0 || empty($doc_type)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid input parameters']);
    exit;
}

// Check if machinery exists
$stmt = $conn->prepare("SELECT id, documents FROM afme WHERE id = ?");
$stmt->bind_param("i", $machinery_id);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
if (!$existing) {
    echo json_encode(['status' => 'error', 'message' => 'Machinery not found']);
    exit;
}

// Check if file was uploaded
if (!isset($_FILES['document_file']) || $_FILES['document_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['status' => 'error', 'message' => 'No file uploaded or upload error']);
    exit;
}

$file = $_FILES['document_file'];
$file_name = $file['name'];
$file_size = $file['size'];
$file_tmp = $file['tmp_name'];

// Validate file size
if ($file_size > MAX_FILE_SIZE) {
    echo json_encode(['status' => 'error', 'message' => 'File size exceeds maximum limit of 10MB']);
    exit;
}

// Validate file type
$allowed_types = array_merge(ALLOWED_IMAGE_TYPES, ALLOWED_DOCUMENT_TYPES);
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $file_tmp);
finfo_close($finfo);

if (!in_array($mime_type, $allowed_types)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid file type. Allowed: PDF, DOC, DOCX, JPG, PNG']);
    exit;
}

// Create upload directory if it doesn't exist
$upload_dir = UPLOAD_DIR . 'AFME/' . $machinery_id . '/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Generate unique filename
$file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
$unique_filename = uniqid() . '_' . time() . '.' . $file_extension;
$file_path = $upload_dir . $unique_filename;

// Move uploaded file
if (!move_uploaded_file($file_tmp, $file_path)) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to save file']);
    exit;
}

// Save into afme.documents JSON
$documents = json_decode((string)($existing['documents'] ?? '[]'), true);
if (!is_array($documents)) {
    $documents = [];
}
$document_id = count($documents) + 1;
$documents[] = [
    'id' => $document_id,
    'doc_type' => $doc_type,
    'file_path' => $file_path,
    'file_name' => $file_name,
    'stage' => $stage,
    'upload_date' => date('Y-m-d H:i:s'),
    'uploaded_by' => $user_id,
    'review_status' => 'Pending',
    'reviewed_by' => null,
    'reviewed_at' => null
];
$documentsJson = json_encode($documents);

$stmt = $conn->prepare("UPDATE afme SET documents = ? WHERE id = ?");
$stmt->bind_param("si", $documentsJson, $machinery_id);

if ($stmt->execute()) {
    logAudit('UPLOAD_MACHINERY_DOCUMENT', 'AFME', $machinery_id, null, [
        'doc_type' => $doc_type,
        'file_name' => $file_name,
        'file_path' => $file_path
    ]);

    echo json_encode([
        'status' => 'success',
        'message' => 'Document uploaded successfully',
        'document_id' => $document_id
    ]);
} else {
    // Delete uploaded file if database insert failed
    unlink($file_path);
    echo json_encode(['status' => 'error', 'message' => 'Failed to save document record']);
}

$stmt->close();
$conn->close();
?>

