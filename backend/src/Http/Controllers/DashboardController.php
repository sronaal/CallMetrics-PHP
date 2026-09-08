<?php
declare(strict_types=1);

namespace CallMetrics\Http\Controllers;

use CallMetrics\Core\{Request, Response, Database, TenantContext};

/**
 * Controlador del dashboard — resumen de KPIs y metricas en tiempo real.
 */
class DashboardController extends Controller
{
    /**
     * GET /api/dashboard/summary
     *
     * Retorna KPIs consolidados: usuarios, llamadas, agentes, alertas y estado PBX.
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

        Response::ok([
            'totalEmpresas'    => $empresas,
            'totalUsuarios'    => $usuarios,
            'usuariosActivos'  => $usuariosActivos,
            'llamadasHoy'      => $llamadasHoy,
            'llamadasActivas'  => $llamadasActivas,
            'agentesActivos'   => $agentesActivos,
            'alertasActivas'   => $alertasActivas,
            'pbxOnline'        => $pbxOnline,
        ]);
    }
}
