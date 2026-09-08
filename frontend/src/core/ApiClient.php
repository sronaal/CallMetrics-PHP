<?php
declare(strict_types=1);

/**
 * Cliente HTTP singleton para llamar al backend API.
 */
class ApiClient
{
    private static ?ApiClient $instance = null;
    private string $baseUrl;

    private function __construct()
    {
        $this->baseUrl = Config::API_URL;
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function get(string $path, array $query = []): array
    {
        return $this->request('GET', $path, null, $query);
    }

    public function post(string $path, array $data = []): array
    {
        return $this->request('POST', $path, $data);
    }

    public function put(string $path, array $data = []): array
    {
        return $this->request('PUT', $path, $data);
    }

    public function patch(string $path, array $data = []): array
    {
        return $this->request('PATCH', $path, $data);
    }

    public function delete(string $path): array
    {
        return $this->request('DELETE', $path);
    }

    private function request(string $method, string $path, ?array $data = null, array $query = []): array
    {
        $url = $this->baseUrl . $path;
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        $ch = curl_init();
        $headers = ['Content-Type: application/json'];

        $token = Session::token();
        if ($token) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => Config::API_TIMEOUT,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => $method,
        ]);

        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'message' => "Error de conexion: $error", 'data' => null];
        }

        $decoded = json_decode($response, true);
        if ($decoded === null) {
            return ['success' => false, 'message' => 'Respuesta invalida del servidor', 'data' => null];
        }

        $decoded['_http_code'] = $httpCode;
        return $decoded;
    }
}
