<?php
/**
 * Portfolio vs overall system performance — used by Projects batch action.
 * Compares selected projects to catalog-wide averages (approved, non-archived).
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../functions/helpers.php';
require_once __DIR__ . '/../services/ProjectRepository.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode((string) file_get_contents('php://input'), true) ?: [];
$ids = $input['ids'] ?? [];
if (!is_array($ids)) {
    $ids = [];
}
$ids = array_values(array_filter(array_map('intval', $ids), fn ($n) => $n > 0));

$repo = new ProjectRepository($conn);
$avg = $repo->avgProgress('all');
$counts = $repo->counts('all');
$fin = $repo->financialSummary('all');

$system = [
    'total_projects' => $counts['total'],
    'total_allocated' => $fin['total_allocated'],
    'avg_physical' => $avg['avg_physical'],
    'avg_financial' => $avg['avg_financial'],
];

$selection = null;
if ($ids !== []) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    $sql = "SELECT 
        COUNT(*) AS cnt,
        COALESCE(AVG(physical_progress), 0) AS avg_physical,
        COALESCE(AVG(financial_progress), 0) AS avg_financial,
        COALESCE(SUM(allocated_amount), 0) AS total_allocated
        FROM projects 
        WHERE id IN ($placeholders) 
        AND approval_status = 'Approved' 
        AND (status IS NULL OR status <> 'Archived')";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database error']);
        exit;
    }
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $selection = [
        'count' => (int) ($row['cnt'] ?? 0),
        'avg_physical' => round((float) ($row['avg_physical'] ?? 0), 1),
        'avg_financial' => round((float) ($row['avg_financial'] ?? 0), 1),
        'total_allocated' => (float) ($row['total_allocated'] ?? 0),
    ];
}

echo json_encode([
    'status' => 'success',
    'data' => [
        'system' => $system,
        'selection' => $selection,
    ],
]);
