<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use CallMetrics\Core\Config;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for CallMetrics\Core\Config.
 *
 * Config reads from $_ENV (populated by .env in bootstrap).
 * Tests cover defaults, env value retrieval, and specific accessor methods.
 */
class ConfigTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function testGetReturnsDefault(): void
    {
        $value = Config::get('NONEXISTENT_KEY_XYZ', 'fallback');

        $this->assertSame('fallback', $value);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testGetReturnsEnvValue(): void
    {
        // APP_ENV is set to 'development' in .env
        $value = Config::get('APP_ENV');

        $this->assertSame('development', $value);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testJwtSecretReturnsString(): void
    {
        $secret = Config::jwtSecret();

        $this->assertIsString($secret);
        $this->assertNotEmpty($secret);
        $this->assertGreaterThanOrEqual(32, strlen($secret));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testJwtAccessExpiryReturnsPositiveInt(): void
    {
        $expiry = Config::jwtAccessExpiry();

        $this->assertIsInt($expiry);
        $this->assertGreaterThan(0, $expiry);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testJwtRefreshExpiryReturnsPositiveInt(): void
    {
        $expiry = Config::jwtRefreshExpiry();

        $this->assertIsInt($expiry);
        $this->assertGreaterThan(0, $expiry);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testIsDevDefault(): void
    {
        // .env sets APP_ENV=development
        $this->assertTrue(Config::isDev());
    }
}
