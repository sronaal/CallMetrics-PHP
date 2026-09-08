<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use CallMetrics\Core\Request;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for CallMetrics\Core\Request.
 *
 * Covers constructor storage, accessor methods, header case-insensitivity,
 * input defaults, route params, and all() merge behaviour.
 */
class RequestTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function testConstructorStoresValues(): void
    {
        $request = new Request('POST', '/api/test', ['foo' => 'bar'], ['name' => 'X'], ['content-type' => 'application/json']);

        $this->assertSame('POST', $request->method());
        $this->assertSame('/api/test', $request->path());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testMethodAccessor(): void
    {
        $get  = new Request('GET', '/', [], [], []);
        $post = new Request('POST', '/', [], [], []);

        $this->assertSame('GET', $get->method());
        $this->assertSame('POST', $post->method());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testPathAccessor(): void
    {
        $request = new Request('GET', '/api/tenants/7', [], [], []);

        $this->assertSame('/api/tenants/7', $request->path());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testQueryParamAccessor(): void
    {
        $query = ['page' => '2', 'limit' => '25'];
        $request = new Request('GET', '/', $query, [], []);

        $this->assertSame($query, $request->query());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testBodyAccessor(): void
    {
        $body = ['nombre' => 'Tenant Uno', 'email' => 'a@b.com'];
        $request = new Request('POST', '/', [], $body, []);

        $this->assertSame($body, $request->body());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testHeaderCaseInsensitive(): void
    {
        $request = new Request('GET', '/', [], [], [
            'content-type' => 'application/json',
            'authorization' => 'Bearer xyz',
        ]);

        // Access via lowercase
        $this->assertSame('application/json', $request->header('content-type'));
        $this->assertSame('Bearer xyz', $request->header('authorization'));

        // Access via mixed case → should still match (lowercased internally)
        $this->assertSame('application/json', $request->header('Content-Type'));
        $this->assertSame('Bearer xyz', $request->header('AUTHORIZATION'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testInputReturnsDefault(): void
    {
        $request = new Request('POST', '/', [], ['name' => 'X'], []);

        $this->assertNull($request->input('missing'));
        $this->assertSame('fallback', $request->input('missing', 'fallback'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testInputReturnsValue(): void
    {
        $request = new Request('POST', '/', [], ['email' => 'test@co.com'], []);

        $this->assertSame('test@co.com', $request->input('email'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testParamFromSetRouteParams(): void
    {
        $request = new Request('GET', '/api/tenants/5', [], [], []);
        $request->setRouteParams(['id' => '5']);

        $this->assertSame('5', $request->param('id'));
        $this->assertNull($request->param('missing'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testAllMergesQueryAndBody(): void
    {
        $query = ['page' => '1'];
        $body  = ['name' => 'Test'];
        $request = new Request('POST', '/api/test', $query, $body, []);

        $all = $request->all();
        $this->assertSame('1', $all['page']);
        $this->assertSame('Test', $all['name']);
    }
}
