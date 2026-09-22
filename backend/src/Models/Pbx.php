<?php
declare(strict_types=1);

namespace CallMetrics\Models;

use CallMetrics\Core\Database;

/**
 * Clase Pbx
 *
 * Modelo de servidores PBX — filtrado por tenant. Almacena configuración de
 * conexiones a servidores Asterisk/FreePBX. Soporta autenticación por token
 * de agente y búsqueda por UUID del agente collector.
 *
 * @description Modelo multi-tenant para gestión de servidores PBX. Los tokens
 *              se almacenan como hashes SHA-256 por seguridad. La búsqueda por
 *              agente_id es cross-tenant ya que el agente se autentica por su UUID.
 * @package CallMetrics\Models
 */
class Pbx extends BaseModel
{
    /** @var string Nombre de la tabla en la base de datos */
    protected static string $table = 'pbx';

    /** @var bool Filtrado por tenant habilitado */
    protected static bool $tenantScoped = true;

    /**
     * Genera un hash SHA-256 del token para almacenamiento seguro.
     *
     * @description Crea un hash unidireccional del token utilizando SHA-256.
     *              Los tokens nunca se almacenan en texto plano en la BD.
     *
     * @param string $token Token de autenticación del agente
     * @return string Hash SHA-256 del token en formato hexadecimal
     */
    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Busca un servidor PBX por su token de autenticación de agente.
     *
     * @description Compara hashes SHA-256 — nunca almacena tokens en texto plano.
     *              Utilizado para autenticar solicitudes del agente collector.
     *
     * @param string $token Token de autenticación a buscar
     * @return ?array Datos del servidor PBX o null si no existe el token
     */
    public static function findByToken(string $token): ?array
    {
        $hashed = self::hashToken($token);
        return parent::findBy('token_agente', $hashed);
    }

    /**
     * Busca un servidor PBX por el UUID del agente collector.
     *
     * @description Compatible con agente Python (X-Agent-ID) y agente Spring/Java.
     *              Usa findBy() que NO aplica filtro de tenant (búsqueda cross-tenant).
     *              Esto es intencional: el agente se autentica por su UUID, no por tenant.
     *
     * @param string $agenteId UUID del agente collector a buscar
     * @return ?array Datos del servidor PBX o null si no existe el agente
     */
    public static function findByAgenteId(string $agenteId): ?array
    {
        return parent::findBy('agente_id', $agenteId);
    }

    /**
     * Cuenta extensiones registradas en un servidor PBX específico.
     *
     * @description Ejecuta un COUNT sobre la tabla extensiones filtrando por pbx_id.
     *              Útil para mostrar estadísticas del servidor PBX.
     *
     * @param int $pbxId ID del servidor PBX
     * @return int Número total de extensiones en el servidor
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
     * Cuenta llamadas registradas hoy en un servidor PBX específico.
     *
     * @description Ejecuta un COUNT sobre la tabla llamadas_cdr filtrando por pbx_id
     *              y fecha actual. Útil para métricas en tiempo real del dashboard.
     *
     * @param int $pbxId ID del servidor PBX
     * @return int Número de llamadas registradas hoy
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
