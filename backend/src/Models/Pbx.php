<?php
declare(strict_types=1);

namespace CallMetrics\Models;

use CallMetrics\Core\Database;

/**
 * Modelo de servidores PBX — filtrado por tenant.
 * Almacena configuración de conexiones a servidores Asterisk/FreePBX.
 */
class Pbx extends BaseModel
{
    protected static string $table = 'pbx';
    protected static bool $tenantScoped = true;

    /**
     * Buscar un servidor PBX por su token de autenticación de agente.
     */
    public static function findByToken(string $token): ?array
    {
        return parent::findBy('token_agente', $token);
    }

    /**
     * Contar extensiones registradas en un servidor PBX específico.
     */
    public static function countExtensions(int $pbxId): int
    {
        $db = Database::getInstance();
        $result = $db->fetchOne(
            "SELECT COUNT(*) as total FROM extensiones WHERE pbx_id = :pbx_id",
            [':pbx_id' => $pbxId]
        );
        return (int) $result['total'];
    }

    /**
     * Contar llamadas registradas hoy en un servidor PBX específico.
     */
    public static function countCallsToday(int $pbxId): int
    {
        $db = Database::getInstance();
        $result = $db->fetchOne(
            "SELECT COUNT(*) as total FROM llamadas_cdr WHERE pbx_id = :pbx_id AND DATE(inicio_llamada) = CURDATE()",
            [':pbx_id' => $pbxId]
        );
        return (int) $result['total'];
    }
}
