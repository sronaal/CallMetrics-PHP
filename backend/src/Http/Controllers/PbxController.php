<?php
declare(strict_types=1);

namespace CallMetrics\Http\Controllers;

use CallMetrics\Core\{Request, Response, TenantContext};
use CallMetrics\Models\Pbx;

/**
 * Controlador CRUD para servidores PBX.
 * Todos los endpoints requieren el rol ADMIN_TENANT.
 */
class PbxController extends Controller
{
    /**
     * GET /api/pbx?page=0&size=10&search=
     *
     * Lista paginada de servidores PBX con búsqueda por nombre o IP.
     */
    public function index(Request $request): void
    {
        $page = max(0, (int) ($request->query()['page'] ?? 0));
        $size = max(1, min(100, (int) ($request->query()['size'] ?? 10)));
        $search = $request->query()['search'] ?? '';

        $result = Pbx::paginate($page, $size, [], $search, ['nombre', 'ip_address']);

        // Enriquecer con conteos
        foreach ($result['data'] as &$pbx) {
            $pbx['extensiones_count'] = Pbx::countExtensions((int) $pbx['id']);
            $pbx['llamadas_hoy'] = Pbx::countCallsToday((int) $pbx['id']);
            unset($pbx['token_agente']);
            unset($pbx['updated_at']);
        }

        Response::paginated($result['data'], $page, $size, $result['total']);
    }

    /**
     * GET /api/pbx/{id}
     *
     * Detalle de un servidor PBX con conteos de extensiones y llamadas del día.
     */
    public function show(Request $request): void
    {
        $id = (int) $request->param('id');
        $pbx = Pbx::find($id);

        if (!$pbx) {
            Response::notFound('Servidor PBX no encontrado');
        }

        $pbx['extensiones_count'] = Pbx::countExtensions($id);
        $pbx['llamadas_hoy'] = Pbx::countCallsToday($id);
        unset($pbx['token_agente']);
        unset($pbx['updated_at']);

        Response::ok($pbx);
    }

    /**
     * POST /api/pbx
     *
     * Crear un nuevo servidor PBX. Requiere: nombre, ip_address, token_agente.
     */
    public function store(Request $request): void
    {
        $data = $request->body();
        $errors = [];

        if (empty($data['nombre'])) $errors['nombre'] = 'Nombre es requerido';
        if (empty($data['ip_address'])) $errors['ip_address'] = 'Dirección IP es requerida';
        if (empty($data['token_agente'])) $errors['token_agente'] = 'Token de agente es requerido';

        if (!empty($errors)) {
            Response::error('Errores de validación', 422, $errors);
        }

        $tenantId = TenantContext::resolveTenantIdForWrite($request);
        if ($tenantId === null) {
            Response::error('Tenant no especificado', 400);
        }

        $pbxId = Pbx::create([
            'tenant_id'    => $tenantId,
            'nombre'       => trim($data['nombre']),
            'ip_address'   => trim($data['ip_address']),
            'puerto_ami'   => (int) ($data['puerto_ami'] ?? 5038),
            'puerto_http'  => (int) ($data['puerto_http'] ?? 80),
            'tipo'         => strtoupper($data['tipo'] ?? 'ASTERISK'),
            'version'      => trim($data['version'] ?? ''),
            'token_agente' => trim($data['token_agente']),
            'estado'       => 'OFFLINE',
            'activo'       => 1,
        ]);

        $pbx = Pbx::find($pbxId);
        unset($pbx['token_agente']);
        Response::created($pbx, 'Servidor PBX creado correctamente');
    }

    /**
     * PUT /api/pbx/{id}
     *
     * Actualizar un servidor PBX existente.
     */
    public function update(Request $request): void
    {
        $id = (int) $request->param('id');
        $pbx = Pbx::find($id);

        if (!$pbx) {
            Response::notFound('Servidor PBX no encontrado');
        }

        $data = $request->body();

        $updateData = array_filter([
            'nombre'       => trim($data['nombre'] ?? ''),
            'ip_address'   => trim($data['ip_address'] ?? ''),
            'puerto_ami'   => isset($data['puerto_ami']) ? (int) $data['puerto_ami'] : null,
            'puerto_http'  => isset($data['puerto_http']) ? (int) $data['puerto_http'] : null,
            'tipo'         => strtoupper($data['tipo'] ?? ''),
            'version'      => trim($data['version'] ?? ''),
            'token_agente' => trim($data['token_agente'] ?? ''),
        ], fn($v) => $v !== '' && $v !== null);

        Pbx::update($id, $updateData);

        $updated = Pbx::find($id);
        unset($updated['token_agente']);
        Response::ok($updated, 'Servidor PBX actualizado');
    }

    /**
     * PATCH /api/pbx/{id}/toggle
     *
     * Activar/desactivar un servidor PBX.
     */
    public function toggle(Request $request): void
    {
        $id = (int) $request->param('id');
        $pbx = Pbx::find($id);

        if (!$pbx) {
            Response::notFound('Servidor PBX no encontrado');
        }

        $newStatus = $pbx['activo'] ? 0 : 1;
        Pbx::update($id, ['activo' => $newStatus]);

        $updated = Pbx::find($id);
        unset($updated['token_agente']);
        Response::ok($updated, $newStatus ? 'Servidor PBX activado' : 'Servidor PBX desactivado');
    }
}
