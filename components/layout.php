<?php
/**
 * Shared app layout helper for ABED IDM Hub.
 *
 * Usage in page files (after session and logic):
 *   require_once __DIR__ . '/components/layout.php';
 *   renderAppLayout($page_title);
 *   // page-specific content here
 *   renderAppLayoutFooter();
 */

function renderAppLayout($page_title = 'ABED IDM Hub', $extra_head = '') {
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title ?? 'ABED IDM Hub'); ?></title>
    <link rel="stylesheet" href="assets/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <?php
    // Allow pages to inject extra <head> content (styles, meta, etc.)
    if (!empty($extra_head)) echo $extra_head;
    ?>
</head>
<body>
    <?php include __DIR__ . '/sidebar.php'; ?>
    <?php include __DIR__ . '/topbar.php'; ?>
    <?php include __DIR__ . '/navbar.php'; ?>

    <main class="app-main">
    <?php
}

function renderAppLayoutFooter() {
    ?>
    </main>
    <script src="assets/bootstrap/js/bootstrap.bundle.js"></script>
    <script src="assets/js/app-modal.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>
    <?php
}
