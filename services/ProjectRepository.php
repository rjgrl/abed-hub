<?php
/**
 * ProjectRepository — the single source of truth for all project-related SQL.
 *
 * Usage:
 *   require_once __DIR__ . '/../services/ProjectRepository.php';
 *   $repo = new ProjectRepository($conn);
 *   $stats = $repo->counts('fspf');
 */
class ProjectRepository {

    private mysqli $conn;

    /** Valid module values for filtering. */
    private const MODULES = ['all', 'fspf', 'idp', 'afme'];

    /** Maps a module name to its project table. */
    private const TABLE = [
        'fspf' => 'fspf_projects',
        'idp'  => 'idp_projects',
        'afme' => 'afme_projects',
    ];

    public function __construct(mysqli $conn) {
        $this->conn = $conn;
    }

    // ─── Private helpers ────────────────────────────────────────────────────

    /**
     * Build a UNION ALL subquery string covering the requested module(s).
     * $cols is a comma-separated list of column expressions to SELECT in each branch.
     */
    private function unionSource(string $module, string $cols): string {
        $safe = in_array($module, self::MODULES, true) ? $module : 'all';
        if ($safe !== 'all') {
            $tbl = self::TABLE[$safe];
            return "SELECT $cols FROM $tbl";
        }
        $parts = [];
        foreach (self::TABLE as $tbl) {
            $parts[] = "SELECT $cols FROM $tbl";
        }
        return implode("\nUNION ALL\n", $parts);
    }

    /** Run a query and return its single-row assoc result. */
    private function fetchOne(string $sql): array {
        $res = $this->conn->query($sql);
        if (!$res) return [];
        return $res->fetch_assoc() ?? [];
    }

    /** Run a query and return all rows as an assoc array. */
    private function fetchAll(string $sql): array {
        $res = $this->conn->query($sql);
        if (!$res) return [];
        return $res->fetch_all(MYSQLI_ASSOC);
    }

    // ─── Public API ─────────────────────────────────────────────────────────

    /**
     * Per-type and total project counts.
     * Returns: ['fspf' => int, 'idp' => int, 'afme' => int, 'total' => int]
     */
    public function counts(string $module = 'all'): array {
        $fspf = (int) $this->fetchOne("SELECT COUNT(*) AS c FROM fspf_projects")['c'];
        $idp  = (int) $this->fetchOne("SELECT COUNT(*) AS c FROM idp_projects")['c'];
        $afme = (int) $this->fetchOne("SELECT COUNT(*) AS c FROM afme_projects")['c'];

        return [
            'fspf'  => $fspf,
            'idp'   => $idp,
            'afme'  => $afme,
            'total' => match ($module) {
                'fspf'  => $fspf,
                'idp'   => $idp,
                'afme'  => $afme,
                default => $fspf + $idp + $afme,
            },
        ];
    }

    /**
     * Project count by stage, filtered by module.
     * Returns: [stage => count, ...]
     */
    public function stageDistribution(string $module = 'all'): array {
        $src  = $this->unionSource($module, 'current_stage');
        $rows = $this->fetchAll(
            "SELECT current_stage, COUNT(*) AS cnt
             FROM ($src) AS combined
             GROUP BY current_stage
             ORDER BY cnt DESC"
        );
        $out = [];
        foreach ($rows as $row) {
            $out[$row['current_stage']] = (int) $row['cnt'];
        }
        return $out;
    }

    /**
     * Stage distribution filtered to a specific funding year.
     * Returns: [stage => count, ...]
     */
    public function stageDistributionByYear(int $year, string $module = 'all'): array {
        $safe = in_array($module, self::MODULES, true) ? $module : 'all';
        $tables = $safe === 'all' ? array_values(self::TABLE) : [self::TABLE[$safe]];
        $parts  = array_map(fn($tbl) => "SELECT current_stage FROM $tbl WHERE YEAR(created_at) = $year", $tables);
        $src    = implode("\nUNION ALL\n", $parts);

        $rows = $this->fetchAll(
            "SELECT current_stage, COUNT(*) AS cnt
             FROM ($src) AS combined
             GROUP BY current_stage"
        );
        $out = [];
        foreach ($rows as $row) {
            $out[$row['current_stage']] = (int) $row['cnt'];
        }
        return $out;
    }

