<?php
/* ============================================================
   helpers.php — Utility/format functions extracted from mock.php.

   These are pure helper functions with no mock data dependencies.
   Both mock.php and components.php include this file so pages
   can use them without pulling in the mock data layer.
   ============================================================ */

/* Umbrales de alerta (segundos / porcentaje) — compartidos por
   helpers de color y por las páginas que pintan duraciones. */
const CM_THRESHOLD_DURACION_ALERTA  = 180;
const CM_THRESHOLD_DURACION_CRITICA = 300;
const CM_THRESHOLD_ESPERA_ALERTA    = 60;
const CM_THRESHOLD_ESPERA_CRITICA   = 120;

/** 300 → "5m 0s"; 3661 → "1h 1m 1s"; 45 → "45s" */
function cm_format_duration($segundos): string
{
    $segundos = max(0, (int) $segundos);
    $h = intdiv($segundos, 3600);
    $m = intdiv($segundos % 3600, 60);
    $s = $segundos % 60;
    if ($h > 0) {
        return "{$h}h {$m}m {$s}s";
    }
    if ($m > 0) {
        return "{$m}m {$s}s";
    }
    return "{$s}s";
}

/** Fecha absoluta + sufijo relativo: "04/08/2026, 09:12 · hace 5 min" */
function cm_format_date($timestamp): string
{
    $ts = (int) $timestamp;
    $diff = time() - $ts;
    if ($diff < 60) {
        $relativo = 'ahora';
    } elseif ($diff < 3600) {
        $relativo = 'hace ' . intdiv($diff, 60) . ' min';
    } elseif ($diff < 86400) {
        $relativo = 'hace ' . intdiv($diff, 3600) . 'h';
    } else {
        $relativo = 'hace ' . intdiv($diff, 86400) . 'd';
    }
    return date('d/m/Y, H:i', $ts) . ' · ' . $relativo;
}

/** Número con locale es-ES: 1234.5 → "1.234,5" */
function cm_format_number($numero): string
{
    if ($numero === null || $numero === '') {
        return '—';
    }
    $n = (float) $numero;
    $dec = ($n == floor($n)) ? 0 : 1;
    return number_format($n, $dec, ',', '.');
}

/** Token de color de SLA: ≥90 "ok", ≥75 "warn", si no "bad" */
function cm_sla_color($pct): string
{
    $pct = (float) $pct;
    if ($pct >= 90) {
        return 'ok';
    }
    if ($pct >= 75) {
        return 'warn';
    }
    return 'bad';
}

/** Mapea estados React → etiqueta español neutro */
function cm_status_label($key): string
{
    $mapa = [
        'active'     => 'Activo',
        'break'      => 'Descanso',
        'ringing'    => 'Llamando',
        'on-call'    => 'En llamada',
        'offline'    => 'Desconectado',
        'critical'   => 'Crítico',
        'degraded'   => 'Degradado',
        'online'     => 'En línea',
        'overflow'   => 'Saturada',
        'paused'     => 'En pausa',
        'answered'   => 'Atendida',
        'abandoned'  => 'Abandonada',
        'transferred'=> 'Transferida',
        'trial'      => 'Prueba',
        'suspended'  => 'Suspendida',
        'inactive'   => 'Inactivo',
        'info'       => 'Info',
        'warning'    => 'Advertencia',
        'error'      => 'Error',
        'ok'         => 'OK',
        'activa'     => 'Activa',
        'en_espera'  => 'En espera',
        'grabando'   => 'Grabando',
    ];
    $key = (string) $key;
    if (isset($mapa[$key])) {
        return $mapa[$key];
    }
    return ucfirst(str_replace('_', ' ', $key));
}

/** Traduce claves UI al español neutro (fallback: la propia clave) */
function cm_t($key): string
{
    $dict = [
        'connected'       => 'Conectado',
        'active_calls'    => 'Llamadas activas',
        'uptime'          => 'Tiempo activo',
        'total'           => 'Total',
        'SUPER_ADMIN'     => 'Super Admin',
        'ADMIN_TENANT'    => 'Admin Empresa',
        'ADMIN_EMPRESA'   => 'Admin Empresa',
        'SUPERVISOR'      => 'Supervisor',
        'OPERADOR'        => 'Operador',
        'all'             => 'Todos',
        'search'          => 'Buscar',
        'cancel'          => 'Cancelar',
        'save'            => 'Guardar',
        'delete'          => 'Eliminar',
        'back'            => 'Volver',
    ];
    $key = (string) $key;
    return $dict[$key] ?? $key;
}

/** Color de duración por umbral: >300s rojo, >180s amarillo */
function cm_duracion_class($segundos): string
{
    $segundos = (int) $segundos;
    if ($segundos > CM_THRESHOLD_DURACION_CRITICA) {
        return 'text-danger';
    }
    if ($segundos > CM_THRESHOLD_DURACION_ALERTA) {
        return 'text-warning';
    }
    return '';
}

/** Color de espera en cola: >120s rojo, >60s amarillo */
function cm_espera_class($segundos): string
{
    $segundos = (int) $segundos;
    if ($segundos > CM_THRESHOLD_ESPERA_CRITICA) {
        return 'text-danger';
    }
    if ($segundos > CM_THRESHOLD_ESPERA_ALERTA) {
        return 'text-warning';
    }
    return '';
}
