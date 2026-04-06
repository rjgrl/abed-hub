<?php
require_once __DIR__ . '/components/layout.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/functions/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Handle export requests
$export = $_GET['export'] ?? null;
$report_type = $_GET['report_type'] ?? 'projects';
$project_type = $_GET['project_type'] ?? null;
$year = $_GET['year'] ?? date('Y');

if ($export) {
    handleExport($export, $report_type, $project_type, $year);
    exit;
}

function handleExport($format, $report_type, $project_type, $year) {
    global $conn;

    $filename = $report_type . '_' . $year . '.' . strtolower($format);

    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"$filename\"");
        
        $output = fopen('php://output', 'w');
        
        if ($report_type === 'projects') {
            exportProjectsCSV($output, $project_type, $year);
        } elseif ($report_type === 'financial') {
            exportFinancialCSV($output, $project_type, $year);
        } elseif ($report_type === 'machinery') {
            exportMachineryCSV($output, $project_type, $year);
        }
        
        fclose($output);
    } elseif ($format === 'pdf') {
        // PDF export would require TCPDF or similar library
        // For now, show a summary that can be printed
        header("Content-Type: application/pdf");
        header("Content-Disposition: attachment; filename=\"$filename\"");
        
        generatePDFReport($report_type, $project_type, $year);
    }
}

function exportProjectsCSV($output, $project_type, $year) {
    global $conn;
    
    // CSV Header
    fputcsv($output, [
        'Type', 'Code', 'Title', 'Province', 'Municipality', 
        'Proposed Amount', 'Allocated Amount', 'Current Stage', 
        'Physical Progress', 'Financial Progress', 'Created Date', 'Updated Date'
    ]);

    // Query data
    $tables = [];
    if ($project_type === null || $project_type === 'fspf') $tables[] = 'fspf_projects';
    if ($project_type === null || $project_type === 'idp') $tables[] = 'idp_projects';
    if ($project_type === null || $project_type === 'afme') $tables[] = 'afme_projects';

    foreach ($tables as $table) {
        $type = strtoupper(str_replace('_projects', '', $table));
        
        $query = "SELECT 
                    '$type' as type,
                    project_code, project_title, province, municipality,
                    proposed_amount, allocated_amount, current_stage,
                    physical_progress, financial_progress,
                    created_date, updated_at
                  FROM $table
                  WHERE YEAR(created_date) = ?
                  ORDER BY created_date DESC";

        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $year);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['type'],
                $row['project_code'],
                $row['project_title'],
                $row['province'] ?? '',
                $row['municipality'] ?? '',
                $row['proposed_amount'] ?? 0,
                $row['allocated_amount'] ?? 0,
                $row['current_stage'] ?? '',
                $row['physical_progress'] ?? 0,
                $row['financial_progress'] ?? 0,
                $row['created_date'],
                $row['updated_at']
            ]);
        }
    }
}

function exportFinancialCSV($output, $project_type, $year) {
    global $conn;
    
    fputcsv($output, [
        'Project Type', 'Project ID', 'Record Type', 'Amount', 
        'Reference Number', 'Particulars', 'Record Date', 'Status'
    ]);

    $query = "SELECT 
                CASE 
                    WHEN fspf_project_id IS NOT NULL THEN 'FSPF'
                    WHEN idp_project_id IS NOT NULL THEN 'IDP'
                    WHEN afme_project_id IS NOT NULL THEN 'AFME'
                END as project_type,
                COALESCE(fspf_project_id, idp_project_id, afme_project_id) as project_id,
                record_type, amount, reference_number, particulars, 
                record_date, record_status
              FROM project_financial_tracker
              WHERE YEAR(record_date) = ?
              ORDER BY record_date DESC";

    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $year);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['project_type'],
            $row['project_id'],
            $row['record_type'],
            $row['amount'],
            $row['reference_number'] ?? '',
            $row['particulars'] ?? '',
            $row['record_date'],
            $row['record_status']
        ]);
    }
}

