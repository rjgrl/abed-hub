<?php
/**
 * ProjectRepository - consolidated data access on the refactored schema.
 */
class ProjectRepository {

    private mysqli $conn;
    private const MODULES = ['all', 'fspf', 'idp', 'afme'];

    public function __construct(mysqli $conn) {
        $this->conn = $conn;
    }

    private function fetchOne(string $sql): array {
        $res = $this->conn->query($sql);
        if (!$res) {
            return [];
        }
        return $res->fetch_assoc() ?? [];
    }

    private function fetchAll(string $sql): array {
        $res = $this->conn->query($sql);
        if (!$res) {
            return [];
        }
        return $res->fetch_all(MYSQLI_ASSOC);
    }

    private function normalizeModule(string $module): string {
        return in_array($module, self::MODULES, true) ? $module : 'all';
    }

    /**
     * WHERE clause for catalog queries (excludes employee submissions pending Super Admin approval).
     */
    private function catalogWhereClause(string $module): string {
        $safe = $this->normalizeModule($module);
        $parts = ["approval_status = 'Approved'"];
        if ($safe !== 'all') {
            $parts[] = "project_type = '{$safe}'";
        }
        return ' WHERE ' . implode(' AND ', $parts);
    }

    public function counts(string $module = 'all'): array {
        $fspf = (int) ($this->fetchOne("SELECT COUNT(*) AS c FROM projects WHERE project_type = 'fspf' AND approval_status = 'Approved'")['c'] ?? 0);
        $idp  = (int) ($this->fetchOne("SELECT COUNT(*) AS c FROM projects WHERE project_type = 'idp' AND approval_status = 'Approved'")['c'] ?? 0);
        $afme = (int) ($this->fetchOne("SELECT COUNT(*) AS c FROM projects WHERE project_type = 'afme' AND approval_status = 'Approved'")['c'] ?? 0);

        return [
            'fspf' => $fspf,
            'idp' => $idp,
            'afme' => $afme,
            'total' => match ($this->normalizeModule($module)) {
                'fspf' => $fspf,
                'idp' => $idp,
                'afme' => $afme,
                default => $fspf + $idp + $afme,
            },
        ];
    }

    public function stageDistribution(string $module = 'all'): array {
        $where = $this->catalogWhereClause($module);
        $rows = $this->fetchAll(
            "SELECT current_stage, COUNT(*) AS cnt
             FROM projects{$where}
             GROUP BY current_stage
             ORDER BY cnt DESC"
        );
        $out = [];
        foreach ($rows as $row) {
            $out[$row['current_stage']] = (int) $row['cnt'];
        }
        return $out;
    }

    public function stageDistributionByYear(int $year, string $module = 'all'): array {
        $where = " WHERE YEAR(created_at) = {$year} AND approval_status = 'Approved'";
        $safe = $this->normalizeModule($module);
        if ($safe !== 'all') {
            $where .= " AND project_type = '{$safe}'";
        }
        $rows = $this->fetchAll(
            "SELECT current_stage, COUNT(*) AS cnt
             FROM projects{$where}
             GROUP BY current_stage"
        );
        $out = [];
        foreach ($rows as $row) {
            $out[$row['current_stage']] = (int) $row['cnt'];
        }
        return $out;
    }

    public function financialSummary(string $module = 'all'): array {
        $where = $this->catalogWhereClause($module);
        $row = $this->fetchOne(
            "SELECT SUM(proposed_amount) AS total_proposed,
                    SUM(allocated_amount) AS total_allocated
             FROM projects{$where}"
        );
        return [
            'total_proposed' => (float) ($row['total_proposed'] ?? 0),
            'total_allocated' => (float) ($row['total_allocated'] ?? 0),
        ];
    }

    public function avgProgress(string $module = 'all'): array {
        $where = $this->catalogWhereClause($module);
        $row = $this->fetchOne(
            "SELECT ROUND(AVG(physical_progress), 1) AS avg_physical,
                    ROUND(AVG(financial_progress), 1) AS avg_financial
             FROM projects{$where}"
        );
        return [
            'avg_physical' => (float) ($row['avg_physical'] ?? 0),
            'avg_financial' => (float) ($row['avg_financial'] ?? 0),
        ];
    }

    public function recent(string $module = 'all', int $limit = 10): array {
        $safe = $this->normalizeModule($module);
        $where = " WHERE approval_status IN ('Pending', 'Approved') AND (status IS NULL OR status <> 'Archived')";
        if ($safe !== 'all') {
            $where .= " AND project_type = '{$safe}'";
        }
        $limit = max(1, (int) $limit);
        return $this->fetchAll(
            "SELECT id, project_code, title AS project_title, current_stage, created_at, project_type AS type, approval_status
             FROM projects{$where}
             ORDER BY created_at DESC
             LIMIT {$limit}"
        );
    }

