<?php
declare(strict_types=1);

namespace CallMetrics\Http\Controllers;

use CallMetrics\Core\{Request, Response, TenantContext};
use CallMetrics\Models\{Queue, Pbx};

/**
 * Controlador CRUD para colas de atención.
 * Todos los endpoints requieren el rol ADMIN_TENANT.
 */
class QueueController extends Controller
{
    /**
     * GET /api/colas?page=0&size=10&search=&pbx_id=
     *
     * Lista paginada de colas, filtrable por servidor PBX.
     */
    public function index(Request $request): void
    {
        $page = max(0, (int) ($request->query()['page'] ?? 0));
        $size = max(1, min(100, (int) ($request->query()['size'] ?? 10)));
        $search = $request->query()['search'] ?? '';

        $conditions = [];
        if (!empty($request->query()['pbx_id'])) {
            $conditions['pbx_id'] = (int) $request->query()['pbx_id'];
        }

        $result = Queue::paginate($page, $size, $conditions, $search);

        // Enriquecer con conteo de agentes
        foreach ($result['data'] as &$cola) {
            $cola['agentes_count'] = Queue::countAgents((int) $cola['id']);
            unset($cola['updated_at']);
        }

        Response::paginated($result['data'], $page, $size, $result['total']);
    }

    /**
     * GET /api/colas/{id}
     *
     * Detalle de una cola con conteo de agentes asignados.
     */
    public function show(Request $request): void
    {
        $id = (int) $request->param('id');
        $cola = Queue::find($id);

        if (!$cola) {
            Response::notFound('Cola no encontrada');
        }

        $cola['agentes_count'] = Queue::countAgents($id);
        unset($cola['updated_at']);

        Response::ok($cola);
    }

    /**
     * POST /api/colas
     *
     * Crear una nueva cola de atención. Requiere: nombre, pbx_id.
     */
    public function store(Request $request): void
    {
        $data = $request->body();
        $errors = [];

        if (empty($data['nombre'])) $errors['nombre'] = 'Nombre es requerido';
        if (empty($data['pbx_id'])) $errors['pbx_id'] = 'Servidor PBX es requerido';

        // Verificar que el PBX exista
        if (!empty($data['pbx_id'])) {
            $pbx = Pbx::find((int) $data['pbx_id']);
            if (!$pbx) {
                $errors['pbx_id'] = 'Servidor PBX no encontrado';
            }
        }

        if (!empty($errors)) {
            Response::error('Errores de validación', 422, $errors);
        }

        $tenantId = TenantContext::resolveTenantIdForWrite($request);
        if ($tenantId === null) {
            Response::error('Tenant no especificado', 400);
        }

        $colaId = Queue::create([
            'tenant_id'   => $tenantId,
            'pbx_id'      => (int) $data['pbx_id'],
            'nombre'      => trim($data['nombre']),
            'estrategia'  => strtoupper($data['estrategia'] ?? 'RINGALL'),
            'max_waiting' => (int) ($data['max_waiting'] ?? 300),
            'mus_on_hold' => trim($data['mus_on_hold'] ?? 'default'),
            'estado'      => 'ACTIVA',
            'activo'      => 1,
        ]);

        $cola = Queue::find($colaId);
        unset($cola['updated_at']);
        Response::created($cola, 'Cola creada correctamente');
    }

    /**
     * PUT /api/colas/{id}
     *
     * Actualizar una cola existente.
     */
    public function update(Request $request): void
    {
        $id = (int) $request->param('id');
        $cola = Queue::find($id);

        if (!$cola) {
            Response::notFound('Cola no encontrada');
        }

        $data = $request->body();

        $updateData = array_filter([
            'nombre'      => trim($data['nombre'] ?? ''),
            'estrategia'  => strtoupper($data['estrategia'] ?? ''),
            'max_waiting' => isset($data['max_waiting']) ? (int) $data['max_waiting'] : null,
            'mus_on_hold' => trim($data['mus_on_hold'] ?? ''),
            'estado'      => strtoupper($data['estado'] ?? ''),
        ], fn($v) => $v !== '' && $v !== null);

        Queue::update($id, $updateData);

        $updated = Queue::find($id);
        unset($updated['updated_at']);
        Response::ok($updated, 'Cola actualizada');
    }

    /**
     * PATCH /api/colas/{id}/toggle
     *
     * Activar/desactivar una cola.
     */
    public function toggle(Request $request): void
    {
        $id = (int) $request->param('id');
        $cola = Queue::find($id);

        if (!$cola) {
            Response::notFound('Cola no encontrada');
        }

        $newStatus = $cola['activo'] ? 0 : 1;
        Queue::update($id, ['activo' => $newStatus]);

        $updated = Queue::find($id);
        unset($updated['updated_at']);
        Response::ok($updated, $newStatus ? 'Cola activada' : 'Cola desactivada');
    }
}
