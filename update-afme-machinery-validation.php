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
$date_validation_start = $_POST['date_validation_start'] ?? null;
$date_validation_end = $_POST['date_validation_end'] ?? null;
$implementation_type = sanitize($_POST['implementation_type'] ?? '');
$service_area = sanitize($_POST['service_area'] ?? '');
$latitude = floatval($_POST['latitude'] ?? 0);
$longitude = floatval($_POST['longitude'] ?? 0);

// Optional legacy column support: some deployments have implementation_type, others do not.
$hasImplementationType = false;
$colCheck = $conn->query("SELECT COUNT(*) AS c FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'afme' AND COLUMN_NAME = 'implementation_type'");
if ($colCheck && ($row = $colCheck->fetch_assoc())) {
    $hasImplementationType = ((int) ($row['c'] ?? 0)) > 0;
}

// Verify machinery
$stmt = $conn->prepare("SELECT id FROM afme WHERE id = ?");
$stmt->bind_param("i", $machinery_id);
$stmt->execute();
if ($stmt->get_result()->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Machinery not found']);
    exit;
}

// Handle file uploads
$validation_report_path = null;
$geotagged_photos = [];

if (isset($_FILES['validation_report']) && $_FILES['validation_report']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = UPLOAD_DIR . 'afme/validation-reports/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $file = $_FILES['validation_report'];
    if ($file['type'] === 'application/pdf' && $file['size'] <= MAX_FILE_SIZE) {
        $filename = 'validation_' . $machinery_id . '_' . time() . '.pdf';
        if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
            $validation_report_path = $upload_dir . $filename;
        }
    }
}

if (isset($_FILES['geotagged_photos'])) {
    $upload_dir = UPLOAD_DIR . 'afme/geotagged-photos/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    foreach ($_FILES['geotagged_photos']['tmp_name'] as $key => $tmp_name) {
        if ($_FILES['geotagged_photos']['error'][$key] === UPLOAD_ERR_OK) {
            $file_type = $_FILES['geotagged_photos']['type'][$key];
            $file_size = $_FILES['geotagged_photos']['size'][$key];
            $file_name = $_FILES['geotagged_photos']['name'][$key];

            if (in_array($file_type, ALLOWED_IMAGE_TYPES) && $file_size <= MAX_FILE_SIZE) {
                $filename = 'photo_' . $machinery_id . '_' . time() . '_' . $key . '.' . pathinfo($file_name, PATHINFO_EXTENSION);
                $photo_path = $upload_dir . $filename;
                
                if (move_uploaded_file($tmp_name, $photo_path)) {
                    $geotagged_photos[] = $photo_path;
                }
            }
        }
    }
}

$geotagged_photos_json = json_encode($geotagged_photos);

// Update validation fields in consolidated afme table
if ($hasImplementationType) {
    $stmt = $conn->prepare("
        UPDATE afme SET
            date_validation_start = ?,
            date_validation_end = ?,
            implementation_type = ?,
            service_area = ?,
            validation_report_path = COALESCE(?, validation_report_path),
            geotagged_photo_paths = ?,
            latitude = ?,
            longitude = ?,
            validation_status = 'Completed',
            current_status = 'Pre-Implementation'
        WHERE id = ?
    ");
    $stmt->bind_param(
        "ssssssddi",
        $date_validation_start, $date_validation_end,
        $implementation_type, $service_area, $validation_report_path,
        $geotagged_photos_json, $latitude, $longitude, $machinery_id
    );
} else {
    $stmt = $conn->prepare("
        UPDATE afme SET
            date_validation_start = ?,
            date_validation_end = ?,
            service_area = ?,
            validation_report_path = COALESCE(?, validation_report_path),
            geotagged_photo_paths = ?,
            latitude = ?,
            longitude = ?,
            validation_status = 'Completed',
            current_status = 'Pre-Implementation'
        WHERE id = ?
    ");
    $stmt->bind_param(
        "sssssddi",
        $date_validation_start, $date_validation_end,
        $service_area, $validation_report_path,
        $geotagged_photos_json, $latitude, $longitude, $machinery_id
    );
}

if ($stmt->execute()) {
    logAudit('UPDATE_AFME_MACHINERY_VALIDATION', 'AFME', $machinery_id, null, $_POST);

    echo json_encode([
        'status' => 'success',
        'message' => 'Validation details updated',
        'latitude' => $latitude,
        'longitude' => $longitude,
        'photos_uploaded' => count($geotagged_photos)
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Error updating validation']);
}

$stmt->close();
$conn->close();
?>

