<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use CallMetrics\Core\JwtHelper;
use PHPUnit\Framework\TestCase;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Unit tests for CallMetrics\Core\JwtHelper.
 *
 * Covers:
 *   - Access token generation and payload content
 *   - Refresh token generation, payload, and uniqueness (jti nonce)
 *   - SHA-256 hash consistency and divergence
 *   - Decode invalid / expired tokens throws exceptions
 */
class JwtHelperTest extends TestCase
{
    private static string $secret;
    private static string $algo = 'HS256';

    public static function setUpBeforeClass(): void
    {
        self::$secret = TEST_JWT_SECRET;
    }

    // ---------------------------------------------------------------
    // Access tokens
    // ---------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function testGenerateAccessTokenReturnsString(): void
    {
        $token = JwtHelper::generateAccessToken(1, 1, 'OPERADOR', 'test@example.com');

        $this->assertIsString($token);
        $this->assertNotEmpty($token);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testAccessTokenContainsCorrectPayload(): void
    {
        $userId   = 42;
        $tenantId = 7;
        $role     = 'ADMIN_TENANT';
        $email    = 'admin@corp.com';

        $token = JwtHelper::generateAccessToken($userId, $tenantId, $role, $email);
        $payload = JWT::decode($token, new Key(self::$secret, self::$algo));

        $this->assertSame($userId, (int) $payload->sub);
        $this->assertSame($tenantId, (int) $payload->tid);
        $this->assertSame($role, $payload->role);
        $this->assertSame($email, $payload->email);
        $this->assertSame('access', $payload->type);
        $this->assertSame('callmetrics', $payload->iss);
        $this->assertObjectHasProperty('iat', $payload);
        $this->assertObjectHasProperty('exp', $payload);
        $this->assertGreaterThan(0, (int) $payload->exp);
    }

    // ---------------------------------------------------------------
    // Refresh tokens
    // ---------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function testGenerateRefreshTokenReturnsString(): void
    {
        $token = JwtHelper::generateRefreshToken(1);

        $this->assertIsString($token);
        $this->assertNotEmpty($token);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testRefreshTokenContainsCorrectPayload(): void
    {
        $userId = 99;
        $token  = JwtHelper::generateRefreshToken($userId);
        $payload = JWT::decode($token, new Key(self::$secret, self::$algo));

        $this->assertSame($userId, (int) $payload->sub);
        $this->assertSame('refresh', $payload->type);
        $this->assertSame('callmetrics', $payload->iss);
        $this->assertObjectHasProperty('jti', $payload);
        $this->assertNotEmpty($payload->jti);
        $this->assertObjectHasProperty('iat', $payload);
        $this->assertObjectHasProperty('exp', $payload);
    }

    // ---------------------------------------------------------------
    // Hash
    // ---------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function testHashReturnsConsistentSha256(): void
    {
        $token = 'eyJhbGciOiJIUzI1NiJ9.test.payload';
        $hash1 = JwtHelper::hash($token);
        $hash2 = JwtHelper::hash($token);

        $this->assertSame($hash1, $hash2);
        $this->assertSame(64, strlen($hash1)); // SHA-256 hex = 64 chars
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $hash1);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testHashIsDifferentForDifferentTokens(): void
    {
        $hash1 = JwtHelper::hash('token-alpha');
        $hash2 = JwtHelper::hash('token-beta');

        $this->assertNotSame($hash1, $hash2);
    }

    // ---------------------------------------------------------------
    // Decode edge cases
    // ---------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function testDecodeInvalidTokenThrowsException(): void
    {
        $this->expectException(\Exception::class);
        JwtHelper::decode('this.is.not.a.valid.jwt');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testDecodeExpiredTokenThrowsException(): void
    {
        // Manually craft an expired token: iat=0, exp=1
        $payload = [
            'iss'  => 'callmetrics',
            'iat'  => 0,
            'exp'  => 1,
            'sub'  => 1,
            'tid'  => 1,
            'role' => 'OPERADOR',
            'type' => 'access',
        ];
        $token = JWT::encode($payload, self::$secret, self::$algo);

        $this->expectException(\Exception::class);
        JwtHelper::decode($token);
    }

    // ---------------------------------------------------------------
    // Uniqueness of refresh tokens
    // ---------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function testTwoRefreshTokensAreDifferent(): void
    {
        $token1 = JwtHelper::generateRefreshToken(1);
        $token2 = JwtHelper::generateRefreshToken(1);

        $this->assertNotSame($token1, $token2);

        // Both decode to the same userId but different jti
        $p1 = JWT::decode($token1, new Key(self::$secret, self::$algo));
        $p2 = JWT::decode($token2, new Key(self::$secret, self::$algo));

        $this->assertSame($p1->sub, $p2->sub);
        $this->assertNotSame($p1->jti, $p2->jti);
    }
}
