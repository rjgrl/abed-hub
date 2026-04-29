<?php
session_name('ABED_IDM_HUB');
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
$turnover_date = $_POST['turnover_date'] ?? date('Y-m-d');
$documentary_requirements_met = isset($_POST['documentary_requirements_met']) ? 1 : 0;

// Verify machinery
$stmt = $conn->prepare("SELECT id, current_status FROM afme WHERE id = ?");
$stmt->bind_param("i", $machinery_id);
$stmt->execute();
$machinery = $stmt->get_result()->fetch_assoc();

if (!$machinery) {
    echo json_encode(['status' => 'error', 'message' => 'Machinery not found']);
    exit;
}

if ($machinery['current_status'] !== 'Delivered') {
    echo json_encode(['status' => 'error', 'message' => 'Machinery must be Delivered before turn-over']);
    exit;
}

// Handle turn-over photos
$turnover_photos = [];
if (isset($_FILES['turnover_photos'])) {
    $upload_dir = UPLOAD_DIR . 'afme/turnover-photos/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    foreach ($_FILES['turnover_photos']['tmp_name'] as $key => $tmp_name) {
        if ($_FILES['turnover_photos']['error'][$key] === UPLOAD_ERR_OK) {
            $file_type = $_FILES['turnover_photos']['type'][$key];
            $file_size = $_FILES['turnover_photos']['size'][$key];
            $file_name = $_FILES['turnover_photos']['name'][$key];

            if (in_array($file_type, ALLOWED_IMAGE_TYPES) && $file_size <= MAX_FILE_SIZE) {
                $filename = 'turnover_' . $machinery_id . '_' . time() . '_' . $key . '.' . pathinfo($file_name, PATHINFO_EXTENSION);
                $photo_path = $upload_dir . $filename;
                
                if (move_uploaded_file($tmp_name, $photo_path)) {
                    $turnover_photos[] = $photo_path;
                    
                    // photos are kept on disk only in refactored schema
                }
            }
        }
    }
}

// Update turn-over fields in consolidated afme table
$stmt = $conn->prepare("
    UPDATE afme
    SET turnover_date = ?,
        turnover_status = 'Completed',
        documentary_requirements_met = ?,
        current_status = 'Turned-Over'
    WHERE id = ?
");

$stmt->bind_param("sii", $turnover_date, $documentary_requirements_met, $machinery_id);

if ($stmt->execute()) {
    logAudit('AFME_MACHINERY_TURNOVER', 'AFME', $machinery_id, null, $_POST);

    echo json_encode([
        'status' => 'success',
        'message' => 'Machinery successfully turned-over',
        'turnover_date' => $turnover_date,
        'photos_uploaded' => count($turnover_photos)
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Error processing turn-over']);
}

$stmt->close();
$conn->close();
?>

