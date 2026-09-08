<?php
declare(strict_types=1);

namespace CallMetrics\Core;

class Response
{
    /**
     * Emitir una respuesta JSON estandarizada y salir.
     */
    public static function json(
        mixed $data = null,
        string $message = '',
        int $status = 200,
        array $meta = []
    ): void {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');

        $payload = [
            'success' => $status >= 200 && $status < 300,
            'message' => $message,
            'data'    => $data,
        ];

        if (!empty($meta)) {
            $payload['meta'] = (object) $meta;
        }

        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function ok(mixed $data, string $message = ''): void
    {
        self::json($data, $message, 200);
    }

    public static function created(mixed $data, string $message = 'Creado correctamente'): void
    {
        self::json($data, $message, 201);
    }

    public static function noContent(): void
    {
        http_response_code(204);
        exit;
    }

    public static function error(string $message, int $status = 400, array $errors = []): void
    {
        $data = !empty($errors) ? ['errors' => $errors] : null;
        self::json($data, $message, $status);
    }

    public static function unauthorized(string $message = 'No autenticado'): void
    {
        self::error($message, 401);
    }

    public static function forbidden(string $message = 'Sin permisos'): void
    {
        self::error($message, 403);
    }

    public static function notFound(string $message = 'Recurso no encontrado'): void
    {
        self::error($message, 404);
    }

    public static function paginated(array $data, int $page, int $size, int $total): void
    {
        self::json($data, '', 200, [
            'page'       => $page,
            'size'       => $size,
            'total'      => $total,
            'totalPages' => (int) ceil($total / $size),
        ]);
    }
}
