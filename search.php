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

// Get search parameters
$query = $_GET['q'] ?? '';
$type_filter = json_decode($_GET['types'] ?? '[]', true);
$stage_filter = json_decode($_GET['stages'] ?? '[]', true);
$year_filter = json_decode($_GET['years'] ?? '[]', true);
$location_filter = json_decode($_GET['locations'] ?? '[]', true);
$budget_min = (float)($_GET['budget_min'] ?? 0);
$budget_max = (float)($_GET['budget_max'] ?? 10000000);
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build search query
$queries = [];
$count_query = '';
$search_term = '%' . $conn->real_escape_string($query) . '%';

if (empty($type_filter)) {
    $type_filter = ['fspf', 'idp', 'afme'];
}

// Build queries for each type
foreach ($type_filter as $type) {
    $q = "SELECT '$type' as type, id, project_code, project_title, municipality, 
                  proposed_amount, allocated_amount, current_stage, 
                  physical_progress, financial_progress, created_at
           FROM (
                SELECT project_type, id, project_code, title AS project_title, municipality,
                       proposed_amount, allocated_amount, current_stage, physical_progress, financial_progress, created_at
                FROM projects
                WHERE approval_status = 'Approved'
           ) p
           WHERE 1=1";
    $q .= " AND p.project_type = '" . $conn->real_escape_string($type) . "'";

    if ($query) {
        $q .= " AND (project_code LIKE '$search_term' OR project_title LIKE '$search_term' 
                     OR municipality LIKE '$search_term')";
    }

    if (!empty($stage_filter)) {
        $stages_str = "'" . implode("','", array_map(fn($s) => $conn->real_escape_string($s), $stage_filter)) . "'";
        $q .= " AND current_stage IN ($stages_str)";
    }

    if (!empty($year_filter)) {
        $years_str = "'" . implode("','", array_map(fn($y) => (int)$y, $year_filter)) . "'";
        $q .= " AND YEAR(created_at) IN ($years_str)";
    }

    if (!empty($location_filter)) {
        $locations_str = "'" . implode("','", array_map(fn($l) => $conn->real_escape_string($l), $location_filter)) . "'";
        $q .= " AND municipality IN ($locations_str)";
    }

    $q .= " AND proposed_amount BETWEEN $budget_min AND $budget_max";

    $queries[] = $q;
}

// Combine queries
if (!empty($queries)) {
    $combined_query = "(" . implode(") UNION (", $queries) . ")";
    
    // Get total count
    $count_result = $conn->query("SELECT COUNT(*) as count FROM ($combined_query) as temp");
    $count_row = $count_result->fetch_assoc();
    $total_count = $count_row['count'];
    $total_pages = ceil($total_count / $per_page);

    // Get paginated results
    $full_query = "SELECT * FROM ($combined_query) as temp 
                   ORDER BY created_at DESC LIMIT $offset, $per_page";
    $results = $conn->query($full_query)->fetch_all(MYSQLI_ASSOC);
} else {
    $results = [];
    $total_count = 0;
    $total_pages = 0;
}

// Get facet data for sidebar
$types_facet = [];
foreach (['fspf', 'idp', 'afme'] as $t) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM projects WHERE project_type = ? AND approval_status = 'Approved'");
    $stmt->bind_param('s', $t);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $types_facet[] = ['type' => strtoupper($t), 'code' => $t, 'count' => $result['count']];
}

// Get stages facet
$stages_facet_query = "SELECT DISTINCT current_stage as stage FROM projects WHERE approval_status = 'Approved' ORDER BY stage ASC";
$stages_facet = $conn->query($stages_facet_query)->fetch_all(MYSQLI_ASSOC);

// Get years facet
$years_facet_query = "SELECT DISTINCT YEAR(created_at) as year FROM projects WHERE approval_status = 'Approved' ORDER BY year DESC";
$years_facet = $conn->query($years_facet_query)->fetch_all(MYSQLI_ASSOC);

// Get locations facet
$locations_facet_query = "
    SELECT DISTINCT municipality
    FROM projects
    WHERE approval_status = 'Approved' AND municipality IS NOT NULL AND municipality <> ''
    ORDER BY municipality ASC
