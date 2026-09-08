<?php
// Project root: frontend/
define('BASE_PATH', dirname(__DIR__));
// Source directory: frontend/src/
define('SRC_PATH', __DIR__);
define('APP_NAME', 'CallMetrics');

// URL base for links and assets (e.g. /CallMetrics_4TO/frontend/).
// Auto-detected from the Apache DocumentRoot so it keeps working if the
// project moves to another folder. Use BASE_URL in href/src, never BASE_PATH
// (BASE_PATH is a filesystem path for require/include only).
$docRoot = str_replace('\\', '/', rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/'));
$appRoot = str_replace('\\', '/', rtrim(dirname(__DIR__), '/'));
if ($docRoot !== '' && strpos($appRoot . '/', $docRoot . '/') === 0) {
    define('BASE_URL', rtrim(substr($appRoot, strlen($docRoot)), '/') . '/');
} else {
    // Fallback for CLI or projects outside the document root.
    define('BASE_URL', '/');
}

// Block direct access to page files: always route through the entry points.
$script = (string)($_SERVER['SCRIPT_NAME'] ?? '');
if (strpos($script, '/src/pages/') !== false) {
    $target = strpos($script, '/auth.php') !== false ? BASE_URL . 'auth.php' : BASE_URL . 'index.php';
    header('Location: ' . $target);
    exit;
}
