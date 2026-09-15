<?php
declare(strict_types=1);

namespace CallMetrics\Http\Controllers;

use CallMetrics\Core\{Request, Response, Database, TenantContext};

/**
 * Controlador de lectura para el CDR Report (datos del agente-collector).
 * Lee de las tablas cdr_llamadas, cdr_colas_resumen, cdr_agentes_resumen,
 * cdr_estadisticas_colas y cdr_llamadas_real.
 *
 * Todos los endpoints requieren SUPERVISOR o superior.
 */
class CdrReportController extends Controller
{
    private const PERMITTED_STATES = [
        'ANSWERED', 'NOANSWER', 'BUSY', 'FAILED', 'CANCELLED',
        'CONGESTION', 'CHANUNAVAIL', 'ANSWER', 'NO ANSWER',
    ];

    /**
     * GET /api/cdr-report/llamadas?page=0&size=50&fecha_inicio=&fecha_fin=&nombre_cola=&extension_agente=&estado_final=
     *
     * Llamadas individuales del reporte del agente.
     */
    public function llamadas(Request $request): void
    {
        $db = Database::getInstance();
        $q = $request->query();
        $page = max(0, (int) ($q['page'] ?? 0));
        $size = max(1, min(100, (int) ($q['size'] ?? 50)));

        $tenantId = TenantContext::get();
        $isSuperAdmin = TenantContext::isSuperAdmin();
        if ($tenantId === null && !$isSuperAdmin) {
            Response::error('Tenant no especificado', 400);
            return;
        }

        $conditions = [];
        $params = [];

        if ($isSuperAdmin) {
            $tidFilter = !empty($q['tenant_id']) ? (int) $q['tenant_id'] : null;
            if ($tidFilter !== null) {
                $conditions[] = 'c.tenant_id = :tenant_id';
                $params[':tenant_id'] = $tidFilter;
            }
        } else {
            $conditions[] = 'c.tenant_id = :tenant_id';
            $params[':tenant_id'] = $tenantId;
        }

        if (!empty($q['fecha_inicio'])) {
            $conditions[] = 'c.fecha_inicio >= :fecha_inicio';
            $params[':fecha_inicio'] = $q['fecha_inicio'];
        }
        if (!empty($q['fecha_fin'])) {
            $conditions[] = 'c.fecha_inicio <= :fecha_fin';
            $params[':fecha_fin'] = $q['fecha_fin'];
        }
        if (!empty($q['nombre_cola'])) {
            $conditions[] = 'c.nombre_cola = :nombre_cola';
            $params[':nombre_cola'] = $q['nombre_cola'];
        }
        if (!empty($q['extension_agente'])) {
            $conditions[] = 'c.extension_agente = :extension_agente';
            $params[':extension_agente'] = $q['extension_agente'];
        }
        if (!empty($q['estado_final'])) {
            $conditions[] = 'c.estado_final = :estado_final';
            $params[':estado_final'] = $q['estado_final'];
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $total = $db->count(
            "SELECT COUNT(*) FROM cdr_llamadas c $where",
            $params
        );

        $rows = $db->fetchAll(
            "SELECT c.id, c.tenant_id, c.pbx_id, c.linkedid, c.fecha_inicio,
                    c.numero_origen, c.destino_inicial, c.paso_por_cola, c.nombre_cola,
                    c.extension_agente, c.nombre_agente, c.tiempo_conversacion,
                    c.tiempo_timbrado, c.estado_final, c.created_at
             FROM cdr_llamadas c
             $where
             ORDER BY c.fecha_inicio DESC
             LIMIT :limit OFFSET :offset",
            array_merge($params, [':limit' => $size, ':offset' => $page * $size])
        );

        Response::paginated($rows, $page, $size, $total);
    }

    /**
     * GET /api/cdr-report/colas
     *
     * Resumen de colas (una fila por cola).
     */
    public function colas(Request $request): void
    {
        $db = Database::getInstance();
        $q = $request->query();
        $tenantId = TenantContext::get();
        $isSuperAdmin = TenantContext::isSuperAdmin();

        if ($tenantId === null && !$isSuperAdmin) {
            Response::error('Tenant no especificado', 400);
            return;
        }

        $conditions = [];
        $params = [];

        if ($isSuperAdmin) {
            $tidFilter = !empty($q['tenant_id']) ? (int) $q['tenant_id'] : null;
            if ($tidFilter !== null) {
                $conditions[] = 'cr.tenant_id = :tenant_id';
                $params[':tenant_id'] = $tidFilter;
            }
        } else {
            $conditions[] = 'cr.tenant_id = :tenant_id';
            $params[':tenant_id'] = $tenantId;
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $rows = $db->fetchAll(
            "SELECT cr.id, cr.tenant_id, cr.pbx_id, cr.numero_cola,
                    cr.total_llamadas, cr.contestadas, cr.no_contestadas,
                    cr.ocupadas, cr.fallidas, cr.porcentaje_efectividad,
                    cr.promedio_espera, cr.promedio_duracion, cr.updated_at
             FROM cdr_colas_resumen cr
             $where
             ORDER BY cr.total_llamadas DESC",
            $params
        );

        Response::ok($rows);
    }

    /**
     * GET /api/cdr-report/agentes
     *
     * Resumen de agentes (una fila por agente).
     */
    public function agentes(Request $request): void
    {
        $db = Database::getInstance();
        $q = $request->query();
        $tenantId = TenantContext::get();
        $isSuperAdmin = TenantContext::isSuperAdmin();

        if ($tenantId === null && !$isSuperAdmin) {
            Response::error('Tenant no especificado', 400);
            return;
        }

        $conditions = [];
        $params = [];

        if ($isSuperAdmin) {
            $tidFilter = !empty($q['tenant_id']) ? (int) $q['tenant_id'] : null;
            if ($tidFilter !== null) {
                $conditions[] = 'ar.tenant_id = :tenant_id';
                $params[':tenant_id'] = $tidFilter;
            }
        } else {
            $conditions[] = 'ar.tenant_id = :tenant_id';
            $params[':tenant_id'] = $tenantId;
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $rows = $db->fetchAll(
            "SELECT ar.id, ar.tenant_id, ar.pbx_id, ar.extension_agente,
                    ar.nombre_agente, ar.contestadas, ar.no_contestadas,
                    ar.ocupadas, ar.fallidas, ar.total_llamadas,
                    ar.porcentaje_efectividad, ar.promedio_espera,
                    ar.promedio_duracion, ar.updated_at
             FROM cdr_agentes_resumen ar
             $where
             ORDER BY ar.total_llamadas DESC",
            $params
        );

        Response::ok($rows);
    }

    /**
     * GET /api/cdr-report/estadisticas?page=0&size=50&numero_cola=&fecha_inicio=&fecha_fin=
     *
     * Estadisticas por llamada y cola (una fila por llamada-cola).
     */
    public function estadisticas(Request $request): void
    {
        $db = Database::getInstance();
        $q = $request->query();
        $page = max(0, (int) ($q['page'] ?? 0));
        $size = max(1, min(100, (int) ($q['size'] ?? 50)));

        $tenantId = TenantContext::get();
        $isSuperAdmin = TenantContext::isSuperAdmin();

        if ($tenantId === null && !$isSuperAdmin) {
            Response::error('Tenant no especificado', 400);
            return;
        }

        $conditions = [];
        $params = [];

        if ($isSuperAdmin) {
            $tidFilter = !empty($q['tenant_id']) ? (int) $q['tenant_id'] : null;
            if ($tidFilter !== null) {
                $conditions[] = 'ec.tenant_id = :tenant_id';
                $params[':tenant_id'] = $tidFilter;
            }
        } else {
            $conditions[] = 'ec.tenant_id = :tenant_id';
            $params[':tenant_id'] = $tenantId;
        }

        if (!empty($q['numero_cola'])) {
            $conditions[] = 'ec.numero_cola = :numero_cola';
            $params[':numero_cola'] = $q['numero_cola'];
        }
        if (!empty($q['fecha_inicio'])) {
            $conditions[] = 'ec.fecha_entrada >= :fecha_inicio';
            $params[':fecha_inicio'] = $q['fecha_inicio'];
        }
        if (!empty($q['fecha_fin'])) {
            $conditions[] = 'ec.fecha_entrada <= :fecha_fin';
            $params[':fecha_fin'] = $q['fecha_fin'];
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $total = $db->count(
            "SELECT COUNT(*) FROM cdr_estadisticas_colas ec $where",
            $params
        );

        $rows = $db->fetchAll(
            "SELECT ec.id, ec.tenant_id, ec.pbx_id, ec.linkedid,
                    ec.numero_cola, ec.fecha_entrada, ec.estado_final,
                    ec.caller_id, ec.agente_asignado, ec.espera_seg,
                    ec.duracion_seg, ec.created_at
             FROM cdr_estadisticas_colas ec
             $where
             ORDER BY ec.fecha_entrada DESC
             LIMIT :limit OFFSET :offset",
            array_merge($params, [':limit' => $size, ':offset' => $page * $size])
        );

        Response::paginated($rows, $page, $size, $total);
    }

    /**
     * GET /api/cdr-report/real?page=0&size=50&fecha_inicio=&fecha_fin=
     *
     * Llamadas reales con tiempos exactos.
     */
    public function real(Request $request): void
    {
        $db = Database::getInstance();
        $q = $request->query();
        $page = max(0, (int) ($q['page'] ?? 0));
        $size = max(1, min(100, (int) ($q['size'] ?? 50)));

        $tenantId = TenantContext::get();
        $isSuperAdmin = TenantContext::isSuperAdmin();

        if ($tenantId === null && !$isSuperAdmin) {
            Response::error('Tenant no especificado', 400);
            return;
        }

        $conditions = [];
        $params = [];

        if ($isSuperAdmin) {
            $tidFilter = !empty($q['tenant_id']) ? (int) $q['tenant_id'] : null;
            if ($tidFilter !== null) {
                $conditions[] = 'lr.tenant_id = :tenant_id';
                $params[':tenant_id'] = $tidFilter;
            }
        } else {
            $conditions[] = 'lr.tenant_id = :tenant_id';
            $params[':tenant_id'] = $tenantId;
        }

        if (!empty($q['fecha_inicio'])) {
            $conditions[] = 'lr.fecha_inicio >= :fecha_inicio';
            $params[':fecha_inicio'] = $q['fecha_inicio'];
        }
        if (!empty($q['fecha_fin'])) {
            $conditions[] = 'lr.fecha_inicio <= :fecha_fin';
            $params[':fecha_fin'] = $q['fecha_fin'];
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $total = $db->count(
            "SELECT COUNT(*) FROM cdr_llamadas_real lr $where",
            $params
        );

        $rows = $db->fetchAll(
            "SELECT lr.id, lr.tenant_id, lr.pbx_id, lr.linkedid,
                    lr.fecha_inicio, lr.fecha_fin, lr.total_segmentos,
                    lr.duracion_total, lr.tiempo_total_conversacion,
                    lr.created_at, lr.updated_at
             FROM cdr_llamadas_real lr
             $where
             ORDER BY lr.fecha_inicio DESC
             LIMIT :limit OFFSET :offset",
            array_merge($params, [':limit' => $size, ':offset' => $page * $size])
        );

        Response::paginated($rows, $page, $size, $total);
    }

    /**
     * GET /api/cdr-report/stats
     *
     * KPI cards: total llamadas, total contestadas, total colas, efectividad.
     */
    public function stats(Request $request): void
    {
        $db = Database::getInstance();
        $q = $request->query();
        $tenantId = TenantContext::get();
        $isSuperAdmin = TenantContext::isSuperAdmin();

        if ($tenantId === null && !$isSuperAdmin) {
            Response::error('Tenant no especificado', 400);
            return;
        }

        $conditions = [];
        $params = [];

        if ($isSuperAdmin) {
            $tidFilter = !empty($q['tenant_id']) ? (int) $q['tenant_id'] : null;
            if ($tidFilter !== null) {
                $conditions[] = 'tenant_id = :tenant_id';
                $params[':tenant_id'] = $tidFilter;
            }
        } else {
            $conditions[] = 'tenant_id = :tenant_id';
            $params[':tenant_id'] = $tenantId;
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $llamadas = $db->fetchOne(
            "SELECT COUNT(*) as total,
                    SUM(CASE WHEN estado_final IN ('ANSWERED','ANSWER') THEN 1 ELSE 0 END) as contestadas,
                    SUM(CASE WHEN estado_final IN ('NOANSWER','NO ANSWER','CANCELLED','CANCEL') THEN 1 ELSE 0 END) as no_contestadas,
                    SUM(CASE WHEN estado_final IN ('BUSY','CONGESTION') THEN 1 ELSE 0 END) as ocupadas,
                    SUM(CASE WHEN estado_final IN ('FAILED','CHANUNAVAIL') THEN 1 ELSE 0 END) as fallidas
             FROM cdr_llamadas $where",
            $params
        );

        $colas = $db->fetchOne(
            "SELECT COUNT(DISTINCT numero_cola) as total FROM cdr_colas_resumen $where",
            $params
        );

        $agentes = $db->fetchOne(
            "SELECT COUNT(DISTINCT extension_agente) as total FROM cdr_agentes_resumen $where",
            $params
        );

        $total = (int) ($llamadas['total'] ?? 0);
        $contestadas = (int) ($llamadas['contestadas'] ?? 0);
        $efectividad = $total > 0 ? (int) round($contestadas / $total * 100) : 0;

        Response::ok([
            'total_llamadas' => $total,
            'contestadas' => $contestadas,
            'no_contestadas' => (int) ($llamadas['no_contestadas'] ?? 0),
            'ocupadas' => (int) ($llamadas['ocupadas'] ?? 0),
            'fallidas' => (int) ($llamadas['fallidas'] ?? 0),
            'total_colas' => (int) ($colas['total'] ?? 0),
            'total_agentes' => (int) ($agentes['total'] ?? 0),
            'efectividad' => $efectividad,
        ]);
    }
}
