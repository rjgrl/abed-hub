<?php
/**
 * User guide: single source of truth tied to app constants and live DB stats.
 * Helpers/constants used by the guide page are loaded by user-guide.php before this file.
 */

/**
 * Stages used in the UI and DB (current_stage). Order matches typical workflow.
 */
function user_guide_project_stages(): array
{
    return [
        [
            'name' => 'Proposal',
            'description' => 'Initial stage—project proposal and validation workflow.',
        ],
        [
            'name' => 'Pre-Implementation',
            'description' => 'Planning and preparation before procurement or construction.',
        ],
        [
            'name' => 'Procurement',
            'description' => 'Acquiring materials, services, or contractor mobilization.',
        ],
        [
            'name' => 'Implementation',
            'description' => 'Active execution of works or delivery of outputs.',
        ],
        [
            'name' => 'Completed',
            'description' => 'Substantial completion of project works.',
        ],
        [
            'name' => 'Delivered',
            'description' => 'Used for machinery (AFME)—equipment delivered to beneficiaries.',
        ],
        [
            'name' => 'Turned-Over',
            'description' => 'Formal turnover to beneficiaries or local partners.',
        ],
    ];
}

/**
 * Project types from PROJECT_TYPES + routes used in the app.
 */
function user_guide_project_types(): array
{
    return [
        [
            'code' => 'FSPF',
            'label' => 'FSPF',
            'description' => 'Farm Structure and Processing Facilities.',
            'badge' => 'primary',
            'register' => 'register-fspf-project.php',
        ],
        [
            'code' => 'IDP',
            'label' => 'IDP',
            'description' => 'Irrigation Development Projects.',
            'badge' => 'info',
            'register' => 'register-idp-project.php',
        ],
        [
            'code' => 'AFME',
            'label' => 'AFME',
            'description' => 'Agricultural and Fisheries Machineries and Equipment.',
            'badge' => 'success',
            'register' => 'register-afme-project.php',
        ],
    ];
}

/**
 * Features aligned with sidebar navigation and real modules.
 */
function user_guide_features(): array
{
    return [
        [
            'title' => 'Dashboard',
            'icon' => 'fa-tachometer-alt',
            'summary' => 'Role-based overview, metrics, and recent activity.',
            'bullets' => [
                'Aggregated project counts and financial snapshots where configured.',
                'Recent projects and performance highlights.',
                'Notification badge when the notifications module has items.',
            ],
            'href' => 'dashboard.php',
            'link_label' => 'Open Dashboard',
        ],
        [
            'title' => 'Projects',
            'icon' => 'fa-folder-open',
            'summary' => 'Browse and manage FSPF, IDP, and AFME projects in one table.',
            'bullets' => [
                'Filter by project type (tabs), stage, funding year, and text search on code or title.',
                'Pagination and saved views per user (when configured).',
                'Export and detailed project pages including documents and activity.',
            ],
            'href' => 'projects-advanced.php',
            'link_label' => 'Open Projects',
        ],
        [
            'title' => 'Analytics & Reports',
            'icon' => 'fa-chart-bar',
            'summary' => 'Reporting and charts derived from live project data.',
            'bullets' => [
                'Stage distribution and trends by program.',
                'Performance views for monitoring delivery.',
            ],
            'href' => 'analytics-reports.php',
            'link_label' => 'Open Analytics & Reports',
        ],
        [
            'title' => 'Geo Map',
            'icon' => 'fa-map',
            'summary' => 'Map of geotagged projects with filters.',
            'bullets' => [
                'Filter by project type and stage; inspect coordinates where recorded.',
            ],
            'href' => 'geomap.php',
            'link_label' => 'Open Geo Map',
        ],
        [
            'title' => 'Project Detail & Documents',
            'icon' => 'fa-file-alt',
            'summary' => 'Per-project overview, financial entries, JSON-stored documents, and audit trail.',
            'bullets' => [
                'Upload documents subject to MAX_FILE_SIZE and allowed types (see System Settings / upload handlers).',
                'AFME: machinery tabs and delivery workflow where applicable.',
            ],
            'href' => 'projects-advanced.php',
            'link_label' => 'Go to Projects',
        ],
        [
            'title' => 'Administration',
            'icon' => 'fa-users-cog',
            'summary' => 'Available to Super Admin only.',
            'bullets' => [
                'User management, system settings, and audit log.',
            ],
            'href' => 'admin-dashboard.php',
            'link_label' => 'Admin Tools',
            'require_roles' => ['admin'],
        ],
    ];
}

