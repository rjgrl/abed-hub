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
$stmt = $conn->prepare("SELECT id FROM afme_machinery WHERE id = ?");
$stmt->bind_param("i", $machinery_id);
$stmt->execute();
if ($stmt->get_result()->num_rows == 0) {
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

// Save to database
$stmt = $conn->prepare("
    INSERT INTO afme_machinery_documents (
        machinery_id, doc_type, file_path, file_name, stage, upload_date, uploaded_by
    ) VALUES (?, ?, ?, ?, ?, NOW(), ?)
");

$stmt->bind_param(
    "issssi",
    $machinery_id, $doc_type, $file_path, $file_name, $stage, $user_id
);

if ($stmt->execute()) {
    $document_id = $stmt->insert_id;
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

