<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/functions/helpers.php';
require_once __DIR__ . '/components/layout.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

requireLogin();

require_once __DIR__ . '/config/user_guide.php';
$guide_stats = user_guide_fetch_stats($conn);
$user_role = $_SESSION['role'] ?? 'viewer';

$page_title = 'Help & Guide';
renderAppLayout($page_title);
?>
<div class="container-fluid py-4">
            <!-- Header -->
            <div class="row mb-4">
                <div class="col">
                    <h1 class="h3 mb-0">User Guide</h1>
                    <p class="text-muted">Complete documentation for ABED IDM Hub</p>
                </div>
            </div>

            <!-- Navigation Tabs -->
            <ul class="nav nav-tabs mb-4" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="tab-getting-started" data-bs-toggle="tab" data-bs-target="#getting-started" type="button" role="tab" aria-controls="getting-started" aria-selected="true">
                        <i class="fas fa-play-circle"></i> Getting Started
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-projects" data-bs-toggle="tab" data-bs-target="#projects" type="button" role="tab" aria-controls="projects" aria-selected="false">
                        <i class="fas fa-project-diagram"></i> Projects
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-features" data-bs-toggle="tab" data-bs-target="#features" type="button" role="tab" aria-controls="features" aria-selected="false">
                        <i class="fas fa-star"></i> Features
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-faq" data-bs-toggle="tab" data-bs-target="#faq" type="button" role="tab" aria-controls="faq" aria-selected="false">
                        <i class="fas fa-question-circle"></i> FAQ
                    </button>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content">
                <!-- Getting Started -->
                <div id="getting-started" class="tab-pane fade show active" role="tabpanel" aria-labelledby="tab-getting-started" tabindex="0">
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="card border-0 shadow-sm mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Welcome to ABED IDM Hub</h5>
                                </div>
                                <div class="card-body">
                                    <p>ABED IDM Hub is a comprehensive project management system designed to help you effectively track and manage projects across multiple funding programs:</p>
                                    <ul>
                                        <li><strong>FSPF</strong> — Farm Structure and Processing Facilities</li>
                                        <li><strong>IDP</strong> — Irrigation Development Projects</li>
                                        <li><strong>AFME</strong> — Agricultural and Fisheries Machineries and Equipment</li>
                                    </ul>
                                </div>
                            </div>

                            <div class="card border-0 shadow-sm mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Key Sections Overview</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <h6><i class="fas fa-th-large text-primary"></i> Dashboard</h6>
                                            <p>Get an overview of all your projects, key metrics, recent activities, and pending approvals.</p>
                                        </div>
                                        <div class="col-md-6">
                                            <h6><i class="fas fa-list text-primary"></i> Projects</h6>
                                            <p>Browse, search, and filter all projects by type, stage, and status.</p>
                                        </div>
                                        <div class="col-md-6">
                                            <h6><i class="fas fa-map-marked-alt text-primary"></i> Geo Map</h6>
                                            <p>View geotagged projects on the interactive map and filter by type and stage.</p>
                                        </div>
                                        <div class="col-md-6">
                                            <h6><i class="fas fa-chart-bar text-primary"></i> Analytics & Reports</h6>
                                            <p>Generate comprehensive reports and analyze project performance across all programs.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card border-0 shadow-sm">
                                <div class="card-header">
                                    <h5 class="mb-0">First Steps</h5>
                                </div>
                                <div class="card-body">
                                    <ol>
                                        <li><strong>Log In</strong> - Use your credentials to access the system</li>
                                        <li><strong>View Dashboard</strong> - Get an overview of all projects and metrics</li>
                                        <li><strong>Browse Projects</strong> - Navigate to Projects page to see all available projects</li>
                                        <li><strong>View Project Details</strong> - Click on any project to see detailed information</li>
                                        <li><strong>Open Geo Map</strong> — Validate project locations and mapped assets</li>
                                        <li><strong>Review Analytics</strong> - Use Analytics & Reports for snapshots and trends</li>
                                    </ol>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <div class="card border-0 shadow-sm mb-3">
                                <div class="card-header bg-primary text-white">
                                    <h6 class="mb-0">Quick Links</h6>
                                </div>
                                <div class="list-group list-group-flush">
                                    <a href="dashboard.php" class="list-group-item list-group-item-action">
                                        <i class="fas fa-th-large"></i> Dashboard
                                    </a>
                                    <a href="projects-advanced.php" class="list-group-item list-group-item-action">
                                        <i class="fas fa-list"></i> Projects
                                    </a>
                                    <a href="analytics-reports.php" class="list-group-item list-group-item-action">
                                        <i class="fas fa-chart-bar"></i> Analytics & Reports
                                    </a>
                                    <a href="geomap.php" class="list-group-item list-group-item-action">
                                        <i class="fas fa-map-marked-alt"></i> Geo Map
                                    </a>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- Projects Section (stages & types from config; counts from DB) -->
                <div id="projects" class="tab-pane fade" role="tabpanel" aria-labelledby="tab-projects" tabindex="0">
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body">
                                    <h6 class="text-muted mb-1">Active Projects (Approved, Non-Archived)</h6>
                                    <p class="h4 mb-0"><?php echo number_format($guide_stats['total']); ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body">
                                    <h6 class="text-muted mb-2">In Your Database Right Now</h6>
                                    <p class="small mb-0">Counts below reflect <code>projects</code> rows with <code>approval_status = 'Approved'</code> and status not archived—same scope as the main Projects list.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="card border-0 shadow-sm mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Project Stages (Workflow)</h5>
                                </div>
                                <div class="card-body">
                                    <p>Stages stored in <code>current_stage</code> and used on the Projects and Geo Map filters:</p>
                                    <div class="table-responsive">
                                        <table class="table align-middle">
                                            <tbody>
                                                <?php foreach (user_guide_project_stages() as $stage): ?>
                                                <?php
                                                    $badge = getStatusBadgeColor($stage['name']);
                                                    $count = $guide_stats['by_stage'][$stage['name']] ?? 0;
                                                ?>
                                                <tr>
                                                    <td width="22%">
                                                        <span class="badge bg-<?php echo htmlspecialchars($badge); ?>"><?php echo htmlspecialchars($stage['name']); ?></span>
                                                        <?php if ($guide_stats['total'] > 0): ?>
                                                            <span class="text-muted small ms-1">(<?php echo number_format($count); ?>)</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($stage['description']); ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <?php
                                    $extra_stages = array_diff(array_keys($guide_stats['by_stage']), array_column(user_guide_project_stages(), 'name'));
                                    if (!empty($extra_stages)):
                                    ?>
                                    <p class="small text-muted mb-0">Other stages present in data: <?php echo htmlspecialchars(implode(', ', $extra_stages)); ?>.</p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="card border-0 shadow-sm mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Project Progress Tracking</h5>
                                </div>
                                <div class="card-body">
                                    <h6>Understanding progress metrics</h6>
                                    <ul>
                                        <li><strong>Physical progress</strong> — percentage of actual work completed (<code>physical_progress</code>).</li>
                                        <li><strong>Financial progress</strong> — percentage of funds utilized (<code>financial_progress</code>).</li>
                                        <li><strong>Variance</strong> — difference between physical and financial progress; large gaps may need review.</li>
                                    </ul>
                                    <h6 class="mt-3">Variance interpretation</h6>
                                    <ul>
                                        <li><span class="badge bg-success">Positive variance</span> — physical ahead of financial (often efficient use of budget).</li>
                                        <li><span class="badge bg-danger">Negative variance</span> — financial ahead of physical (possible overspend vs delivery).</li>
                                        <li><span class="badge bg-info">Small variance</span> — aligned execution.</li>
                                    </ul>
                                </div>
                            </div>

                            <div class="card border-0 shadow-sm">
                                <div class="card-header">
                                    <h5 class="mb-0">Project Detail Page</h5>
                                </div>
                                <div class="card-body">
                                    <p>Opened from <a href="projects-advanced.php">Projects</a> — typical sections:</p>
                                    <ul>
                                        <li><strong>Overview</strong> — identity, location, stage, and progress fields.</li>
                                        <li><strong>Financial</strong> — amounts and JSON financial entries where configured.</li>
                                        <li><strong>Documents</strong> — uploads stored on the project record.</li>
                                        <li><strong>Machinery</strong> — AFME machinery workflow when applicable.</li>
                                        <li><strong>Activity</strong> — changes and audit-style history where implemented.</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <div class="card border-0 shadow-sm mb-3">
                                <div class="card-header">
                                    <h5 class="mb-0">Project types (<code>project_type</code>)</h5>
                                </div>
                                <div class="card-body">
                                    <?php foreach (user_guide_project_types() as $pt): ?>
                                    <div class="mb-3">
                                        <h6><span class="badge bg-<?php echo htmlspecialchars($pt['badge']); ?>"><?php echo htmlspecialchars($pt['label']); ?></span>
                                            <?php
                                                $tc = $guide_stats['by_type'][$pt['code']] ?? 0;
                                            ?>
                                            <?php if ($guide_stats['total'] > 0): ?>
                                                <span class="text-muted small">— <?php echo number_format($tc); ?> in DB</span>
                                            <?php endif; ?>
                                        </h6>
                                        <p class="small mb-1"><?php echo htmlspecialchars($pt['description']); ?></p>
                                        <a class="small" href="<?php echo htmlspecialchars($pt['register']); ?>">Registration Form</a>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="card border-0 shadow-sm mb-3">
                                <div class="card-header">
                                    <h5 class="mb-0">Counts by Stage (Live)</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($guide_stats['by_stage'])): ?>
                                        <p class="small text-muted mb-0">No stage breakdown yet (empty dataset or all excluded by filters above).</p>
                                    <?php else: ?>
                                    <ul class="small list-unstyled mb-0">
                                        <?php foreach ($guide_stats['by_stage'] as $st => $cnt): ?>
                                        <li class="d-flex justify-content-between border-bottom py-1">
                                            <span><?php echo htmlspecialchars($st); ?></span>
                                            <strong><?php echo number_format($cnt); ?></strong>
                                        </li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="card border-0 shadow-sm">
                                <div class="card-header">
                                    <h5 class="mb-0">Filtering on Projects</h5>
                                </div>
                                <div class="card-body">
                                    <p class="small mb-2">On <a href="projects-advanced.php">projects-advanced.php</a>:</p>
                                    <ul class="small mb-0">
                                        <li>Program tab → <code>project_type</code> (fspf / idp / afme)</li>
                                        <li>Stage dropdown → <code>current_stage</code></li>
                                        <li>Year → <code>YEAR(created_at)</code></li>
                                        <li>Search → <code>project_code</code> or title</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Features Section (same modules as sidebar + routes) -->
                <div id="features" class="tab-pane fade" role="tabpanel" aria-labelledby="tab-features" tabindex="0">
                    <p class="text-muted mb-4">These entries mirror the application menu and main PHP modules—each link goes to the live screen.</p>
                    <div class="row g-4">
                        <?php
                        foreach (user_guide_features() as $feat) {
                            if (!empty($feat['require_roles']) && !in_array($user_role, $feat['require_roles'], true)) {
                                continue;
                            }
                        ?>
                        <div class="col-md-6 col-xl-4">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-header d-flex align-items-center gap-2">
                                    <i class="fas <?php echo htmlspecialchars($feat['icon']); ?> text-primary"></i>
                                    <h5 class="mb-0"><?php echo htmlspecialchars($feat['title']); ?></h5>
                                </div>
                                <div class="card-body d-flex flex-column">
                                    <p class="card-text"><?php echo htmlspecialchars($feat['summary']); ?></p>
                                    <ul class="small mb-3">
                                        <?php foreach ($feat['bullets'] as $b): ?>
                                        <li><?php echo htmlspecialchars($b); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <a class="btn btn-outline-primary btn-sm mt-auto align-self-start" href="<?php echo htmlspecialchars($feat['href']); ?>"><?php echo htmlspecialchars($feat['link_label']); ?></a>
                                </div>
                            </div>
                        </div>
                        <?php } ?>
                    </div>
                </div>

                <!-- FAQ Section (from config; answers reference real routes/constants) -->
                <div id="faq" class="tab-pane fade" role="tabpanel" aria-labelledby="tab-faq" tabindex="0">
                    <div class="accordion" id="faqAccordion">
                        <?php foreach (user_guide_faqs() as $i => $faq): ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button<?php echo $i > 0 ? ' collapsed' : ''; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#faq-acc-<?php echo $i; ?>" aria-expanded="<?php echo $i === 0 ? 'true' : 'false'; ?>" aria-controls="faq-acc-<?php echo $i; ?>">
                                    <?php echo htmlspecialchars($faq['q']); ?>
                                </button>
                            </h2>
                            <div id="faq-acc-<?php echo $i; ?>" class="accordion-collapse collapse<?php echo $i === 0 ? ' show' : ''; ?>" data-bs-parent="#faqAccordion">
                                <div class="accordion-body"><?php echo $faq['a']; ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
<?php renderAppLayoutFooter(); ?>
