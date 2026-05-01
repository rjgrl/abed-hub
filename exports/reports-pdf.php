<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/PDFService.php';

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

requireLogin();

$source = $_GET['source'] ?? 'reports';
$year = $_GET['year'] ?? date('Y');
$status = $_GET['status'] ?? 'all';

$safeSource = in_array($source, ['reports', 'analytics'], true) ? $source : 'reports';

$title = $safeSource === 'analytics' ? 'Analytics Report' : 'Project Report';
$html = '<h2 style="margin-bottom:8px;">' . htmlspecialchars($title) . '</h2>';
$html .= '<p style="margin-top:0;color:#666;">Generated: ' . date('Y-m-d H:i') . '</p>';
$html .= '<p><strong>Year:</strong> ' . htmlspecialchars((string) $year) . ' &nbsp; <strong>Status:</strong> ' . htmlspecialchars((string) $status) . '</p>';

$summary = $conn->query("
    SELECT
        (SELECT COUNT(*) FROM projects WHERE approval_status = 'Approved') AS total_projects
")->fetch_assoc();

$html .= '<table width="100%" cellpadding="8" cellspacing="0" border="1" style="border-collapse: collapse; font-size: 12px;">';
$html .= '<tr><th align="left">Metric</th><th align="left">Value</th></tr>';
$html .= '<tr><td>Total Projects</td><td>' . (int) ($summary['total_projects'] ?? 0) . '</td></tr>';
$html .= '</table>';

$css = 'body { font-family: sans-serif; font-size: 12px; } h2 { color: #0d6efd; } th { background: #f4f6f8; }';

try {
    generatePDF([
        'title' => $title,
        'filename' => strtolower(str_replace(' ', '-', $title)) . '-' . date('Ymd-His') . '.pdf',
        'html' => $html,
        'css' => $css,
        'mode' => 'I',
    ]);
} catch (RuntimeException $e) {
    http_response_code(500);
    echo htmlspecialchars($e->getMessage());
}
