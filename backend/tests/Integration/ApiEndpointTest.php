<?php
declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Integration tests that hit the live backend at http://localhost:8080.
 *
 * Prerequisites:
 *   - PHP built-in server running: php -S localhost:8080 -t public
 *   - MySQL running with the callmetrics database seeded
 *
 * The setUp() checks server reachability. Individual tests attempt login
 * and skip themselves if the server is not available.
 */
class ApiEndpointTest extends TestCase
{
    private string $baseUrl;

    protected function setUp(): void
    {
        $this->baseUrl = TEST_API_BASE;

        // Verify the server is reachable
        $ch = curl_init($this->baseUrl . '/api/auth/login');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 5,
        ]);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 0) {
            $this->markTestSkipped('Backend server not reachable at ' . $this->baseUrl);
        }
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function post(string $path, array $data, ?string $token = null): array
    {
        $ch = curl_init($this->baseUrl . $path);
        $headers = ['Content-Type: application/json'];
        if ($token !== null) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);

        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'status' => $code,
            'body'   => json_decode($body ?: '{}', true),
        ];
    }

    private function get(string $path, ?string $token = null): array
    {
        $ch = curl_init($this->baseUrl . $path);
        $headers = ['Content-Type: application/json'];
        if ($token !== null) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 10,
        ]);

        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'status' => $code,
            'body'   => json_decode($body ?: '{}', true),
        ];
    }

    /**
     * Login helper — returns [accessToken, refreshToken] or marks test skipped.
     */
    private function loginOrFail(string $email, string $password): array
    {
        $result = $this->post('/api/auth/login', [
            'email'    => $email,
            'password' => $password,
        ]);

        $this->assertSame(200, $result['status'], 'Login failed: ' . json_encode($result['body']));

        $data = $result['body']['data'] ?? null;
        $this->assertNotNull($data, 'Login response missing data');
        $this->assertArrayHasKey('accessToken', $data);
        $this->assertArrayHasKey('refreshToken', $data);

        return [
            $data['accessToken'],
            $data['refreshToken'],
        ];
    }

    // ---------------------------------------------------------------
    // Auth tests
    // ---------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function testLoginEndpoint(): void
    {
        $result = $this->post('/api/auth/login', [
            'email'    => 'admin@callmetrics.com',
            'password' => 'admin123',
        ]);

        $this->assertSame(200, $result['status']);
        $this->assertTrue($result['body']['success'] ?? false);

        $data = $result['body']['data'];
        $this->assertArrayHasKey('accessToken', $data);
        $this->assertArrayHasKey('refreshToken', $data);
        $this->assertNotEmpty($data['accessToken']);
        $this->assertNotEmpty($data['refreshToken']);

        // User info is also present
        $this->assertArrayHasKey('user', $data);
        $this->assertSame('admin@callmetrics.com', $data['user']['email']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testLoginInvalidCredentials(): void
    {
        $result = $this->post('/api/auth/login', [
            'email'    => 'admin@callmetrics.com',
            'password' => 'wrong_password_xyz',
        ]);

        $this->assertSame(401, $result['status']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testRefreshEndpoint(): void
    {
        [, $refreshToken] = $this->loginOrFail('admin@callmetrics.com', 'admin123');

        $result = $this->post('/api/auth/refresh', [
            'refreshToken' => $refreshToken,
        ]);

        $this->assertSame(200, $result['status']);
        $data = $result['body']['data'];
        $this->assertArrayHasKey('accessToken', $data);
        $this->assertArrayHasKey('refreshToken', $data);
        $this->assertNotEmpty($data['accessToken']);
        $this->assertNotEmpty($data['refreshToken']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testRefreshWithRevokedToken(): void
    {
        [, $refreshToken] = $this->loginOrFail('admin@callmetrics.com', 'admin123');

        // First refresh should succeed (and revoke the old token)
        $first = $this->post('/api/auth/refresh', [
            'refreshToken' => $refreshToken,
        ]);
        $this->assertSame(200, $first['status']);

        // Second refresh with the same (now revoked) token should fail
        $second = $this->post('/api/auth/refresh', [
            'refreshToken' => $refreshToken,
        ]);
        $this->assertNotSame(200, $second['status']);
    }

    // ---------------------------------------------------------------
    // Protected endpoints
    // ---------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function testProtectedEndpointWithoutToken(): void
    {
        $result = $this->get('/api/tenants');

        $this->assertSame(401, $result['status']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testProtectedEndpointWithToken(): void
    {
        [$accessToken] = $this->loginOrFail('admin@callmetrics.com', 'admin123');

        $result = $this->get('/api/tenants', $accessToken);

        $this->assertSame(200, $result['status']);
    }

    // ---------------------------------------------------------------
    // Dashboard
    // ---------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function testDashboardSummary(): void
    {
        [$accessToken] = $this->loginOrFail('admin@callmetrics.com', 'admin123');

        $result = $this->get('/api/dashboard/summary', $accessToken);

        $this->assertSame(200, $result['status']);
        $this->assertIsArray($result['body']['data'] ?? $result['body']);
    }

    // ---------------------------------------------------------------
    // Tenants CRUD
    // ---------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function testCreateTenant(): void
    {
        [$accessToken] = $this->loginOrFail('admin@callmetrics.com', 'admin123');

        $result = $this->post('/api/tenants', [
            'nombre' => 'Test Tenant ' . uniqid(),
            'nit'    => substr(uniqid('N', true), 0, 20),
            'email'  => 'tenant_' . uniqid() . '@test.com',
        ], $accessToken);

        $this->assertContains($result['status'], [200, 201]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testGetTenants(): void
    {
        [$accessToken] = $this->loginOrFail('admin@callmetrics.com', 'admin123');

        $result = $this->get('/api/tenants', $accessToken);

        $this->assertSame(200, $result['status']);
        $this->assertIsArray($result['body']['data'] ?? $result['body']);
    }

    // ---------------------------------------------------------------
    // Health / heartbeat (no auth required per routes.php)
    // ---------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function testHealthEndpoint(): void
    {
        // The heartbeat route (auth: false) still requires X-Agent-Token header
        // via the controller's own authenticateAgent(). Without it → 401.
        // With a valid agent token → 200. We verify the route responds at all.
        $result = $this->post('/api/agent/heartbeat', [
            'agent_id' => 'test-agent',
            'estado'   => 'ONLINE',
        ]);

        // Without X-Agent-Token we expect 401; with a valid token we'd get 200.
        // Either way, the server responded — the route exists and is reachable.
        $this->assertContains($result['status'], [200, 401]);
    }
}
