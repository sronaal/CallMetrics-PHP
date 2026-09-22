<?php
declare(strict_types=1);

namespace CallMetrics\Http\Controllers;

use CallMetrics\Core\{Request, Response, TenantContext};
use CallMetrics\Models\Agent;

/**
 * Clase AgentController
 *
 * Controlador CRUD para agentes (operadores telefónicos). Todos los endpoints
 * requieren el rol ADMIN_TENANT. Gestiona la creación, actualización y
 * activación/desactivación de agentes en las colas de atención.
 *
 * @description Soporta filtrado por cola, búsqueda por nombre y cambio de estado
 *              entre DESCONECTADO y DISPONIBLE.
 * @package CallMetrics\Http\Controllers
 */
class AgentController extends Controller
{
    /**
     * Lista paginada de agentes, filtrable por cola.
     *
     * @description Retorna una página de agentes con búsqueda por nombre y
     *              filtrado opcional por cola_id.
     *
     * @param Request $request Solicitud con parámetros de query: page, size, search, cola_id
     * @return void Nunca retorna — termina con Response
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
     * Muestra el detalle de un agente específico.
     *
     * @description Retorna los datos de un agente por su ID.
     *
     * @param Request $request Solicitud con parámetro de ruta: id
     * @return void Nunca retorna — termina con Response
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
     * Crea un nuevo agente.
     *
     * @description Valida campo requerido (nombre), resuelve el tenant_id y crea
     *              el agente con estado por defecto DESCONECTADO y estadísticas en 0.
     *
     * @param Request $request Solicitud con datos del agente en el body
     * @return void Nunca retorna — termina con Response
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
     * Actualiza un agente existente.
     *
     * @description Solo actualiza los campos proporcionados (no vacíos ni null).
     *
     * @param Request $request Solicitud con parámetro de ruta: id y datos en el body
     * @return void Nunca retorna — termina con Response
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
     * Cambia el estado del agente (toggle DESCONECTADO ↔ DISPONIBLE).
     *
     * @description Alterna el estado del agente entre DESCONECTADO y DISPONIBLE.
     *              Utilizado para conectar/desconectar agentes de las colas.
     *
     * @param Request $request Solicitud con parámetro de ruta: id
     * @return void Nunca retorna — termina con Response
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
