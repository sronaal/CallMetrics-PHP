<?php
/* ============================================================
   mock.php — capa de datos 100% estática del port PHP.

   Expone 13 colecciones en un único schema snake_case (resuelve
   la inconsistencia camelCase de la API vs snake_case del store)
   más helpers reutilizables con prefijo cm_*.

   Acceso lazy vía cm_mock(): la primera llamada construye y
   cachea el dataset; los accessors cm_<coleccion>() devuelven
   cada colección sin exponer estado global.
   ============================================================ */

/* Umbrales de alerta (segundos / porcentaje) — compartidos por
   helpers de color y por las páginas que pintan duraciones. */
const CM_THRESHOLD_DURACION_ALERTA  = 180;
const CM_THRESHOLD_DURACION_CRITICA = 300;
const CM_THRESHOLD_ESPERA_ALERTA    = 60;
const CM_THRESHOLD_ESPERA_CRITICA   = 120;

/**
 * Construye el dataset completo (una sola vez por request).
 * Interno: no llamar directo — usar cm_mock() o los accessors.
 */
function cm_mock_data(): array
{
    $now = time();

    $pbx = [
        ['id' => 1, 'nombre' => 'PBX-1', 'host' => '10.0.0.10', 'puerto' => 5038, 'estado' => 'online',   'version' => '22.5.0', 'uptime' => 34 * 86400 + 45800],
        ['id' => 2, 'nombre' => 'PBX-2', 'host' => '10.0.0.11', 'puerto' => 5038, 'estado' => 'online',   'version' => '22.5.0', 'uptime' => 12 * 86400 + 7200],
        ['id' => 3, 'nombre' => 'PBX-3', 'host' => '10.0.0.12', 'puerto' => 5038, 'estado' => 'degraded', 'version' => '21.4.1', 'uptime' => 6 * 86400 + 3600],
        ['id' => 4, 'nombre' => 'PBX-4', 'host' => '10.0.0.13', 'puerto' => 5038, 'estado' => 'online',   'version' => '22.5.0', 'uptime' => 2 * 86400 + 900],
        ['id' => 5, 'nombre' => 'PBX-5', 'host' => '10.0.0.14', 'puerto' => 5038, 'estado' => 'offline',  'version' => '21.4.1', 'uptime' => 0],
        ['id' => 6, 'nombre' => 'PBX-6', 'host' => '10.0.0.15', 'puerto' => 5038, 'estado' => 'online',   'version' => '20.13.0', 'uptime' => 21 * 86400 + 10800],
    ];

    $agentes = [
        ['id' => 1,  'nombre' => 'Carlos Méndez', 'extension' => '1001', 'estado' => 'active',   'heartbeat' => $now - 20,       'ultima_actividad' => $now - 42],
        ['id' => 2,  'nombre' => 'Ana Rojas',     'extension' => '1002', 'estado' => 'on-call',  'heartbeat' => $now - 8,        'ultima_actividad' => $now - 8],
        ['id' => 3,  'nombre' => 'Miguel Fernández', 'extension' => '1003', 'estado' => 'break',  'heartbeat' => $now - 55,      'ultima_actividad' => $now - 310],
        ['id' => 4,  'nombre' => 'Laura Pérez',   'extension' => '1004', 'estado' => 'ringing',  'heartbeat' => $now - 5,        'ultima_actividad' => $now - 5],
        ['id' => 5,  'nombre' => 'Roberto Kano',  'extension' => '1005', 'estado' => 'active',   'heartbeat' => $now - 30,       'ultima_actividad' => $now - 96],
        ['id' => 6,  'nombre' => 'Sandra Villalba', 'extension' => '1006', 'estado' => 'offline', 'heartbeat' => $now - 3600,    'ultima_actividad' => $now - 7200],
        ['id' => 7,  'nombre' => 'Elena Blanco',  'extension' => '1007', 'estado' => 'on-call',  'heartbeat' => $now - 12,       'ultima_actividad' => $now - 12],
        ['id' => 8,  'nombre' => 'Pablo Torres',  'extension' => '1008', 'estado' => 'active',   'heartbeat' => $now - 25,       'ultima_actividad' => $now - 140],
        ['id' => 9,  'nombre' => 'Diana Gómez',   'extension' => '1009', 'estado' => 'break',    'heartbeat' => $now - 90,       'ultima_actividad' => $now - 600],
        ['id' => 10, 'nombre' => 'Andrés Silva',  'extension' => '1010', 'estado' => 'active',   'heartbeat' => $now - 15,       'ultima_actividad' => $now - 75],
        ['id' => 11, 'nombre' => 'María López',   'extension' => '1011', 'estado' => 'ringing',  'heartbeat' => $now - 10,       'ultima_actividad' => $now - 10],
        ['id' => 12, 'nombre' => 'Javier Ruiz',   'extension' => '1012', 'estado' => 'offline',  'heartbeat' => $now - 7200,     'ultima_actividad' => $now - 86400],
        ['id' => 13, 'nombre' => 'Lucía Castro',  'extension' => '1013', 'estado' => 'active',   'heartbeat' => $now - 40,       'ultima_actividad' => $now - 210],
        ['id' => 14, 'nombre' => 'Hugo Ramos',    'extension' => '1014', 'estado' => 'on-call',  'heartbeat' => $now - 18,       'ultima_actividad' => $now - 18],
    ];

    $eventos = [
        ['id' => 1,  'timestamp' => $now - 45,      'severidad' => 'info',    'tipo' => 'Newchannel',      'pbx' => 'PBX-1', 'evento' => 'Nueva llamada entrante desde +34 612 345 678', 'payload' => ['channel' => 'SIP/trunk-mx-01-00000123', 'caller' => '+34612345678', 'dest' => 'Cola Ventas']],
        ['id' => 2,  'timestamp' => $now - 120,     'severidad' => 'info',    'tipo' => 'QueueCallerJoin', 'pbx' => 'PBX-1', 'evento' => 'Llamada encolada en Ventas', 'payload' => ['queue' => 'ventas', 'position' => 3]],
        ['id' => 3,  'timestamp' => $now - 300,     'severidad' => 'warning', 'tipo' => 'QueueCallerAbandon', 'pbx' => 'PBX-1', 'evento' => 'Cliente abandonó la cola de Ventas tras 4 min', 'payload' => ['queue' => 'ventas', 'espera_seg' => 245]],
        ['id' => 4,  'timestamp' => $now - 480,     'severidad' => 'info',    'tipo' => 'Bridge',          'pbx' => 'PBX-2', 'evento' => 'Llamada conectada con agente 1002', 'payload' => ['agent' => 'Ana Rojas', 'channel' => 'SIP/1002-00000456']],
        ['id' => 5,  'timestamp' => $now - 900,     'severidad' => 'error',   'tipo' => 'Registration',    'pbx' => 'PBX-5', 'evento' => 'Fallo de registro SIP para trunk-nyc-02', 'payload' => ['trunk' => 'trunk-nyc-02', 'reason' => 'timeout']],
        ['id' => 6,  'timestamp' => $now - 1500,    'severidad' => 'info',    'tipo' => 'Hangup',          'pbx' => 'PBX-1', 'evento' => 'Llamada finalizada correctamente (duración 4m 32s)', 'payload' => ['channel' => 'SIP/trunk-mx-01-00000123', 'dur_sec' => 272]],
        ['id' => 7,  'timestamp' => $now - 2100,    'severidad' => 'warning', 'tipo' => 'QueueCallerJoin', 'pbx' => 'PBX-3', 'evento' => 'Cola Soporte con espera superior a 2 minutos', 'payload' => ['queue' => 'soporte', 'wait_seg' => 135]],
        ['id' => 8,  'timestamp' => $now - 3000,    'severidad' => 'info',    'tipo' => 'Newchannel',      'pbx' => 'PBX-2', 'evento' => 'Nueva llamada saliente hacia +1 555 234 5678', 'payload' => ['caller' => '1005', 'dest' => '+15552345678']],
        ['id' => 9,  'timestamp' => $now - 3600,    'severidad' => 'error',   'tipo' => 'Bridge',          'pbx' => 'PBX-3', 'evento' => 'Error AMI al conectar llamada — reintentando', 'payload' => ['error' => 'CHANNEL_UNAVAILABLE', 'channel' => 'SIP/trunk-es-03-0000088']],
        ['id' => 10, 'timestamp' => $now - 5400,    'severidad' => 'info',    'tipo' => 'Registration',    'pbx' => 'PBX-1', 'evento' => 'Tronco SIP trunk-mx-01 registrado correctamente', 'payload' => ['trunk' => 'trunk-mx-01', 'ip' => '187.33.1.44']],
        ['id' => 11, 'timestamp' => $now - 7200,    'severidad' => 'warning', 'tipo' => 'QueueCallerAbandon', 'pbx' => 'PBX-2', 'evento' => 'Abandono en Cobranza: 6 llamadas en los últimos 30 min', 'payload' => ['queue' => 'cobranza', 'abandonos' => 6]],
        ['id' => 12, 'timestamp' => $now - 10800,   'severidad' => 'info',    'tipo' => 'Hangup',          'pbx' => 'PBX-1', 'evento' => 'Llamada transferida a ext. 1042', 'payload' => ['channel' => 'SIP/trunk-mx-01-00000098', 'dest' => '1042']],
    ];

    $empresas = [
        ['id' => 1, 'nombre' => 'Corporación Alpha S.A.', 'plan' => 'Enterprise', 'usuarios' => 42, 'pbx' => 4, 'estado' => 'active',    'creada' => $now - 720 * 86400],
        ['id' => 2, 'nombre' => 'Tecnología Beta S.L.',    'plan' => 'Pro',       'usuarios' => 18, 'pbx' => 2, 'estado' => 'active',    'creada' => $now - 360 * 86400],
        ['id' => 3, 'nombre' => 'Grupo Gamma Corp.',       'plan' => 'Enterprise', 'usuarios' => 31, 'pbx' => 3, 'estado' => 'active',   'creada' => $now - 540 * 86400],
        ['id' => 4, 'nombre' => 'Delta Servicios Ltda.',   'plan' => 'Pro',       'usuarios' => 9,  'pbx' => 1, 'estado' => 'trial',     'creada' => $now - 12 * 86400],
        ['id' => 5, 'nombre' => 'Epsilon Retail',          'plan' => 'Trial',     'usuarios' => 5,  'pbx' => 1, 'estado' => 'trial',     'creada' => $now - 6 * 86400],
        ['id' => 6, 'nombre' => 'Zeta Comunicaciones',     'plan' => 'Pro',       'usuarios' => 14, 'pbx' => 2, 'estado' => 'suspended', 'creada' => $now - 90 * 86400],
    ];

    $usuarios = [
        ['id' => 1,  'usuario' => 'jmendoza', 'nombre' => 'Jorge Mendoza',  'email' => 'jorge.mendoza@corporacionalpha.com', 'rol' => 'SUPER_ADMIN',    'empresa_id' => 1, 'activo' => true],
        ['id' => 2,  'usuario' => 'cperez',   'nombre' => 'Carolina Pérez', 'email' => 'cperez@corporacionalpha.com',        'rol' => 'ADMIN_EMPRESA',  'empresa_id' => 1, 'activo' => true],
        ['id' => 3,  'usuario' => 'dramirez', 'nombre' => 'Diego Ramírez',  'email' => 'dramirez@corporacionalpha.com',      'rol' => 'SUPERVISOR',     'empresa_id' => 1, 'activo' => true],
        ['id' => 4,  'usuario' => 'ngomez',   'nombre' => 'Natalia Gómez',  'email' => 'ngomez@tecnologiabeta.com',          'rol' => 'ADMIN_EMPRESA',  'empresa_id' => 2, 'activo' => true],
        ['id' => 5,  'usuario' => 'rcastro',  'nombre' => 'Ricardo Castro', 'email' => 'rcastro@tecnologiabeta.com',         'rol' => 'SUPERVISOR',     'empresa_id' => 2, 'activo' => true],
        ['id' => 6,  'usuario' => 'lsuarez',  'nombre' => 'Lucía Suárez',   'email' => 'lsuarez@grupo-gamma.com',            'rol' => 'OPERADOR',       'empresa_id' => 3, 'activo' => true],
        ['id' => 7,  'usuario' => 'fmunoz',   'nombre' => 'Felipe Muñoz',   'email' => 'fmunoz@grupo-gamma.com',             'rol' => 'OPERADOR',       'empresa_id' => 3, 'activo' => false],
        ['id' => 8,  'usuario' => 'atorres',  'nombre' => 'Andrea Torres',  'email' => 'atorres@delta-servicios.com',        'rol' => 'SUPERVISOR',     'empresa_id' => 4, 'activo' => true],
        ['id' => 9,  'usuario' => 'projas',   'nombre' => 'Paula Rojas',    'email' => 'projas@epsilon-retail.com',          'rol' => 'OPERADOR',       'empresa_id' => 5, 'activo' => true],
        ['id' => 10, 'usuario' => 'mvega',    'nombre' => 'Mauricio Vega',  'email' => 'mvega@zeta-comunicaciones.com',      'rol' => 'ADMIN_EMPRESA',  'empresa_id' => 6, 'activo' => false],
    ];

    $colas_cc = [
        ['id' => 1, 'nombre' => 'Ventas',      'en_espera' => 31, 'nivel_servicio_pct' => 68.4, 'llamadas_hora' => 47, 'estado' => 'overflow', 'espera_max' => 187],
        ['id' => 2, 'nombre' => 'Soporte',     'en_espera' => 12, 'nivel_servicio_pct' => 82.1, 'llamadas_hora' => 38, 'estado' => 'active',   'espera_max' => 96],
        ['id' => 3, 'nombre' => 'Cobranza',    'en_espera' => 8,  'nivel_servicio_pct' => 91.5, 'llamadas_hora' => 24, 'estado' => 'active',   'espera_max' => 58],
        ['id' => 4, 'nombre' => 'Postventa',   'en_espera' => 4,  'nivel_servicio_pct' => 93.2, 'llamadas_hora' => 17, 'estado' => 'active',   'espera_max' => 41],
        ['id' => 5, 'nombre' => 'Facturación', 'en_espera' => 6,  'nivel_servicio_pct' => 77.8, 'llamadas_hora' => 15, 'estado' => 'active',   'espera_max' => 74],
        ['id' => 6, 'nombre' => 'Calidad',     'en_espera' => 2,  'nivel_servicio_pct' => 95.0, 'llamadas_hora' => 6,  'estado' => 'active',   'espera_max' => 22],
        ['id' => 7, 'nombre' => 'Novedades',   'en_espera' => 9,  'nivel_servicio_pct' => 71.2, 'llamadas_hora' => 19, 'estado' => 'paused',   'espera_max' => 118],
        ['id' => 8, 'nombre' => 'VIP',         'en_espera' => 1,  'nivel_servicio_pct' => 98.1, 'llamadas_hora' => 4,  'estado' => 'active',   'espera_max' => 12],
    ];

    $agentes_cc = [
        ['id' => 1,  'nombre' => 'Carlos Méndez', 'extension' => '1001', 'cola_id' => 1, 'estado' => 'active',   'tiempo_estado' => 540,  'llamadas_hoy' => 23, 'espera_max' => 62],
        ['id' => 2,  'nombre' => 'Ana Rojas',     'extension' => '1002', 'cola_id' => 2, 'estado' => 'on-call',  'tiempo_estado' => 312,  'llamadas_hoy' => 19, 'espera_max' => 48],
        ['id' => 3,  'nombre' => 'Miguel Fernández', 'extension' => '1003', 'cola_id' => 1, 'estado' => 'break',  'tiempo_estado' => 845, 'llamadas_hoy' => 17, 'espera_max' => 55],
        ['id' => 4,  'nombre' => 'Laura Pérez',   'extension' => '1004', 'cola_id' => 3, 'estado' => 'ringing',  'tiempo_estado' => 12,   'llamadas_hoy' => 11, 'espera_max' => 40],
        ['id' => 5,  'nombre' => 'Roberto Kano',  'extension' => '1005', 'cola_id' => 2, 'estado' => 'active',   'tiempo_estado' => 221,  'llamadas_hoy' => 25, 'espera_max' => 71],
        ['id' => 6,  'nombre' => 'Sandra Villalba', 'extension' => '1006', 'cola_id' => 4, 'estado' => 'offline', 'tiempo_estado' => 4200, 'llamadas_hoy' => 0,  'espera_max' => 0],
        ['id' => 7,  'nombre' => 'Elena Blanco',  'extension' => '1007', 'cola_id' => 1, 'estado' => 'on-call',  'tiempo_estado' => 98,   'llamadas_hoy' => 21, 'espera_max' => 52],
        ['id' => 8,  'nombre' => 'Pablo Torres',  'extension' => '1008', 'cola_id' => 5, 'estado' => 'active',   'tiempo_estado' => 403,  'llamadas_hoy' => 14, 'espera_max' => 66],
        ['id' => 9,  'nombre' => 'Diana Gómez',   'extension' => '1009', 'cola_id' => 3, 'estado' => 'break',    'tiempo_estado' => 610,  'llamadas_hoy' => 9,  'espera_max' => 35],
        ['id' => 10, 'nombre' => 'Andrés Silva',  'extension' => '1010', 'cola_id' => 2, 'estado' => 'active',   'tiempo_estado' => 180,  'llamadas_hoy' => 18, 'espera_max' => 44],
        ['id' => 11, 'nombre' => 'María López',   'extension' => '1011', 'cola_id' => 6, 'estado' => 'ringing',  'tiempo_estado' => 7,    'llamadas_hoy' => 6,  'espera_max' => 18],
        ['id' => 12, 'nombre' => 'Javier Ruiz',   'extension' => '1012', 'cola_id' => 1, 'estado' => 'active',   'tiempo_estado' => 730,  'llamadas_hoy' => 26, 'espera_max' => 88],
    ];

    $llamadas_activas = [
        ['origen' => '+34 612 345 678', 'destino' => 'Cola Ventas',    'duracion' => 272, 'estado' => 'activa',      'agente' => 'Carlos M.', 'pbx' => 'PBX-1'],
        ['origen' => '+1 555 234 5678', 'destino' => 'Cola Soporte',   'duracion' => 767, 'estado' => 'en_espera',   'agente' => null,        'pbx' => 'PBX-2'],
        ['origen' => '+34 654 789 012', 'destino' => 'Ext. 1042',      'duracion' => 135, 'estado' => 'activa',      'agente' => 'Ana R.',    'pbx' => 'PBX-1'],
        ['origen' => '+44 7700 123456', 'destino' => 'Cola Ventas',    'duracion' => 45,  'estado' => 'grabando',    'agente' => 'Miguel F.', 'pbx' => 'PBX-1'],
        ['origen' => '+34 699 876 543', 'destino' => 'Cola Postventa', 'duracion' => 438, 'estado' => 'transferida', 'agente' => 'Laura P.',  'pbx' => 'PBX-3'],
        ['origen' => '+52 55 1234 5678','destino' => 'Cola Cobranza',  'duracion' => 89,  'estado' => 'activa',      'agente' => 'Roberto K.', 'pbx' => 'PBX-2'],
        ['origen' => '+34 611 222 333', 'destino' => 'Ext. 2015',      'duracion' => 512, 'estado' => 'activa',      'agente' => 'Sandra V.', 'pbx' => 'PBX-1'],
        ['origen' => '+1 800 555 0199', 'destino' => 'Cola Soporte',   'duracion' => 34,  'estado' => 'en_espera',   'agente' => null,        'pbx' => 'PBX-2'],
        ['origen' => '+34 678 901 234', 'destino' => 'Cola Ventas',    'duracion' => 198, 'estado' => 'grabando',    'agente' => 'Elena B.',  'pbx' => 'PBX-3'],
        ['origen' => '+49 30 1234567',  'destino' => 'Ext. 3087',      'duracion' => 65,  'estado' => 'activa',      'agente' => 'Pablo T.',  'pbx' => 'PBX-1'],
    ];

    $llamadas_cola = [
        ['origen' => '+57 300 111 2233', 'cola' => 'Ventas',    'espera_seg' => 187, 'pbx' => 'PBX-1'],
        ['origen' => '+34 600 222 3344', 'cola' => 'Soporte',   'espera_seg' => 96,  'pbx' => 'PBX-2'],
        ['origen' => '+1 555 010 8899',  'cola' => 'Ventas',    'espera_seg' => 310, 'pbx' => 'PBX-1'],
        ['origen' => '+52 55 9876 5432', 'cola' => 'Cobranza',  'espera_seg' => 58,  'pbx' => 'PBX-2'],
        ['origen' => '+34 601 333 4455', 'cola' => 'Soporte',   'espera_seg' => 145, 'pbx' => 'PBX-3'],
        ['origen' => '+49 170 111 2222', 'cola' => 'Ventas',    'espera_seg' => 65,  'pbx' => 'PBX-1'],
        ['origen' => '+57 310 555 6677', 'cola' => 'Novedades', 'espera_seg' => 118, 'pbx' => 'PBX-2'],
        ['origen' => '+34 655 000 1111', 'cola' => 'Facturación', 'espera_seg' => 22, 'pbx' => 'PBX-1'],
    ];

    $cdr = [
        ['id' => 1,  'fecha' => $now - 3600,     'origen' => '+34 612 345 678', 'destino' => 'Cola Ventas',    'cola' => 'Ventas',    'agente' => 'Carlos M.', 'duracion' => 272, 'resultado' => 'answered'],
        ['id' => 2,  'fecha' => $now - 7200,     'origen' => '+1 555 234 5678', 'destino' => 'Cola Soporte',   'cola' => 'Soporte',   'agente' => 'Ana R.',    'duracion' => 767, 'resultado' => 'answered'],
        ['id' => 3,  'fecha' => $now - 10800,    'origen' => '+34 654 789 012', 'destino' => 'Cola Ventas',    'cola' => 'Ventas',    'agente' => null,        'duracion' => 245, 'resultado' => 'abandoned'],
        ['id' => 4,  'fecha' => $now - 14400,    'origen' => '+44 7700 123456', 'destino' => 'Ext. 1042',      'cola' => 'Ventas',    'agente' => 'Miguel F.', 'duracion' => 45,  'resultado' => 'transferred'],
        ['id' => 5,  'fecha' => $now - 18000,    'origen' => '+34 699 876 543', 'destino' => 'Cola Postventa', 'cola' => 'Postventa', 'agente' => 'Laura P.',  'duracion' => 438, 'resultado' => 'answered'],
        ['id' => 6,  'fecha' => $now - 21600,    'origen' => '+52 55 1234 5678','destino' => 'Cola Cobranza',  'cola' => 'Cobranza',  'agente' => 'Roberto K.', 'duracion' => 89,  'resultado' => 'answered'],
        ['id' => 7,  'fecha' => $now - 25200,    'origen' => '+34 611 222 333', 'destino' => 'Cola Soporte',   'cola' => 'Soporte',   'agente' => 'Sandra V.', 'duracion' => 512, 'resultado' => 'answered'],
        ['id' => 8,  'fecha' => $now - 28800,    'origen' => '+1 800 555 0199', 'destino' => 'Cola Ventas',    'cola' => 'Ventas',    'agente' => null,        'duracion' => 34,  'resultado' => 'abandoned'],
        ['id' => 9,  'fecha' => $now - 32400,    'origen' => '+34 678 901 234', 'destino' => 'Ext. 3087',      'cola' => 'Ventas',    'agente' => 'Elena B.',  'duracion' => 198, 'resultado' => 'transferred'],
        ['id' => 10, 'fecha' => $now - 36000,    'origen' => '+49 30 1234567',  'destino' => 'Cola Ventas',    'cola' => 'Ventas',    'agente' => 'Pablo T.',  'duracion' => 65,  'resultado' => 'answered'],
        ['id' => 11, 'fecha' => $now - 90000,    'origen' => '+34 600 222 3344', 'destino' => 'Cola Soporte',  'cola' => 'Soporte',   'agente' => 'Diana G.',  'duracion' => 320, 'resultado' => 'answered'],
        ['id' => 12, 'fecha' => $now - 100000,   'origen' => '+57 300 111 2233', 'destino' => 'Cola Ventas',   'cola' => 'Ventas',    'agente' => null,        'duracion' => 410, 'resultado' => 'abandoned'],
        ['id' => 13, 'fecha' => $now - 120000,   'origen' => '+34 655 000 1111', 'destino' => 'Cola Facturación', 'cola' => 'Facturación', 'agente' => 'Andrés S.', 'duracion' => 96,  'resultado' => 'answered'],
        ['id' => 14, 'fecha' => $now - 150000,   'origen' => '+49 170 111 2222', 'destino' => 'Ext. 2015',     'cola' => 'Ventas',    'agente' => 'María L.',  'duracion' => 183, 'resultado' => 'transferred'],
    ];

    $reportes_diarios = [];
    $colsReporte = [
        ['cola' => 'Ventas',  'llamadas' => 412, 'atendidas' => 344, 'abandonadas' => 68],
        ['cola' => 'Soporte', 'llamadas' => 298, 'atendidas' => 271, 'abandonadas' => 27],
    ];
    for ($d = 0; $d < 7; $d++) {
        $fecha = strtotime('-' . $d . ' day', strtotime(date('Y-m-d', $now)));
        foreach ($colsReporte as $r) {
            $atendidas = max(0, $r['atendidas'] - $d * 3);
            $abandonadas = max(0, $r['abandonadas'] - $d);
            $llamadas = $atendidas + $abandonadas;
            $reportes_diarios[] = [
                'fecha' => $fecha, 'cola' => $r['cola'], 'llamadas' => $llamadas,
                'atendidas' => $atendidas, 'abandonadas' => $abandonadas,
                'sla_pct' => round(($atendidas / max(1, $llamadas)) * 100, 1),
            ];
        }
    }

    $heartbeats = [
        ['id' => 1,  'pbx_id' => 1, 'timestamp' => $now - 300,   'estado' => 'ok',       'latencia' => 12,  'perdida_pct' => 0.0, 'cpu' => 34, 'ram' => 52, 'disco' => 41, 'servidor' => 'pbx-1.internal'],
        ['id' => 2,  'pbx_id' => 1, 'timestamp' => $now - 600,   'estado' => 'ok',       'latencia' => 18,  'perdida_pct' => 0.2, 'cpu' => 41, 'ram' => 54, 'disco' => 41, 'servidor' => 'pbx-1.internal'],
        ['id' => 3,  'pbx_id' => 1, 'timestamp' => $now - 900,   'estado' => 'ok',       'latencia' => 15,  'perdida_pct' => 0.0, 'cpu' => 38, 'ram' => 51, 'disco' => 41, 'servidor' => 'pbx-1.internal'],
        ['id' => 4,  'pbx_id' => 1, 'timestamp' => $now - 1200,  'estado' => 'warning',  'latencia' => 96,  'perdida_pct' => 1.8, 'cpu' => 74, 'ram' => 63, 'disco' => 43, 'servidor' => 'pbx-1.internal'],
        ['id' => 5,  'pbx_id' => 1, 'timestamp' => $now - 1500,  'estado' => 'ok',       'latencia' => 22,  'perdida_pct' => 0.1, 'cpu' => 47, 'ram' => 55, 'disco' => 42, 'servidor' => 'pbx-1.internal'],
        ['id' => 6,  'pbx_id' => 1, 'timestamp' => $now - 1800,  'estado' => 'ok',       'latencia' => 14,  'perdida_pct' => 0.0, 'cpu' => 33, 'ram' => 50, 'disco' => 42, 'servidor' => 'pbx-1.internal'],
        ['id' => 7,  'pbx_id' => 1, 'timestamp' => $now - 2100,  'estado' => 'warning',  'latencia' => 88,  'perdida_pct' => 1.2, 'cpu' => 71, 'ram' => 66, 'disco' => 44, 'servidor' => 'pbx-1.internal'],
        ['id' => 8,  'pbx_id' => 1, 'timestamp' => $now - 2400,  'estado' => 'ok',       'latencia' => 25,  'perdida_pct' => 0.3, 'cpu' => 44, 'ram' => 53, 'disco' => 43, 'servidor' => 'pbx-1.internal'],
        ['id' => 9,  'pbx_id' => 1, 'timestamp' => $now - 2700,  'estado' => 'critical', 'latencia' => 340, 'perdida_pct' => 6.4, 'cpu' => 94, 'ram' => 88, 'disco' => 79, 'servidor' => 'pbx-1.internal'],
        ['id' => 10, 'pbx_id' => 1, 'timestamp' => $now - 3000,  'estado' => 'ok',       'latencia' => 16,  'perdida_pct' => 0.0, 'cpu' => 36, 'ram' => 51, 'disco' => 43, 'servidor' => 'pbx-1.internal'],
        ['id' => 11, 'pbx_id' => 1, 'timestamp' => $now - 3300,  'estado' => 'ok',       'latencia' => 19,  'perdida_pct' => 0.1, 'cpu' => 39, 'ram' => 52, 'disco' => 43, 'servidor' => 'pbx-1.internal'],
        ['id' => 12, 'pbx_id' => 1, 'timestamp' => $now - 3600,  'estado' => 'warning',  'latencia' => 102, 'perdida_pct' => 2.1, 'cpu' => 78, 'ram' => 69, 'disco' => 45, 'servidor' => 'pbx-1.internal'],
        ['id' => 13, 'pbx_id' => 2, 'timestamp' => $now - 400,   'estado' => 'ok',       'latencia' => 9,   'perdida_pct' => 0.0, 'cpu' => 22, 'ram' => 38, 'disco' => 27, 'servidor' => 'pbx-2.internal'],
        ['id' => 14, 'pbx_id' => 2, 'timestamp' => $now - 800,   'estado' => 'ok',       'latencia' => 11,  'perdida_pct' => 0.0, 'cpu' => 25, 'ram' => 40, 'disco' => 27, 'servidor' => 'pbx-2.internal'],
        ['id' => 15, 'pbx_id' => 2, 'timestamp' => $now - 1200,  'estado' => 'warning',  'latencia' => 67,  'perdida_pct' => 0.9, 'cpu' => 63, 'ram' => 58, 'disco' => 29, 'servidor' => 'pbx-2.internal'],
        ['id' => 16, 'pbx_id' => 3, 'timestamp' => $now - 500,   'estado' => 'ok',       'latencia' => 21,  'perdida_pct' => 0.1, 'cpu' => 31, 'ram' => 47, 'disco' => 35, 'servidor' => 'pbx-3.internal'],
        ['id' => 17, 'pbx_id' => 3, 'timestamp' => $now - 1000,  'estado' => 'critical', 'latencia' => 285, 'perdida_pct' => 5.2, 'cpu' => 89, 'ram' => 81, 'disco' => 72, 'servidor' => 'pbx-3.internal'],
        ['id' => 18, 'pbx_id' => 3, 'timestamp' => $now - 1500,  'estado' => 'warning',  'latencia' => 118, 'perdida_pct' => 2.4, 'cpu' => 69, 'ram' => 64, 'disco' => 38, 'servidor' => 'pbx-3.internal'],
    ];

    $alertas = [
        ['severidad' => 'critical', 'mensaje' => 'Cola Ventas saturada — 31 llamadas en espera',      'tiempo' => $now - 120,  'pbx' => 'PBX-1', 'fuente' => 'Queue Monitor'],
        ['severidad' => 'high',     'mensaje' => 'CPU PBX-3 al 89% — umbral superado (límite: 80%)',  'tiempo' => $now - 300,  'pbx' => 'PBX-3', 'fuente' => 'System Health'],
        ['severidad' => 'medium',   'mensaje' => 'Latencia SIP elevada: 340ms promedio',              'tiempo' => $now - 720,  'pbx' => 'PBX-1', 'fuente' => 'SIP Monitor'],
        ['severidad' => 'high',     'mensaje' => 'Agente "Sandra V." sin respuesta — 5 llamadas perdidas', 'tiempo' => $now - 1080, 'pbx' => 'PBX-1', 'fuente' => 'Agent Monitor'],
        ['severidad' => 'low',      'mensaje' => 'Nuevo tronco SIP registrado: trunk-mx-01',           'tiempo' => $now - 2040, 'pbx' => 'PBX-1', 'fuente' => 'SIP Registry'],
        ['severidad' => 'medium',   'mensaje' => 'PBX-5 sin heartbeats — posible caída',               'tiempo' => $now - 1800, 'pbx' => 'PBX-5', 'fuente' => 'Heartbeat Monitor'],
    ];

    return [
        'pbx'              => $pbx,
        'agentes'          => $agentes,
        'eventos'          => $eventos,
        'usuarios'         => $usuarios,
        'empresas'         => $empresas,
        'colas_cc'         => $colas_cc,
        'agentes_cc'       => $agentes_cc,
        'llamadas_activas' => $llamadas_activas,
        'llamadas_cola'    => $llamadas_cola,
        'cdr'              => $cdr,
        'reportes_diarios' => $reportes_diarios,
        'heartbeats'       => $heartbeats,
        'alertas'          => $alertas,
    ];
}

