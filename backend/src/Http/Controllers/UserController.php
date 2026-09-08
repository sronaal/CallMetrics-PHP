<?php
declare(strict_types=1);

namespace CallMetrics\Http\Controllers;

use CallMetrics\Core\{Request, Response, TenantContext};
use CallMetrics\Models\User;

class UserController extends Controller
{
    /**
     * GET /api/usuarios?page=0&size=10&search=
     */
    public function index(Request $request): void
    {
        $page = max(0, (int) ($request->query()['page'] ?? 0));
        $size = max(1, min(100, (int) ($request->query()['size'] ?? 10)));
        $search = $request->query()['search'] ?? '';

        $result = User::paginate($page, $size, [], $search, ['nombre', 'email']);

        // Limpiar datos sensibles
        foreach ($result['data'] as &$user) {
            unset($user['password_hash']);
            unset($user['updated_at']);
        }

        Response::paginated($result['data'], $page, $size, $result['total']);
    }

    /**
     * GET /api/usuarios/{id}
     */
    public function show(Request $request): void
    {
        $id = (int) $request->param('id');
        $user = User::find($id);

        if (!$user) {
            Response::notFound('Usuario no encontrado');
        }

        unset($user['password_hash']);
        Response::ok($user);
    }

    /**
     * POST /api/usuarios
     */
    public function store(Request $request): void
    {
        $data = $request->body();
        $errors = [];

        if (empty($data['nombre']))  $errors['nombre'] = 'Nombre es requerido';
        if (empty($data['email']))   $errors['email'] = 'Email es requerido';
        if (empty($data['password'])) $errors['password'] = 'Password es requerido';
        if (strlen($data['password'] ?? '') < 6) $errors['password'] = 'Minimo 6 caracteres';

        // ID del tenant desde el contexto (el admin crea usuarios en SU tenant)
        $tenantId = TenantContext::isSuperAdmin()
            ? ($data['tenant_id'] ?? TenantContext::get())
            : TenantContext::get();

        if ($tenantId === null && !TenantContext::isSuperAdmin()) {
            $errors['tenant_id'] = 'Tenant requerido';
        } elseif ($tenantId === 0 && !TenantContext::isSuperAdmin()) {
            $errors['tenant_id'] = 'Tenant requerido';
        }

        // Verificar que el email sea único en el tenant
        if (!empty($data['email']) && $tenantId) {
            if (User::emailExistsInTenant($data['email'], $tenantId)) {
                $errors['email'] = 'Este email ya esta registrado en la empresa';
            }
        }

        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email invalido';
        }

        $rolesValidos = ['SUPER_ADMIN', 'ADMIN_TENANT', 'SUPERVISOR', 'OPERADOR'];
        if (!empty($data['rol']) && !in_array($data['rol'], $rolesValidos)) {
            $errors['rol'] = 'Rol invalido';
        }

        if (!empty($errors)) {
            Response::error('Errores de validacion', 422, $errors);
        }

        $userId = User::createWithPassword([
            'tenant_id'     => $tenantId,
            'nombre'        => trim($data['nombre']),
            'email'         => trim($data['email']),
            'password'      => $data['password'],
            'rol'           => strtoupper($data['rol'] ?? 'OPERADOR'),
            'extension'     => trim($data['extension'] ?? ''),
            'activo'        => 1,
            'primer_ingreso'=> 1,
        ]);

        $user = User::find($userId);
        unset($user['password_hash']);
        Response::created($user, 'Usuario creado correctamente');
    }

    /**
     * PUT /api/usuarios/{id}
     */
    public function update(Request $request): void
    {
        $id = (int) $request->param('id');
        $user = User::find($id);

        if (!$user) {
            Response::notFound('Usuario no encontrado');
        }

        $data = $request->body();
        $errors = [];

        // Verificar unicidad del email si cambió
        if (!empty($data['email']) && $data['email'] !== $user['email']) {
            $tenantId = (int) $user['tenant_id'];
            if (User::emailExistsInTenant($data['email'], $tenantId, $id)) {
                $errors['email'] = 'Este email ya esta registrado en la empresa';
            }
        }

        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email invalido';
        }

        if (!empty($data['password']) && strlen($data['password']) < 6) {
            $errors['password'] = 'Minimo 6 caracteres';
        }

        if (!empty($errors)) {
            Response::error('Errores de validacion', 422, $errors);
        }

        $updateData = array_filter([
            'nombre'    => trim($data['nombre'] ?? ''),
            'email'     => trim($data['email'] ?? ''),
            'rol'       => strtoupper($data['rol'] ?? ''),
            'extension' => trim($data['extension'] ?? ''),
        ], fn($v) => $v !== '');

        if (!empty($data['password'])) {
            User::updateWithPassword($id, array_merge($updateData, ['password' => $data['password']]));
        } else {
            User::update($id, $updateData);
        }

        $updated = User::find($id);
        unset($updated['password_hash']);
        Response::ok($updated, 'Usuario actualizado');
    }

    /**
     * PATCH /api/usuarios/{id}/toggle
     */
    public function toggle(Request $request): void
    {
        $id = (int) $request->param('id');
        $user = User::find($id);

        if (!$user) {
            Response::notFound('Usuario no encontrado');
        }

        // Evitar auto-desactivación
        if ((int) $user['id'] === TenantContext::getUserId()) {
            Response::error('No puedes desactivar tu propia cuenta', 400);
        }

        // Proteger SUPER_ADMIN
        if ($user['rol'] === 'SUPER_ADMIN' && !TenantContext::isSuperAdmin()) {
            Response::forbidden('Solo SUPER_ADMIN puede modificar un SUPER_ADMIN');
        }

        $newStatus = $user['activo'] ? 0 : 1;
        User::update($id, ['activo' => $newStatus]);

        $updated = User::find($id);
        unset($updated['password_hash']);
        Response::ok($updated, $newStatus ? 'Usuario activado' : 'Usuario desactivado');
    }
}
