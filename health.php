<?php
/**
 * Lightweight deployment check. Open in browser: /health.php
 * Remove or protect this file in production if you prefer not to expose PHP version.
 */
header('Content-Type: text/plain; charset=UTF-8');

echo "PHP version: " . PHP_VERSION . " (required: 8.0+)\n";
echo 'PHP 8+ OK: ' . (PHP_VERSION_ID >= 80000 ? 'yes' : 'NO — upgrade PHP') . "\n";

echo 'mysqli extension: ' . (extension_loaded('mysqli') ? 'loaded' : 'MISSING') . "\n";

echo "\nOptional DB check (set TRY_DB=1 as query string to run): ";
if (!isset($_GET['TRY_DB']) || (string) $_GET['TRY_DB'] !== '1') {
    echo "skipped (add ?TRY_DB=1)\n";
    exit;
}

require_once __DIR__ . '/config/database.php';

echo "\nDatabase: connected OK as " . DB_USER . '@' . DB_HOST . '/' . DB_NAME . "\n";