function exportMachineryCSV($output, $project_type, $year) {
    global $conn;
    
    fputcsv($output, [
        'AFME Project ID', 'Machinery Type', 'Unit Quantity', 'Unit Cost',
        'Total Cost', 'Status', 'Created Date', 'Validation Status'
    ]);

    $query = "SELECT 
                am.afme_project_id, am.machinery_type, am.unit_quantity,
                am.unit_cost, (am.unit_quantity * am.unit_cost) as total_cost,
                am.machinery_status, am.created_date,
                COALESCE(amv.validation_status, 'Pending') as validation_status
              FROM afme_machinery am
              LEFT JOIN afme_machinery_validation amv ON am.id = amv.machinery_id
              WHERE YEAR(am.created_date) = ?
              ORDER BY am.created_date DESC";

    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $year);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['afme_project_id'],
            $row['machinery_type'],
            $row['unit_quantity'],
            $row['unit_cost'],
            $row['total_cost'],
            $row['machinery_status'],
            $row['created_date'],
            $row['validation_status']
        ]);
    }
}

function generatePDFReport($report_type, $project_type, $year) {
    // For now, return HTML that can be printed to PDF
    // In production, use TCPDF or similar
    global $conn;

    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Report</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            h1 { color: #333; border-bottom: 2px solid #0d6efd; padding-bottom: 10px; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #0d6efd; color: white; }
            tr:nth-child(even) { background-color: #f9f9f9; }
            .summary { margin: 20px 0; padding: 20px; background-color: #f0f7ff; border-radius: 4px; }
            .page-break { page-break-after: always; }
        </style>
    </head>
    <body>
    <?php

    echo "<h1>" . ucfirst(str_replace('_', ' ', $report_type)) . " Report - " . $year . "</h1>";

    if ($report_type === 'projects') {
        generateProjectsReport($project_type, $year);
    } elseif ($report_type === 'financial') {
        generateFinancialReport($project_type, $year);
    } elseif ($report_type === 'machinery') {
        generateMachineryReport($year);
    }

    echo "</body></html>";
    
    $html = ob_get_clean();
    echo $html;
}

function generateProjectsReport($project_type, $year) {
    global $conn;
    
    $tables = [];
    if ($project_type === null || $project_type === 'fspf') $tables[] = ['fspf_projects', 'FSPF'];
    if ($project_type === null || $project_type === 'idp') $tables[] = ['idp_projects', 'IDP'];
    if ($project_type === null || $project_type === 'afme') $tables[] = ['afme_projects', 'AFME'];

    foreach ($tables as [$table, $type]) {
        echo "<div class='page-break'>";
        echo "<h2>$type Projects</h2>";

        $query = "SELECT * FROM $table WHERE YEAR(created_date) = ? ORDER BY created_date DESC";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $year);
        $stmt->execute();
        $result = $stmt->get_result();

        // Summary stats
        $count = $result->num_rows;
        $result->data_seek(0);
        
        $total_proposed = 0;
        $total_allocated = 0;
        while ($row = $result->fetch_assoc()) {
            $total_proposed += $row['proposed_amount'] ?? 0;
            $total_allocated += $row['allocated_amount'] ?? 0;
        }

        echo "<div class='summary'>";
        echo "<strong>Total Projects:</strong> " . $count . "<br>";
        echo "<strong>Total Proposed:</strong> ₱" . number_format($total_proposed, 2) . "<br>";
        echo "<strong>Total Allocated:</strong> ₱" . number_format($total_allocated, 2) . "<br>";
        echo "</div>";

        // Table
        echo "<table>";
        echo "<tr><th>Code</th><th>Title</th><th>Location</th><th>Proposed</th><th>Allocated</th><th>Stage</th></tr>";
        
        $result->data_seek(0);
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['project_code']) . "</td>";
            echo "<td>" . htmlspecialchars($row['project_title']) . "</td>";
            echo "<td>" . htmlspecialchars($row['municipality'] ?? '') . "</td>";
            echo "<td>₱" . number_format($row['proposed_amount'] ?? 0, 2) . "</td>";
            echo "<td>₱" . number_format($row['allocated_amount'] ?? 0, 2) . "</td>";
            echo "<td>" . htmlspecialchars($row['current_stage'] ?? '') . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
        echo "</div>";
    }
}

function generateFinancialReport($project_type, $year) {
    global $conn;

    $query = "SELECT 
                CASE 
                    WHEN fspf_project_id IS NOT NULL THEN 'FSPF'
                    WHEN idp_project_id IS NOT NULL THEN 'IDP'
                    WHEN afme_project_id IS NOT NULL THEN 'AFME'
                END as project_type,
                record_type,
                SUM(amount) as total_amount,
                COUNT(*) as record_count
              FROM project_financial_tracker
              WHERE YEAR(record_date) = ?
              GROUP BY project_type, record_type
              ORDER BY project_type, record_type";

    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $year);
    $stmt->execute();
    $result = $stmt->get_result();

    echo "<div class='summary'>";
    while ($row = $result->fetch_assoc()) {
        echo "<strong>" . $row['project_type'] . " - " . $row['record_type'] . ":</strong> " . 
             "₱" . number_format($row['total_amount'], 2) . 
             " (" . $row['record_count'] . " records)<br>";
    }
    echo "</div>";

    // Detailed table
    $detail_query = "SELECT 
                    CASE 
                        WHEN fspf_project_id IS NOT NULL THEN 'FSPF'
                        WHEN idp_project_id IS NOT NULL THEN 'IDP'
                        WHEN afme_project_id IS NOT NULL THEN 'AFME'
                    END as project_type,
                    record_type, amount, reference_number, 
                    particulars, record_date
                  FROM project_financial_tracker
                  WHERE YEAR(record_date) = ?
                  ORDER BY record_date DESC, project_type";

    $stmt = $conn->prepare($detail_query);
    $stmt->bind_param('i', $year);
    $stmt->execute();
    $detail_result = $stmt->get_result();

    echo "<table>";
    echo "<tr><th>Type</th><th>Record Type</th><th>Amount</th><th>Reference</th><th>Particulars</th><th>Date</th></tr>";

    while ($row = $detail_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['project_type'] . "</td>";
        echo "<td>" . $row['record_type'] . "</td>";
        echo "<td>₱" . number_format($row['amount'], 2) . "</td>";
        echo "<td>" . htmlspecialchars($row['reference_number'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['particulars'] ?? '') . "</td>";
        echo "<td>" . date('M d, Y', strtotime($row['record_date'])) . "</td>";
        echo "</tr>";
    }

    echo "</table>";
}

function generateMachineryReport($year) {
    global $conn;

    $query = "SELECT 
                ap.project_code, ap.project_title,
                SUM(am.unit_quantity) as total_units,
                SUM(am.unit_quantity * am.unit_cost) as total_cost
              FROM afme_projects ap
              LEFT JOIN afme_machinery am ON ap.id = am.afme_project_id
              WHERE YEAR(ap.created_date) = ?
              GROUP BY ap.id
              ORDER BY ap.created_date DESC";

    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $year);
    $stmt->execute();
    $result = $stmt->get_result();

    echo "<table>";
    echo "<tr><th>Project Code</th><th>Project Title</th><th>Total Units</th><th>Total Cost</th></tr>";

    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['project_code']) . "</td>";
        echo "<td>" . htmlspecialchars($row['project_title']) . "</td>";
        echo "<td>" . ($row['total_units'] ?? 0) . "</td>";
        echo "<td>₱" . number_format($row['total_cost'] ?? 0, 2) . "</td>";
        echo "</tr>";
    }

    echo "</table>";
}

