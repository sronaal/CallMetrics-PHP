<?php
declare(strict_types=1);

namespace CallMetrics\Http\Controllers;

use CallMetrics\Core\{Database, Request, Response, TenantContext};
use CallMetrics\Models\AlertRule;

/**
 * Controlador CRUD para reglas de alerta y su historial.
 * Todos los endpoints requieren el rol ADMIN_TENANT.
 */
class AlertRuleController extends Controller
{
    /**
     * GET /api/alertas?page=0&size=10&search=
     *
     * Lista paginada de reglas de alerta.
     */
    public function index(Request $request): void
    {
        $page = max(0, (int) ($request->query()['page'] ?? 0));
        $size = max(1, min(100, (int) ($request->query()['size'] ?? 10)));
        $search = $request->query()['search'] ?? '';

        $result = AlertRule::paginate($page, $size, [], $search);

        foreach ($result['data'] as &$regla) {
            unset($regla['updated_at']);
        }

        Response::paginated($result['data'], $page, $size, $result['total']);
    }

    /**
     * GET /api/alertas/{id}
     *
     * Detalle de una regla de alerta.
     */
    public function show(Request $request): void
    {
        $id = (int) $request->param('id');
        $regla = AlertRule::find($id);

        if (!$regla) {
            Response::notFound('Regla de alerta no encontrada');
        }

        unset($regla['updated_at']);
        Response::ok($regla);
    }

    /**
     * POST /api/alertas
     *
     * Crear una nueva regla de alerta. Requiere: nombre, tipo, umbral.
     */
    public function store(Request $request): void
    {
        $data = $request->body();
        $errors = [];

        if (empty($data['nombre'])) $errors['nombre'] = 'Nombre es requerido';
        if (empty($data['tipo'])) $errors['tipo'] = 'Tipo es requerido';
        if (!isset($data['umbral']) || $data['umbral'] === '') $errors['umbral'] = 'Umbral es requerido';

        $tiposValidos = ['LLAMADAS_PERDIDAS', 'CPU', 'RAM', 'COLA_SATURADA', 'TRONCAL_CAIDA'];
        if (!empty($data['tipo']) && !in_array(strtoupper($data['tipo']), $tiposValidos)) {
            $errors['tipo'] = 'Tipo de alerta inválido. Valores: ' . implode(', ', $tiposValidos);
        }

        $condicionesValidas = ['MAYOR', 'MENOR', 'IGUAL'];
        if (!empty($data['condicion']) && !in_array(strtoupper($data['condicion']), $condicionesValidas)) {
            $errors['condicion'] = 'Condición inválida. Valores: ' . implode(', ', $condicionesValidas);
        }

        if (!empty($errors)) {
            Response::error('Errores de validación', 422, $errors);
        }

        $tenantId = TenantContext::resolveTenantIdForWrite($request);
        if ($tenantId === null) {
            Response::error('Tenant no especificado', 400);
        }

        $reglaId = AlertRule::create([
            'tenant_id'       => $tenantId,
            'nombre'          => trim($data['nombre']),
            'tipo'            => strtoupper($data['tipo']),
            'condicion'       => strtoupper($data['condicion'] ?? 'MAYOR'),
            'umbral'          => (float) $data['umbral'],
            'unidad'          => trim($data['unidad'] ?? ''),
            'notificar_email' => (int) ($data['notificar_email'] ?? 1),
            'notificar_web'   => (int) ($data['notificar_web'] ?? 1),
            'activo'          => 1,
        ]);

        $regla = AlertRule::find($reglaId);
        unset($regla['updated_at']);
        Response::created($regla, 'Regla de alerta creada correctamente');
    }

    /**
     * PUT /api/alertas/{id}
     *
     * Actualizar una regla de alerta existente.
     */
    public function update(Request $request): void
    {
        $id = (int) $request->param('id');
        $regla = AlertRule::find($id);

        if (!$regla) {
            Response::notFound('Regla de alerta no encontrada');
        }

        $data = $request->body();

        $updateData = array_filter([
            'nombre'          => trim($data['nombre'] ?? ''),
            'tipo'            => strtoupper($data['tipo'] ?? ''),
            'condicion'       => strtoupper($data['condicion'] ?? ''),
            'umbral'          => isset($data['umbral']) ? (float) $data['umbral'] : null,
            'unidad'          => trim($data['unidad'] ?? ''),
            'notificar_email' => isset($data['notificar_email']) ? (int) $data['notificar_email'] : null,
            'notificar_web'   => isset($data['notificar_web']) ? (int) $data['notificar_web'] : null,
        ], fn($v) => $v !== '' && $v !== null);

        AlertRule::update($id, $updateData);

        $updated = AlertRule::find($id);
        unset($updated['updated_at']);
        Response::ok($updated, 'Regla de alerta actualizada');
    }

    /**
     * PATCH /api/alertas/{id}/toggle
     *
     * Activar/desactivar una regla de alerta.
     */
    public function toggle(Request $request): void
    {
        $id = (int) $request->param('id');
        $regla = AlertRule::find($id);

        if (!$regla) {
            Response::notFound('Regla de alerta no encontrada');
        }

        $newStatus = $regla['activo'] ? 0 : 1;
        AlertRule::update($id, ['activo' => $newStatus]);

        $updated = AlertRule::find($id);
        unset($updated['updated_at']);
        Response::ok($updated, $newStatus ? 'Regla de alerta activada' : 'Regla de alerta desactivada');
    }

    /**
     * GET /api/alertas/{id}/historial
     *
     * Obtener el historial de alertas disparadas para una regla específica.
     */
    public function history(Request $request): void
    {
        $id = (int) $request->param('id');
        $regla = AlertRule::find($id);

        if (!$regla) {
            Response::notFound('Regla de alerta no encontrada');
        }

        $page = max(0, (int) ($request->query()['page'] ?? 0));
        $size = max(1, min(100, (int) ($request->query()['size'] ?? 10)));

        $db = Database::getInstance();
        $params = [':regla_id' => $id];
        $wheres = ['regla_id = :regla_id'];

        // Filtrar por tenant
        $tenantId = TenantContext::resolveTenantId($request);
        if ($tenantId !== null && $tenantId > 0) {
            $wheres[] = 'tenant_id = :tenant_id';
            $params[':tenant_id'] = $tenantId;
        }

        $whereClause = ' WHERE ' . implode(' AND ', $wheres);

        $countSql = "SELECT COUNT(*) as total FROM historial_alertas" . $whereClause;
        $total = (int) $db->fetchOne($countSql, $params)['total'];

        $offset = $page * $size;
        $dataSql = "SELECT * FROM historial_alertas" . $whereClause
                 . " ORDER BY created_at DESC LIMIT $size OFFSET $offset";
        $data = $db->fetchAll($dataSql, $params);

        foreach ($data as &$hist) {
            unset($hist['created_at']);
        }

        Response::paginated($data, $page, $size, $total);
    }
}
