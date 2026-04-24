<?php
require_once __DIR__ . '/config/database.php';

requireLogin();

if (isSuperAdmin()) {
    require_once __DIR__ . '/admin-dashboard.php';
    exit;
}

require_once __DIR__ . '/dashboard-enhanced.php';