    /**
     * Financial summary filtered by module.
     * Returns: ['total_proposed' => float, 'total_allocated' => float]
     */
    public function financialSummary(string $module = 'all'): array {
        $src = $this->unionSource($module, 'proposed_amount, allocated_amount');
        $row = $this->fetchOne(
            "SELECT SUM(proposed_amount) AS total_proposed,
                    SUM(allocated_amount) AS total_allocated
             FROM ($src) AS combined"
        );
        return [
            'total_proposed'  => (float) ($row['total_proposed'] ?? 0),
            'total_allocated' => (float) ($row['total_allocated'] ?? 0),
        ];
    }

    /**
     * Average physical and financial progress, filtered by module.
     * Returns: ['avg_physical' => float, 'avg_financial' => float]
     */
    public function avgProgress(string $module = 'all'): array {
        $safe   = in_array($module, self::MODULES, true) ? $module : 'all';
        $tables = $safe === 'all'
            ? ['fspf_projects', 'idp_projects']
            : (in_array($safe, ['fspf', 'idp']) ? [self::TABLE[$safe]] : []);

        if (empty($tables)) {
            return ['avg_physical' => 0.0, 'avg_financial' => 0.0];
        }
        $parts = array_map(fn($tbl) => "SELECT physical_progress, financial_progress FROM $tbl", $tables);
        $src   = implode("\nUNION ALL\n", $parts);

        $row = $this->fetchOne(
            "SELECT ROUND(AVG(physical_progress), 1) AS avg_physical,
                    ROUND(AVG(financial_progress), 1) AS avg_financial
             FROM ($src) AS combined"
        );
        return [
            'avg_physical'  => (float) ($row['avg_physical'] ?? 0),
            'avg_financial' => (float) ($row['avg_financial'] ?? 0),
        ];
    }

    /**
     * Recent projects across module(s), ordered by created_at DESC.
     * Returns an array of project rows each with a `type` column.
     */
    public function recent(string $module = 'all', int $limit = 10): array {
        $safe = in_array($module, self::MODULES, true) ? $module : 'all';
        if ($safe !== 'all') {
            $tbl  = self::TABLE[$safe];
            $src  = "SELECT id, project_code, project_title, current_stage, created_at, '$safe' AS type FROM $tbl";
        } else {
            $parts = [];
            foreach (self::TABLE as $key => $tbl) {
                $parts[] = "SELECT id, project_code, project_title, current_stage, created_at, '$key' AS type FROM $tbl";
            }
            $src = implode("\nUNION ALL\n", $parts);
        }
        $limit = max(1, (int) $limit);
        return $this->fetchAll("SELECT * FROM ($src) AS combined ORDER BY created_at DESC LIMIT $limit");
    }

    /**
     * Pending-approval projects (approval_status = 'Pending') across module(s).
     */
    public function pendingApprovals(string $module = 'all', int $limit = 5): array {
        $safe = in_array($module, self::MODULES, true) ? $module : 'all';
        if ($safe !== 'all') {
            $tbl  = self::TABLE[$safe];
            $src  = "SELECT id, project_code, project_title, '$safe' AS type FROM $tbl WHERE approval_status = 'Pending'";
        } else {
            $parts = [];
            foreach (self::TABLE as $key => $tbl) {
                $parts[] = "SELECT id, project_code, project_title, '$key' AS type FROM $tbl WHERE approval_status = 'Pending'";
            }
            $src = implode("\nUNION ALL\n", $parts);
        }
        $limit = max(1, (int) $limit);
        return $this->fetchAll("SELECT * FROM ($src) AS combined LIMIT $limit");
    }

    /**
     * Count of projects at or above a physical progress threshold.
     */
    public function countAboveProgress(string $module, int $threshold): int {
        $safe = in_array($module, ['fspf', 'idp'], true) ? $module : 'fspf';
        $tbl  = self::TABLE[$safe];
        $row  = $this->fetchOne("SELECT COUNT(*) AS c FROM $tbl WHERE physical_progress >= $threshold");
        return (int) ($row['c'] ?? 0);
    }

