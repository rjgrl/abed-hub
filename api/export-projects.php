<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../functions/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$project_type = strtolower(trim($_POST['type'] ?? 'fspf'));
if ($project_type === '') {
    $project_type = 'fspf';
}
$selected_ids = isset($_POST['selected_ids']) ? json_decode($_POST['selected_ids'], true) : null;

$table = 'projects';

$catalog_filter = "approval_status = 'Approved' AND (status IS NULL OR status <> 'Archived')";

// Build query
if ($selected_ids && is_array($selected_ids) && count($selected_ids) > 0) {
    $placeholders = str_repeat('?,', count($selected_ids) - 1) . '?';
    if ($project_type === 'all') {
        $query = "SELECT * FROM $table WHERE id IN ($placeholders) AND $catalog_filter ORDER BY project_code";
        $stmt = $conn->prepare($query);
        $stmt->bind_param(str_repeat('i', count($selected_ids)), ...array_map('intval', $selected_ids));
    } else {
        $query = "SELECT * FROM $table WHERE project_type = ? AND id IN ($placeholders) AND $catalog_filter ORDER BY project_code";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('s' . str_repeat('i', count($selected_ids)), $project_type, ...array_map('intval', $selected_ids));
    }
} else {
    if ($project_type === 'all') {
        $query = "SELECT * FROM $table WHERE $catalog_filter ORDER BY project_code";
        $stmt = $conn->prepare($query);
    } else {
        $query = "SELECT * FROM $table WHERE project_type = ? AND $catalog_filter ORDER BY project_code";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('s', $project_type);
    }
}

$stmt->execute();
$projects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
$fname_prefix = $project_type === 'all' ? 'ALL' : strtoupper($project_type);
header('Content-Disposition: attachment; filename="' . $fname_prefix . '_projects_' . date('Y-m-d') . '.csv"');

// Create output stream
$output = fopen('php://output', 'w');

// Write CSV headers
fputcsv($output, [
    'Project Code',
    'Project Title',
    'Current Stage',
    'Physical Progress (%)',
    'Financial Progress (%)',
    'Allocated Amount',
    'Contract Amount',
    'Disbursed Amount',
    'Location',
    'Implementing Agency',
    'Created Date',
    'Updated Date'
]);

// Write project data
foreach ($projects as $project) {
    fputcsv($output, [
        $project['project_code'],
        $project['title'],
        $project['current_stage'],
        $project['physical_progress'],
        $project['financial_progress'],
        $project['allocated_amount'],
        $project['contract_amount'] ?? '',
        $project['disbursed_amount'] ?? '',
        trim(($project['municipality'] ?? '') . ', ' . ($project['province'] ?? ''), ', '),
        $project['implementing_office'] ?? '',
        $project['created_at'],
        $project['updated_at']
    ]);
}

fclose($output);
exit;
?>