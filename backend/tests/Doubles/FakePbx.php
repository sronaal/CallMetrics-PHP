<?php
declare(strict_types=1);

namespace CallMetrics\Models;

/**
 * Fake Pbx for unit testing — replaces the real Pbx model via autoloader override.
 * Loaded via require_once in separate-process tests BEFORE the real class is autoloaded.
 */
class Pbx
{
    private static ?array $findByAgenteIdResult = null;

    public static function setFindByAgenteIdResult(?array $result): void
    {
        self::$findByAgenteIdResult = $result;
    }

    public static function findByAgenteId(string $agenteId): ?array
    {
        return self::$findByAgenteIdResult;
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function findByToken(string $token): ?array
    {
        return null;
    }
}