// Get years for filter
$years_result = $conn->query("
    SELECT DISTINCT YEAR(created_date) as year FROM fspf_projects
    UNION
    SELECT DISTINCT YEAR(created_date) as year FROM idp_projects
    UNION
    SELECT DISTINCT YEAR(created_date) as year FROM afme_projects
    ORDER BY year DESC
");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - ABED IDM Hub</title>
    <link rel="stylesheet" href="assets/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'components/sidebar.php'; ?>
    <?php include 'components/topbar.php'; ?>
    <?php include 'components/navbar.php'; ?>

    <main class="app-main">
        <div class="container-fluid py-4">
            <!-- Page Header -->
            <div class="row align-items-center mb-4">
                <div class="col">
                    <h1 class="h2">
                        <i class="fas fa-file-alt"></i> Reports & Exports
                    </h1>
                    <p class="text-muted">Generate and export project data in various formats</p>
                </div>
            </div>

            <!-- Report Selection -->
            <div class="row g-3">
                <div class="col-lg-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-folder-open text-primary" style="font-size: 2rem;"></i>
                            <h6 class="card-title mt-3">Projects Report</h6>
                            <p class="card-text small text-muted">
                                Summary and details of all projects by type and year
                            </p>
                            <button class="btn btn-primary btn-sm w-100" data-bs-toggle="modal" data-bs-target="#projectReportModal">
                                <i class="fas fa-download"></i> Generate
                            </button>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-money-bill-wave text-success" style="font-size: 2rem;"></i>
                            <h6 class="card-title mt-3">Financial Report</h6>
                            <p class="card-text small text-muted">
                                Obligations, disbursements, and liquidations breakdown
                            </p>
                            <button class="btn btn-success btn-sm w-100" data-bs-toggle="modal" data-bs-target="#financialReportModal">
                                <i class="fas fa-download"></i> Generate
                            </button>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-cog text-warning" style="font-size: 2rem;"></i>
                            <h6 class="card-title mt-3">Machinery Report</h6>
                            <p class="card-text small text-muted">
                                AFME equipment and specifications by project
                            </p>
                            <button class="btn btn-warning btn-sm w-100" data-bs-toggle="modal" data-bs-target="#machineryReportModal">
                                <i class="fas fa-download"></i> Generate
                            </button>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-chart-line text-info" style="font-size: 2rem;"></i>
                            <h6 class="card-title mt-3">Summary Dashboard</h6>
                            <p class="card-text small text-muted">
                                Quick overview of system statistics
                            </p>
                            <a href="dashboard-v2.php" class="btn btn-info btn-sm w-100">
                                <i class="fas fa-eye"></i> View
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Reports -->
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header">
                    <h6 class="mb-0">Report Templates</h6>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">Select a pre-configured report template:</p>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <a href="?export=csv&report_type=projects&year=<?php echo date('Y'); ?>" 
                               class="btn btn-outline-primary btn-sm w-100">
                                <i class="fas fa-download"></i> Projects (CSV - Current Year)
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="?export=pdf&report_type=projects&year=<?php echo date('Y'); ?>" 
                               class="btn btn-outline-danger btn-sm w-100">
                                <i class="fas fa-download"></i> Projects (PDF - Current Year)
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="?export=csv&report_type=financial&year=<?php echo date('Y'); ?>" 
                               class="btn btn-outline-success btn-sm w-100">
                                <i class="fas fa-download"></i> Financial (CSV - Current Year)
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="?export=csv&report_type=machinery&year=<?php echo date('Y'); ?>" 
                               class="btn btn-outline-warning btn-sm w-100">
                                <i class="fas fa-download"></i> Machinery (CSV - Current Year)
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Modals -->
    <!-- Projects Report Modal -->
    <div class="modal fade" id="projectReportModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Projects Report</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="projectForm">
                        <div class="mb-3">
                            <label class="form-label">Project Type</label>
                            <select class="form-select" id="projectType" name="project_type">
                                <option value="">All Types</option>
                                <option value="fspf">FSPF</option>
                                <option value="idp">IDP</option>
                                <option value="afme">AFME</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Year</label>
                            <select class="form-select" name="year">
                                <?php while($row = $years_result->fetch_assoc()): ?>
                                <option value="<?php echo $row['year']; ?>"><?php echo $row['year']; ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <a href="#" id="projectCsvBtn" class="btn btn-secondary">
                        <i class="fas fa-file-csv"></i> Download CSV
                    </a>
                    <a href="#" id="projectPdfBtn" class="btn btn-danger">
                        <i class="fas fa-file-pdf"></i> Download PDF
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Financial Report Modal -->
    <div class="modal fade" id="financialReportModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Financial Report</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="financialForm">
                        <div class="mb-3">
                            <label class="form-label">Project Type</label>
                            <select class="form-select" id="projectType2" name="project_type">
                                <option value="">All Types</option>
                                <option value="fspf">FSPF</option>
                                <option value="idp">IDP</option>
                                <option value="afme">AFME</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Year</label>
                            <select class="form-select" id="financialYear" name="year">
                                <?php 
                                // Re-query years
                                $years_result = $conn->query("
                                    SELECT DISTINCT YEAR(created_date) as year FROM fspf_projects
                                    UNION
                                    SELECT DISTINCT YEAR(created_date) as year FROM idp_projects
                                    UNION
                                    SELECT DISTINCT YEAR(created_date) as year FROM afme_projects
                                    ORDER BY year DESC
                                ");
                                while($row = $years_result->fetch_assoc()): 
                                ?>
                                <option value="<?php echo $row['year']; ?>"><?php echo $row['year']; ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <a href="#" id="financialCsvBtn" class="btn btn-secondary">
                        <i class="fas fa-file-csv"></i> Download CSV
                    </a>
                    <a href="#" id="financialPdfBtn" class="btn btn-danger">
                        <i class="fas fa-file-pdf"></i> Download PDF
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Machinery Report Modal -->
    <div class="modal fade" id="machineryReportModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Machinery Report</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="machineryForm">
                        <div class="mb-3">
                            <label class="form-label">Year</label>
                            <select class="form-select" id="machineryYear" name="year">
                                <?php 
                                $years_result = $conn->query("SELECT DISTINCT YEAR(created_date) as year FROM afme_projects ORDER BY year DESC");
                                while($row = $years_result->fetch_assoc()): 
                                ?>
                                <option value="<?php echo $row['year']; ?>"><?php echo $row['year']; ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <a href="#" id="machineryCsvBtn" class="btn btn-secondary">
                        <i class="fas fa-file-csv"></i> Download CSV
                    </a>
                    <a href="#" id="machineryPdfBtn" class="btn btn-danger">
                        <i class="fas fa-file-pdf"></i> Download PDF
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        // Projects
        document.getElementById('projectCsvBtn').addEventListener('click', function(e) {
            e.preventDefault();
            const type = document.getElementById('projectType').value;
            const year = document.querySelector('#projectForm select[name="year"]').value;
            window.location = `?export=csv&report_type=projects&project_type=${type}&year=${year}`;
        });

        document.getElementById('projectPdfBtn').addEventListener('click', function(e) {
            e.preventDefault();
            const type = document.getElementById('projectType').value;
            const year = document.querySelector('#projectForm select[name="year"]').value;
            window.location = `?export=pdf&report_type=projects&project_type=${type}&year=${year}`;
        });

        // Financial
        document.getElementById('financialCsvBtn').addEventListener('click', function(e) {
            e.preventDefault();
            const type = document.getElementById('projectType2').value;
            const year = document.getElementById('financialYear').value;
            window.location = `?export=csv&report_type=financial&project_type=${type}&year=${year}`;
        });

        document.getElementById('financialPdfBtn').addEventListener('click', function(e) {
            e.preventDefault();
            const type = document.getElementById('projectType2').value;
            const year = document.getElementById('financialYear').value;
            window.location = `?export=pdf&report_type=financial&project_type=${type}&year=${year}`;
        });

        // Machinery
        document.getElementById('machineryCsvBtn').addEventListener('click', function(e) {
            e.preventDefault();
            const year = document.getElementById('machineryYear').value;
            window.location = `?export=csv&report_type=machinery&year=${year}`;
        });

        document.getElementById('machineryPdfBtn').addEventListener('click', function(e) {
            e.preventDefault();
            const year = document.getElementById('machineryYear').value;
            window.location = `?export=pdf&report_type=machinery&year=${year}`;
        });
    </script>
</body>
</html>
