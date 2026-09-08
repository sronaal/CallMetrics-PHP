<?php
declare(strict_types=1);

namespace Tests\Unit\Models;

use CallMetrics\Models\User;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for CallMetrics\Models\User::verifyPassword().
 *
 * This is a pure wrapper around password_verify — no database access needed.
 */
class UserTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function testVerifyPasswordReturnsTrue(): void
    {
        $password = 'my_secure_password';
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

        $this->assertTrue(User::verifyPassword($password, $hash));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testVerifyPasswordReturnsFalse(): void
    {
        $hash = password_hash('correct_password', PASSWORD_BCRYPT, ['cost' => 12]);

        $this->assertFalse(User::verifyPassword('wrong_password', $hash));
    }
}
