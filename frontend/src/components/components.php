<?php
/* ============================================================
   components.php — partials renderizables compartidos (DRY).

   Cada función devuelve HTML de un componente reutilizable por
   todas las páginas del port. Consumen helpers de mock.php
   (cm_status_label, cm_t, cm_format_number).

   Convención de badges: cm-badge + token de color
   (ok|warn|bad|info|violet|muted) estilizados en pages.css.
   ============================================================ */

require_once __DIR__ . '/../data/mock.php';

/**
 * Token de color de badge para un estado mock.
 * @internal
 */
function cm_badge_token($estado): string
{
    $token = [
        // Verde: saludable / atendido
        'online'    => 'ok',
        'active'    => 'ok',
        'ok'        => 'ok',
        'answered'  => 'ok',
        // Amarillo: degradado / pausado / prueba
        'degraded'  => 'warn',
        'break'     => 'warn',
        'paused'    => 'warn',
        'warning'   => 'warn',
        'trial'     => 'warn',
        // Rojo: crítico / caído / abandonado
        'critical'  => 'bad',
        'error'     => 'bad',
        'abandoned' => 'bad',
        'suspended' => 'bad',
        'overflow'  => 'bad',
        // Azul: en llamada / informativo
        'on-call'   => 'info',
        'info'      => 'info',
        // Violeta: timbrando
        'ringing'   => 'violet',
        // Gris: inactivo / sin conexión
        'offline'   => 'muted',
        'inactive'  => 'muted',
    ];
    $estado = (string) $estado;
    return $token[$estado] ?? 'muted';
}

/** Badge de estado: `<span class="cm-badge cm-badge-{token}">Label</span>` */
function cm_render_badge($estado): string
{
    $token = cm_badge_token($estado);
    $label = htmlspecialchars(cm_status_label($estado), ENT_QUOTES);
    return '<span class="cm-badge cm-badge-' . htmlspecialchars($token) . '">' . $label . '</span>';
}

/** Cabecera de tabla: array de etiquetas → `<thead>` */
function cm_render_table_headers(array $encabezados): string
{
    $html = '<thead><tr>';
    foreach ($encabezados as $titulo) {
        $html .= '<th scope="col">' . htmlspecialchars($titulo) . '</th>';
    }
    return $html . '</tr></thead>';
}

/**
 * Paginación Bootstrap. $base es el prefijo de URL completo con
 * query ya incluida (termina en '?' o '&') y se le concatena page=N.
 */
function cm_render_pagination($pagina, $totalPaginas, $base): string
{
    $pagina = max(1, (int) $pagina);
    $totalPaginas = max(1, (int) $totalPaginas);
    if ($totalPaginas <= 1) {
        return '';
    }

    $html = '<nav aria-label="Paginación"><ul class="pagination pagination-sm justify-content-end cm-pagination mb-0">';

    $anterior = $pagina - 1;
    $html .= '<li class="page-item' . ($pagina <= 1 ? ' disabled' : '') . '">'
        . '<a class="page-link" href="' . htmlspecialchars($base) . 'page=' . max(1, $anterior) . '" aria-label="Anterior">'
        . '<i class="bi bi-chevron-left"></i></a></li>';

    for ($i = 1; $i <= $totalPaginas; $i++) {
        $html .= '<li class="page-item' . ($i === $pagina ? ' active' : '') . '">'
            . '<a class="page-link" href="' . htmlspecialchars($base) . 'page=' . $i . '">' . $i . '</a></li>';
    }

    $siguiente = $pagina + 1;
    $html .= '<li class="page-item' . ($pagina >= $totalPaginas ? ' disabled' : '') . '">'
        . '<a class="page-link" href="' . htmlspecialchars($base) . 'page=' . min($totalPaginas, $siguiente) . '" aria-label="Siguiente">'
        . '<i class="bi bi-chevron-right"></i></a></li>';

    return $html . '</ul></nav>';
}

/**
 * Convierte color hex a rgba(). @internal
 */
