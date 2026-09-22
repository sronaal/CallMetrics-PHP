<?php
declare(strict_types=1);

namespace CallMetrics\Http\Controllers;

use CallMetrics\Core\{Request, Response, TenantContext};
use CallMetrics\Models\CallRecord;

/**
 * Clase CallRecordController
 *
 * Controlador para registros de llamadas (CDR). Los endpoints de lectura
 * requieren SUPERVISOR; stats y export también requieren SUPERVISOR.
 * Soporta paginación con filtros de fecha, estado y servidor PBX.
 *
 * @description Incluye exportación a CSV y estadísticas del día actual.
 *              SUPER_ADMIN puede ver estadísticas globales sin filtro de tenant.
 * @package CallMetrics\Http\Controllers
 */
class CallRecordController extends Controller
{
    /**
     * Lista paginada de llamadas con filtros de fecha, estado y PBX.
     *
     * @description Retorna una página de llamadas CDR con filtros opcionales
     *              por rango de fechas, estado de la llamada y servidor PBX.
     *
     * @param Request $request Solicitud con parámetros de query: page, size, fecha_inicio, fecha_fin, estado, pbx_id
     * @return void Nunca retorna — termina con Response
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
     * Muestra el detalle de una llamada específica.
     *
     * @description Retorna los datos completos de una llamada CDR por su ID.
     *
     * @param Request $request Solicitud con parámetro de ruta: id
     * @return void Nunca retorna — termina con Response
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
     * Obtiene estadísticas del día actual: total, contestadas, perdidas, duración promedio.
     *
     * @description Para SUPER_ADMIN retorna estadísticas globales (sin filtro de tenant).
     *              Para otros roles retorna estadísticas filtradas por tenant.
     *
     * @param Request $request Solicitud actual
     * @return void Nunca retorna — termina con Response
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
     * Exporta llamadas a CSV.
     *
     * @description Obtiene todas las llamadas (sin paginación) con los filtros
     *              aplicados y las exporta a un archivo CSV con encabezados
     *              descriptivos. El archivo se descarga con nombre llamadas_YYYY-MM-DD.csv.
     *
     * @param Request $request Solicitud con parámetros de query: fecha_inicio, fecha_fin, estado, pbx_id
     * @return void Nunca retorna — termina con descarga de archivo
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
