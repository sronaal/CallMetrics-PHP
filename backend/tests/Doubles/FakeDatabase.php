<?php
declare(strict_types=1);

namespace CallMetrics\Core;

/**
 * Fake Database for unit testing — replaces the real Database singleton via autoloader override.
 * Loaded via require_once in separate-process tests BEFORE the real class is autoloaded.
 */
class Database
{
    private static ?self $instance = null;

    /** @var array<int, array{sql: string, params: array}> */
    public array $insertCalls = [];

    /** @var array<int, array{sql: string, params: array}> */
    public array $executeCalls = [];

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    public function insert(string $sql, array $params = []): int
    {
        $this->insertCalls[] = ['sql' => $sql, 'params' => $params];
        return 1;
    }

    public function execute(string $sql, array $params = []): int
    {
        $this->executeCalls[] = ['sql' => $sql, 'params' => $params];
        return 1;
    }

    public function fetchOne(string $sql, array $params = []): ?array
    {
        return null;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        return [];
    }

    public function count(string $sql, array $params = []): int
    {
        return 0;
    }
}
