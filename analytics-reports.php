<?php
require_once __DIR__ . '/components/layout.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/functions/helpers.php';
require_once __DIR__ . '/services/ProjectRepository.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

requireLogin();

$page_title = 'Analytics & Reports - ABED IDM Hub';

$year        = intval($_GET['year'] ?? date('Y'));
$month       = $_GET['month'] ?? date('m');
$report_type = $_GET['report'] ?? 'summary';
if (!in_array($report_type, ['summary', 'detailed', 'performance'], true)) {
    $report_type = 'summary';
}

$curve_program = strtolower((string) ($_GET['curve'] ?? 'all'));
if (!in_array($curve_program, ['all', 'fspf', 'idp', 'afme'], true)) {
    $curve_program = 'all';
}
$curve_label = $curve_program === 'all' ? 'All programs' : strtoupper($curve_program);

$repo = new ProjectRepository($conn);

$counts          = $repo->counts();
$all_projects    = $counts['total'];
$fspf_count      = $counts['fspf'];
$idp_count       = $counts['idp'];
$afme_count      = $counts['afme'];

$finance         = $repo->financialSummary();
$total_proposed  = $finance['total_proposed'];
$total_allocated = $finance['total_allocated'];

$stage_dist_map  = $repo->stageDistribution();
$stage_dist      = array_map(
    fn($stage, $cnt) => ['current_stage' => $stage, 'count' => $cnt],
    array_keys($stage_dist_map),
    $stage_dist_map
);

$monthly_data    = $repo->monthlyTrend($year, $curve_program);

$stage_yearly_map = $repo->stageDistributionByYear($year);
$stage_yearly     = array_map(
    fn($stage, $cnt) => ['current_stage' => $stage, 'count' => $cnt],
    array_keys($stage_yearly_map),
    $stage_yearly_map
);

$top_performers  = $repo->topPerformers('all', 10);
$at_risk         = $repo->atRiskProjects(10);
$avg_progress    = $repo->avgProgress('all');
$completed       = $repo->completedCount();

// Derived insights (used for summary vs performance narrative — distinct copy per report type)
$stage_dist_total = array_sum($stage_dist_map);
$dominant_stage   = '';
$dominant_count   = 0;
foreach ($stage_dist_map as $st => $c) {
    if ($c > $dominant_count) {
        $dominant_count = $c;
        $dominant_stage = $st;
    }
}
$stage_dist_total = array_sum($stage_dist_map);
$utilization_pct = $total_proposed > 0
    ? round(($total_allocated / $total_proposed) * 100, 1)
    : null;
$at_risk_count   = count($at_risk);

