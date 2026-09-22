<?php
declare(strict_types=1);

namespace CallMetrics\Http\Controllers;

use CallMetrics\Core\{Request, Response, TenantContext};
use CallMetrics\Models\{Extension, Pbx};

/**
 * Clase ExtensionController
 *
 * Controlador CRUD para extensiones SIP. Todos los endpoints requieren
 * el rol ADMIN_TENANT. Gestiona la creación, actualización y activación/
 * desactivación de extensiones telefónicas internas.
 *
 * @description Soporta filtrado por servidor PBX y búsqueda por número o nombre.
 *              Valida la existencia del PBX asociado antes de crear una extensión.
 * @package CallMetrics\Http\Controllers
 */
class ExtensionController extends Controller
{
    /**
     * Lista paginada de extensiones, filtrable por servidor PBX.
     *
     * @description Retorna una página de extensiones con búsqueda por número o nombre
     *              y filtrado opcional por pbx_id.
     *
     * @param Request $request Solicitud con parámetros de query: page, size, search, pbx_id
     * @return void Nunca retorna — termina con Response
     */
    public function index(Request $request): void
    {
        $page = max(0, (int) ($request->query()['page'] ?? 0));
        $size = max(1, min(100, (int) ($request->query()['size'] ?? 10)));
        $search = $request->query()['search'] ?? '';

        $conditions = [];
        if (!empty($request->query()['pbx_id'])) {
            $conditions['pbx_id'] = (int) $request->query()['pbx_id'];
        }

        $result = Extension::paginate($page, $size, $conditions, $search);

        foreach ($result['data'] as &$ext) {
            unset($ext['updated_at']);
        }

        Response::paginated($result['data'], $page, $size, $result['total']);
    }

    /**
     * Muestra el detalle de una extensión específica.
     *
     * @description Retorna los datos de una extensión por su ID.
     *
     * @param Request $request Solicitud con parámetro de ruta: id
     * @return void Nunca retorna — termina con Response
     */
    public function show(Request $request): void
    {
        $id = (int) $request->param('id');
        $extension = Extension::find($id);

        if (!$extension) {
            Response::notFound('Extensión no encontrada');
        }

        unset($extension['updated_at']);
        Response::ok($extension);
    }

    /**
     * Crea una nueva extensión SIP.
     *
     * @description Valida campos requeridos (numero, pbx_id), verifica que el PBX
     *              exista y cree la extensión con estado por defecto OFFLINE.
     *
     * @param Request $request Solicitud con datos de la extensión en el body
     * @return void Nunca retorna — termina con Response
     */
    public function store(Request $request): void
    {
        $data = $request->body();
        $errors = [];

        if (empty($data['numero'])) $errors['numero'] = 'Número es requerido';
        if (empty($data['pbx_id'])) $errors['pbx_id'] = 'Servidor PBX es requerido';

        // Verificar que el PBX exista
        if (!empty($data['pbx_id'])) {
            $pbx = Pbx::find((int) $data['pbx_id']);
            if (!$pbx) {
                $errors['pbx_id'] = 'Servidor PBX no encontrado';
            }
        }

        if (!empty($errors)) {
            Response::error('Errores de validación', 422, $errors);
        }

        $tenantId = TenantContext::resolveTenantIdForWrite($request);
        if ($tenantId === null) {
            Response::error('Tenant no especificado', 400);
        }

        $extId = Extension::create([
            'tenant_id' => $tenantId,
            'pbx_id'    => (int) $data['pbx_id'],
            'numero'    => trim($data['numero']),
            'nombre'    => trim($data['nombre'] ?? ''),
            'tipo'      => strtoupper($data['tipo'] ?? 'SIP'),
            'estado'    => 'OFFLINE',
            'activo'    => 1,
        ]);

        $extension = Extension::find($extId);
        unset($extension['updated_at']);
        Response::created($extension, 'Extensión creada correctamente');
    }

    /**
     * Actualiza una extensión existente.
     *
     * @description Solo actualiza los campos proporcionados (no vacíos ni null).
     *
     * @param Request $request Solicitud con parámetro de ruta: id y datos en el body
     * @return void Nunca retorna — termina con Response
     */
    public function update(Request $request): void
    {
        $id = (int) $request->param('id');
        $extension = Extension::find($id);

        if (!$extension) {
            Response::notFound('Extensión no encontrada');
        }

        $data = $request->body();

        $updateData = array_filter([
            'numero' => trim($data['numero'] ?? ''),
            'nombre' => trim($data['nombre'] ?? ''),
            'tipo'   => strtoupper($data['tipo'] ?? ''),
            'pbx_id' => isset($data['pbx_id']) ? (int) $data['pbx_id'] : null,
        ], fn($v) => $v !== '' && $v !== null);

        Extension::update($id, $updateData);

        $updated = Extension::find($id);
        unset($updated['updated_at']);
        Response::ok($updated, 'Extensión actualizada');
    }

    /**
     * Activa o desactiva una extensión (toggle).
     *
     * @description Alterna el estado activo de la extensión (0 ↔ 1).
     *
     * @param Request $request Solicitud con parámetro de ruta: id
     * @return void Nunca retorna — termina con Response
     */
    public function toggle(Request $request): void
    {
        $id = (int) $request->param('id');
        $extension = Extension::find($id);

        if (!$extension) {
            Response::notFound('Extensión no encontrada');
        }

        $newStatus = $extension['activo'] ? 0 : 1;
        Extension::update($id, ['activo' => $newStatus]);

        $updated = Extension::find($id);
        unset($updated['updated_at']);
        Response::ok($updated, $newStatus ? 'Extensión activada' : 'Extensión desactivada');
    }
}
