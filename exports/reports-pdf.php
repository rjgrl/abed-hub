<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/PDFService.php';
require_once __DIR__ . '/../services/ProjectRepository.php';

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

requireLogin();

$source = $_GET['source'] ?? 'reports';
$year = $_GET['year'] ?? date('Y');
$status = $_GET['status'] ?? 'all';
$report = $_GET['report'] ?? 'summary';
if (!in_array($report, ['summary', 'detailed', 'performance'], true)) {
    $report = 'summary';
}
$curve_param = strtolower((string) ($_GET['curve'] ?? 'all'));
if (!in_array($curve_param, ['all', 'fspf', 'idp', 'afme'], true)) {
    $curve_param = 'all';
}
$curve_pdf_label = $curve_param === 'all' ? 'All programs' : strtoupper($curve_param);

$safeSource = in_array($source, ['reports', 'analytics'], true) ? $source : 'reports';

$title = $safeSource === 'analytics' ? 'Analytics Report' : 'Project Report';
if ($safeSource === 'analytics') {
    $title .= match ($report) {
        'summary' => ' — Summary',
        'detailed' => ' — Detailed',
        default => ' — Performance',
    };
}

$html = '<h2 style="margin-bottom:8px;">' . htmlspecialchars($title) . '</h2>';
$html .= '<p style="margin-top:0;color:#666;">Generated: ' . date('Y-m-d H:i') . '</p>';
$html .= '<p><strong>Year:</strong> ' . htmlspecialchars((string) $year) . ' &nbsp; <strong>Status:</strong> ' . htmlspecialchars((string) $status) . '</p>';

if ($safeSource === 'analytics') {
    $html .= '<p><strong>Monthly chart cohort:</strong> ' . htmlspecialchars($curve_pdf_label) . ' <span style="color:#666;">(FSPF / IDP / AFME / All — same as Analytics filters)</span></p>';
    $repo = new ProjectRepository($conn);
    $counts = $repo->counts();
    $finance = $repo->financialSummary();
    $avg = $repo->avgProgress('all');
    $completed = $repo->completedCount();
    $total_prop = $finance['total_proposed'];
    $total_alloc = $finance['total_allocated'];
    $util = $total_prop > 0 ? round(($total_alloc / $total_prop) * 100, 1) : null;

    $html .= '<p style="font-size:12px;line-height:1.5;"><strong>Report mode:</strong> ';
    if ($report === 'summary') {
        $html .= 'Executive summary — headline KPIs only; open the web view for the portfolio mix chart.</p>';
        $html .= '<ul style="font-size:12px;"><li>Approved projects: <strong>' . (int) $counts['total'] . '</strong></li>';
        $html .= '<li>Total allocated: <strong>₱' . number_format($total_alloc, 0) . '</strong></li>';
        $html .= '<li>Average physical progress: <strong>' . $avg['avg_physical'] . '%</strong></li>';
        $html .= '<li>Completed / turned over: <strong>' . $completed . '</strong></li></ul>';
    } elseif ($report === 'detailed') {
        $html .= 'Detailed — includes methodology note on screen; PDF lists totals and proposed vs allocated.</p>';
        $html .= '<table width="100%" cellpadding="8" cellspacing="0" border="1" style="border-collapse: collapse; font-size: 12px;">';
        $html .= '<tr><th align="left">Metric</th><th align="left">Value</th></tr>';
        $html .= '<tr><td>Total projects (approved)</td><td>' . (int) $counts['total'] . '</td></tr>';
        $html .= '<tr><td>Total proposed (catalog)</td><td>₱' . number_format($total_prop, 0) . '</td></tr>';
        $html .= '<tr><td>Total allocated</td><td>₱' . number_format($total_alloc, 0) . '</td></tr>';
        $html .= '<tr><td>Average physical %</td><td>' . $avg['avg_physical'] . '%</td></tr>';
        $html .= '<tr><td>Average financial %</td><td>' . $avg['avg_financial'] . '%</td></tr>';
        $html .= '<tr><td>Completed / turned over</td><td>' . $completed . '</td></tr>';
        $html .= '</table>';
        $html .= '<p style="font-size:11px;color:#555;margin-top:8px;">Charts and extended tables are available in Analytics &amp; Reports (Detailed) in the browser.</p>';
    } else {
        $gap = round($avg['avg_physical'] - $avg['avg_financial'], 1);
        $html .= 'Performance — metrics and variance interpretation.</p>';
        $html .= '<table width="100%" cellpadding="8" cellspacing="0" border="1" style="border-collapse: collapse; font-size: 12px;">';
        $html .= '<tr><th align="left">Metric</th><th align="left">Value</th></tr>';
        $html .= '<tr><td>Avg physical %</td><td>' . $avg['avg_physical'] . '%</td></tr>';
        $html .= '<tr><td>Avg financial %</td><td>' . $avg['avg_financial'] . '%</td></tr>';
        $html .= '<tr><td>Spread (physical − financial)</td><td>' . ($gap >= 0 ? '+' : '') . $gap . ' pts</td></tr>';
        $html .= '<tr><td>Allocated / proposed</td><td>' . ($util !== null ? $util . '%' : '—') . '</td></tr>';
        $html .= '<tr><td>Completion count</td><td>' . $completed . ' / ' . (int) $counts['total'] . ' catalog</td></tr>';
        $html .= '</table>';
        $html .= '<p style="font-size:11px;color:#555;margin-top:8px;">Ranked performer and variance tables are on the Performance view in the app.</p>';
    }
} else {
    $summary = $conn->query("
        SELECT
            (SELECT COUNT(*) FROM projects WHERE approval_status = 'Approved') AS total_projects
    ")->fetch_assoc();

    $html .= '<table width="100%" cellpadding="8" cellspacing="0" border="1" style="border-collapse: collapse; font-size: 12px;">';
    $html .= '<tr><th align="left">Metric</th><th align="left">Value</th></tr>';
    $html .= '<tr><td>Total Projects</td><td>' . (int) ($summary['total_projects'] ?? 0) . '</td></tr>';
    $html .= '</table>';
}

$css = 'body { font-family: sans-serif; font-size: 12px; } h2 { color: #5b8def; } th { background: #f4f6f8; }';

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