/**
 * FAQs with answers tied to actual routes and constants.
 */
function user_guide_faqs(): array
{
    $maxMb = defined('MAX_FILE_SIZE') ? round(MAX_FILE_SIZE / (1024 * 1024)) : 10;

    return [
        [
            'q' => 'How do I filter projects by stage?',
            'a' => 'Open <a href="projects-advanced.php">Projects</a>, choose the program tab (FSPF / IDP / AFME), then use the Stage filter, year, and search box for code or title.',
        ],
        [
            'q' => 'What does progress variance mean?',
            'a' => 'Variance is the gap between physical progress (work done) and financial progress (funds utilized). Large gaps may warrant review on the project detail page.',
        ],
        [
            'q' => 'How can I export project data?',
            'a' => 'Use export actions on the Projects list or outputs from <a href="analytics-reports.php">Analytics & Reports</a> where CSV or reports are provided.',
        ],
        [
            'q' => 'How do I update project progress?',
            'a' => 'Open a project from <a href="projects-advanced.php">Projects</a>, go to the project detail screen, and use the edit/update controls for progress and stage (subject to your role).',
        ],
        [
            'q' => 'What is the file size limit for document uploads?',
            'a' => 'The application enforces MAX_FILE_SIZE (currently ' . (int) $maxMb . ' MB). Allowed types depend on the upload handler (commonly PDF and Office formats for project documents).',
        ],
        [
            'q' => 'How do I use the map?',
            'a' => 'Open <a href="geomap.php">Geo Map</a> to see projects with coordinates; use filters to narrow by type and stage.',
        ],
        [
            'q' => 'How do I generate reports?',
            'a' => 'Use <a href="analytics-reports.php">Analytics & Reports</a> to select year ranges and report views supported by the system.',
        ],
        [
            'q' => 'Who can access admin tools?',
            'a' => 'User Management, System Settings, and Audit Log are limited to Super Admin (see sidebar when logged in).',
        ],
    ];
}

/**
 * Live statistics from the database (approved, non-archived projects).
 *
 * @return array{total: int, by_type: array<string, int>, by_stage: array<string, int>}
 */
function user_guide_fetch_stats(mysqli $conn): array
{
    $where = "approval_status = 'Approved' AND (status IS NULL OR status <> 'Archived')";

    $total = 0;
    $r = $conn->query("SELECT COUNT(*) AS c FROM projects WHERE $where");
    if ($r) {
        $total = (int) ($r->fetch_assoc()['c'] ?? 0);
    }

    /** @var array<string, int> $by_type */
    $by_type = [];
    $r = $conn->query(
        "SELECT UPPER(project_type) AS t, COUNT(*) AS c FROM projects WHERE $where GROUP BY UPPER(project_type) ORDER BY t"
    );
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $by_type[(string) $row['t']] = (int) $row['c'];
        }
    }

    /** @var array<string, int> $by_stage */
    $by_stage = [];
    $r = $conn->query(
        "SELECT current_stage, COUNT(*) AS c FROM projects WHERE $where GROUP BY current_stage ORDER BY current_stage"
    );
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $stage = (string) ($row['current_stage'] ?? '');
            if ($stage !== '') {
                $by_stage[$stage] = (int) $row['c'];
            }
        }
    }

    return [
        'total' => $total,
        'by_type' => $by_type,
        'by_stage' => $by_stage,
    ];
}
