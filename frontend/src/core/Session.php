<?php
declare(strict_types=1);

/**
 * Gestion de sesiones PHP con JWT.
 */
class Session
{
    public static function start(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    public static function login(string $accessToken, string $refreshToken, array $user): void
    {
        self::start();
        $_SESSION['access_token'] = $accessToken;
        $_SESSION['refresh_token'] = $refreshToken;
        $_SESSION['user'] = $user;
        $_SESSION['last_activity'] = time();
    }

    public static function isAuthenticated(): bool
    {
        self::start();
        return isset($_SESSION['access_token'])
            && (time() - ($_SESSION['last_activity'] ?? 0)) < Config::SESSION_TIMEOUT;
    }

    public static function user(): ?array
    {
        self::start();
        return $_SESSION['user'] ?? null;
    }

    public static function token(): ?string
    {
        self::start();
        return $_SESSION['access_token'] ?? null;
    }

    public static function refreshToken(): ?string
    {
        self::start();
        return $_SESSION['refresh_token'] ?? null;
    }

    public static function logout(): void
    {
        self::start();
        session_destroy();
    }

    public static function touch(): void
    {
        self::start();
        $_SESSION['last_activity'] = time();
    }
}
