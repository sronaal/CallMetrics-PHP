<?php
declare(strict_types=1);

/**
 * PHPUnit bootstrap file.
 *
 * Loads the Composer autoloader and the .env file from the project root
 * so that $_ENV values are available during tests.
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Load .env from the project root (one directory above tests/)
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Test constants
define('TEST_DB_NAME', $_ENV['DB_NAME'] ?? 'callmetrics');
define('TEST_DB_USER', $_ENV['DB_USER'] ?? 'root');
define('TEST_DB_PASS', $_ENV['DB_PASS'] ?? '');
define('TEST_JWT_SECRET', $_ENV['JWT_SECRET'] ?? 'tu_clave_secreta_minimo_32_caracteres_aqui_2026');
define('TEST_APP_URL', $_ENV['APP_URL'] ?? 'http://localhost');
define('TEST_API_BASE', TEST_APP_URL . ':8080');