    public function recentDeleted(string $module = 'all', int $limit = 10): array {
        $safe = $this->normalizeModule($module);
        $where = " WHERE status = 'Archived'";
        if ($safe !== 'all') {
            $where .= " AND project_type = '{$safe}'";
        }
        $limit = max(1, (int) $limit);
        return $this->fetchAll(
            "SELECT id, project_code, title AS project_title, current_stage, updated_at, project_type AS type
             FROM projects{$where}
             ORDER BY updated_at DESC
             LIMIT {$limit}"
        );
    }

    public function pendingApprovals(string $module = 'all', int $limit = 5): array {
        $safe = $this->normalizeModule($module);
        $where = " WHERE approval_status = 'Pending'";
        if ($safe !== 'all') {
            $where .= " AND project_type = '{$safe}'";
        }
        $limit = max(1, (int) $limit);
        return $this->fetchAll(
            "SELECT id, project_code, title AS project_title, project_type AS type
             FROM projects{$where}
             LIMIT {$limit}"
        );
    }

    public function pendingByUser(int $userId, string $module = 'all', int $limit = 5): array {
        $safeUserId = max(0, $userId);
        $safe = $this->normalizeModule($module);
        $where = " WHERE approval_status = 'Pending' AND user_id = {$safeUserId}";
        if ($safe !== 'all') {
            $where .= " AND project_type = '{$safe}'";
        }
        $limit = max(1, (int) $limit);
        return $this->fetchAll(
            "SELECT id, project_code, title AS project_title, project_type AS type
             FROM projects{$where}
             ORDER BY created_at DESC
             LIMIT {$limit}"
        );
    }

    public function countAboveProgress(string $module, int $threshold): int {
        $safe = in_array($module, ['fspf', 'idp', 'afme'], true) ? $module : 'fspf';
        $row = $this->fetchOne(
            "SELECT COUNT(*) AS c
             FROM projects
             WHERE project_type = '{$safe}' AND approval_status = 'Approved' AND physical_progress >= {$threshold}"
        );
        return (int) ($row['c'] ?? 0);
    }

    public function topPerformers(string $module = 'all', int $limit = 10): array {
        $safe = $this->normalizeModule($module);
        $filters = [
            "approval_status = 'Approved'",
            "current_stage IN ('Implementation','Completed','Delivered','Turned-Over','Operation and Maintenance')",
        ];
        if ($safe !== 'all') {
            $filters[] = "project_type = '{$safe}'";
        }
        $where = ' WHERE ' . implode(' AND ', $filters);
        $limit = max(1, (int) $limit);
        return $this->fetchAll(
            "SELECT project_code,
                    title AS project_title,
                    physical_progress,
                    financial_progress,
                    project_type AS type
             FROM projects{$where}
             ORDER BY physical_progress DESC
             LIMIT {$limit}"
        );
    }

    public function atRiskProjects(int $limit = 10): array {
        $limit = max(1, (int) $limit);
        return $this->fetchAll(
            "SELECT project_code,
                    title AS project_title,
                    physical_progress,
                    financial_progress,
                    (physical_progress - financial_progress) AS variance,
                    project_type AS type
             FROM projects
             WHERE approval_status = 'Approved'
               AND ((physical_progress - financial_progress) < -15
                OR (physical_progress - financial_progress) > 20)
             ORDER BY ABS(variance) DESC
             LIMIT {$limit}"
        );
    }

    public function monthlyTrend(int $year, string $module = 'all'): array {
        $safe = $this->normalizeModule($module);
        $year = max(2000, min(2100, $year));
        $typeSql = '';
        if ($safe !== 'all') {
            $typeSql = " AND project_type = '{$safe}'";
        }
        $rows = $this->fetchAll(
            "SELECT MONTH(created_at) AS month,
                    COUNT(*) AS count,
                    ROUND(AVG(physical_progress), 1) AS avg_physical,
                    ROUND(AVG(financial_progress), 1) AS avg_financial
             FROM projects
             WHERE YEAR(created_at) = {$year}
               AND approval_status = 'Approved'
               AND (status IS NULL OR status <> 'Archived')
               {$typeSql}
             GROUP BY MONTH(created_at)
             ORDER BY month"
        );
        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row['month']] = [
                'month' => (int) $row['month'],
                'count' => (int) $row['count'],
                'avg_physical' => (float) $row['avg_physical'],
                'avg_financial' => (float) $row['avg_financial'],
            ];
        }
        return $out;
    }

    public function completedCount(): int {
        $row = $this->fetchOne(
            "SELECT COUNT(*) AS c
             FROM projects
             WHERE approval_status = 'Approved' AND current_stage IN ('Completed', 'Turned-Over')"
        );
        return (int) ($row['c'] ?? 0);
    }
}