";
$locations_facet = $conn->query($locations_facet_query)->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Search - ABED IDM Hub</title>
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
                <h1 class="h2"><i class="fas fa-search"></i> Advanced Search</h1>
            </div>

            <div class="row g-3">
                <!-- Sidebar - Filters -->
                <div class="col-lg-3">
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-header bg-light">
                            <h6 class="mb-0">Search Query</h6>
                        </div>
                        <div class="card-body">
                            <form method="GET" id="searchForm">
                                <div class="mb-3">
                                    <input type="text" name="q" class="form-control form-control-sm" 
                                           placeholder="Search by code, title, or location..."
                                           value="<?php echo htmlspecialchars($query); ?>">
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm w-100">
                                    <i class="fas fa-search"></i> Search
                                </button>
                                <a href="search.php" class="btn btn-secondary btn-sm w-100 mt-2">
                                    <i class="fas fa-redo"></i> Reset
                                </a>
                            </form>
                        </div>
                    </div>

                    <!-- Project Type Filter -->
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-header bg-light">
                            <h6 class="mb-0">Project Type</h6>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <?php foreach ($types_facet as $type): ?>
                                    <label class="list-group-item p-3 mb-0">
                                        <input type="checkbox" name="type" value="<?php echo $type['code']; ?>" 
                                               class="form-check-input me-2"
                                               <?php echo in_array($type['code'], $type_filter) ? 'checked' : ''; ?>>
                                        <span><?php echo $type['type']; ?></span>
                                        <span class="badge bg-light text-dark float-end">
                                            <?php echo $type['count']; ?>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Stage Filter -->
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-header bg-light">
                            <h6 class="mb-0">Current Stage</h6>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <?php foreach ($stages_facet as $stage): ?>
                                    <label class="list-group-item p-3 mb-0">
                                        <input type="checkbox" name="stage" value="<?php echo htmlspecialchars($stage['stage']); ?>"
                                               class="form-check-input me-2"
                                               <?php echo in_array($stage['stage'], $stage_filter) ? 'checked' : ''; ?>>
                                        <?php echo htmlspecialchars($stage['stage']); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Year Filter -->
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-header bg-light">
                            <h6 class="mb-0">Year</h6>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <?php foreach ($years_facet as $year): ?>
                                    <label class="list-group-item p-3 mb-0">
                                        <input type="checkbox" name="year" value="<?php echo $year['year']; ?>"
                                               class="form-check-input me-2"
                                               <?php echo in_array($year['year'], $year_filter) ? 'checked' : ''; ?>>
                                        <?php echo $year['year']; ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Location Filter -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light">
                            <h6 class="mb-0">Location</h6>
                        </div>
                        <div class="card-body">
                            <div class="search-results-scroll">
                                <?php foreach ($locations_facet as $loc): ?>
                                    <label class="form-check mb-2">
                                        <input type="checkbox" name="location" 
                                               value="<?php echo htmlspecialchars($loc['municipality']); ?>"
                                               class="form-check-input"
                                               <?php echo in_array($loc['municipality'], $location_filter) ? 'checked' : ''; ?>>
                                        <span class="form-check-label">
                                            <?php echo htmlspecialchars($loc['municipality']); ?>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Budget Range Slider -->
                    <div class="card border-0 shadow-sm mt-3">
                        <div class="card-header bg-light">
                            <h6 class="mb-0">Budget Range</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Min: ₱<span id="budgetMinDisplay"><?php echo number_format($budget_min, 0); ?></span></label>
                                <input type="range" name="budget_min" class="form-range" 
                                       value="<?php echo $budget_min; ?>" min="0" max="10000000" step="100000"
                                       id="budgetMin">
                            </div>
                            <div>
                                <label class="form-label">Max: ₱<span id="budgetMaxDisplay"><?php echo number_format($budget_max, 0); ?></span></label>
                                <input type="range" name="budget_max" class="form-range" 
                                       value="<?php echo $budget_max; ?>" min="0" max="10000000" step="100000"
                                       id="budgetMax">
                            </div>
                        </div>
                    </div>

                    <!-- Apply Filters Button -->
                    <button type="button" class="btn btn-primary w-100 mt-3" id="applyFiltersBtn">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                </div>

                <!-- Main Results -->
                <div class="col-lg-9">
                    <!-- Active Filters Tags -->
                    <div class="mb-3">
                        <?php if ($query): ?>
                            <span class="badge bg-primary">Search: <?php echo htmlspecialchars($query); ?></span>
                        <?php endif; ?>

                        <?php foreach ($type_filter as $t): ?>
                            <span class="badge bg-info"><?php echo strtoupper($t); ?></span>
                        <?php endforeach; ?>

                        <?php foreach ($stage_filter as $s): ?>
                            <span class="badge bg-warning text-dark"><?php echo htmlspecialchars($s); ?></span>
                        <?php endforeach; ?>

                        <?php foreach ($year_filter as $y): ?>
                            <span class="badge bg-secondary"><?php echo $y; ?></span>
                        <?php endforeach; ?>
                    </div>

                    <!-- Results Summary -->
                    <div class="alert alert-info">
                        Found <strong><?php echo $total_count; ?></strong> project(s)
                        (Page <?php echo $page; ?> of <?php echo max(1, $total_pages); ?>)
                    </div>

                    <!-- Results Cards -->
                    <div class="row g-3 mb-4">
                        <?php
                        if (empty($results)) {
                            echo '<div class="col-12"><div class="alert alert-warning">No projects found matching your criteria.</div></div>';
                        } else {
                            foreach ($results as $result) {
                                $type_color = match($result['type']) {
                                    'fspf' => 'primary',
                                    'idp' => 'info',
                                    'afme' => 'warning',
                                };
                                $stage_color = match($result['current_stage']) {
                                    'Proposal' => 'secondary',
                                    'Pre-Implementation' => 'info',
                                    'Procurement' => 'warning',
                                    'Implementation' => 'primary',
                                    'Completed', 'Turned-Over' => 'success',
                                    default => 'light'
                                };
                                ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="card h-100 border-0 shadow-sm hover-lift">
                                        <div class="card-body">
                                            <div class="mb-2">
                                                <span class="badge bg-<?php echo $type_color; ?>">
                                                    <?php echo $result['type']; ?>
                                                </span>
                                                <span class="badge bg-<?php echo $stage_color; ?>">
                                                    <?php echo htmlspecialchars($result['current_stage']); ?>
                                                </span>
                                            </div>

                                            <h6 class="card-title">
                                                <code><?php echo htmlspecialchars($result['project_code']); ?></code>
                                            </h6>

                                            <p class="card-text small mb-2">
                                                <?php echo htmlspecialchars(substr($result['project_title'], 0, 80)); ?>
                                            </p>

                                            <div class="mb-2 small">
                                                <strong>Location:</strong> <?php echo htmlspecialchars($result['municipality']); ?><br>
                                                <strong>Proposed:</strong> ₱<?php echo number_format($result['proposed_amount'], 0); ?><br>
                                                <strong>Allocated:</strong> ₱<?php echo number_format($result['allocated_amount'], 0); ?>
                                            </div>

                                            <!-- Progress -->
                                            <div class="mb-3">
                                                <div class="progress progress-rail mb-1">
                                                    <div class="progress-bar bg-primary progress-bar-w"
                                                         style="--w: <?php echo min($result['physical_progress'], 100); ?>%">
                                                        <small><?php echo round($result['physical_progress'], 1); ?>%</small>
                                                    </div>
                                                </div>
                                                <small class="text-muted">Physical Progress</small>
                                            </div>

                                            <a href="project-detail-enhanced.php?type=<?php echo $result['type']; ?>&id=<?php echo $result['id']; ?>"
                                               class="btn btn-sm btn-outline-primary w-100">
                                                <i class="fas fa-arrow-right"></i> View Details
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <?php
                            }
                        }
                        ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <nav>
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?php echo $page === 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?q=<?php echo urlencode($query); ?>&page=1">First</a>
                                </li>

                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?q=<?php echo urlencode($query); ?>&page=<?php echo $i; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <li class="page-item <?php echo $page === $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?q=<?php echo urlencode($query); ?>&page=<?php echo $total_pages; ?>">Last</a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script src="assets/bootstrap/js/bootstrap.bundle.js"></script>
    <script>
        // Budget range sliders
        document.getElementById('budgetMin').addEventListener('change', function() {
            document.getElementById('budgetMinDisplay').textContent = 
                new Intl.NumberFormat('en-PH').format(this.value);
        });

        document.getElementById('budgetMax').addEventListener('change', function() {
            document.getElementById('budgetMaxDisplay').textContent = 
                new Intl.NumberFormat('en-PH').format(this.value);
        });

        // Apply filters
        document.getElementById('applyFiltersBtn').addEventListener('click', function() {
            const form = document.getElementById('searchForm');
            
            // Get all checked filters
            const types = Array.from(document.querySelectorAll('input[name="type"]:checked'))
                .map(x => x.value);
            const stages = Array.from(document.querySelectorAll('input[name="stage"]:checked'))
                .map(x => x.value);
            const years = Array.from(document.querySelectorAll('input[name="year"]:checked'))
                .map(x => x.value);
            const locations = Array.from(document.querySelectorAll('input[name="location"]:checked'))
                .map(x => x.value);
            const budgetMin = document.getElementById('budgetMin').value;
            const budgetMax = document.getElementById('budgetMax').value;

            // Build URL
            let url = '?q=' + encodeURIComponent(document.querySelector('input[name="q"]').value);
            if (types.length) url += '&types=' + encodeURIComponent(JSON.stringify(types));
            if (stages.length) url += '&stages=' + encodeURIComponent(JSON.stringify(stages));
            if (years.length) url += '&years=' + encodeURIComponent(JSON.stringify(years));
            if (locations.length) url += '&locations=' + encodeURIComponent(JSON.stringify(locations));
            url += '&budget_min=' + budgetMin + '&budget_max=' + budgetMax;

            window.location.href = url;
        });
    </script>
</body>
</html>
