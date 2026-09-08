<?php
declare(strict_types=1);

namespace Tests\Unit\Services;

use CallMetrics\Services\AlertEngine;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Unit tests for CallMetrics\Services\AlertEngine.
 *
 * The `compare` method is private. We test it via ReflectionMethod.
 * These tests verify the comparison logic only — no database interaction.
 */
class AlertEngineTest extends TestCase
{
    /**
     * Helper: invoke the private compare method via reflection.
     *
     * We need an instance of AlertEngine, but its constructor calls
     * Database::getInstance(). To avoid a real DB connection we create
     * an instance with a mock or simply let the constructor run if a
     * connection is available. Since this is unit testing the compare
     * logic, we create the object via a simple trick — we suppress the
     * constructor with a callback or just call the method statically.
     *
     * Actually AlertEngine's constructor does `$this->db = Database::getInstance()`.
     * We can use ReflectionClass to instantiate without calling the constructor,
     * then invoke the private method on that instance.
     */
    private function invokeCompare(float $value, string $condition, float $threshold): bool
    {
        $reflection = new \ReflectionClass(AlertEngine::class);

        // Create instance without calling constructor (avoids DB connection)
        $engine = $reflection->newInstanceWithoutConstructor();

        $method = $reflection->getMethod('compare');
        $method->setAccessible(true);

        return $method->invoke($engine, $value, $condition, $threshold);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testCompareMayor(): void
    {
        $this->assertTrue($this->invokeCompare(10.0, 'MAYOR', 5.0));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testCompareMenor(): void
    {
        $this->assertTrue($this->invokeCompare(3.0, 'MENOR', 5.0));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testCompareIgual(): void
    {
        $this->assertTrue($this->invokeCompare(5.0, 'IGUAL', 5.0));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testCompareMayorReturnsFalseWhenEqual(): void
    {
        $this->assertFalse($this->invokeCompare(5.0, 'MAYOR', 5.0));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testCompareMenorReturnsFalseWhenEqual(): void
    {
        $this->assertFalse($this->invokeCompare(5.0, 'MENOR', 5.0));
    }
}
