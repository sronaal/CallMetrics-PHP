<?php
declare(strict_types=1);

/**
 * Guard de autenticacion para paginas protegidas.
 */
class AuthMiddleware
{
    public static function check(): void
    {
        Session::start();

        if (!Session::isAuthenticated()) {
            header('Location: ' . BASE_URL . 'auth.php');
            exit;
        }

        // Intentar refresh si el token esta por expirar
        self::refreshIfNeeded();
    }

    public static function guest(): void
    {
        Session::start();

        if (Session::isAuthenticated()) {
            header('Location: ' . BASE_URL . 'dashboard.php');
            exit;
        }
    }

    private static function refreshIfNeeded(): void
    {
        $refreshToken = Session::refreshToken();
        if (!$refreshToken) return;

        $client = ApiClient::getInstance();
        $response = $client->post('/auth/refresh', ['refreshToken' => $refreshToken]);

        if ($response['success'] && isset($response['data'])) {
            Session::login(
                $response['data']['accessToken'],
                $response['data']['refreshToken'],
                Session::user()
            );
        } else {
            Session::logout();
            header('Location: ' . BASE_URL . 'auth.php');
            exit;
        }
    }
}
