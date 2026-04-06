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

$page_title = 'Projects';

$type = isset($_GET['type']) ? $_GET['type'] : 'FSPF';
$stage = isset($_GET['stage']) ? $_GET['stage'] : 'Implementation';

// If old table exists, fallback with simple query
$tableCheck = $conn->query("SHOW TABLES LIKE 'projects'");
if ($tableCheck && $tableCheck->num_rows > 0) {
    $sql = "SELECT * FROM projects WHERE category = ? AND stage = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $type, $stage);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    // New schema uses fspf_projects, idp_projects, afme_machinery
    // Build project list from modern table schema
    $allRows = [];

    if ($type === 'all' || $type === 'FSPF') {
        $stmt = $conn->prepare("SELECT id, project_code AS project_name, CONCAT(municipality, ', ', province) AS location, allocated_amount AS budget, current_stage AS stage FROM fspf_projects WHERE current_stage = ?");
        $stmt->bind_param("s", $stage);
        $stmt->execute();
        $allRows = array_merge($allRows, $stmt->get_result()->fetch_all(MYSQLI_ASSOC));
    }

    if ($type === 'all' || $type === 'IDP') {
        $stmt = $conn->prepare("SELECT id, project_code AS project_name, CONCAT(municipality, ', ', province) AS location, allocated_amount AS budget, current_stage AS stage FROM idp_projects WHERE current_stage = ?");
        $stmt->bind_param("s", $stage);
        $stmt->execute();
        $allRows = array_merge($allRows, $stmt->get_result()->fetch_all(MYSQLI_ASSOC));
    }

    if ($type === 'all' || $type === 'AFME') {
        $stmt = $conn->prepare("SELECT id, machine_name AS project_name, COALESCE(farm_location, '') AS location, allocated_amount AS budget, current_status AS stage FROM afme_machinery WHERE current_status = ?");
        $stmt->bind_param("s", $stage);
        $stmt->execute();
        $allRows = array_merge($allRows, $stmt->get_result()->fetch_all(MYSQLI_ASSOC));
    }

    $result = $allRows;
}

$rows = [];
if ($result instanceof mysqli_result) {
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
} elseif (is_array($result)) {
    $rows = $result;
}

?>
<?php
require_once __DIR__ . '/components/layout.php';
renderAppLayout($page_title);
?>

        <div class="container-fluid">
            <div class="page-header mb-4">
                <h3><i class="fas fa-folder-open me-2"></i><?php echo htmlspecialchars($page_title ?? 'Projects'); ?></h3>
                <p class="text-muted">View and manage all projects in the system for selected type/stage.</p>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Selection</h5>
                </div>
                <div class="card-body">
                    <form class="row g-3" method="GET">
                        <div class="col-md-3">
                            <label class="form-label" for="type">Type</label>
                            <select id="type" name="type" class="form-select">
                                <option value="all"<?php echo $type === 'all' ? ' selected' : ''; ?>>All</option>
                                <option value="FSPF"<?php echo $type === 'FSPF' ? ' selected' : ''; ?>>FSPF</option>
                                <option value="IDP"<?php echo $type === 'IDP' ? ' selected' : ''; ?>>IDP</option>
                                <option value="AFME"<?php echo $type === 'AFME' ? ' selected' : ''; ?>>AFME</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="stage">Stage</label>
                            <select id="stage" name="stage" class="form-select">
                                <?php $stages = ['Proposal','Pre-Implementation','Procurement','Implementation','Completed','Turned-Over']; ?>
                                <?php foreach ($stages as $s): ?>
                                    <option value="<?php echo $s; ?>"<?php echo $stage === $s ? ' selected' : ''; ?>><?php echo $s; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 align-self-end">
                            <button type="submit" class="btn btn-success w-100"><i class="fas fa-search me-2"></i>Filter</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Project List (<?php echo strtoupper($type); ?> - <?php echo htmlspecialchars($stage); ?>)</h5>
                </div>
                <div class="card-body table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Project Name</th>
                            <th>Location</th>
                            <th>Budget</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted">No projects found for this selection.</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($rows as $row): ?>
                                <?php
                                    $projectName = $row['project_title'] ?? $row['project_name'] ?? $row['machine_name'] ?? 'N/A';
                                    $location = $row['location'] ?? ($row['farm_location'] ?? (($row['municipality'] ?? '') . ', ' . ($row['province'] ?? '')));
                                    $budget = $row['allocated_amount'] ?? $row['budget'] ?? 0;
                                    $status = $row['current_stage'] ?? $row['stage'] ?? $row['current_status'] ?? 'Unknown';
                                    $projectId = $row['id'] ?? 0;
                                    $routeType = ($type === 'all' && isset($row['type'])) ? $row['type'] : $type;
                                    $actionUrl = ($routeType === 'AFME') ? "afme-machinery-details.php?id={$projectId}" : "project-details.php?type=" . urlencode($routeType) . "&id={$projectId}";
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($projectName); ?></td>
                                    <td><?php echo htmlspecialchars($location); ?></td>
                                    <td>₱<?php echo number_format($budget, 2); ?></td>
                                    <td><?php echo htmlspecialchars($status); ?></td>
                                    <td>
                                        <a href="<?php echo $actionUrl; ?>" class="btn btn-sm btn-primary">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php renderAppLayoutFooter(); ?>