function cm_hex_to_rgba($hex, $alpha): string
{
    $hex = ltrim((string) $hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    if (strlen($hex) !== 6) {
        return 'rgba(79,110,247,' . $alpha . ')';
    }
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    return "rgba({$r},{$g},{$b},{$alpha})";
}

/**
 * Tarjeta de métrica compacta (estilo stat card).
 * $color = hex del acento; $icon = clase Bootstrap Icons (ej. bi-activity).
 */
function cm_render_stat_card($label, $value, $color = null, $icon = null): string
{
    $color = $color ?? '#4f6ef7';
    $iconHtml = '';
    if ($icon) {
        $bg = 'background:' . cm_hex_to_rgba($color, 0.14);
        $iconHtml = '<span class="cm-stat-card-icon" style="' . $bg . '">'
            . '<i class="bi ' . htmlspecialchars($icon) . '" style="color:' . htmlspecialchars($color) . '"></i>'
            . '</span>';
    }
    return '<div class="dashboard-card cm-stat-card d-flex align-items-center gap-3">'
        . $iconHtml
        . '<div class="cm-stat-card-body">'
        . '<div class="cm-stat-card-label">' . htmlspecialchars($label) . '</div>'
        . '<div class="cm-stat-card-value" style="color:' . htmlspecialchars($color) . '">' . $value . '</div>'
        . '</div></div>';
}

/**
 * QueueCard del monitor Call Center (estilos en callcenter.css, L3).
 * $cola: id, nombre, en_espera, nivel_servicio_pct, llamadas_hora, estado.
 */
function cm_render_queue_card(array $cola): string
{
    $nombre = htmlspecialchars($cola['nombre'] ?? '—');
    $enEspera = htmlspecialchars((string) ($cola['en_espera'] ?? 0));
    $slaPct = (float) ($cola['nivel_servicio_pct'] ?? 0);
    $slaToken = cm_sla_color($slaPct);
    $slaLabel = htmlspecialchars(cm_format_number($slaPct));
    $llamadasHora = htmlspecialchars((string) ($cola['llamadas_hora'] ?? 0));

    return '<div class="dashboard-card cm-queue-card">'
        . '<div class="cm-queue-card-head d-flex justify-content-between align-items-center gap-2">'
        . '<span class="cm-queue-card-name">' . $nombre . '</span>'
        . cm_render_badge($cola['estado'] ?? 'inactive')
        . '</div>'
        . '<div class="cm-queue-card-body d-flex gap-4 mt-3">'
        . '<div class="cm-queue-metric"><span class="cm-queue-metric-label">En espera</span>'
        . '<span class="cm-queue-metric-value">' . $enEspera . '</span></div>'
        . '<div class="cm-queue-metric"><span class="cm-queue-metric-label">SLA</span>'
        . '<span class="cm-queue-metric-value cm-sla-' . htmlspecialchars($slaToken) . '">' . $slaLabel . '%</span></div>'
        . '<div class="cm-queue-metric"><span class="cm-queue-metric-label">Llamadas/hora</span>'
        . '<span class="cm-queue-metric-value">' . $llamadasHora . '</span></div>'
        . '</div></div>';
}

/**
 * Estado vacío centrado (sin datos / sin permisos).
 * $icon: clase Bootstrap Icons opcional (default bi-inbox).
 */
function cm_render_empty_state($titulo, $mensaje, $ctaUrl = null, $ctaText = null, $icon = 'bi-inbox'): string
{
    $html = '<div class="cm-empty-state">'
        . '<div class="cm-empty-state-icon"><i class="bi ' . htmlspecialchars($icon) . '"></i></div>'
        . '<h3 class="cm-empty-state-title">' . htmlspecialchars($titulo) . '</h3>'
        . '<p class="cm-empty-state-msg">' . htmlspecialchars($mensaje) . '</p>';
    if ($ctaUrl && $ctaText) {
        $html .= '<a class="btn btn-primary btn-sm" href="' . htmlspecialchars($ctaUrl) . '">' . htmlspecialchars($ctaText) . '</a>';
    }
    return $html . '</div>';
}

/**
 * Iniciales para avatar (topbar/sidebar). Nombre distinto al helper
 * cm_agent_initials() de dashboard.php para evitar colisión.
 */
function cm_initials($nombre): string
{
    $partes = explode(' ', (string) $nombre);
    $iniciales = '';
    foreach ($partes as $parte) {
        if ($parte !== '') {
            $iniciales .= mb_strtoupper(mb_substr($parte, 0, 1));
        }
    }
    return $iniciales !== '' ? $iniciales : '—';
}
