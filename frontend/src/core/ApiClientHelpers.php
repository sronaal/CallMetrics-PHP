<?php
declare(strict_types=1);

/**
 * Funciones helper para llamadas API comunes.
 */

function api_get_empresas(int $page = 0, int $size = 10, string $search = ''): array
{
    $client = ApiClient::getInstance();
    $query = ['page' => $page, 'size' => $size];
    if ($search) $query['search'] = $search;
    return $client->get('/tenants', $query);
}

function api_get_usuario(int $id): array
{
    return ApiClient::getInstance()->get("/usuarios/$id");
}

function api_create_empresa(array $data): array
{
    return ApiClient::getInstance()->post('/tenants', $data);
}

function api_update_empresa(int $id, array $data): array
{
    return ApiClient::getInstance()->put("/tenants/$id", $data);
}

function api_toggle_empresa(int $id): array
{
    return ApiClient::getInstance()->patch("/tenants/$id/toggle");
}

function api_get_usuarios(int $page = 0, int $size = 10, string $search = ''): array
{
    $client = ApiClient::getInstance();
    $query = ['page' => $page, 'size' => $size];
    if ($search) $query['search'] = $search;
    return $client->get('/usuarios', $query);
}

function api_create_usuario(array $data): array
{
    return ApiClient::getInstance()->post('/usuarios', $data);
}

function api_update_usuario(int $id, array $data): array
{
    return ApiClient::getInstance()->put("/usuarios/$id", $data);
}

function api_toggle_usuario(int $id): array
{
    return ApiClient::getInstance()->patch("/usuarios/$id/toggle");
}

function api_get_pbx(int $page = 0, int $size = 10, string $search = ''): array
{
    $client = ApiClient::getInstance();
    $query = ['page' => $page, 'size' => $size];
    if ($search) $query['search'] = $search;
    return $client->get('/pbx', $query);
}

function api_create_pbx(array $data): array
{
    return ApiClient::getInstance()->post('/pbx', $data);
}

function api_update_pbx(int $id, array $data): array
{
    return ApiClient::getInstance()->put("/pbx/$id", $data);
}

function api_toggle_pbx(int $id): array
{
    return ApiClient::getInstance()->patch("/pbx/$id/toggle");
}

function api_get_extensiones(int $page = 0, int $size = 10, ?int $pbxId = null): array
{
    $client = ApiClient::getInstance();
    $query = ['page' => $page, 'size' => $size];
    if ($pbxId) $query['pbx_id'] = $pbxId;
    return $client->get('/extensiones', $query);
}

function api_get_colas(int $page = 0, int $size = 10, ?int $pbxId = null): array
{
    $client = ApiClient::getInstance();
    $query = ['page' => $page, 'size' => $size];
    if ($pbxId) $query['pbx_id'] = $pbxId;
    return $client->get('/colas', $query);
}

function api_get_agentes(int $page = 0, int $size = 10, ?int $colaId = null): array
{
    $client = ApiClient::getInstance();
    $query = ['page' => $page, 'size' => $size];
    if ($colaId) $query['cola_id'] = $colaId;
    return $client->get('/agentes', $query);
}

function api_get_llamadas(int $page = 0, int $size = 10, array $filters = []): array
{
    $client = ApiClient::getInstance();
    $query = array_merge(['page' => $page, 'size' => $size], $filters);
    return $client->get('/llamadas', $query);
}

function api_get_llamadas_stats(): array
{
    return ApiClient::getInstance()->get('/llamadas/stats');
}

function api_export_llamadas_csv(array $filters = []): void
{
    $client = ApiClient::getInstance();
    $query = http_build_query($filters);
    $url = Config::DIRECT_API_URL . "/llamadas/export?" . $query;

    $token = Session::token();
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
    ]);
    $data = curl_exec($ch);
    curl_close($ch);

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="llamadas_' . date('Y-m-d') . '.csv"');
    echo $data;
    exit;
}

function api_get_eventos(int $page = 0, int $size = 10, array $filters = []): array
{
    $client = ApiClient::getInstance();
    $query = array_merge(['page' => $page, 'size' => $size], $filters);
    return $client->get('/eventos', $query);
}

function api_get_alertas(int $page = 0, int $size = 10): array
{
    return ApiClient::getInstance()->get('/alertas', ['page' => $page, 'size' => $size]);
}

function api_create_alerta(array $data): array
{
    return ApiClient::getInstance()->post('/alertas', $data);
}

function api_update_alerta(int $id, array $data): array
{
    return ApiClient::getInstance()->put("/alertas/$id", $data);
}

function api_toggle_alerta(int $id): array
{
    return ApiClient::getInstance()->patch("/alertas/$id/toggle");
}

function api_get_dashboard(): array
{
    return ApiClient::getInstance()->get('/dashboard/summary');
}
