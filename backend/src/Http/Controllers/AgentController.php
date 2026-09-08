<?php
declare(strict_types=1);

namespace CallMetrics\Http\Controllers;

use CallMetrics\Core\{Request, Response, TenantContext};
use CallMetrics\Models\Agent;

/**
 * Controlador CRUD para agentes (operadores telefónicos).
 * Todos los endpoints requieren el rol ADMIN_TENANT.
 */
class AgentController extends Controller
{
    /**
     * GET /api/agentes?page=0&size=10&search=&cola_id=
     *
     * Lista paginada de agentes, filtrable por cola.
     */
    public function index(Request $request): void
    {
        $page = max(0, (int) ($request->query()['page'] ?? 0));
        $size = max(1, min(100, (int) ($request->query()['size'] ?? 10)));
        $search = $request->query()['search'] ?? '';

        $conditions = [];
        if (!empty($request->query()['cola_id'])) {
            $conditions['cola_id'] = (int) $request->query()['cola_id'];
        }

        $result = Agent::paginate($page, $size, $conditions, $search);

        foreach ($result['data'] as &$agente) {
            unset($agente['updated_at']);
        }

        Response::paginated($result['data'], $page, $size, $result['total']);
    }

    /**
     * GET /api/agentes/{id}
     *
     * Detalle de un agente específico.
     */
    public function show(Request $request): void
    {
        $id = (int) $request->param('id');
        $agente = Agent::find($id);

        if (!$agente) {
            Response::notFound('Agente no encontrado');
        }

        unset($agente['updated_at']);
        Response::ok($agente);
    }

    /**
     * POST /api/agentes
     *
     * Crear un nuevo agente. Requiere: nombre.
     */
    public function store(Request $request): void
    {
        $data = $request->body();
        $errors = [];

        if (empty($data['nombre'])) $errors['nombre'] = 'Nombre es requerido';

        if (!empty($errors)) {
            Response::error('Errores de validación', 422, $errors);
        }

        $tenantId = TenantContext::resolveTenantIdForWrite($request);
        if ($tenantId === null) {
            Response::error('Tenant no especificado', 400);
        }

        $agenteId = Agent::create([
            'tenant_id'        => $tenantId,
            'usuario_id'       => !empty($data['usuario_id']) ? (int) $data['usuario_id'] : null,
            'extension_id'     => !empty($data['extension_id']) ? (int) $data['extension_id'] : null,
            'cola_id'          => !empty($data['cola_id']) ? (int) $data['cola_id'] : null,
            'nombre'           => trim($data['nombre']),
            'estado'           => strtoupper($data['estado'] ?? 'DESCONECTADO'),
            'llamadas_atendidas' => 0,
            'tiempo_total_llamadas' => 0,
        ]);

        $agente = Agent::find($agenteId);
        unset($agente['updated_at']);
        Response::created($agente, 'Agente creado correctamente');
    }

    /**
     * PUT /api/agentes/{id}
     *
     * Actualizar un agente existente.
     */
    public function update(Request $request): void
    {
        $id = (int) $request->param('id');
        $agente = Agent::find($id);

        if (!$agente) {
            Response::notFound('Agente no encontrado');
        }

        $data = $request->body();

        $updateData = array_filter([
            'nombre'        => trim($data['nombre'] ?? ''),
            'usuario_id'    => isset($data['usuario_id']) ? (int) $data['usuario_id'] : null,
            'extension_id'  => isset($data['extension_id']) ? (int) $data['extension_id'] : null,
            'cola_id'       => isset($data['cola_id']) ? (int) $data['cola_id'] : null,
            'estado'        => strtoupper($data['estado'] ?? ''),
        ], fn($v) => $v !== '' && $v !== null);

        Agent::update($id, $updateData);

        $updated = Agent::find($id);
        unset($updated['updated_at']);
        Response::ok($updated, 'Agente actualizado');
    }

    /**
     * PATCH /api/agentes/{id}/toggle
     *
     * Cambiar el estado del agente (DESCONECTADO ↔ DISPONIBLE).
     */
    public function toggle(Request $request): void
    {
        $id = (int) $request->param('id');
        $agente = Agent::find($id);

        if (!$agente) {
            Response::notFound('Agente no encontrado');
        }

        // Alternar entre DESCONECTADO y DISPONIBLE
        $newEstado = $agente['estado'] === 'DESCONECTADO' ? 'DISPONIBLE' : 'DESCONECTADO';
        Agent::update($id, ['estado' => $newEstado]);

        $updated = Agent::find($id);
        unset($updated['updated_at']);
        Response::ok($updated, $newEstado === 'DISPONIBLE' ? 'Agente conectado' : 'Agente desconectado');
    }
}
