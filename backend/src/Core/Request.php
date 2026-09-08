<?php
declare(strict_types=1);

namespace CallMetrics\Core;

class Request
{
    private string $method;
    private string $path;
    private array $queryParams;
    private array $body;
    private array $headers;
    private array $routeParams = [];

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
     * Crear una instancia de Request desde las superglobales de PHP.
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

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function query(): array
    {
        return $this->queryParams;
    }

    public function body(): array
    {
        return $this->body;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    public function param(string $key): ?string
    {
        return $this->routeParams[$key] ?? null;
    }

    public function all(): array
    {
        return array_merge($this->queryParams, $this->body);
    }

    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    public function tenantId(): ?int
    {
        return TenantContext::get();
    }
}
