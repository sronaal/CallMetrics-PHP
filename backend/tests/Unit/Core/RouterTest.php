<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use CallMetrics\Core\Router;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for CallMetrics\Core\Router.
 *
 * Covers exact match, method-not-allowed, trailing-slash normalisation,
 * dynamic param extraction, no-match null, return structure, multiple
 * methods, and routes with multiple params.
 */
class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router([
            ['GET',  '/api/tenants',              'TenantController@index',  true,  'ADMIN_TENANT'],
            ['POST', '/api/tenants',              'TenantController@create', true,  'ADMIN_TENANT'],
            ['GET',  '/api/tenants/{id}',         'TenantController@show',   true,  'ADMIN_TENANT'],
            ['PUT',  '/api/tenants/{id}',         'TenantController@update', true,  'ADMIN_TENANT'],
            ['DELETE', '/api/tenants/{id}',       'TenantController@delete', true,  'SUPER_ADMIN'],
            ['GET',  '/api/calls/{tenantId}/ext/{ext}', 'CallController@byExt', true, 'SUPERVISOR'],
            ['POST', '/api/auth/login',           'AuthController@login',    false, null],
            ['GET',  '/api/dashboard/summary',    'DashboardController@summary', true, 'OPERADOR'],
        ]);
    }

    // ---------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function testExactMatch(): void
    {
        $result = $this->router->match('GET', '/api/tenants');

        $this->assertNotNull($result);
        $this->assertSame('TenantController@index', $result['handler']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testMethodNotAllowed(): void
    {
        // The route exists for GET but not POST with this specific check:
        // Actually /api/tenants exists for both GET and POST.
        // Let's test a route that only exists for GET.
        $result = $this->router->match('POST', '/api/dashboard/summary');

        // dashboard/summary only exists for GET → should return null
        $this->assertNull($result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testTrailingSlashNormalized(): void
    {
        $result = $this->router->match('GET', '/api/tenants/');

        $this->assertNotNull($result);
        $this->assertSame('TenantController@index', $result['handler']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testDynamicParamExtraction(): void
    {
        $result = $this->router->match('GET', '/api/tenants/42');

        $this->assertNotNull($result);
        $this->assertSame('TenantController@show', $result['handler']);
        $this->assertArrayHasKey('id', $result['params']);
        $this->assertSame('42', $result['params']['id']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testNoMatchReturnsNull(): void
    {
        $result = $this->router->match('GET', '/api/nonexistent');

        $this->assertNull($result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testReturnStructure(): void
    {
        $result = $this->router->match('POST', '/api/auth/login');

        $this->assertNotNull($result);
        $this->assertArrayHasKey('handler', $result);
        $this->assertArrayHasKey('auth', $result);
        $this->assertArrayHasKey('role', $result);
        $this->assertArrayHasKey('params', $result);

        $this->assertSame('AuthController@login', $result['handler']);
        $this->assertFalse($result['auth']);
        $this->assertNull($result['role']);
        $this->assertIsArray($result['params']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testMultipleRoutesDifferentMethods(): void
    {
        $get  = $this->router->match('GET', '/api/tenants');
        $post = $this->router->match('POST', '/api/tenants');

        $this->assertNotNull($get);
        $this->assertNotNull($post);
        $this->assertNotSame($get['handler'], $post['handler']);
        $this->assertSame('TenantController@index', $get['handler']);
        $this->assertSame('TenantController@create', $post['handler']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testRouteWithMultipleParams(): void
    {
        $result = $this->router->match('GET', '/api/calls/5/ext/1001');

        $this->assertNotNull($result);
        $this->assertSame('CallController@byExt', $result['handler']);
        $this->assertSame('5', $result['params']['tenantId']);
        $this->assertSame('1001', $result['params']['ext']);
    }
}
