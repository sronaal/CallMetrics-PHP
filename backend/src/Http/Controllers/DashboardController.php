<?php
declare(strict_types=1);

namespace CallMetrics\Http\Controllers;

use CallMetrics\Core\{Request, Response, Database, TenantContext};

/**
 * Clase DashboardController
 *
 * Controlador del dashboard — resumen de KPIs y métricas en tiempo real.
 * Proporciona datos consolidados para el panel principal de la aplicación,
 * incluyendo contadores de usuarios, llamadas, agentes, alertas y estado PBX.
 *
 * @description Soporta vistas tanto para usuarios normales (filtrado por tenant)
 *              como para SUPER_ADMIN (vista global sin restricciones de tenant).
 * @package CallMetrics\Http\Controllers
 */
class DashboardController extends Controller
{
    /**
     * Obtiene KPIs consolidados del dashboard.
     *
     * @description Retorna métricas consolidadas: total de empresas (solo SUPER_ADMIN),
     *              total de usuarios, usuarios activos, llamadas del día, llamadas activas,
     *              agentes disponibles, alertas sin notificar y PBX en estado ONLINE.
     *              Para usuarios normales filtra por tenant; para SUPER_ADMIN muestra totales globales.
     *
     * @param Request $request Solicitud actual (no utiliza parámetros específicos)
     * @return void Nunca retorna — termina con Response
     */
    public function summary(Request $request): void
    {
        $db = Database::getInstance();
        $tid = TenantContext::get();
        $isSuperAdmin = TenantContext::isSuperAdmin();

        // Filtro de tenant
        $tenantFilter = $isSuperAdmin ? '' : 'WHERE tenant_id = :tid';
        $params = $isSuperAdmin ? [] : [':tid' => $tid];

        // Total de empresas (solo SUPER_ADMIN)
        $empresas = $isSuperAdmin
            ? (int) $db->fetchOne("SELECT COUNT(*) as t FROM empresas")['t']
            : 0;

        // Total de usuarios
        $usuarios = (int) $db->fetchOne("SELECT COUNT(*) as t FROM usuarios $tenantFilter", $params)['t'];

        // Usuarios activos
        $usuariosActivosFilter = $isSuperAdmin ? 'WHERE activo = 1' : "$tenantFilter AND activo = 1";
        $usuariosActivos = (int) $db->fetchOne(
            "SELECT COUNT(*) as t FROM usuarios $usuariosActivosFilter",
            $params
        )['t'];

        // Llamadas del dia actual
        $llamadasFilter = $isSuperAdmin ? 'WHERE' : 'WHERE tenant_id = :tid AND';
        $llamadasParams = $isSuperAdmin ? [] : [':tid' => $tid];

        $llamadasHoy = (int) $db->fetchOne(
            "SELECT COUNT(*) as t FROM llamadas_cdr $llamadasFilter DATE(inicio_llamada) = CURDATE()",
            $llamadasParams
        )['t'];

        // Llamadas activas (sin fin_llamada)
        $llamadasActivas = (int) $db->fetchOne(
            "SELECT COUNT(*) as t FROM llamadas_cdr $llamadasFilter fin_llamada IS NULL",
            $llamadasParams
        )['t'];

        // Agentes en estado DISPONIBLE
        $agentesActivos = (int) $db->fetchOne(
            "SELECT COUNT(*) as t FROM agentes $llamadasFilter estado = 'DISPONIBLE'",
            $llamadasParams
        )['t'];

        // Alertas sin notificar
        $alertasActivas = (int) $db->fetchOne(
            "SELECT COUNT(*) as t FROM historial_alertas $llamadasFilter notificado = 0",
            $llamadasParams
        )['t'];

        // PBX en estado ONLINE
        $pbxOnline = (int) $db->fetchOne(
            "SELECT COUNT(*) as t FROM pbx $llamadasFilter estado = 'ONLINE'",
            $llamadasParams
        )['t'];

        // Tasa ASR (Answer Seizure Ratio): contestadas / total * 100
        $llamadasStats = $db->fetchOne(
            "SELECT COUNT(*) as total,
                    SUM(CASE WHEN estado = 'ANSWERED' THEN 1 ELSE 0 END) as contestadas,
                    AVG(CASE WHEN estado = 'ANSWERED' THEN duracion ELSE NULL END) as duracion_promedio
             FROM llamadas_cdr $llamadasFilter DATE(inicio_llamada) = CURDATE()",
            $llamadasParams
        );
        $totalLlamadas = (int) ($llamadasStats['total'] ?? 0);
        $contestadas = (int) ($llamadasStats['contestadas'] ?? 0);
        $tasaASR = $totalLlamadas > 0 ? round(($contestadas / $totalLlamadas) * 100, 1) : 0;
        $duracionPromedio = (int) round((float) ($llamadasStats['duracion_promedio'] ?? 0));
        $acdPromedio = intdiv($duracionPromedio, 60) . ':' . str_pad((string) ($duracionPromedio % 60), 2, '0', STR_PAD_LEFT);

        // Total de agentes
        $agentesTotal = (int) $db->fetchOne(
            "SELECT COUNT(*) as t FROM agentes $tenantFilter",
            $params
        )['t'];

        Response::ok([
            'totalEmpresas'    => $empresas,
            'totalUsuarios'    => $usuarios,
            'usuariosActivos'  => $usuariosActivos,
            'llamadasHoy'      => $llamadasHoy,
            'llamadasActivas'  => $llamadasActivas,
            'agentesActivos'   => $agentesActivos,
            'agentesTotal'     => $agentesTotal,
            'alertasActivas'   => $alertasActivas,
            'pbxOnline'        => $pbxOnline,
            'tasaASR'          => $tasaASR,
            'acdPromedio'      => $acdPromedio,
        ]);
    }
}
