<?php
declare(strict_types=1);

namespace CallMetrics\Http\Controllers;

use CallMetrics\Core\{Request, Response};
use CallMetrics\Models\Tenant;

/**
 * Clase TenantController
 *
 * Controlador CRUD para empresas (tenants). Todos los endpoints requieren
 * el rol SUPER_ADMIN. Gestiona la creación, actualización, eliminación
 * y activación/desactivación de empresas en el sistema multi-tenant.
 *
 * @description Implementa operaciones CRUD completas con validación de unicidad
 *              de NIT, conteo de usuarios asociados y protección contra eliminación
 *              de empresas con usuarios activos.
 * @package CallMetrics\Http\Controllers
 */
class TenantController extends Controller
{
    /**
     * Lista paginada de empresas con búsqueda por nombre o NIT.
     *
     * @description Retorna una página de tenants enriquecidos con el conteo de
     *              usuarios asociados. Soporta parámetros page, size y search.
     *
     * @param Request $request Solicitud con parámetros de query: page, size, search
     * @return void Nunca retorna — termina con Response
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
     * Muestra el detalle de una empresa específica.
     *
     * @description Retorna los datos de un tenant por su ID, enriquecido con
     *              el conteo de usuarios asociados.
     *
     * @param Request $request Solicitud con parámetro de ruta: id
     * @return void Nunca retorna — termina con Response
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
     * Crea una nueva empresa.
     *
     * @description Valida campos requeridos (nombre, nit, email), verifica unicidad
     *              de NIT y formato de email. Establece plan por defecto 'FREE'.
     *
     * @param Request $request Solicitud con datos de la empresa en el body
     * @return void Nunca retorna — termina con Response
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
     * Actualiza una empresa existente.
     *
     * @description Valida unicidad de NIT si cambió y formato de email.
     *              Solo actualiza los campos proporcionados (no vacíos).
     *
     * @param Request $request Solicitud con parámetro de ruta: id y datos en el body
     * @return void Nunca retorna — termina con Response
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
     * Elimina una empresa y todos sus datos asociados.
     *
     * @description No permite eliminar si tiene usuarios asociados. Las FKs con
     *              ON DELETE CASCADE se encargan de pbx, extensiones, colas,
     *              agentes, llamadas_cdr, eventos, reglas_alerta, etc.
     *              usuarios.tenant_id usa ON DELETE SET NULL (no borra usuarios).
     *
     * @param Request $request Solicitud con parámetro de ruta: id
     * @return void Nunca retorna — termina con Response
     */
    public function delete(Request $request): void
    {
        $id = (int) $request->param('id');
        $tenant = Tenant::find($id);

        if (!$tenant) {
            Response::notFound('Empresa no encontrada');
        }

        // No permitir eliminar si tiene usuarios asociados
        $userCount = Tenant::countUsers($id);
        if ($userCount > 0) {
            Response::error(
                "No se puede eliminar: la empresa tiene {$userCount} usuario(s) asociado(s). Desactívela primero.",
                409
            );
        }

        try {
            Tenant::delete($id);
        } catch (\PDOException $e) {
            Response::error('Error al eliminar: ' . $e->getMessage(), 500);
        }

        Response::ok(null, 'Empresa eliminada correctamente');
    }

    /**
     * Activa o desactiva una empresa (toggle).
     *
     * @description Alterna el estado activo de la empresa (0 ↔ 1).
     *
     * @param Request $request Solicitud con parámetro de ruta: id
     * @return void Nunca retorna — termina con Response
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
