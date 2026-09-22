<?php
declare(strict_types=1);

namespace CallMetrics\Http\Controllers;

use CallMetrics\Core\{Request, Response, TenantContext};
use CallMetrics\Models\{Queue, Pbx};

/**
 * Clase QueueController
 *
 * Controlador CRUD para colas de atención. Todos los endpoints requieren
 * el rol ADMIN_TENANT. Gestiona la creación, actualización y activación/
 * desactivación de colas de llamadas con estrategia de distribución.
 *
 * @description Soporta filtrado por servidor PBX, búsqueda por nombre y
 *              enriquecimiento con conteo de agentes asignados.
 * @package CallMetrics\Http\Controllers
 */
class QueueController extends Controller
{
    /**
     * Lista paginada de colas, filtrable por servidor PBX.
     *
     * @description Retorna una página de colas enriquecidas con el conteo de
     *              agentes asignados a cada una.
     *
     * @param Request $request Solicitud con parámetros de query: page, size, search, pbx_id
     * @return void Nunca retorna — termina con Response
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
     * Muestra el detalle de una cola con conteo de agentes asignados.
     *
     * @description Retorna los datos de una cola por su ID, enriquecido con
     *              el conteo de agentes asignados.
     *
     * @param Request $request Solicitud con parámetro de ruta: id
     * @return void Nunca retorna — termina con Response
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
     * Crea una nueva cola de atención.
     *
     * @description Valida campos requeridos (nombre, pbx_id), verifica que el PBX
     *              exista y crea la cola con estrategia por defecto RINGALL y
     *              tiempo máximo de espera de 300 segundos.
     *
     * @param Request $request Solicitud con datos de la cola en el body
     * @return void Nunca retorna — termina con Response
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
     * Actualiza una cola existente.
     *
     * @description Solo actualiza los campos proporcionados (no vacíos ni null).
     *
     * @param Request $request Solicitud con parámetro de ruta: id y datos en el body
     * @return void Nunca retorna — termina con Response
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
     * Activa o desactiva una cola (toggle).
     *
     * @description Alterna el estado activo de la cola (0 ↔ 1).
     *
     * @param Request $request Solicitud con parámetro de ruta: id
     * @return void Nunca retorna — termina con Response
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
