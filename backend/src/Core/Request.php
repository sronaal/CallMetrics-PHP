<?php
declare(strict_types=1);

namespace CallMetrics\Core;

/**
 * Clase Request
 *
 * encapsula la información de una solicitud HTTP entrante. Proporciona una
 * interfaz unificada para acceder a datos como el método HTTP, la ruta,
 * parámetros de consulta, cuerpo de la solicitud y headers. Utiliza el patrón
 * de diseño Request Object para desacoplar la lógica de negocio de las
 * superglobales de PHP.
 *
 * @package CallMetrics\Core
 */
class Request
{
    /** @var string Método HTTP (GET, POST, PUT, DELETE, etc.) */
    private string $method;

    /** @var string Ruta URI de la solicitud */
    private string $path;

    /** @var array Parámetros de query string */
    private array $queryParams;

    /** @var array Cuerpo de la solicitud (parseado desde JSON) */
    private array $body;

    /** @var array Headers HTTP normalizados */
    private array $headers;

    /** @var array Parámetros de ruta dinámicos (ej: /users/{id}) */
    private array $routeParams = [];

    /**
     * Constructor de la clase Request.
     *
     * @description Inicializa los atributos de la solicitud con los valores
     *              proporcionados. Se utiliza principalmente desde fromGlobals().
     *
     * @param string $method Método HTTP de la solicitud
     * @param string $path Ruta URI de la solicitud
     * @param array $queryParams Parámetros de query string
     * @param array $body Cuerpo de la solicitud (array asociativo)
     * @param array $headers Headers HTTP normalizados
     */
    public function __construct(
        string $method,
        string $path,
        array $queryParams,
        array $body,
        array $headers
    ) {
        $this->method = $method;
        $this->path = $path;
        $this->queryParams = $queryParams;
        $this->body = $body;
        $this->headers = $headers;
    }

    /**
     * Crea una instancia de Request desde las superglobales de PHP.
     *
     * @description Analiza las superglobales $_SERVER, $_GET y php://input
     *              para construir un objeto Request. Parsea el cuerpo JSON
     *              y normaliza los headers HTTP con nombres en minúsculas
     *              y guiones medios.
     *
     * @return self Instancia de Request con los datos de la solicitud actual
     */
    public static function fromGlobals(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $queryParams = $_GET;

        // Parsear el body JSON desde php://input
        $body = [];
        $rawBody = file_get_contents('php://input');
        if ($rawBody) {
            $decoded = json_decode($rawBody, true);
            if (is_array($decoded)) {
                $body = $decoded;
            }
        }

        // Normalizar los headers HTTP desde $_SERVER
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $header = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$header] = $value;
            }
        }

        // También capturar Content-Type (no tiene prefijo HTTP_)
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
        }

        return new self($method, $path, $queryParams, $body, $headers);
    }

    /**
     * Obtiene el método HTTP de la solicitud.
     *
     * @description Retorna el método HTTP utilizado en la solicitud (GET, POST,
     *              PUT, DELETE, PATCH, etc.).
     *
     * @return string Método HTTP en mayúsculas
     */
    public function method(): string
    {
        return $this->method;
    }

    /**
     * Obtiene la ruta URI de la solicitud.
     *
     * @description Retorna la ruta de la URI sin parámetros de query string.
     *
     * @return string Ruta URI de la solicitud
     */
    public function path(): string
    {
        return $this->path;
    }

    /**
     * Obtiene los parámetros de query string.
     *
     * @description Retorna todos los parámetros de la cadena de consulta URL.
     *
     * @return array Parámetros de query string como array asociativo
     */
    public function query(): array
    {
        return $this->queryParams;
    }

    /**
     * Obtiene el cuerpo de la solicitud.
     *
     * @description Retorna el cuerpo de la solicitud parseado como array
     *              asociativo (originalmente JSON).
     *
     * @return array Cuerpo de la solicitud como array asociativo
     */
    public function body(): array
    {
        return $this->body;
    }

    /**
     * Obtiene un header específico por nombre.
     *
     * @description Busca un header HTTP por su nombre (case-insensitive).
     *
     * @param string $name Nombre del header a buscar
     * @return ?string Valor del header o null si no existe
     */
    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /**
     * Obtiene un valor específico del cuerpo de la solicitud.
     *
     * @description Busca un valor en el array body por su clave. Si no existe,
     *              retorna el valor por defecto proporcionado.
     *
     * @param string $key Clave del valor a buscar en el body
     * @param mixed $default Valor por defecto si la clave no existe
     * @return mixed Valor encontrado o el valor por defecto
     */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    /**
     * Obtiene un parámetro de ruta dinámico.
     *
     * @description Busca un parámetro de ruta (ej: {id} en /users/{id}) por su nombre.
     *
     * @param string $key Nombre del parámetro de ruta
     * @return ?string Valor del parámetro o null si no existe
     */
    public function param(string $key): ?string
    {
        return $this->routeParams[$key] ?? null;
    }

    /**
     * Obtiene todos los datos combinados (query params + body).
     *
     * @description Retorna un array con la fusión de los parámetros de query
     *              string y el cuerpo de la solicitud. Prioriza el body sobre
     *              los query params en caso de duplicados.
     *
     * @return array Todos los datos combinados de la solicitud
     */
    public function all(): array
    {
        return array_merge($this->queryParams, $this->body);
    }

    /**
     * Establece los parámetros de ruta dinámicos.
     *
     * @description Asigna los parámetros de ruta extraídos por el Router.
     *
     * @param array $params Array de parámetros de ruta como clave => valor
     * @return void
     */
    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    /**
     * Obtiene el tenant_id del contexto actual.
     *
     * @description Retorna el ID del tenant del usuario autenticado actualmente.
     *              Utiliza TenantContext para obtener el valor del JWT.
     *
     * @return ?int ID del tenant o null si no hay contexto de tenant
     */
    public function tenantId(): ?int
    {
        return TenantContext::get();
    }
}
