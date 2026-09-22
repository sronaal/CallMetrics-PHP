<?php
declare(strict_types=1);

namespace CallMetrics\Http\Controllers;

use CallMetrics\Core\{Request, Response};
use CallMetrics\Models\Event;

/**
 * Clase EventController
 *
 * Controlador de eventos del sistema (AMI, CEL, monitoreo). Los endpoints
 * requieren el rol SUPERVISOR. Proporciona listado paginado con filtros
 * por tipo, nombre de evento, servidor PBX y rango de fechas.
 *
 * @description Parsea el contenido JSON de los eventos para facilitar su
 *              lectura en la respuesta.
 * @package CallMetrics\Http\Controllers
 */
class EventController extends Controller
{
    /**
     * Lista paginada de eventos con filtros por tipo, nombre, PBX y rango de fechas.
     *
     * @description Retorna una página de eventos del sistema con filtros opcionales.
     *              El campo contenido se parsea de JSON a array para facilitar la lectura.
     *
     * @param Request $request Solicitud con parámetros de query: page, size, tipo, evento, pbx_id, fecha_inicio, fecha_fin
     * @return void Nunca retorna — termina con Response
     */
    public function index(Request $request): void
    {
        $page = max(0, (int) ($request->query()['page'] ?? 0));
        $size = max(1, min(100, (int) ($request->query()['size'] ?? 10)));

        $tipo = $request->query()['tipo'] ?? null;
        $evento = $request->query()['evento'] ?? null;
        $pbxId = !empty($request->query()['pbx_id']) ? (int) $request->query()['pbx_id'] : null;
        $fechaInicio = $request->query()['fecha_inicio'] ?? null;
        $fechaFin = $request->query()['fecha_fin'] ?? null;

        $result = Event::paginateFiltered($page, $size, $tipo, $evento, $pbxId, $fechaInicio, $fechaFin);

        foreach ($result['data'] as &$ev) {
            // Parsear contenido JSON para facilitar lectura
            if (is_string($ev['contenido'])) {
                $ev['contenido'] = json_decode($ev['contenido'], true);
            }
        }

        Response::paginated($result['data'], $page, $size, $result['total']);
    }

    /**
     * Muestra el detalle de un evento específico.
     *
     * @description Retorna los datos de un evento por su ID, con el campo
     *              contenido parseado de JSON a array.
     *
     * @param Request $request Solicitud con parámetro de ruta: id
     * @return void Nunca retorna — termina con Response
     */
    public function show(Request $request): void
    {
        $id = (int) $request->param('id');
        $evento = Event::find($id);

        if (!$evento) {
            Response::notFound('Evento no encontrado');
        }

        // Parsear contenido JSON
        if (is_string($evento['contenido'])) {
            $evento['contenido'] = json_decode($evento['contenido'], true);
        }

        Response::ok($evento);
    }
}