renderAppLayout($page_title, '<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>');
?>
        <div class="container-fluid py-4">
            <!-- Header -->
            <div class="row align-items-center mb-4">
                <div class="col">
                    <h1 class="h3 mb-0">Analytics & Reports</h1>
                    <?php if ($report_type === 'summary'): ?>
                        <p class="text-muted mb-0">Executive snapshot — headline portfolio metrics and a single visual overview.</p>
                    <?php elseif ($report_type === 'detailed'): ?>
                        <p class="text-muted mb-0">Full analytical view — charts, breakdown tables, definitions, and contextual notes for <?php echo (int) $year; ?>.</p>
                    <?php else: ?>
                        <p class="text-muted mb-0">Performance scorecard — execution metrics, variance analysis, and evaluative commentary.</p>
                    <?php endif; ?>
                </div>
                <div class="col-auto">
                    <button class="btn btn-outline-primary" onclick="openAnalyticsPdf()">
                        <i class="fas fa-print"></i> Print
                    </button>
                </div>
            </div>

            <!-- Filters -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <form method="GET" id="analyticsFilterForm" class="row g-3 align-items-start">
                        <div class="col-12 col-md-4 col-lg-3">
                            <label class="form-label mb-1" for="analyticsYear">Year</label>
                            <select id="analyticsYear" name="year" class="form-select" onchange="this.form.submit()">
                                <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                                    <option value="<?php echo $y; ?>" <?php echo $year == $y ? 'selected' : ''; ?>>
                                        <?php echo $y; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                            <small class="text-muted d-block mt-1">Filters charts and cohort tables that use creation year.</small>
                        </div>
                        <div class="col-12 col-md-8 col-lg-9">
                            <label class="form-label mb-1" for="analyticsReportType">Report Type</label>
                            <select id="analyticsReportType" name="report" class="form-select" onchange="this.form.submit()">
                                <option value="summary" <?php echo $report_type === 'summary' ? 'selected' : ''; ?>>Summary — concise insights</option>
                                <option value="detailed" <?php echo $report_type === 'detailed' ? 'selected' : ''; ?>>Detailed — full context &amp; data</option>
                                <option value="performance" <?php echo $report_type === 'performance' ? 'selected' : ''; ?>>Performance — metrics &amp; evaluation</option>
                            </select>
                            <?php if ($report_type === 'summary'): ?>
                                <small class="text-muted d-block mt-1">Shows KPIs, one chart, and brief bullet takeaways only.</small>
                            <?php elseif ($report_type === 'detailed'): ?>
                                <small class="text-muted d-block mt-1">Includes methodology notes, all visuals, extended tables, and financial columns.</small>
                            <?php else: ?>
                                <small class="text-muted d-block mt-1">Emphasizes averages, physical vs financial execution, and variance-led rankings.</small>
                            <?php endif; ?>
                        </div>
                        <div class="col-12">
                            <label class="form-label mb-1">Monthly charts — program cohort</label>
                            <div class="btn-group flex-wrap" role="group" aria-label="Program filter for monthly execution charts">
                                <input type="radio" class="btn-check" name="curve" id="curve_fspf" value="fspf" <?php echo $curve_program === 'fspf' ? 'checked' : ''; ?> onchange="this.form.submit()">
                                <label class="btn btn-outline-primary" for="curve_fspf">FSPF</label>
                                <input type="radio" class="btn-check" name="curve" id="curve_idp" value="idp" <?php echo $curve_program === 'idp' ? 'checked' : ''; ?> onchange="this.form.submit()">
                                <label class="btn btn-outline-primary" for="curve_idp">IDP</label>
                                <input type="radio" class="btn-check" name="curve" id="curve_afme" value="afme" <?php echo $curve_program === 'afme' ? 'checked' : ''; ?> onchange="this.form.submit()">
                                <label class="btn btn-outline-primary" for="curve_afme">AFME</label>
                                <input type="radio" class="btn-check" name="curve" id="curve_all" value="all" <?php echo $curve_program === 'all' ? 'checked' : ''; ?> onchange="this.form.submit()">
                                <label class="btn btn-outline-primary" for="curve_all">All Projects</label>
                            </div>
                            <small class="text-muted d-block mt-1">Filters monthly activity and the <?php echo (int) $year; ?> monthly execution curve to projects of that type (created in the selected year). Same pattern as <a href="projects-advanced.php">Projects</a>.</small>
                        </div>
                    </form>
                </div>
            </div>

            <?php
            $gap_pf = round($avg_progress['avg_physical'] - $avg_progress['avg_financial'], 1);
            ?>

            <?php if ($report_type === 'summary'): ?>

            <!-- Summary: KPI only + one chart + bullets -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center py-3">
                            <h3 class="text-primary mb-0"><?php echo $all_projects; ?></h3>
                            <small class="text-muted">Approved projects</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center py-3">
                            <h3 class="text-success mb-0">₱<?php echo number_format($total_allocated, 0); ?></h3>
                            <small class="text-muted">Total allocated</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center py-3">
                            <h3 class="text-info mb-0"><?php echo $avg_progress['avg_physical']; ?>%</h3>
                            <small class="text-muted">Avg physical progress</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center py-3">
                            <h3 class="text-warning mb-0"><?php echo $completed; ?></h3>
                            <small class="text-muted">Completed / turned over</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header">
                            <h6 class="mb-0">Portfolio mix (by program type)</h6>
                        </div>
                        <div class="card-body analytics-chart-card-body">
                            <canvas id="typeChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header">
                            <h6 class="mb-0">Executive highlights</h6>
                        </div>
                        <div class="card-body">
                            <ul class="mb-0 ps-3">
                                <li class="mb-2">The approved catalog contains <strong><?php echo $all_projects; ?></strong> projects with <strong>₱<?php echo number_format($total_allocated, 0); ?></strong> in recorded allocations<?php echo $utilization_pct !== null ? ' (' . $utilization_pct . '% of aggregate proposed amounts).' : '.'; ?></li>
                                <li class="mb-2">Mean physical completion sits at <strong><?php echo $avg_progress['avg_physical']; ?>%</strong>; <strong><?php echo $completed; ?></strong> projects are closed out as completed or turned over.</li>
                                <?php if ($dominant_stage !== '' && $dominant_count > 0): ?>
                                    <li class="mb-2">The largest stage concentration is <strong><?php echo htmlspecialchars($dominant_stage); ?></strong> (<strong><?php echo $dominant_count; ?></strong> projects<?php echo $stage_dist_total > 0 ? ', ' . round(100 * $dominant_count / $stage_dist_total) . '% of staged records' : ''; ?>).</li>
                                <?php endif; ?>
                                <li class="mb-0">Program split: FSPF <strong><?php echo $fspf_count; ?></strong>, IDP <strong><?php echo $idp_count; ?></strong>, AFME <strong><?php echo $afme_count; ?></strong>.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <?php elseif ($report_type === 'detailed'): ?>

            <div class="alert alert-light border mb-4" role="note">
                <strong>Reading this report.</strong> Figures below combine the full approved project catalog for counts, progress, and finance. Charts labeled with <?php echo (int) $year; ?> use projects whose <em>creation date</em> falls in that year; stage and KPI cards reflect the current catalog regardless of year. Use this view when you need definitions, supporting series side by side, and tabular backup for stakeholders or audits.
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <h3 class="text-primary"><?php echo $all_projects; ?></h3>
                            <small class="text-muted">Total projects (approved catalog)</small>
                            <p class="small text-muted mb-0 mt-2">Each row is an approved project record across FSPF, IDP, and AFME.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <h3 class="text-success">₱<?php echo number_format($total_allocated, 0); ?></h3>
                            <small class="text-muted">Total allocated budget</small>
                            <p class="small text-muted mb-0 mt-2">Sum of allocated_amount across the catalog; compare to proposed totals below.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <h3 class="text-info"><?php echo $avg_progress['avg_physical']; ?>%</h3>
                            <small class="text-muted">Average physical progress</small>
                            <p class="small text-muted mb-0 mt-2">Unweighted mean of physical_progress for all approved projects.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <h3 class="text-warning"><?php echo $completed; ?></h3>
                            <small class="text-muted">Completed / turned over</small>
                            <p class="small text-muted mb-0 mt-2">Projects whose current_stage is Completed or Turned-Over.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header">
                            <h6 class="mb-0">Distribution by type</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="typeChart" height="100"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header">
                            <h6 class="mb-0">Distribution by stage (current)</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="stageChart" height="100"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header">
                            <h6 class="mb-0">Monthly activity <?php echo (int) $year; ?> <span class="badge bg-light text-dark border ms-1"><?php echo htmlspecialchars($curve_label); ?></span></h6>
                        </div>
                        <div class="card-body">
                            <p class="small text-muted mb-2">Blue: projects created per month. Orange: average physical % among projects created that month. Cohort matches the program tab above (FSPF / IDP / AFME / All Projects).</p>
                            <div class="position-relative analytics-monthly-chart-wrap">
                                <canvas id="monthlyChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header">
                            <h6 class="mb-0">Leading projects by physical progress</h6>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Project</th>
                                            <th>Physical</th>
                                            <th>Financial</th>
                                            <th>Note</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (array_slice($top_performers, 0, 8) as $proj): ?>
                                            <tr>
                                                <td>
                                                    <small>
                                                        <strong><?php echo htmlspecialchars($proj['project_code']); ?></strong><br>
                                                        <span class="text-muted"><?php echo htmlspecialchars(substr($proj['project_title'], 0, 42)); ?><?php echo strlen($proj['project_title']) > 42 ? '…' : ''; ?></span>
                                                    </small>
                                                </td>
                                                <td>
                                                    <div class="progress progress-inline-metric">
                                                        <div class="progress-bar bg-success progress-bar-w" style="--w: <?php echo min(100, (float) $proj['physical_progress']); ?>%;">
                                                            <small><?php echo round($proj['physical_progress'], 0); ?>%</small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><small><?php echo round($proj['financial_progress'], 0); ?>%</small></td>
                                                <td><small class="text-muted">In active delivery stages; ranked by physical %.</small></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header">
                            <h6 class="mb-0">Large physical vs financial gaps</h6>
                        </div>
                        <div class="card-body border-bottom">
                            <p class="small mb-0 text-muted">Variance = physical_progress − financial_progress. Negative values often mean disbursement or booking ahead of site progress; strongly positive values can mean delivery ahead of payment curves. Thresholds follow the alerts in the last column.</p>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Project</th>
                                            <th>Variance</th>
                                            <th>Physical / Fin</th>
                                            <th>Alert</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (array_slice($at_risk, 0, 8) as $proj): ?>
                                            <tr>
                                                <td>
                                                    <small>
                                                        <strong><?php echo htmlspecialchars($proj['project_code']); ?></strong><br>
                                                        <span class="text-muted"><?php echo htmlspecialchars(substr($proj['project_title'], 0, 36)); ?><?php echo strlen($proj['project_title']) > 36 ? '…' : ''; ?></span>
                                                    </small>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo htmlspecialchars(variance_badge_class((float) $proj['variance']), ENT_QUOTES, 'UTF-8'); ?>">
                                                        <?php echo $proj['variance'] > 0 ? '+' : ''; ?><?php echo round($proj['variance'], 1); ?>%
                                                    </span>
                                                </td>
                                                <td><small><?php echo round($proj['physical_progress'], 0); ?>% / <?php echo round($proj['financial_progress'], 0); ?>%</small></td>
                                                <td>
                                                    <?php if ($proj['variance'] < -15): ?>
                                                        <small class="badge bg-danger">Must act</small>
                                                    <?php elseif ($proj['variance'] < -5): ?>
                                                        <small class="badge bg-warning text-dark">Monitor</small>
                                                    <?php else: ?>
                                                        <small class="badge bg-info">Watch</small>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header">
                            <h6 class="mb-0">Cohort created in <?php echo (int) $year; ?> — current stage</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <tbody>
                                        <?php foreach ($stage_yearly as $stage): ?>
                                            <tr>
                                                <td>
                                                    <span class="badge <?php echo htmlspecialchars(stage_badge_class((string) $stage['current_stage']), ENT_QUOTES, 'UTF-8'); ?>">
                                                        <?php echo htmlspecialchars($stage['current_stage']); ?>
                                                    </span>
                                                </td>
                                                <td class="text-end">
                                                    <strong><?php echo $stage['count']; ?></strong>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header">
                            <h6 class="mb-0">Counts by program type (full catalog)</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <tbody>
                                        <tr>
                                            <td><span class="badge bg-primary">FSPF</span></td>
                                            <td class="text-end"><strong><?php echo $fspf_count; ?></strong> projects</td>
                                        </tr>
                                        <tr>
                                            <td><span class="badge bg-info">IDP</span></td>
                                            <td class="text-end"><strong><?php echo $idp_count; ?></strong> projects</td>
                                        </tr>
                                        <tr>
                                            <td><span class="badge bg-success">AFME</span></td>
                                            <td class="text-end"><strong><?php echo $afme_count; ?></strong> projects</td>
                                        </tr>
                                        <tr class="table-active">
                                            <td><strong>Total</strong></td>
                                            <td class="text-end"><strong><?php echo $all_projects; ?></strong> projects</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <p class="small text-muted mb-0 mt-3"><strong>Proposed vs allocated (catalog):</strong> proposed ₱<?php echo number_format($total_proposed, 0); ?>; allocated ₱<?php echo number_format($total_allocated, 0); ?><?php echo $total_proposed > 0 ? ' — allocated represents <strong>' . $utilization_pct . '%</strong> of aggregate proposed.' : '.'; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <?php else: /* performance */ ?>

            <div class="row g-3 mb-4">
                <div class="col">
                    <p class="text-muted mb-0">This layout foregrounds <strong>measurable execution</strong>: portfolio-level averages, physical versus financial curves by month, and projects ranked by delivery metrics or variance. Narrative bullets interpret gaps — they do not repeat the definitional text used in the Detailed report.</p>
                </div>
            </div>

            <div class="row row-cols-2 row-cols-md-3 row-cols-lg-6 g-3 mb-4">
                <div class="col">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center py-3">
                            <h4 class="text-primary mb-0"><?php echo $all_projects; ?></h4>
                            <small class="text-muted">Catalog projects</small>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center py-3">
                            <h4 class="text-success mb-0">₱<?php echo number_format($total_allocated, 0); ?></h4>
                            <small class="text-muted">Allocated</small>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center py-3">
                            <h4 class="text-info mb-0"><?php echo $avg_progress['avg_physical']; ?>%</h4>
                            <small class="text-muted">Avg physical</small>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center py-3">
                            <h4 class="text-secondary mb-0"><?php echo $avg_progress['avg_financial']; ?>%</h4>
                            <small class="text-muted">Avg financial</small>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center py-3">
                            <h4 class="text-warning mb-0"><?php echo $completed; ?></h4>
                            <small class="text-muted">Completed / TO</small>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center py-3">
                            <h4 class="text-dark mb-0"><?php echo $utilization_pct !== null ? $utilization_pct . '%' : '—'; ?></h4>
                            <small class="text-muted">Allocated / proposed</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header">
                    <h6 class="mb-0">Evaluative read</h6>
                </div>
                <div class="card-body">
                    <ul class="mb-0 ps-3">
                        <li class="mb-2">Mean physical delivery (<strong><?php echo $avg_progress['avg_physical']; ?>%</strong>) versus mean financial execution (<strong><?php echo $avg_progress['avg_financial']; ?>%</strong>) yields a spread of <strong><?php echo $gap_pf >= 0 ? '+' : ''; ?><?php echo $gap_pf; ?></strong> points. <?php if ($gap_pf > 2): ?>Physical progress is ahead of the financial curve on average — watch cash flow against milestones.<?php elseif ($gap_pf < -2): ?>Financial progress leads physical on average — review sites listed under variance for burn ahead of delivery.<?php else: ?>Physical and financial progress are broadly aligned at portfolio level; drill into individual outliers below.<?php endif; ?></li>
                        <li class="mb-2"><strong><?php echo $at_risk_count; ?></strong> project<?php echo $at_risk_count === 1 ? '' : 's'; ?> meet the high-variance rule set (|physical − financial| beyond thresholds). <?php echo $at_risk_count === 0 ? 'No rows appear in the variance table — treat this as a clean signal only if data entry is current.' : 'Prioritize reviews starting with the largest absolute gaps.'; ?></li>
                        <li class="mb-0">Completion throughput: <strong><?php echo $completed; ?></strong> of <strong><?php echo $all_projects; ?></strong> records are in a terminal stage (<?php echo $all_projects > 0 ? round(100 * $completed / $all_projects, 1) : 0; ?>% of catalog).</li>
                    </ul>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header">
                            <h6 class="mb-0">Share by program (weight)</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="typeChart" height="100"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header">
                            <h6 class="mb-0">Pipeline load by stage</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="stageChart" height="100"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header">
                            <h6 class="mb-0"><?php echo (int) $year; ?> monthly execution curve <span class="badge bg-light text-dark border ms-1"><?php echo htmlspecialchars($curve_label); ?></span></h6>
                        </div>
                        <div class="card-body">
                            <p class="small text-muted mb-2">Physical vs financial % for projects <em>created</em> each month in this cohort — compares execution shape across the year. Use <strong>Monthly charts — program cohort</strong> (FSPF, IDP, AFME, All Projects) in the filters card to match <a href="projects-advanced.php">Projects</a>.</p>
                            <div class="position-relative analytics-monthly-chart-wrap">
                                <canvas id="monthlyChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">Top delivery scores</h6>
                            <span class="badge bg-success">Ranked by physical %</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Code</th>
                                            <th class="text-end">Physical</th>
                                            <th class="text-end">Financial</th>
                                            <th class="text-end">Gap</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (array_slice($top_performers, 0, 10) as $proj):
                                            $row_gap = round((float) $proj['physical_progress'] - (float) $proj['financial_progress'], 1);
                                            ?>
                                            <tr>
                                                <td>
                                                    <small><strong><?php echo htmlspecialchars($proj['project_code']); ?></strong><br>
                                                    <span class="text-muted"><?php echo htmlspecialchars(substr($proj['project_title'], 0, 28)); ?><?php echo strlen($proj['project_title']) > 28 ? '…' : ''; ?></span></small>
                                                </td>
                                                <td class="text-end"><strong><?php echo round($proj['physical_progress'], 0); ?>%</strong></td>
                                                <td class="text-end"><?php echo round($proj['financial_progress'], 0); ?>%</td>
                                                <td class="text-end"><span class="badge bg-light text-dark"><?php echo $row_gap >= 0 ? '+' : ''; ?><?php echo $row_gap; ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">Variance outliers</h6>
                            <span class="badge bg-warning text-dark">Execution risk</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Code</th>
                                            <th class="text-end">Variance</th>
                                            <th class="text-end">Phys / Fin</th>
                                            <th>Rating</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (array_slice($at_risk, 0, 10) as $proj): ?>
                                            <tr>
                                                <td>
                                                    <small><strong><?php echo htmlspecialchars($proj['project_code']); ?></strong><br>
                                                    <span class="text-muted"><?php echo htmlspecialchars(substr($proj['project_title'], 0, 26)); ?><?php echo strlen($proj['project_title']) > 26 ? '…' : ''; ?></span></small>
                                                </td>
                                                <td class="text-end">
                                                    <span class="badge <?php echo htmlspecialchars(variance_badge_class((float) $proj['variance']), ENT_QUOTES, 'UTF-8'); ?>">
                                                        <?php echo $proj['variance'] > 0 ? '+' : ''; ?><?php echo round($proj['variance'], 1); ?>%
                                                    </span>
                                                </td>
                                                <td class="text-end"><small><?php echo round($proj['physical_progress'], 0); ?> / <?php echo round($proj['financial_progress'], 0); ?></small></td>
                                                <td>
                                                    <?php if ($proj['variance'] < -15): ?>
                                                        <small class="badge bg-danger">Critical</small>
                                                    <?php elseif ($proj['variance'] < -5): ?>
                                                        <small class="badge bg-warning text-dark">Elevated</small>
                                                    <?php else: ?>
                                                        <small class="badge bg-info">Elevated</small>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php endif; ?>

        </div>

    <script>
        function openAnalyticsPdf() {
            const year = encodeURIComponent('<?php echo htmlspecialchars((string) $year); ?>');
            const report = encodeURIComponent('<?php echo htmlspecialchars($report_type); ?>');
            const curve = encodeURIComponent('<?php echo htmlspecialchars($curve_program); ?>');
            window.open(`exports/reports-pdf.php?source=analytics&year=${year}&report=${report}&curve=${curve}`, '_blank');
        }

        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const monthlyData = <?php echo json_encode($monthly_data); ?>;

        function monthlySeries(fnCount, fnPhys, fnFin) {
            const count = [], phys = [], fin = [];
            for (let i = 1; i <= 12; i++) {
                const row = monthlyData[i];
                count.push(fnCount(row));
                phys.push(fnPhys(row));
                fin.push(fnFin(row));
            }
            return { count, phys, fin };
        }

        <?php if ($report_type === 'summary'): ?>
        new Chart(document.getElementById('typeChart'), {
            type: 'doughnut',
            data: {
                labels: ['FSPF', 'IDP', 'AFME'],
                datasets: [{
                    data: [<?php echo $fspf_count; ?>, <?php echo $idp_count; ?>, <?php echo $afme_count; ?>],
                    backgroundColor: ['#5b8def', '#c694f9', '#5fd4a8']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: { legend: { position: 'bottom' } }
            }
        });
        <?php elseif ($report_type === 'detailed'): ?>
        new Chart(document.getElementById('typeChart'), {
            type: 'doughnut',
            data: {
                labels: ['FSPF', 'IDP', 'AFME'],
                datasets: [{
                    data: [<?php echo $fspf_count; ?>, <?php echo $idp_count; ?>, <?php echo $afme_count; ?>],
                    backgroundColor: ['#5b8def', '#c694f9', '#5fd4a8']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } }
            }
        });
        const stages = <?php echo json_encode(array_column($stage_dist, 'current_stage')); ?>;
        const stageCounts = <?php echo json_encode(array_column($stage_dist, 'count')); ?>;
        new Chart(document.getElementById('stageChart'), {
            type: 'bar',
            data: {
                labels: stages,
                datasets: [{
                    label: 'Projects',
                    data: stageCounts,
                    backgroundColor: ['#9ca3af', '#c694f9', '#f5c57a', '#5b8def', '#5fd4a8']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true } },
                plugins: { legend: { display: false } }
            }
        });
        (function () {
            const s = monthlySeries(
                (row) => row?.count || 0,
                (row) => row?.avg_physical || 0,
                (row) => row?.avg_financial || 0
            );
            new Chart(document.getElementById('monthlyChart'), {
                type: 'line',
                data: {
                    labels: months,
                    datasets: [
                        {
                            label: 'Projects created',
                            data: s.count,
                            borderColor: '#5b8def',
                            backgroundColor: 'rgba(91, 141, 239, 0.08)',
                            yAxisID: 'y',
                            tension: 0,
                            spanGaps: true
                        },
                        {
                            label: 'Avg physical % (cohort)',
                            data: s.phys,
                            borderColor: '#fd7e14',
                            backgroundColor: 'rgba(253, 126, 20, 0.06)',
                            yAxisID: 'y1',
                            tension: 0,
                            spanGaps: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: false,
                    interaction: { mode: 'index', intersect: false },
                    scales: {
                        y: {
                            type: 'linear',
                            position: 'left',
                            min: 0,
                            max: 500,
                            ticks: {
                                stepSize: 50,
                                maxTicksLimit: 12
                            },
                            title: { display: true, text: 'Projects created' }
                        },
                        y1: {
                            type: 'linear',
                            position: 'right',
                            min: 0,
                            max: 100,
                            grid: { drawOnChartArea: false },
                            ticks: {
                                stepSize: 10,
                                maxTicksLimit: 11
                            },
                            title: { display: true, text: 'Avg physical %' }
                        }
                    }
                }
            });
        })();
        <?php else: ?>
        new Chart(document.getElementById('typeChart'), {
            type: 'doughnut',
            data: {
                labels: ['FSPF', 'IDP', 'AFME'],
                datasets: [{
                    data: [<?php echo $fspf_count; ?>, <?php echo $idp_count; ?>, <?php echo $afme_count; ?>],
                    backgroundColor: ['#5b8def', '#c694f9', '#5fd4a8']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } }
            }
        });
        const stagesP = <?php echo json_encode(array_column($stage_dist, 'current_stage')); ?>;
        const stageCountsP = <?php echo json_encode(array_column($stage_dist, 'count')); ?>;
        new Chart(document.getElementById('stageChart'), {
            type: 'bar',
            data: {
                labels: stagesP,
                datasets: [{
                    label: 'Count',
                    data: stageCountsP,
                    backgroundColor: ['#9ca3af', '#c694f9', '#f5c57a', '#5b8def', '#5fd4a8']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true } },
                plugins: { legend: { display: false } }
            }
        });
        (function () {
            const cohortValue = (row, pick) => {
                if (row == null || !row.count) {
                    return null;
                }
                const v = pick(row);
                return v == null || Number.isNaN(v) ? null : v;
            };
            const s = monthlySeries(
                () => 0,
                (row) => cohortValue(row, (r) => r.avg_physical),
                (row) => cohortValue(row, (r) => r.avg_financial)
            );
            new Chart(document.getElementById('monthlyChart'), {
                type: 'line',
                data: {
                    labels: months,
                    datasets: [
                        {
                            label: 'Avg physical %',
                            data: s.phys,
                            borderColor: '#0dcaf0',
                            backgroundColor: 'rgba(13, 202, 240, 0.08)',
                            yAxisID: 'y',
                            tension: 0,
                            spanGaps: false,
                            pointRadius: 3,
                            pointHoverRadius: 5
                        },
                        {
                            label: 'Avg financial %',
                            data: s.fin,
                            borderColor: '#6f42c1',
                            backgroundColor: 'rgba(111, 66, 193, 0.08)',
                            yAxisID: 'y',
                            tension: 0,
                            spanGaps: false,
                            pointRadius: 3,
                            pointHoverRadius: 5
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: false,
                    interaction: { mode: 'index', intersect: false },
                    scales: {
                        y: {
                            type: 'linear',
                            position: 'left',
                            min: 0,
                            max: 100,
                            title: { display: true, text: 'Physical / financial %' }
                        }
                    }
                }
            });
        })();
        <?php endif; ?>
    </script>

<?php
renderAppLayoutFooter();
?>