    /**
     * Top performers by physical_progress (Implementation or Completed stage).
     * Returns: array of project rows each with a `type` column.
     */
    public function topPerformers(string $module = 'all', int $limit = 10): array {
        $safe = in_array($module, self::MODULES, true) ? $module : 'all';
        if ($safe !== 'all') {
            $tbl = self::TABLE[$safe];
            $physCol = in_array($safe, ['fspf', 'idp']) ? 'physical_progress' : '0';
            $finCol  = in_array($safe, ['fspf', 'idp']) ? 'financial_progress' : '0';
            $src = "SELECT project_code, project_title, $physCol AS physical_progress,
                           $finCol AS financial_progress, '$safe' AS type
                    FROM $tbl
                    WHERE current_stage IN ('Implementation','Completed','Delivered','Turned-Over','Operation and Maintenance')";
        } else {
            $parts = [
                "SELECT project_code, project_title, physical_progress, financial_progress, 'fspf' AS type
                 FROM fspf_projects WHERE current_stage IN ('Implementation','Completed')",
                "SELECT project_code, project_title, physical_progress, financial_progress, 'idp' AS type
                 FROM idp_projects WHERE current_stage IN ('Implementation','Completed')",
                "SELECT project_code, project_title, 0 AS physical_progress, 0 AS financial_progress, 'afme' AS type
                 FROM afme_projects WHERE current_stage IN ('Implementation','Delivered','Turned-Over','Operation and Maintenance')",
            ];
            $src = implode("\nUNION ALL\n", $parts);
        }
        $limit = max(1, (int) $limit);
        return $this->fetchAll("SELECT * FROM ($src) AS combined ORDER BY physical_progress DESC LIMIT $limit");
    }

    /**
     * Projects with significant physical vs financial variance.
     * Returns: array of project rows with a `variance` column.
     */
    public function atRiskProjects(int $limit = 10): array {
        $src = "
            SELECT project_code, project_title, physical_progress, financial_progress,
                   (physical_progress - financial_progress) AS variance, 'fspf' AS type
            FROM fspf_projects
            WHERE (physical_progress - financial_progress) < -15
               OR (physical_progress - financial_progress) > 20
            UNION ALL
            SELECT project_code, project_title, physical_progress, financial_progress,
                   (physical_progress - financial_progress) AS variance, 'idp' AS type
            FROM idp_projects
            WHERE (physical_progress - financial_progress) < -15
               OR (physical_progress - financial_progress) > 20
            UNION ALL
            SELECT project_code, project_title, 0 AS physical_progress,
                   0 AS financial_progress, 0 AS variance, 'afme' AS type
            FROM afme_projects
            WHERE current_stage IN ('Implementation','Delivered','Turned-Over')
        ";
        $limit = max(1, (int) $limit);
        return $this->fetchAll("SELECT * FROM ($src) AS combined ORDER BY ABS(variance) DESC LIMIT $limit");
    }

    /**
     * Monthly project creation + average progress for a specific year.
     * Returns: [month_number => ['month' => int, 'count' => int, 'avg_physical' => float, 'avg_financial' => float], ...]
     */
    public function monthlyTrend(int $year): array {
        $src = "
            SELECT created_at, physical_progress, financial_progress
            FROM fspf_projects WHERE YEAR(created_at) = $year
            UNION ALL
            SELECT created_at, physical_progress, financial_progress
            FROM idp_projects WHERE YEAR(created_at) = $year
            UNION ALL
            SELECT created_at, 0 AS physical_progress, 0 AS financial_progress
            FROM afme_projects WHERE YEAR(created_at) = $year
        ";
        $rows = $this->fetchAll(
            "SELECT MONTH(created_at) AS month,
                    COUNT(*) AS count,
                    ROUND(AVG(physical_progress), 1) AS avg_physical,
                    ROUND(AVG(financial_progress), 1) AS avg_financial
             FROM ($src) AS combined
             GROUP BY MONTH(created_at)
             ORDER BY month"
        );
        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row['month']] = [
                'month'        => (int) $row['month'],
                'count'        => (int) $row['count'],
                'avg_physical' => (float) $row['avg_physical'],
                'avg_financial'=> (float) $row['avg_financial'],
            ];
        }
        return $out;
    }

    /**
     * Count of completed/turned-over projects across all modules.
     */
    public function completedCount(): int {
        $row = $this->fetchOne("
            SELECT COUNT(*) AS c FROM (
                SELECT id FROM fspf_projects WHERE current_stage IN ('Completed','Turned-Over')
                UNION ALL
                SELECT id FROM idp_projects WHERE current_stage IN ('Completed','Turned-Over')
                UNION ALL
                SELECT id FROM afme_projects WHERE current_stage IN ('Completed','Turned-Over')
            ) AS combined
        ");
        return (int) ($row['c'] ?? 0);
    }
}
