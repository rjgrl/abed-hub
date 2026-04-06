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

// Get filter parameters
$project_type = $_GET['type'] ?? 'fspf';
$stage_filter = $_GET['stage'] ?? '';
$search = $_GET['search'] ?? '';
$year = $_GET['year'] ?? date('Y');
$page = (int)($_GET['page'] ?? 1);
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Map type to table
$type_map = [
    'fspf' => 'fspf_projects',
    'idp' => 'idp_projects',
    'afme' => 'afme_projects'
];

$table = $type_map[$project_type] ?? 'fspf_projects';

// Build query
$query = "SELECT * FROM $table WHERE 1=1";
$count_query = "SELECT COUNT(*) as total FROM $table WHERE 1=1";
$params = [];
$types = '';

if ($stage_filter) {
    $query .= " AND current_stage = ?";
    $count_query .= " AND current_stage = ?";
    $params[] = $stage_filter;
    $types .= 's';
}

if ($search) {
    $search_term = "%{$search}%";
    $query .= " AND (project_code LIKE ? OR project_title LIKE ?)";
    $count_query .= " AND (project_code LIKE ? OR project_title LIKE ?)";
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= 'ss';
}

if ($year) {
    $query .= " AND YEAR(created_date) = ?";
    $count_query .= " AND YEAR(created_date) = ?";
    $params[] = $year;
    $types .= 'i';
}

// Get total count
$count_stmt = $conn->prepare($count_query);
if ($params) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $per_page);

// Get projects
$query .= " ORDER BY created_date DESC LIMIT ? OFFSET ?";
$limit_params = $params;
$limit_params[] = $per_page;
$limit_params[] = $offset;
$limit_types = $types . 'ii';

