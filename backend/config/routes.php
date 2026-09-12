<?php
declare(strict_types=1);

// Route registry: [method, URI, Controller@action, requires_auth, min_role?]

return [
    // Auth routes
    ['POST',   '/api/auth/login',            'AuthController@login',         false],
    ['POST',   '/api/auth/refresh',          'AuthController@refresh',       false],
    ['POST',   '/api/auth/logout',           'AuthController@logout',        true],
    ['GET',    '/api/auth/me',               'AuthController@me',            true],
    ['PUT',    '/api/auth/password',         'AuthController@changePassword', true],
    ['PUT',    '/api/auth/primer-ingreso',   'AuthController@primerIngreso', false],

    // Tenant routes (SUPER_ADMIN only)
    ['GET',    '/api/tenants',               'TenantController@index',       true, 'SUPER_ADMIN'],
    ['GET',    '/api/tenants/{id}',          'TenantController@show',        true, 'SUPER_ADMIN'],
    ['POST',   '/api/tenants',               'TenantController@store',       true, 'SUPER_ADMIN'],
    ['PUT',    '/api/tenants/{id}',          'TenantController@update',      true, 'SUPER_ADMIN'],
    ['PATCH',  '/api/tenants/{id}/toggle',   'TenantController@toggle',      true, 'SUPER_ADMIN'],

    // User routes
    ['GET',    '/api/usuarios',              'UserController@index',         true],
    ['GET',    '/api/usuarios/{id}',         'UserController@show',          true],
    ['POST',   '/api/usuarios',              'UserController@store',         true, 'ADMIN_TENANT'],
    ['PUT',    '/api/usuarios/{id}',         'UserController@update',        true, 'ADMIN_TENANT'],
    ['PATCH',  '/api/usuarios/{id}/toggle',  'UserController@toggle',        true, 'ADMIN_TENANT'],

    // Dashboard routes
    ['GET',    '/api/dashboard/summary',     'DashboardController@summary',  true],

    // PBX routes (ADMIN_TENANT+)
    ['GET',    '/api/pbx',               'PbxController@index',            true, 'ADMIN_TENANT'],
    ['GET',    '/api/pbx/{id}',          'PbxController@show',             true, 'ADMIN_TENANT'],
    ['POST',   '/api/pbx',               'PbxController@store',            true, 'ADMIN_TENANT'],
    ['PUT',    '/api/pbx/{id}',          'PbxController@update',           true, 'ADMIN_TENANT'],
    ['PATCH',  '/api/pbx/{id}/toggle',   'PbxController@toggle',           true, 'ADMIN_TENANT'],

    // Extensiones routes (ADMIN_TENANT+)
    ['GET',    '/api/extensiones',              'ExtensionController@index',            true, 'ADMIN_TENANT'],
    ['GET',    '/api/extensiones/{id}',         'ExtensionController@show',             true, 'ADMIN_TENANT'],
    ['POST',   '/api/extensiones',              'ExtensionController@store',            true, 'ADMIN_TENANT'],
    ['PUT',    '/api/extensiones/{id}',         'ExtensionController@update',           true, 'ADMIN_TENANT'],
    ['PATCH',  '/api/extensiones/{id}/toggle',  'ExtensionController@toggle',           true, 'ADMIN_TENANT'],

    // Colas routes (ADMIN_TENANT+)
    ['GET',    '/api/colas',              'QueueController@index',            true, 'ADMIN_TENANT'],
    ['GET',    '/api/colas/{id}',         'QueueController@show',             true, 'ADMIN_TENANT'],
    ['POST',   '/api/colas',              'QueueController@store',            true, 'ADMIN_TENANT'],
    ['PUT',    '/api/colas/{id}',         'QueueController@update',           true, 'ADMIN_TENANT'],
    ['PATCH',  '/api/colas/{id}/toggle',  'QueueController@toggle',           true, 'ADMIN_TENANT'],

    // Agentes routes (ADMIN_TENANT+)
    ['GET',    '/api/agentes',              'AgentController@index',            true, 'ADMIN_TENANT'],
    ['GET',    '/api/agentes/{id}',         'AgentController@show',             true, 'ADMIN_TENANT'],
    ['POST',   '/api/agentes',              'AgentController@store',            true, 'ADMIN_TENANT'],
    ['PUT',    '/api/agentes/{id}',         'AgentController@update',           true, 'ADMIN_TENANT'],
    ['PATCH',  '/api/agentes/{id}/toggle',  'AgentController@toggle',           true, 'ADMIN_TENANT'],

    // Llamadas routes (SUPERVISOR+)
    ['GET',    '/api/llamadas',              'CallRecordController@index',       true, 'SUPERVISOR'],
    ['GET',    '/api/llamadas/stats',        'CallRecordController@stats',       true, 'SUPERVISOR'],
    ['GET',    '/api/llamadas/export',       'CallRecordController@export',      true, 'SUPERVISOR'],
    ['GET',    '/api/llamadas/{id}',         'CallRecordController@show',        true, 'SUPERVISOR'],

    // Eventos routes (SUPERVISOR+)
    ['GET',    '/api/eventos',              'EventController@index',            true, 'SUPERVISOR'],
    ['GET',    '/api/eventos/{id}',         'EventController@show',             true, 'SUPERVISOR'],

    // Alertas routes (ADMIN_TENANT+)
    ['GET',    '/api/alertas',              'AlertRuleController@index',        true, 'ADMIN_TENANT'],
    ['GET',    '/api/alertas/{id}',         'AlertRuleController@show',         true, 'ADMIN_TENANT'],
    ['POST',   '/api/alertas',              'AlertRuleController@store',        true, 'ADMIN_TENANT'],
    ['PUT',    '/api/alertas/{id}',         'AlertRuleController@update',       true, 'ADMIN_TENANT'],
    ['PATCH',  '/api/alertas/{id}/toggle',  'AlertRuleController@toggle',       true, 'ADMIN_TENANT'],
    ['GET',    '/api/alertas/{id}/historial','AlertRuleController@history',     true, 'ADMIN_TENANT'],

    // Agent Ingestion routes (token-based auth, no JWT)
    ['POST',   '/api/agent/heartbeat',    'AgentIngestController@heartbeat', false],
    ['POST',   '/api/agent/cdr',          'AgentIngestController@cdr',       false],
    ['POST',   '/api/agent/events',       'AgentIngestController@events',    false],
    ['POST',   '/api/agent/metrics',      'AgentIngestController@metrics',   false],

    // Agent Ingestion routes — v1 prefix (compatible with Python agent & Spring agent)
    ['POST',   '/api/v1/agent/heartbeat', 'AgentIngestController@heartbeat', false],
    ['POST',   '/api/v1/agent/cdr',       'AgentIngestController@cdr',       false],
    ['POST',   '/api/v1/agent/events',    'AgentIngestController@events',    false],
    ['POST',   '/api/v1/agent/metrics',   'AgentIngestController@metrics',   false],
];
