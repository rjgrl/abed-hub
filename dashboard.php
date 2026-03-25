<?php
session_start();
require_once 'config/database.php';

requireLogin();

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];

// Get dashboard statistics
$stats = [];

// Count projects by type
$types = ['FSPF', 'IDP', 'AFME'];
foreach ($types as $type) {
    $table = $type === 'FSPF' ? 'fspf_projects' : ($type === 'IDP' ? 'idp_projects' : 'afme_projects');
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM $table");
    $stmt->execute();
    $stats['total_' . strtolower($type)] = $stmt->get_result()->fetch_assoc()['total'];
}

// Get recent projects
$recent_projects = [];

// FSPF
$stmt = $conn->prepare("
    SELECT 'FSPF' as type, id, project_code, project_title, proposal_status as status, physical_progress, updated_at 
    FROM fspf_projects 
    ORDER BY updated_at DESC 
    LIMIT 5
");
$stmt->execute();
$recent_projects['fspf'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// IDP
$stmt = $conn->prepare("
    SELECT 'IDP' as type, id, project_code, project_title, proposal_status as status, physical_progress, updated_at 
    FROM idp_projects 
    ORDER BY updated_at DESC 
    LIMIT 5
");
$stmt->execute();
$recent_projects['idp'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// AFME
$stmt = $conn->prepare("
    SELECT 'AFME' as type, id, project_code, project_title, proposal_status as status, updated_at 
    FROM afme_projects 
    ORDER BY updated_at DESC 
    LIMIT 5
");
$stmt->execute();
$recent_projects['afme'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo json_encode([
    'status' => 'success',
    'user' => [
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['full_name'],
        'role' => $_SESSION['role'],
        'office_unit' => $_SESSION['office_unit']
    ],
    'stats' => $stats,
    'recent_projects' => $recent_projects
]);

$conn->close();
?>