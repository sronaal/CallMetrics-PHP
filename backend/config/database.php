<?php
declare(strict_types=1);

return [
    'host'    => $_ENV['DB_HOST'] ?? '127.0.0.1',
    'port'    => (int) ($_ENV['DB_PORT'] ?? 33060),
    'name'    => $_ENV['DB_NAME'] ?? 'callmetrics',
    'user'    => $_ENV['DB_USER'] ?? 'root',
    'pass'    => $_ENV['DB_PASS'] ?? '',
    'charset' => 'utf8mb4',
];
