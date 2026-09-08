<?php
declare(strict_types=1);

namespace Tests\Unit\Middleware;

use CallMetrics\Http\Middleware\AuthMiddleware;
use PHPUnit\Framework\ReflectionException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Unit tests for CallMetrics\Http\Middleware\AuthMiddleware.
 *
 * The `roleHasAccess` method is private, so we test it via reflection.
 * Also tests the ROLE_HIERARCHY constant directly.
 */
class AuthMiddlewareTest extends TestCase
{
    private static array $roleHierarchy;

    public static function setUpBeforeClass(): void
    {
        $reflection = new ReflectionClass(AuthMiddleware::class);
        $constant = $reflection->getConstant('ROLE_HIERARCHY');
        self::$roleHierarchy = $constant;
    }

    /**
     * Helper: invoke the private roleHasAccess method via reflection.
     */
    private function invokeRoleHasAccess(string $userRole, string $requiredRole): bool
    {
        $reflection = new ReflectionClass(AuthMiddleware::class);
        $method = $reflection->getMethod('roleHasAccess');
        $method->setAccessible(true);

        return $method->invoke(null, $userRole, $requiredRole);
    }

    // ---------------------------------------------------------------
    // Hierarchy constant validation
    // ---------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function testRoleHierarchy(): void
    {
        $h = self::$roleHierarchy;

        $this->assertSame(4, $h['SUPER_ADMIN']);
        $this->assertSame(3, $h['ADMIN_TENANT']);
        $this->assertSame(2, $h['SUPERVISOR']);
        $this->assertSame(1, $h['OPERADOR']);

        // Verify ordering
        $this->assertGreaterThan($h['ADMIN_TENANT'], $h['SUPER_ADMIN']);
        $this->assertGreaterThan($h['SUPERVISOR'], $h['ADMIN_TENANT']);
        $this->assertGreaterThan($h['OPERADOR'], $h['SUPERVISOR']);
    }

    // ---------------------------------------------------------------
    // Access checks
    // ---------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function testSuperAdminHasAccessToAllRoles(): void
    {
        $this->assertTrue($this->invokeRoleHasAccess('SUPER_ADMIN', 'SUPER_ADMIN'));
        $this->assertTrue($this->invokeRoleHasAccess('SUPER_ADMIN', 'ADMIN_TENANT'));
        $this->assertTrue($this->invokeRoleHasAccess('SUPER_ADMIN', 'SUPERVISOR'));
        $this->assertTrue($this->invokeRoleHasAccess('SUPER_ADMIN', 'OPERADOR'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testOperadorOnlyHasAccessToOperador(): void
    {
        $this->assertTrue($this->invokeRoleHasAccess('OPERADOR', 'OPERADOR'));

        $this->assertFalse($this->invokeRoleHasAccess('OPERADOR', 'SUPERVISOR'));
        $this->assertFalse($this->invokeRoleHasAccess('OPERADOR', 'ADMIN_TENANT'));
        $this->assertFalse($this->invokeRoleHasAccess('OPERADOR', 'SUPER_ADMIN'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testSupervisorHasAccessToSupervisorAndOperador(): void
    {
        $this->assertTrue($this->invokeRoleHasAccess('SUPERVISOR', 'OPERADOR'));
        $this->assertTrue($this->invokeRoleHasAccess('SUPERVISOR', 'SUPERVISOR'));
        $this->assertFalse($this->invokeRoleHasAccess('SUPERVISOR', 'ADMIN_TENANT'));
        $this->assertFalse($this->invokeRoleHasAccess('SUPERVISOR', 'SUPER_ADMIN'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testAdminTenantHasAccessToAdminAndBelow(): void
    {
        $this->assertTrue($this->invokeRoleHasAccess('ADMIN_TENANT', 'OPERADOR'));
        $this->assertTrue($this->invokeRoleHasAccess('ADMIN_TENANT', 'SUPERVISOR'));
        $this->assertTrue($this->invokeRoleHasAccess('ADMIN_TENANT', 'ADMIN_TENANT'));
        $this->assertFalse($this->invokeRoleHasAccess('ADMIN_TENANT', 'SUPER_ADMIN'));
    }
}
