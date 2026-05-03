<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../functions/helpers.php';
require_once __DIR__ . '/../services/PDFService.php';

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

requireLogin();

$projectType = (string) ($_GET['type'] ?? 'fspf');
$projectId = (int) ($_GET['id'] ?? 0);

if ($projectId <= 0) {
    http_response_code(400);
    exit('Project ID required.');
}

$stmt = $conn->prepare('SELECT *, title AS project_title FROM projects WHERE id = ? AND project_type = ? LIMIT 1');
$stmt->bind_param('is', $projectId, $projectType);
$stmt->execute();
$project = $stmt->get_result()->fetch_assoc();

if (!$project) {
    http_response_code(404);
    exit('Project not found.');
}

$approval = (string) ($project['approval_status'] ?? 'Approved');
if ($approval !== 'Approved') {
    $uid = (int) ($_SESSION['user_id'] ?? 0);
    $owner = (int) ($project['user_id'] ?? 0);
    if (!isSuperAdmin() && $owner !== $uid) {
        http_response_code(404);
        exit('Project not found.');
    }
}

$documents = [];
$rawDocs = json_decode((string) ($project['documents'] ?? '[]'), true);
if (is_array($rawDocs)) {
    $uploadUserIds = [];
    foreach ($rawDocs as $doc) {
        if (!is_array($doc)) {
            continue;
        }
        $uid = (int) ($doc['uploaded_by'] ?? 0);
        if ($uid > 0) {
            $uploadUserIds[$uid] = true;
        }
    }

    $uploadUserNames = [];
    if (!empty($uploadUserIds)) {
        $ids = array_values(array_unique(array_map('intval', array_keys($uploadUserIds))));
        $ids = array_filter($ids, static fn ($id) => $id > 0);
        if (!empty($ids)) {
            $inList = implode(',', $ids);
            $uq = $conn->query("SELECT id, first_name, last_name FROM users WHERE id IN ($inList)");
            if ($uq) {
                foreach ($uq->fetch_all(MYSQLI_ASSOC) as $row) {
                    $uploadUserNames[(int) $row['id']] = user_display_name($row['first_name'] ?? '', $row['last_name'] ?? '');
                }
            }
        }
    }

    foreach ($rawDocs as $doc) {
        if (!is_array($doc)) {
            continue;
        }
        $uid = (int) ($doc['uploaded_by'] ?? 0);
        $documents[] = [
            'original_filename' => (string) ($doc['original_filename'] ?? $doc['file_name'] ?? 'Document'),
            'document_type' => (string) ($doc['document_type'] ?? $doc['doc_type'] ?? 'General'),
            'uploaded_by' => $uid > 0 ? ($uploadUserNames[$uid] ?? ('User #' . $uid)) : 'Unknown',
            'upload_date' => (string) ($doc['upload_date'] ?? ''),
        ];
    }

    usort($documents, static function ($a, $b) {
        return strcmp((string) ($b['upload_date'] ?? ''), (string) ($a['upload_date'] ?? ''));
    });
}

$financialRecords = [];
$rawFinancial = json_decode((string) ($project['financial_entries'] ?? '[]'), true);
if (is_array($rawFinancial)) {
    foreach ($rawFinancial as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $financialRecords[] = [
            'record_type' => (string) ($entry['record_type'] ?? 'Other'),
            'amount' => (float) ($entry['amount'] ?? 0),
            'reference_number' => (string) ($entry['reference_number'] ?? ''),
            'record_date' => (string) ($entry['record_date'] ?? ''),
            'record_status' => (string) ($entry['record_status'] ?? 'Active'),
        ];
    }

    usort($financialRecords, static function ($a, $b) {
        return strcmp((string) ($b['record_date'] ?? ''), (string) ($a['record_date'] ?? ''));
    });
}

