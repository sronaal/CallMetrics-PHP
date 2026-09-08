<?php
declare(strict_types=1);

namespace CallMetrics\Http\Controllers;

use CallMetrics\Core\{Request, Response, TenantContext};
use CallMetrics\Models\CallRecord;

/**
 * Controlador para registros de llamadas (CDR).
 * Los endpoints de lectura requieren SUPERVISOR; stats y export también requieren SUPERVISOR.
 */
class CallRecordController extends Controller
{
    /**
     * GET /api/llamadas?page=0&size=10&fecha_inicio=&fecha_fin=&estado=&pbx_id=
     *
     * Lista paginada de llamadas con filtros de fecha, estado y PBX.
     */
    public function index(Request $request): void
    {
        $page = max(0, (int) ($request->query()['page'] ?? 0));
        $size = max(1, min(100, (int) ($request->query()['size'] ?? 10)));

        $fechaInicio = $request->query()['fecha_inicio'] ?? null;
        $fechaFin = $request->query()['fecha_fin'] ?? null;
        $estado = $request->query()['estado'] ?? null;
        $pbxId = !empty($request->query()['pbx_id']) ? (int) $request->query()['pbx_id'] : null;

        $result = CallRecord::paginateFiltered($page, $size, $fechaInicio, $fechaFin, $estado, $pbxId);

        foreach ($result['data'] as &$llamada) {
            unset($llamada['created_at']);
        }

        Response::paginated($result['data'], $page, $size, $result['total']);
    }

    /**
     * GET /api/llamadas/{id}
     *
     * Detalle de una llamada específica.
     */
    public function show(Request $request): void
    {
        $id = (int) $request->param('id');
        $llamada = CallRecord::find($id);

        if (!$llamada) {
            Response::notFound('Llamada no encontrada');
        }

        Response::ok($llamada);
    }

    /**
     * GET /api/llamadas/stats
     *
     * Estadísticas del día actual: total, contestadas, perdidas, duración promedio.
     */
    public function stats(Request $request): void
    {
        $tenantId = TenantContext::get();
        $isSuperAdmin = TenantContext::isSuperAdmin();

        if ($tenantId === null && !$isSuperAdmin) {
            Response::error('Tenant no especificado', 400);
        }

        // Super admin puede ver stats globales (sin filtro de tenant)
        if ($isSuperAdmin) {
            $stats = CallRecord::getStatsGlobal();
        } else {
            $stats = CallRecord::getStatsToday($tenantId);
        }
        Response::ok($stats);
    }

    /**
     * GET /api/llamadas/export?fecha_inicio=&fecha_fin=&estado=&pbx_id=
     *
     * Exportar llamadas a CSV. Agrega header Content-Type: text/csv.
     */
    public function export(Request $request): void
    {
        $fechaInicio = $request->query()['fecha_inicio'] ?? null;
        $fechaFin = $request->query()['fecha_fin'] ?? null;
        $estado = $request->query()['estado'] ?? null;
        $pbxId = !empty($request->query()['pbx_id']) ? (int) $request->query()['pbx_id'] : null;

        // Obtener todas las llamadas (sin paginación) para exportar
        $result = CallRecord::paginateFiltered(0, 10000, $fechaInicio, $fechaFin, $estado, $pbxId);

        // Configurar headers para CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="llamadas_' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');

        // Encabezados
        fputcsv($output, [
            'ID', 'PBX ID', 'CallID', 'Ext Origen', 'Ext Destino',
            'Número Origen', 'Número Destino', 'Duración (s)',
            'Billable (s)', 'Estado', 'Inicio', 'Fin', 'Grabación'
        ]);

        // Datos
        foreach ($result['data'] as $llamada) {
            fputcsv($output, [
                $llamada['id'],
                $llamada['pbx_id'],
                $llamada['callid'],
                $llamada['extension_origen'],
                $llamada['extension_destino'],
                $llamada['numero_origen'],
                $llamada['numero_destino'],
                $llamada['duracion'],
                $llamada['billable_seconds'],
                $llamada['estado'],
                $llamada['inicio_llamada'],
                $llamada['fin_llamada'],
                $llamada['grabacion_url'],
            ]);
        }

        fclose($output);
        exit;
    }
}
