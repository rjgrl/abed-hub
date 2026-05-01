<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/functions/helpers.php';
require_once __DIR__ . '/components/layout.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

requireLogin();

$page_title = 'Help & Guide';
renderAppLayout($page_title);
?>
<div class="container-fluid py-4">
            <!-- Header -->
            <div class="row mb-4">
                <div class="col">
                    <h1 class="h3 mb-0"><i class="fas fa-book"></i> User Guide</h1>
                    <p class="text-muted">Complete documentation for ABED IDM Hub</p>
                </div>
            </div>

            <!-- Navigation Tabs -->
            <ul class="nav nav-tabs mb-4" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" data-bs-toggle="tab" href="#getting-started">
                        <i class="fas fa-play-circle"></i> Getting Started
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#projects">
                        <i class="fas fa-project-diagram"></i> Projects
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#features">
                        <i class="fas fa-star"></i> Features
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#faq">
                        <i class="fas fa-question-circle"></i> FAQ
                    </a>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content">
                <!-- Getting Started -->
                <div id="getting-started" class="tab-pane fade show active">
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="card border-0 shadow-sm mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Welcome to ABED IDM Hub</h5>
                                </div>
                                <div class="card-body">
                                    <p>ABED IDM Hub is a comprehensive project management system designed to help you effectively track and manage projects across multiple funding programs:</p>
                                    <ul>
                                        <li><strong>FSPF</strong> - Food Security and Pre-Implementation Projects</li>
                                        <li><strong>IDP</strong> - Infrastructure Development Programs</li>
                                        <li><strong>AFME</strong> - Agricultural Farmer Machinery Equipment Projects</li>
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
                                            <h6><i class="fas fa-map-marked-alt text-primary"></i> GeoMap</h6>
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
                                        <li><strong>Open GeoMap</strong> - Validate project locations and mapped assets</li>
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
                                        <i class="fas fa-map-marked-alt"></i> GeoMap
                                    </a>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- Projects Section -->
                <div id="projects" class="tab-pane fade">
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="card border-0 shadow-sm mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Project Stages</h5>
                                </div>
                                <div class="card-body">
                                    <p>Every project progresses through the following stages:</p>
                                    <div class="table-responsive">
                                        <table class="table">
                                            <tbody>
                                                <tr>
                                                    <td width="20%"><span class="badge bg-secondary">Proposal</span></td>
                                                    <td>Initial stage - Project proposal under review</td>
                                                </tr>
                                                <tr>
                                                    <td><span class="badge bg-info">Pre-Implementation</span></td>
                                                    <td>Planning and preparation phase</td>
                                                </tr>
                                                <tr>
                                                    <td><span class="badge bg-warning text-dark">Procurement</span></td>
                                                    <td>Acquiring necessary materials and services</td>
                                                </tr>
                                                <tr>
                                                    <td><span class="badge bg-primary">Implementation</span></td>
                                                    <td>Active project execution phase</td>
                                                </tr>
                                                <tr>
                                                    <td><span class="badge bg-success">Completed</span></td>
                                                    <td>Project work completed</td>
                                                </tr>
                                                <tr>
                                                    <td><span class="badge bg-success">Turned-Over</span></td>
                                                    <td>Project officially handed over to beneficiaries</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <div class="card border-0 shadow-sm mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Project Progress Tracking</h5>
                                </div>
                                <div class="card-body">
                                    <h6>Understanding Progress Metrics:</h6>
                                    <ul>
                                        <li><strong>Physical Progress</strong> - Percentage of actual work completed</li>
                                        <li><strong>Financial Progress</strong> - Percentage of funds disbursed/utilized</li>
                                        <li><strong>Progress Variance</strong> - Difference between physical and financial progress (can indicate over/under-spending)</li>
                                    </ul>

                                    <h6 class="mt-3">Variance Interpretation:</h6>
                                    <ul>
                                        <li><span class="badge bg-success">Positive Variance</span> - Physical progress ahead of financial progress (efficient)</li>
                                        <li><span class="badge bg-danger">Negative Variance</span> - Financial progress ahead of physical progress (over-spending)</li>
                                        <li><span class="badge bg-info">Small Variance</span> - Project proceeding as planned</li>
                                    </ul>
                                </div>
                            </div>

                            <div class="card border-0 shadow-sm">
                                <div class="card-header">
                                    <h5 class="mb-0">Project Details Page</h5>
                                </div>
                                <div class="card-body">
                                    <p>The project details page provides comprehensive information:</p>
                                    <ul>
                                        <li><strong>Overview</strong> - Basic project information and status</li>
                                        <li><strong>Financial Tab</strong> - Budget allocation and spending records</li>
                                        <li><strong>Documents Tab</strong> - Attached project documents and reports</li>
                                        <li><strong>Machinery Tab</strong> - Equipment inventory (for AFME projects only)</li>
                                        <li><strong>Activity Log</strong> - Complete audit trail of changes and updates</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <div class="card border-0 shadow-sm mb-3">
                                <div class="card-header">
                                    <h5 class="mb-0">Project Types</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <h6><span class="badge bg-primary">FSPF</span></h6>
                                        <p class="small">Food Security and Pre-Implementation Foundation Projects</p>
                                    </div>
                                    <div class="mb-3">
                                        <h6><span class="badge bg-info">IDP</span></h6>
                                        <p class="small">Infrastructure Development Programs</p>
                                    </div>
                                    <div>
                                        <h6><span class="badge bg-success">AFME</span></h6>
                                        <p class="small">Agricultural Farmer Machinery and Equipment initiatives</p>
                                    </div>
                                </div>
                            </div>

                            <div class="card border-0 shadow-sm">
                                <div class="card-header">
                                    <h5 class="mb-0">Filtering Projects</h5>
                                </div>
                                <div class="card-body">
                                    <p>On the Projects page, you can filter by:</p>
                                    <ul class="small">
                                        <li>Project Type (FSPF, IDP, AFME)</li>
                                        <li>Current Stage</li>
                                        <li>Year Created</li>
                                        <li>Search by Code or Title</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Features Section -->
                <div id="features" class="tab-pane fade">
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="card border-0 shadow-sm mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">S-Curve Monitoring</h5>
                                </div>
                                <div class="card-body">
                                    <p>The S-Curve is a critical tool for project management. It shows the expected vs. actual project progress.</p>
                                    <h6>Key Features:</h6>
                                    <ul>
                                        <li><strong>Progress Visualization</strong> - See actual progress against expected progress</li>
                                        <li><strong>Variance Analysis</strong> - Track the gap between physical and financial progress</li>
                                        <li><strong>Performance Indices</strong> - CPI (Cost Performance Index) and SPI (Schedule Performance Index)</li>
                                        <li><strong>Milestone Tracking</strong> - Monitor project milestones and their completion status</li>
                                        <li><strong>Completion Estimates</strong> - Automatic calculation of project completion date</li>
                                    </ul>
                                </div>
                            </div>

                            <div class="card border-0 shadow-sm mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Analytics & Reports</h5>
                                </div>
                                <div class="card-body">
                                    <h6>Available Reports:</h6>
                                    <ul>
                                        <li><strong>Summary Report</strong> - Key statistics and overview</li>
                                        <li><strong>Detailed Report</strong> - Complete project information</li>
                                        <li><strong>Performance Report</strong> - Top performers and at-risk projects</li>
                                    </ul>

                                    <h6 class="mt-3">Visualizations:</h6>
                                    <ul>
                                        <li>Project distribution by type and stage</li>
                                        <li>Monthly trends and progress</li>
                                        <li>Financial allocation and spending</li>
                                        <li>Performance indicators</li>
                                    </ul>

                                    <p class="mt-3"><strong>Export:</strong> Download data as CSV for external analysis</p>
                                </div>
                            </div>

                            <div class="card border-0 shadow-sm">
                                <div class="card-header">
                                    <h5 class="mb-0">Document Management</h5>
                                </div>
                                <div class="card-body">
                                    <ul>
                                        <li>Upload project documents (PDF, Word, Excel)</li>
                                        <li>Track document versions and upload dates</li>
                                        <li>Organize by document type and project</li>
                                        <li>Download access with audit trail</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <div class="card border-0 shadow-sm mb-3">
                                <div class="card-header bg-success text-white">
                                    <h6 class="mb-0">Dashboard Widgets</h6>
                                </div>
                                <div class="card-body">
                                    <p>The dashboard displays:</p>
                                    <ul class="small">
                                        <li>Total projects count</li>
                                        <li>Total allocated funds</li>
                                        <li>Average physical progress</li>
                                        <li>Pending approvals</li>
                                        <li>Recent projects</li>
                                        <li>Top performers</li>
                                    </ul>
                                </div>
                            </div>

                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-info text-white">
                                    <h6 class="mb-0">Notifications</h6>
                                </div>
                                <div class="card-body">
                                    <p>Real-time alerts for:</p>
                                    <ul class="small">
                                        <li>Project milestone updates</li>
                                        <li>Approval notifications</li>
                                        <li>Budget alerts</li>
                                        <li>Progress variance warnings</li>
                                        <li>Document uploads</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FAQ Section -->
                <div id="faq" class="tab-pane fade">
                    <div class="accordion" id="faqAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                    How do I filter projects by stage?
                                </button>
                            </h2>
                            <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Go to the Projects page and use the "Stage" dropdown menu to select the desired stage. You can also use the search and year filters for more specific results.
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                    What does progress variance mean?
                                </button>
                            </h2>
                            <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Progress variance is the difference between physical progress (actual work completed) and financial progress (funds spent). Positive variance indicates the project is ahead of budget. Negative variance means more money has been spent than work completed.
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                    How can I export project data?
                                </button>
                            </h2>
                            <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    On the Projects page, click the "Export" button to download the current project list as CSV. You can also use the Analytics & Reports page to generate formatted reports.
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                                    How do I update project progress?
                                </button>
                            </h2>
                            <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Open the project details page and click the "Edit" button in the header. Update the physical progress, financial progress, and current stage as needed, then save the changes.
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq5">
                                    What are the file size limits for document upload?
                                </button>
                            </h2>
                            <div id="faq5" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Documents can be up to 50MB in size. Supported formats include PDF, Word documents (.doc, .docx), and Excel spreadsheets (.xls, .xlsx).
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq6">
                                    How do I understand the S-Curve analysis?
                                </button>
                            </h2>
                            <div id="faq6" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    The S-Curve compares planned vs. actual project progress over time. A typical S-Curve has slow start, rapid acceleration, then slows down at completion. Deviations from this pattern indicate project risks or delays.
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq7">
                                    How do I generate reports?
                                </button>
                            </h2>
                            <div id="faq7" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Go to Analytics & Reports, select the desired year and report type, then view generated metrics and charts.
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq8">
                                    Can I search for specific projects?
                                </button>
                            </h2>
                            <div id="faq8" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Yes, use the search box on the Projects page to find projects by code or title. Results are displayed in real-time as you type.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
<?php renderAppLayoutFooter(); ?>