$machinery = [];
if ($projectType === 'afme') {
    $machStmt = $conn->prepare('SELECT * FROM afme WHERE project_id = ? ORDER BY created_at DESC');
    $machStmt->bind_param('i', $projectId);
    $machStmt->execute();
    $machinery = $machStmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$auditStmt = $conn->prepare('SELECT action, created_at FROM audit_log WHERE project_type = ? AND project_id = ? ORDER BY created_at DESC LIMIT 50');
$auditProjectType = strtoupper($projectType);
$auditStmt->bind_param('si', $auditProjectType, $projectId);
$auditStmt->execute();
$auditLog = $auditStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$fmtDate = static function (?string $dateString, string $format = 'M d, Y'): string {
    if (!$dateString) {
        return '-';
    }
    $ts = strtotime($dateString);
    return $ts ? date($format, $ts) : '-';
};
$fmtMoney = static function ($amount): string {
    return 'P' . number_format((float) $amount, 2);
};

$physicalProgress = round((float) ($project['physical_progress'] ?? 0), 1);
$financialProgress = round((float) ($project['financial_progress'] ?? 0), 1);

$safe = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};

$html = '';
$html .= '<h1>Project Detail Report</h1>';
$html .= '<p class="meta">Generated: ' . $safe(date('Y-m-d H:i:s')) . '</p>';
$html .= '<p class="meta"><strong>Type:</strong> ' . $safe(strtoupper($projectType)) . ' &nbsp; <strong>ID:</strong> ' . (int) $projectId . '</p>';

$html .= '<h2>Project Overview</h2>';
$html .= '<table class="details"><tbody>';
$html .= '<tr><th>Project Code</th><td>' . $safe($project['project_code'] ?? '-') . '</td><th>Title</th><td>' . $safe($project['project_title'] ?? '-') . '</td></tr>';
$html .= '<tr><th>Current Stage</th><td>' . $safe($project['current_stage'] ?? '-') . '</td><th>Approval Status</th><td>' . $safe($approval) . '</td></tr>';
$html .= '<tr><th>Province</th><td>' . $safe($project['province'] ?? '-') . '</td><th>Municipality</th><td>' . $safe($project['municipality'] ?? '-') . '</td></tr>';
$html .= '<tr><th>Barangay</th><td>' . $safe($project['barangay'] ?? '-') . '</td><th>Description</th><td>' . $safe($project['description'] ?? '-') . '</td></tr>';
$html .= '<tr><th>Proposed Amount</th><td>' . $safe($fmtMoney($project['proposed_amount'] ?? 0)) . '</td><th>Allocated Amount</th><td>' . $safe($fmtMoney($project['allocated_amount'] ?? 0)) . '</td></tr>';
$html .= '<tr><th>Physical Progress</th><td>' . $safe($physicalProgress . '%') . '</td><th>Financial Progress</th><td>' . $safe($financialProgress . '%') . '</td></tr>';
$html .= '<tr><th>Created At</th><td>' . $safe($fmtDate($project['created_at'] ?? null, 'M d, Y H:i')) . '</td><th>Updated At</th><td>' . $safe($fmtDate($project['updated_at'] ?? null, 'M d, Y H:i')) . '</td></tr>';
$html .= '</tbody></table>';

$html .= '<h2>Financial Records</h2>';
if (empty($financialRecords)) {
    $html .= '<p class="empty">No financial records.</p>';
} else {
    $html .= '<table><thead><tr><th>Type</th><th>Amount</th><th>Reference</th><th>Date</th><th>Status</th></tr></thead><tbody>';
    foreach ($financialRecords as $record) {
        $html .= '<tr>';
        $html .= '<td>' . $safe($record['record_type']) . '</td>';
        $html .= '<td>' . $safe($fmtMoney($record['amount'])) . '</td>';
        $html .= '<td>' . $safe($record['reference_number'] !== '' ? $record['reference_number'] : '-') . '</td>';
        $html .= '<td>' . $safe($fmtDate($record['record_date'] ?? null)) . '</td>';
        $html .= '<td>' . $safe($record['record_status']) . '</td>';
        $html .= '</tr>';
    }
    $html .= '</tbody></table>';
}

