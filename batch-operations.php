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

$project_type = $_GET['type'] ?? 'fspf';

// Get projects for batch operations
$tables = match($project_type) {
    'fspf' => 'fspf_projects',
    'idp' => 'idp_projects',
    'afme' => 'afme_projects',
    default => 'fspf_projects'
};

$query = "SELECT id, project_code, project_title, municipality,
                 proposed_amount, allocated_amount, current_stage,
                 physical_progress, financial_progress
          FROM $tables
          ORDER BY updated_at DESC
          LIMIT 500";

$projects = $conn->query($query)->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Batch Operations - ABED IDM Hub</title>
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
            <div class="mb-4">
                <h1 class="h2">
                    <i class="fas fa-tasks"></i> Batch Operations
                </h1>
                <p class="text-muted">Perform bulk updates on projects</p>
            </div>

            <div class="row g-3">
                <!-- Project Selection -->
                <div class="col-12">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-light">
                            <h6 class="mb-0">
                                <input type="checkbox" id="selectAll" class="form-check-input"> 
                                Select Projects
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover" id="projectsTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 40px;">
                                                <input type="checkbox" class="form-check-input" id="selectAllCheckbox">
                                            </th>
                                            <th>Code</th>
                                            <th>Title</th>
                                            <th>Location</th>
                                            <th>Stage</th>
                                            <th>Physical %</th>
                                            <th>Financial %</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($projects as $proj): ?>
                                            <tr>
                                                <td>
                                                    <input type="checkbox" class="form-check-input project-checkbox" 
                                                           value="<?php echo $proj['id']; ?>">
                                                </td>
                                                <td><code><?php echo htmlspecialchars($proj['project_code']); ?></code></td>
                                                <td><?php echo htmlspecialchars(substr($proj['project_title'], 0, 40)); ?></td>
                                                <td><?php echo htmlspecialchars($proj['municipality']); ?></td>
                                                <td><?php echo htmlspecialchars($proj['current_stage']); ?></td>
                                                <td>
                                                    <div class="progress" style="height: 20px;">
                                                        <div class="progress-bar" 
                                                             style="width: <?php echo $proj['physical_progress']; ?>%">
                                                            <?php echo round($proj['physical_progress'], 1); ?>%
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="progress" style="height: 20px;">
                                                        <div class="progress-bar bg-success" 
                                                             style="width: <?php echo $proj['financial_progress']; ?>%">
                                                            <?php echo round($proj['financial_progress'], 1); ?>%
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="alert alert-info mt-3">
                                Selected: <strong id="selectedCount">0</strong> project(s)
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Batch Operations -->
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="fas fa-edit"></i> Update Stage</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">New Stage</label>
                                <select id="newStage" class="form-select">
                                    <option value="">-- Select --</option>
                                    <option value="Proposal">Proposal</option>
                                    <option value="Pre-Implementation">Pre-Implementation</option>
                                    <option value="Procurement">Procurement</option>
                                    <option value="Implementation">Implementation</option>
                                    <option value="Completed">Completed</option>
                                    <option value="Turned-Over">Turned-Over</option>
                                </select>
                            </div>
                            <button class="btn btn-primary w-100" id="updateStageBtn" disabled>
                                <i class="fas fa-arrow-right"></i> Update Stage
                            </button>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="fas fa-percentage"></i> Update Progress</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Physical Progress (%)</label>
                                <input type="number" id="physicalProgress" class="form-control" 
                                       min="0" max="100" step="0.1" placeholder="0-100">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Financial Progress (%)</label>
                                <input type="number" id="financialProgress" class="form-control" 
                                       min="0" max="100" step="0.1" placeholder="0-100">
                            </div>
                            <button class="btn btn-success w-100" id="updateProgressBtn" disabled>
                                <i class="fas fa-check"></i> Update Progress
                            </button>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="fas fa-money-bill"></i> Update Amount</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Allocated Amount (₱)</label>
                                <input type="number" id="allocatedAmount" class="form-control" 
                                       min="0" placeholder="Amount">
                            </div>
                            <button class="btn btn-warning w-100" id="updateAmountBtn" disabled>
                                <i class="fas fa-check"></i> Update Amount
                            </button>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="fas fa-download"></i> Export Data</h6>
                        </div>
                        <div class="card-body">
                            <button class="btn btn-info w-100 mb-2" id="exportJsonBtn" disabled>
                                <i class="fas fa-file"></i> Export as JSON
                            </button>
                            <button class="btn btn-secondary w-100" id="exportCsvBtn" disabled>
                                <i class="fas fa-table"></i> Export as CSV
                            </button>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <button class="btn btn-danger" id="deleteProjectsBtn" disabled>
                                <i class="fas fa-trash"></i> Archive Selected Projects
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Operation Status (Hidden, shown after operation) -->
            <div id="statusAlert" class="alert mt-4" role="alert" style="display: none;">
                <strong id="statusTitle"></strong>
                <p id="statusMessage"></p>
                <div id="statusErrors"></div>
            </div>
        </div>
    </main>

    <script src="assets/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        const projectType = '<?php echo htmlspecialchars($project_type); ?>';

        // Select All functionality
        document.getElementById('selectAllCheckbox').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.project-checkbox');
            checkboxes.forEach(cb => cb.checked = this.checked);
            updateSelectedCount();
        });

        // Individual checkbox change
        document.querySelectorAll('.project-checkbox').forEach(cb => {
            cb.addEventListener('change', updateSelectedCount);
        });

        function updateSelectedCount() {
            const count = document.querySelectorAll('.project-checkbox:checked').length;
            document.getElementById('selectedCount').textContent = count;

            // Enable/disable operation buttons
            const enabled = count > 0;
            document.getElementById('updateStageBtn').disabled = !enabled;
            document.getElementById('updateProgressBtn').disabled = !enabled;
            document.getElementById('updateAmountBtn').disabled = !enabled;
            document.getElementById('deleteProjectsBtn').disabled = !enabled;
            document.getElementById('exportJsonBtn').disabled = !enabled;
            document.getElementById('exportCsvBtn').disabled = !enabled;
        }

        function getSelectedProjects() {
            return Array.from(document.querySelectorAll('.project-checkbox:checked'))
                .map(cb => parseInt(cb.value));
        }

        // Update Stage
        document.getElementById('updateStageBtn').addEventListener('click', async function() {
            const stage = document.getElementById('newStage').value;
            if (!stage) {
                alert('Please select a stage');
                return;
            }

            const projects = getSelectedProjects();
            if (projects.length === 0) {
                alert('Please select projects');
                return;
            }

            const response = await fetch('/api/batch.php?action=update-stage', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    type: projectType,
                    projects: projects,
                    stage: stage
                })
            });

            const result = await response.json();
            showStatus('Stage Updated', result, response.ok);
        });

        // Update Progress
        document.getElementById('updateProgressBtn').addEventListener('click', async function() {
            const physical = document.getElementById('physicalProgress').value;
            const financial = document.getElementById('financialProgress').value;

            if (!physical && !financial) {
                alert('Please enter at least one progress value');
                return;
            }

            const projects = getSelectedProjects();
            if (projects.length === 0) {
                alert('Please select projects');
                return;
            }

            const response = await fetch('/api/batch.php?action=update-progress', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    type: projectType,
                    projects: projects,
                    physical_progress: physical ? parseFloat(physical) : null,
                    financial_progress: financial ? parseFloat(financial) : null
                })
            });

            const result = await response.json();
            showStatus('Progress Updated', result, response.ok);
        });

        // Update Amount
        document.getElementById('updateAmountBtn').addEventListener('click', async function() {
            const amount = document.getElementById('allocatedAmount').value;
            if (!amount) {
                alert('Please enter an amount');
                return;
            }

            const projects = getSelectedProjects();
            const response = await fetch('/api/batch.php?action=update-field', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    type: projectType,
                    projects: projects,
                    field: 'allocated_amount',
                    value: amount
                })
            });

            const result = await response.json();
            showStatus('Amount Updated', result, response.ok);
        });

        // Delete/Archive Projects
        document.getElementById('deleteProjectsBtn').addEventListener('click', async function() {
            if (!confirm('Are you sure you want to archive these projects? This action can be reversed.')) {
                return;
            }

            const projects = getSelectedProjects();
            const response = await fetch('/api/batch.php?action=delete-projects', {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    type: projectType,
                    projects: projects
                })
            });

            const result = await response.json();
            showStatus('Projects Archived', result, response.ok);
        });

        // Export Functions
        document.getElementById('exportJsonBtn').addEventListener('click', async function() {
            const projects = getSelectedProjects();
            const response = await fetch('/api/batch.php?action=export', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    type: projectType,
                    projects: projects,
                    format: 'json'
                })
            });

            const result = await response.json();
            if (result.success) {
                downloadJSON(result.data, `projects-${Date.now()}.json`);
            }
        });

        document.getElementById('exportCsvBtn').addEventListener('click', async function() {
            const projects = getSelectedProjects();
            const response = await fetch('/api/batch.php?action=export', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    type: projectType,
                    projects: projects,
                    format: 'csv'
                })
            });

            const result = await response.json();
            if (result.success) {
                downloadCSV(result.data, `projects-${Date.now()}.csv`);
            }
        });

        function showStatus(title, result, success) {
            const alert = document.getElementById('statusAlert');
            alert.className = `alert alert-${success ? 'success' : 'danger'} mt-4`;

            document.getElementById('statusTitle').textContent = success ? '✓ ' + title : '✗ Error';
            document.getElementById('statusMessage').textContent = result.message || (success ? 'Operation completed' : result.error);

            if (result.errors && result.errors.length > 0) {
                const errorList = result.errors.map(e => `<li>${e}</li>`).join('');
                document.getElementById('statusErrors').innerHTML = `<ul>${errorList}</ul>`;
            } else {
                document.getElementById('statusErrors').innerHTML = '';
            }

            alert.style.display = 'block';
            alert.scrollIntoView({ behavior: 'smooth' });
        }

        function downloadJSON(data, filename) {
            const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
            downloadBlob(blob, filename);
        }

        function downloadCSV(data, filename) {
            if (!data || data.length === 0) return;

            let csv = '';
            // Headers
            if (Array.isArray(data[0])) {
                csv = data[0].map(h => `"${h}"`).join(',') + '\n';
                // Rows
                for (let i = 1; i < data.length; i++) {
                    csv += data[i].map(v => `"${v}"`).join(',') + '\n';
                }
            }

            const blob = new Blob([csv], { type: 'text/csv' });
            downloadBlob(blob, filename);
        }

        function downloadBlob(blob, filename) {
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }
    </script>
</body>
</html>
