<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use CallMetrics\Core\TenantContext;
use CallMetrics\Core\Request;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for CallMetrics\Core\TenantContext.
 *
 * TenantContext is a static state holder, so each test must clear state in tearDown.
 * Covers set/get, clear, userId, role checks, and tenant resolution logic.
 */
class TenantContextTest extends TestCase
{
    protected function tearDown(): void
    {
        TenantContext::clear();
    }

    // ---------------------------------------------------------------
    // Basic set / get
    // ---------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function testSetAndGet(): void
    {
        TenantContext::set(10, 1, 'OPERADOR');

        $this->assertSame(10, TenantContext::get());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testGetReturnsNullAfterClear(): void
    {
        TenantContext::set(5, 1, 'OPERADOR');
        TenantContext::clear();

        $this->assertNull(TenantContext::get());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testGetUserId(): void
    {
        TenantContext::set(1, 42, 'OPERADOR');

        $this->assertSame(42, TenantContext::getUserId());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testGetRole(): void
    {
        TenantContext::set(1, 1, 'SUPERVISOR');

        $this->assertSame('SUPERVISOR', TenantContext::getRole());
    }

    // ---------------------------------------------------------------
    // Role checks
    // ---------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function testIsSuperAdmin(): void
    {
        TenantContext::set(0, 1, 'SUPER_ADMIN');

        $this->assertTrue(TenantContext::isSuperAdmin());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testIsAdminIncludesSuperAdminAndAdminTenant(): void
    {
        TenantContext::set(0, 1, 'SUPER_ADMIN');
        $this->assertTrue(TenantContext::isAdmin());

        TenantContext::clear();
        TenantContext::set(5, 2, 'ADMIN_TENANT');
        $this->assertTrue(TenantContext::isAdmin());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testIsAdminReturnsFalseForSupervisor(): void
    {
        TenantContext::set(1, 1, 'SUPERVISOR');

        $this->assertFalse(TenantContext::isAdmin());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testClearResetsAll(): void
    {
        TenantContext::set(10, 42, 'ADMIN_TENANT');
        TenantContext::clear();

        $this->assertNull(TenantContext::get());
        $this->assertNull(TenantContext::getUserId());
        $this->assertNull(TenantContext::getRole());
        $this->assertFalse(TenantContext::isSuperAdmin());
        $this->assertFalse(TenantContext::isAdmin());
    }

    // ---------------------------------------------------------------
    // resolveTenantId
    // ---------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function testResolveTenantIdForNormalUser(): void
    {
        // Normal user with tid > 0 → always returns JWT tid
        TenantContext::set(7, 1, 'OPERADOR');
        $request = new Request('GET', '/api/test', [], [], []);

        $this->assertSame(7, TenantContext::resolveTenantId($request));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testResolveTenantIdForSuperAdminWithBodyTenantId(): void
    {
        // SUPER_ADMIN (tid=0) with body tenant_id
        TenantContext::set(0, 1, 'SUPER_ADMIN');
        $request = new Request('POST', '/api/test', [], ['tenant_id' => 12], []);

        $this->assertSame(12, TenantContext::resolveTenantId($request));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testResolveTenantIdForSuperAdminNoTenantIdReturnsNull(): void
    {
        // SUPER_ADMIN (tid=0) without any tenant_id → null
        TenantContext::set(0, 1, 'SUPER_ADMIN');
        $request = new Request('GET', '/api/test', [], [], []);

        $this->assertNull(TenantContext::resolveTenantId($request));
    }

    // ---------------------------------------------------------------
    // resolveTenantIdForWrite
    // ---------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function testResolveTenantIdForWriteNormalUser(): void
    {
        TenantContext::set(7, 1, 'OPERADOR');
        $request = new Request('POST', '/api/test', [], [], []);

        $this->assertSame(7, TenantContext::resolveTenantIdForWrite($request));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testResolveTenantIdForWriteSuperAdminRequiresBodyTenantId(): void
    {
        TenantContext::set(0, 1, 'SUPER_ADMIN');
        $request = new Request('POST', '/api/test', [], ['tenant_id' => 5], []);

        $this->assertSame(5, TenantContext::resolveTenantIdForWrite($request));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testResolveTenantIdForWriteSuperAdminNoTenantIdReturnsNull(): void
    {
        TenantContext::set(0, 1, 'SUPER_ADMIN');
        $request = new Request('POST', '/api/test', [], [], []);

        $this->assertNull(TenantContext::resolveTenantIdForWrite($request));
    }
}