$stmt = $conn->prepare($query);
if ($limit_params) {
    $stmt->bind_param($limit_types, ...$limit_params);
}
$stmt->execute();
$projects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get available stages
$stages_query = $conn->query("SELECT DISTINCT current_stage FROM $table ORDER BY current_stage");
$available_stages = $stages_query->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projects - ABED IDM Hub</title>
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
            <!-- Header -->
            <div class="row align-items-center mb-4">
                <div class="col">
                    <h1 class="h3 mb-0">Projects</h1>
                    <p class="text-muted">Browse and manage all projects</p>
                </div>
                <div class="col-auto">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#exportModal">
                        <i class="fas fa-download"></i> Export
                    </button>
                </div>
            </div>

            <!-- Filters -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <!-- Type Tabs -->
                        <div class="col-12">
                            <div class="btn-group" role="group">
                                <input type="radio" class="btn-check" name="type" id="type_fspf" value="fspf" <?php echo $project_type === 'fspf' ? 'checked' : ''; ?> onchange="this.form.submit()">
                                <label class="btn btn-outline-primary" for="type_fspf">FSPF</label>

                                <input type="radio" class="btn-check" name="type" id="type_idp" value="idp" <?php echo $project_type === 'idp' ? 'checked' : ''; ?> onchange="this.form.submit()">
                                <label class="btn btn-outline-primary" for="type_idp">IDP</label>

                                <input type="radio" class="btn-check" name="type" id="type_afme" value="afme" <?php echo $project_type === 'afme' ? 'checked' : ''; ?> onchange="this.form.submit()">
                                <label class="btn btn-outline-primary" for="type_afme">AFME</label>
                            </div>
                        </div>

                        <!-- Search -->
                        <div class="col-md-4">
                            <input type="text" name="search" class="form-control" placeholder="Search by code or title" 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>

                        <!-- Stage Filter -->
                        <div class="col-md-3">
                            <select name="stage" class="form-select">
                                <option value="">All Stages</option>
                                <?php foreach ($available_stages as $s): ?>
                                    <option value="<?php echo htmlspecialchars($s['current_stage']); ?>" 
                                            <?php echo $stage_filter === $s['current_stage'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($s['current_stage']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Year Filter -->
                        <div class="col-md-2">
                            <select name="year" class="form-select">
                                <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                                    <option value="<?php echo $y; ?>" <?php echo $year == $y ? 'selected' : ''; ?>>
                                        <?php echo $y; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <!-- Submit -->
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search"></i> Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Results Info -->
            <div class="alert alert-info mb-4">
                Showing <strong><?php echo count($projects); ?></strong> of <strong><?php echo $total_records; ?></strong> 
                <?php echo strtoupper($project_type); ?> projects
                <?php if ($search): ?> matching "<strong><?php echo htmlspecialchars($search); ?></strong>"<?php endif; ?>
            </div>

            <!-- Projects Table -->
            <div class="card border-0 shadow-sm">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Code</th>
                                <th>Title</th>
                                <th>Stage</th>
                                <th>Physical Progress</th>
                                <th>Financial Progress</th>
                                <th>Allocated Amount</th>
                                <th>Modified</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($projects)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">No projects found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($projects as $project): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($project['project_code']); ?></strong>
                                        </td>
                                        <td>
                                            <span title="<?php echo htmlspecialchars($project['project_title']); ?>">
                                                <?php echo htmlspecialchars(substr($project['project_title'], 0, 50)); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge" style="background-color: 
                                                <?php echo match($project['current_stage']) {
                                                    'Proposal' => '#6c757d',
                                                    'Pre-Implementation' => '#17a2b8',
                                                    'Procurement' => '#ffc107',
                                                    'Implementation' => '#0d6efd',
                                                    'Completed', 'Turned-Over' => '#28a745',
                                                    default => '#e3e3e3'
                                                }; ?>; color: <?php echo match($project['current_stage']) {
                                                    'Procurement' => 'black',
                                                    default => 'white'
                                                }; ?>">
                                                <?php echo htmlspecialchars($project['current_stage']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="progress" style="height: 20px; width: 100px;">
                                                <div class="progress-bar" style="width: <?php echo $project['physical_progress']; ?>%; background-color: #0d6efd;">
                                                    <small><?php echo round($project['physical_progress'], 0); ?>%</small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="progress" style="height: 20px; width: 100px;">
                                                <div class="progress-bar bg-success" style="width: <?php echo $project['financial_progress']; ?>%;">
                                                    <small><?php echo round($project['financial_progress'], 0); ?>%</small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            ₱<?php echo number_format($project['allocated_amount'], 0); ?>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?php echo date('M d, Y', strtotime($project['updated_at'])); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="project-detail-enhanced.php?type=<?php echo $project_type; ?>&id=<?php echo $project['id']; ?>" 
                                                   class="btn btn-info" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="scurve-monitoring.php?type=<?php echo $project_type; ?>&id=<?php echo $project['id']; ?>" 
                                                   class="btn btn-secondary" title="S-Curve">
                                                    <i class="fas fa-chart-line"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="card-footer bg-light">
                        <nav aria-label="Page navigation">
                            <ul class="pagination mb-0">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?type=<?php echo $project_type; ?>&page=1&stage=<?php echo htmlspecialchars($stage_filter); ?>&search=<?php echo htmlspecialchars($search); ?>">First</a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="?type=<?php echo $project_type; ?>&page=<?php echo $page - 1; ?>&stage=<?php echo htmlspecialchars($stage_filter); ?>&search=<?php echo htmlspecialchars($search); ?>">Previous</a>
                                    </li>
                                <?php endif; ?>

                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?type=<?php echo $project_type; ?>&page=<?php echo $i; ?>&stage=<?php echo htmlspecialchars($stage_filter); ?>&search=<?php echo htmlspecialchars($search); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?type=<?php echo $project_type; ?>&page=<?php echo $page + 1; ?>&stage=<?php echo htmlspecialchars($stage_filter); ?>&search=<?php echo htmlspecialchars($search); ?>">Next</a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="?type=<?php echo $project_type; ?>&page=<?php echo $total_pages; ?>&stage=<?php echo htmlspecialchars($stage_filter); ?>&search=<?php echo htmlspecialchars($search); ?>">Last</a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script src="assets/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
