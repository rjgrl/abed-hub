<?php
session_start();
require_once 'config/database.php';

requireLogin();
header('Content-Type: application/json');

$filter_status = $_GET['status'] ?? '';
$filter_funding_year = $_GET['funding_year'] ?? '';
$search_term = $_GET['search'] ?? '';

$query = "SELECT 
    m.id, m.machine_name, m.farm_operation, m.beneficiary_name,
    m.current_status, m.funding_year, m.allocated_amount,
    COUNT(d.id) as document_count
FROM afme_machinery m
LEFT JOIN afme_machinery_documents d ON m.id = d.machinery_id
WHERE 1=1";

$params = [];
$types = '';

if ($filter_status) {
    $query .= " AND m.current_status = ?";
    $params[] = $filter_status;
    $types .= 's';
}

if ($filter_funding_year) {
    $query .= " AND m.funding_year = ?";
    $params[] = intval($filter_funding_year);
    $types .= 'i';
}

if ($search_term) {
    $query .= " AND (m.machine_name LIKE ? OR m.beneficiary_name LIKE ?)";
    $search_pattern = '%' . $search_term . '%';
    $params[] = $search_pattern;
    $params[] = $search_pattern;
    $types .= 'ss';
}

$query .= " GROUP BY m.id ORDER BY m.created_at DESC";

$stmt = $conn->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$machinery_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get status statistics
$stmt = $conn->prepare("
    SELECT current_status, COUNT(*) as count
    FROM afme_machinery
    GROUP BY current_status
");
$stmt->execute();
$status_stats = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo json_encode([
    'status' => 'success',
    'machinery' => $machinery_list,
    'total_count' => count($machinery_list),
    'status_statistics' => $status_stats
]);

$conn->close();
?>