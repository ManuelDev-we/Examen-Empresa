<?php

require_once __DIR__ . '/shared.php';
require_once __DIR__ . '/../modelos/Administrador.php';

// ========== MANEJADOR PRINCIPAL ==========
function handleAdminApiRequest(): void
{
    Solicitudes::init();

    $action = $_GET['action'] ?? 'dashboard';

    try {
        $user = Solicitudes::requireActiveCompany();

        $usuarioId = (int) $user['id'];
        $empresaId = (int) $user['active_company']['empresa_id'];

        if ($user['active_company']['rol'] !== 'administrador') {
            Solicitudes::error('Solo los administradores pueden acceder a esto.', 403);
        }

        switch ($action) {
            case 'dashboard':
                Solicitudes::requireMethod('GET');

                $dashboard = Administrador::getDashboard($usuarioId, $empresaId);

                Solicitudes::success($dashboard);
                break;

            case 'add-member':
                Solicitudes::requireMethod('POST');

                $payload = Solicitudes::getJSON();

                $correo = normalizeEmail($payload['correo'] ?? '');
                $telefono = normalizeText($payload['telefono'] ?? '');

                if ($correo === '') {
                    Solicitudes::error('El correo del trabajador es obligatorio.', 422);
                }

                $trabajador = Administrador::addExistingUserByEmail(
                    $usuarioId,
                    $empresaId,
                    $correo,
                    null,
                    $telefono
                );

                Solicitudes::success([
                    'message' => 'Trabajador agregado correctamente.',
                    'trabajador' => $trabajador
                ], 201);

                break;

            case 'assign-task':
                Solicitudes::requireMethod('POST');

                $payload = Solicitudes::getJSON();

                $trabajadorId = (int)($payload['trabajador_id'] ?? 0);
                $titulo = normalizeText($payload['titulo'] ?? '');
                $descripcion = normalizeText($payload['descripcion'] ?? '');
                $fechaLimite = normalizeText($payload['fecha_limite'] ?? '');

                $tarea = Administrador::assignTask(
                    $usuarioId,
                    $empresaId,
                    $trabajadorId,
                    $titulo,
                    $descripcion,
                    $fechaLimite
                );

                Solicitudes::success([
                    'message' => 'Tarea asignada correctamente.',
                    'tarea' => $tarea
                ], 201);

                break;

            case 'review-task':
                Solicitudes::requireMethod('POST');

                $payload = Solicitudes::getJSON();

                $tareaId = (int)($payload['tarea_id'] ?? 0);
                $estado = normalizeText($payload['estado'] ?? '');
                $comentario = normalizeText($payload['comentario'] ?? '');

                $tarea = Administrador::reviewTask(
                    $usuarioId,
                    $empresaId,
                    $tareaId,
                    $estado,
                    $comentario
                );

                Solicitudes::success([
                    'message' => "Tarea marcada como {$estado}.",
                    'tarea' => $tarea
                ]);

                break;

            case 'remove-member':
                Solicitudes::requireMethod('POST');

                $payload = Solicitudes::getJSON();

                $usuarioRemoverId = (int)($payload['usuario_id'] ?? 0);

                if ($usuarioRemoverId <= 0) {
                    Solicitudes::error('Usuario ID inválido.', 422);
                }

                Administrador::removeCoworker(
                    $usuarioId,
                    $empresaId,
                    $usuarioRemoverId
                );

                Solicitudes::success([
                    'message' => 'Trabajador removido correctamente.'
                ]);

                break;

            default:
                Solicitudes::error('Acción no soportada.', 404);
        }
    } catch (InvalidArgumentException $e) {
        Solicitudes::error($e->getMessage(), 422);
    } catch (RuntimeException $e) {
        Solicitudes::error($e->getMessage(), 500);
    } catch (Throwable $e) {
        error_log('api/admin.php: ' . $e->getMessage());

        Solicitudes::error(
            'Error real en admin.php: ' . $e->getMessage(),
            500
        );
    }
}

if (!defined('TESTING_CAPTURE')) {
    handleAdminApiRequest();
}