/**
 * Devuelve el dataset completo (lazy, cacheado por request).
 * Consumidores: accessors cm_<coleccion>() — no acceder directo salvo agregación.
 */
function cm_mock(): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = cm_mock_data();
    }
    return $cache;
}

/* ---------- Accessors por colección (schema snake_case) ---------- */

function cm_pbx(): array
{
    return cm_mock()['pbx'];
}

function cm_agentes(): array
{
    return cm_mock()['agentes'];
}

function cm_eventos(): array
{
    return cm_mock()['eventos'];
}

function cm_usuarios(): array
{
    return cm_mock()['usuarios'];
}

function cm_empresas(): array
{
    return cm_mock()['empresas'];
}

function cm_colas_cc(): array
{
    return cm_mock()['colas_cc'];
}

function cm_agentes_cc(): array
{
    return cm_mock()['agentes_cc'];
}

function cm_llamadas_activas(): array
{
    return cm_mock()['llamadas_activas'];
}

function cm_llamadas_cola(): array
{
    return cm_mock()['llamadas_cola'];
}

function cm_cdr(): array
{
    return cm_mock()['cdr'];
}

function cm_reportes_diarios(): array
{
    return cm_mock()['reportes_diarios'];
}

function cm_heartbeats(): array
{
    return cm_mock()['heartbeats'];
}

function cm_alertas(): array
{
    return cm_mock()['alertas'];
}

/* ---------- Usuario mock ---------- */

/**
 * Usuario autenticado simulado (sin backend).
 * Hook de prueba: definir la constante CM_MOCK_ROL_OVERRIDE antes de
 * incluir mock.php para forzar un rol en los smoke tests de CI.
 */
function cm_usuario_actual(): array
{
    $usuario = [
        'nombre'  => 'Jorge Mendoza',
        'email'   => 'jorge.mendoza@corporacionalpha.com',
        'rol'     => 'SUPER_ADMIN',
        'empresa' => 'Corporación Alpha S.A.',
    ];
    if (defined('CM_MOCK_ROL_OVERRIDE') && CM_MOCK_ROL_OVERRIDE !== '') {
        $usuario['rol'] = CM_MOCK_ROL_OVERRIDE;
    }
    return $usuario;
}

function cm_rol(): string
{
    return cm_usuario_actual()['rol'];
}

function cm_es_admin(): bool
{
    return in_array(cm_rol(), ['SUPER_ADMIN', 'ADMIN_EMPRESA'], true);
}

/* ---------- Helpers de formato y estado ---------- */

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
