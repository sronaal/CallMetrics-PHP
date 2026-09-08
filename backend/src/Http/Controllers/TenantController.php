<?php
declare(strict_types=1);

namespace CallMetrics\Http\Controllers;

use CallMetrics\Core\{Request, Response};
use CallMetrics\Models\Tenant;

/**
 * Controlador CRUD para empresas (tenants).
 * Todos los endpoints requieren el rol SUPER_ADMIN.
 */
class TenantController extends Controller
{
    /**
     * GET /api/tenants?page=0&size=10&search=
     */
    public function index(Request $request): void
    {
        $page   = max(0, (int) ($request->query()['page'] ?? 0));
        $size   = max(1, min(100, (int) ($request->query()['size'] ?? 10)));
        $search = $request->query()['search'] ?? '';

        $result = Tenant::paginate($page, $size, [], $search, ['nombre', 'nit']);

        // Enriquecer con conteo de usuarios
        foreach ($result['data'] as &$tenant) {
            $tenant['usuarios_count'] = Tenant::countUsers((int) $tenant['id']);
            unset($tenant['updated_at']);
        }

        Response::paginated($result['data'], $page, $size, $result['total']);
    }

    /**
     * GET /api/tenants/{id}
     */
    public function show(Request $request): void
    {
        $id = (int) $request->param('id');
        $tenant = Tenant::find($id);

        if (!$tenant) {
            Response::notFound('Empresa no encontrada');
        }

        $tenant['usuarios_count'] = Tenant::countUsers($id);
        unset($tenant['updated_at']);

        Response::ok($tenant);
    }

    /**
     * POST /api/tenants
     */
    public function store(Request $request): void
    {
        $data = $request->body();

        // Validaciones
        $errors = [];
        if (empty($data['nombre'])) $errors['nombre'] = 'Nombre es requerido';
        if (empty($data['nit']))     $errors['nit'] = 'NIT es requerido';
        if (empty($data['email']))   $errors['email'] = 'Email es requerido';

        if (!empty($data['nit']) && Tenant::nitExists($data['nit'])) {
            $errors['nit'] = 'Este NIT ya esta registrado';
        }

        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email invalido';
        }

        if (!empty($errors)) {
            Response::error('Errores de validacion', 422, $errors);
        }

        $tenantId = Tenant::create([
            'nombre'    => trim($data['nombre']),
            'nit'       => trim($data['nit']),
            'email'     => trim($data['email']),
            'telefono'  => trim($data['telefono'] ?? ''),
            'direccion' => trim($data['direccion'] ?? ''),
            'plan'      => strtoupper($data['plan'] ?? 'FREE'),
            'activo'    => 1,
        ]);

        $tenant = Tenant::find($tenantId);
        Response::created($tenant, 'Empresa creada correctamente');
    }

    /**
     * PUT /api/tenants/{id}
     */
    public function update(Request $request): void
    {
        $id = (int) $request->param('id');
        $tenant = Tenant::find($id);

        if (!$tenant) {
            Response::notFound('Empresa no encontrada');
        }

        $data = $request->body();
        $errors = [];

        // Verificar unicidad del NIT si cambió
        if (!empty($data['nit']) && $data['nit'] !== $tenant['nit']) {
            if (Tenant::nitExists($data['nit'], $id)) {
                $errors['nit'] = 'Este NIT ya esta registrado';
            }
        }

        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email invalido';
        }

        if (!empty($errors)) {
            Response::error('Errores de validacion', 422, $errors);
        }

        $updateData = array_filter([
            'nombre'    => trim($data['nombre'] ?? ''),
            'nit'       => trim($data['nit'] ?? ''),
            'email'     => trim($data['email'] ?? ''),
            'telefono'  => trim($data['telefono'] ?? ''),
            'direccion' => trim($data['direccion'] ?? ''),
            'plan'      => strtoupper($data['plan'] ?? ''),
        ], fn($v) => $v !== '');

        Tenant::update($id, $updateData);

        $updated = Tenant::find($id);
        Response::ok($updated, 'Empresa actualizada');
    }

    /**
     * PATCH /api/tenants/{id}/toggle
     */
    public function toggle(Request $request): void
    {
        $id = (int) $request->param('id');
        $tenant = Tenant::find($id);

        if (!$tenant) {
            Response::notFound('Empresa no encontrada');
        }

        $newStatus = $tenant['activo'] ? 0 : 1;
        Tenant::update($id, ['activo' => $newStatus]);

        $updated = Tenant::find($id);
        Response::ok($updated, $newStatus ? 'Empresa activada' : 'Empresa desactivada');
    }
}
