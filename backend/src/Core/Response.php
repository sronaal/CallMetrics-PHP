<?php
declare(strict_types=1);

namespace CallMetrics\Core;

/**
 * Clase Response
 *
 * Proporciona métodos estáticos para emitir respuestas HTTP JSON estandarizadas.
 * Implementa un formato de respuesta consistente con campos: success, message,
 * data y meta (opcional). Incluye métodos de conveniencia para códigos de
 * respuesta comunes (200, 201, 204, 400, 401, 403, 404).
 *
 * @package CallMetrics\Core
 */
class Response
{
    /**
     * Emite una respuesta JSON estandarizada y termina la ejecución.
     *
     * @description Construye el payload JSON con el formato estándar de la API:
     *              success (boolean), message (string), data (mixed) y meta (object).
     *              Establece el código de respuesta HTTP y el header Content-Type.
     *
     * @param mixed $data Datos a retornar en la respuesta
     * @param string $message Mensaje descriptivo del resultado
     * @param int $status Código de respuesta HTTP (por defecto 200)
     * @param array $meta Metadatos adicionales (paginación, etc.)
     * @return void
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

    /**
     * Emite una respuesta exitosa con código 200.
     *
     * @description Retorna los datos solicitados con un mensaje vacío por defecto.
     *
     * @param mixed $data Datos a retornar
     * @param string $message Mensaje descriptivo (opcional)
     * @return void
     */
    public static function ok(mixed $data, string $message = ''): void
    {
        self::json($data, $message, 200);
    }

    /**
     * Emite una respuesta de creación exitosa con código 201.
     *
     * @description Retorna los datos del recurso creado con un mensaje por defecto.
     *
     * @param mixed $data Datos del recurso creado
     * @param string $message Mensaje de confirmación (por defecto "Creado correctamente")
     * @return void
     */
    public static function created(mixed $data, string $message = 'Creado correctamente'): void
    {
        self::json($data, $message, 201);
    }

    /**
     * Emite una respuesta sin contenido con código 204.
     *
     * @description Utilizado para operaciones exitosas que no retornan datos
     *              (ej: DELETE exitoso). Termina la ejecución.
     *
     * @return void
     */
    public static function noContent(): void
    {
        http_response_code(204);
        exit;
    }

    /**
     * Emite una respuesta de error con código de estado personalizado.
     *
     * @description Retorna un error con mensaje y opcionalmente detalles adicionales
     *              en el campo errors. Utilizado para errores de validación y otros
     *              errores de negocio.
     *
     * @param string $message Mensaje descriptivo del error
     * @param int $status Código de error HTTP (por defecto 400)
     * @param array $errors Lista de errores detallados (opcional)
     * @return void
     */
    public static function error(string $message, int $status = 400, array $errors = []): void
    {
        $data = !empty($errors) ? ['errors' => $errors] : null;
        self::json($data, $message, $status);
    }

    /**
     * Emite una respuesta de no autenticado con código 401.
     *
     * @description Retorna un error indicando que el usuario no está autenticado.
     *
     * @param string $message Mensaje de error (por defecto "No autenticado")
     * @return void
     */
    public static function unauthorized(string $message = 'No autenticado'): void
    {
        self::error($message, 401);
    }

    /**
     * Emite una respuesta de prohibido con código 403.
     *
     * @description Retorna un error indicando que el usuario no tiene permisos
     *              para realizar la operación solicitada.
     *
     * @param string $message Mensaje de error (por defecto "Sin permisos")
     * @return void
     */
    public static function forbidden(string $message = 'Sin permisos'): void
    {
        self::error($message, 403);
    }

    /**
     * Emite una respuesta de recurso no encontrado con código 404.
     *
     * @description Retorna un error indicando que el recurso solicitado no existe.
     *
     * @param string $message Mensaje de error (por defecto "Recurso no encontrado")
     * @return void
     */
    public static function notFound(string $message = 'Recurso no encontrado'): void
    {
        self::error($message, 404);
    }

    /**
     * Emite una respuesta paginada con metadatos de paginación.
     *
     * @description Retorna los datos de una página junto con metadatos de paginación:
     *              page (página actual), size (tamaño de página), total (total de
     *              registros) y totalPages (total de páginas calculado).
     *
     * @param array $data Lista de elementos de la página actual
     * @param int $page Número de página actual
     * @param int $size Tamaño de cada página
     * @param int $total Total de registros disponibles
     * @return void
     */
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
