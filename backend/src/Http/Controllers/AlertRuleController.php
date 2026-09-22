<?php
declare(strict_types=1);

namespace CallMetrics\Http\Controllers;

use CallMetrics\Core\{Database, Request, Response, TenantContext};
use CallMetrics\Models\AlertRule;

/**
 * Clase AlertRuleController
 *
 * Controlador CRUD para reglas de alerta y su historial. Todos los endpoints
 * requieren el rol ADMIN_TENANT. Gestiona la configuración de umbrales y
 * condiciones para notificaciones automáticas.
 *
 * @description Soporta operaciones CRUD completas con validación de tipos
 *              de alerta y condiciones permitidas. Incluye endpoint para
 *              consultar el historial de alertas disparadas.
 * @package CallMetrics\Http\Controllers
 */
class AlertRuleController extends Controller
{
    /**
     * Lista paginada de reglas de alerta con búsqueda por nombre.
     *
     * @description Retorna una página de reglas de alerta del tenant actual.
     *
     * @param Request $request Solicitud con parámetros de query: page, size, search
     * @return void Nunca retorna — termina con Response
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
     * Muestra el detalle de una regla de alerta específica.
     *
     * @description Retorna los datos de una regla de alerta por su ID.
     *
     * @param Request $request Solicitud con parámetro de ruta: id
     * @return void Nunca retorna — termina con Response
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
     * Crea una nueva regla de alerta.
     *
     * @description Valida campos requeridos (nombre, tipo, umbral), tipo de alerta
     *              (LLAMADAS_PERDIDAS, CPU, RAM, COLA_SATURADA, TRONCAL_CAIDA)
     *              y condición (MAYOR, MENOR, IGUAL).
     *
     * @param Request $request Solicitud con datos de la regla en el body
     * @return void Nunca retorna — termina con Response
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
     * Actualiza una regla de alerta existente.
     *
     * @description Solo actualiza los campos proporcionados (no vacíos ni null).
     *
     * @param Request $request Solicitud con parámetro de ruta: id y datos en el body
     * @return void Nunca retorna — termina con Response
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
     * Activa o desactiva una regla de alerta (toggle).
     *
     * @description Alterna el estado activo de la regla (0 ↔ 1).
     *
     * @param Request $request Solicitud con parámetro de ruta: id
     * @return void Nunca retorna — termina con Response
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
     * Obtiene el historial de alertas disparadas para una regla específica.
     *
     * @description Retorna una lista paginada de alertas disparadas de la regla,
     *              filtrada por tenant y ordenada por fecha de creación descendente.
     *
     * @param Request $request Solicitud con parámetro de ruta: id y query: page, size
     * @return void Nunca retorna — termina con Response
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