$html .= '<h2>Documents</h2>';
if (empty($documents)) {
    $html .= '<p class="empty">No uploaded documents.</p>';
} else {
    $html .= '<table><thead><tr><th>Filename</th><th>Type</th><th>Uploaded By</th><th>Upload Date</th></tr></thead><tbody>';
    foreach ($documents as $doc) {
        $html .= '<tr>';
        $html .= '<td>' . $safe($doc['original_filename']) . '</td>';
        $html .= '<td>' . $safe($doc['document_type']) . '</td>';
        $html .= '<td>' . $safe($doc['uploaded_by']) . '</td>';
        $html .= '<td>' . $safe($fmtDate($doc['upload_date'] ?? null)) . '</td>';
        $html .= '</tr>';
    }
    $html .= '</tbody></table>';
}

if ($projectType === 'afme') {
    $html .= '<h2>AFME Machinery Details</h2>';
    if (empty($machinery)) {
        $html .= '<p class="empty">No machinery records.</p>';
    } else {
        $totalMachineryCost = 0.0;
        $html .= '<table><thead><tr><th>Machinery Type</th><th>Quantity</th><th>Unit Cost</th><th>Total Cost</th><th>Status</th></tr></thead><tbody>';
        foreach ($machinery as $mach) {
            $qty = (float) ($mach['unit_quantity'] ?? 0);
            $unitCost = (float) ($mach['unit_cost'] ?? 0);
            $rowTotal = $qty * $unitCost;
            $totalMachineryCost += $rowTotal;

            $html .= '<tr>';
            $html .= '<td>' . $safe($mach['machinery_type'] ?? '-') . '</td>';
            $html .= '<td>' . $safe(number_format($qty, 2)) . '</td>';
            $html .= '<td>' . $safe($fmtMoney($unitCost)) . '</td>';
            $html .= '<td>' . $safe($fmtMoney($rowTotal)) . '</td>';
            $html .= '<td>' . $safe($mach['machinery_status'] ?? '-') . '</td>';
            $html .= '</tr>';
        }
        $html .= '<tr><th colspan="3" style="text-align:right;">Total Machinery Cost</th><th colspan="2">' . $safe($fmtMoney($totalMachineryCost)) . '</th></tr>';
        $html .= '</tbody></table>';
    }
}

$html .= '<h2>Activity Log</h2>';
if (empty($auditLog)) {
    $html .= '<p class="empty">No activity recorded.</p>';
} else {
    $html .= '<table><thead><tr><th>Action</th><th>Date/Time</th></tr></thead><tbody>';
    foreach ($auditLog as $entry) {
        $html .= '<tr>';
        $html .= '<td>' . $safe($entry['action'] ?? '-') . '</td>';
        $html .= '<td>' . $safe($fmtDate($entry['created_at'] ?? null, 'M d, Y H:i')) . '</td>';
        $html .= '</tr>';
    }
    $html .= '</tbody></table>';
}

$css = '
body { font-family: sans-serif; font-size: 11px; color: #1f2937; }
h1 { font-size: 20px; margin: 0 0 6px 0; color: #1e3a8a; }
h2 { font-size: 14px; margin: 18px 0 8px 0; padding-bottom: 4px; border-bottom: 1px solid #d1d5db; color: #111827; }
.meta { margin: 0 0 6px 0; color: #4b5563; }
table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
th, td { border: 1px solid #d1d5db; padding: 6px 8px; vertical-align: top; }
th { background: #f3f4f6; text-align: left; }
.details th { width: 18%; }
.details td { width: 32%; }
.empty { color: #6b7280; font-style: italic; }
';

try {
    generatePDF([
        'title' => 'Project Detail - ' . ($project['project_code'] ?? ('ID ' . $projectId)),
        'filename' => 'project-detail-' . $projectType . '-' . $projectId . '-' . date('Ymd-His') . '.pdf',
        'html' => $html,
        'css' => $css,
        'mode' => 'I',
    ]);
} catch (RuntimeException $e) {
    http_response_code(500);
    echo htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
}
