<?php

/**
 * Test manual para validar el modelo Administrador.
 *
 * Uso recomendado en navegador:
 * http://localhost/TEST_Empresa/modelos/test_administrador.php?admin_id=1&empresa_id=1&correo=usuario@correo.com
 *
 * Por defecto NO inserta nada. Solo diagnostica.
 * Para ejecutar la insercion real:
 * http://localhost/TEST_Empresa/modelos/test_administrador.php?admin_id=1&empresa_id=1&correo=usuario@correo.com&run=1
 *
 * IMPORTANTE:
 * Borra este archivo cuando termines las pruebas.
 */

header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

try {
    $sharedPath = __DIR__ . '/../api/shared.php';
    $adminModelPath = __DIR__ . '/Administrador.php';

    if (!file_exists($sharedPath)) {
        throw new RuntimeException('No existe api/shared.php en: ' . $sharedPath);
    }

    if (!file_exists($adminModelPath)) {
        throw new RuntimeException('No existe modelos/Administrador.php en: ' . $adminModelPath);
    }

    require_once $sharedPath;
    require_once $adminModelPath;

    $adminId = (int)($_GET['admin_id'] ?? 0);
    $empresaId = (int)($_GET['empresa_id'] ?? 0);
    $correo = normalizeEmail($_GET['correo'] ?? '');
    $telefono = normalizeText($_GET['telefono'] ?? '');
    $run = (string)($_GET['run'] ?? '0') === '1';

    if ($adminId <= 0) {
        throw new InvalidArgumentException('Falta admin_id. Ejemplo: ?admin_id=1');
    }

    if ($empresaId <= 0) {
        throw new InvalidArgumentException('Falta empresa_id. Ejemplo: &empresa_id=1');
    }

    if ($correo === '') {
        throw new InvalidArgumentException('Falta correo. Ejemplo: &correo=usuario@correo.com');
    }

    $diagnostico = [
        'php_ok' => true,
        'shared_cargado' => function_exists('db'),
        'normalizadores_cargados' => function_exists('normalizeEmail') && function_exists('normalizeText'),
        'clase_usuario_cargada' => class_exists('Usuario'),
        'clase_administrador_cargada' => class_exists('Administrador'),
        'parametros' => [
            'admin_id' => $adminId,
            'empresa_id' => $empresaId,
            'correo' => $correo,
            'telefono' => $telefono,
            'run' => $run,
        ],
    ];

    $conn = db();

    if (!$conn) {
        throw new RuntimeException('db() no devolvio una conexion valida.');
    }

    $diagnostico['db_ok'] = true;

    $adminMembership = Usuario::getMembership($adminId, $empresaId);
    $diagnostico['admin_membership'] = $adminMembership;

    if ($adminMembership === null) {
        throw new RuntimeException('El admin_id no pertenece a empresa_id. No puede administrar esta empresa.');
    }

    if (($adminMembership['rol'] ?? '') !== 'administrador') {
        throw new RuntimeException('El usuario indicado existe en la empresa, pero su rol no es administrador. Rol actual: ' . ($adminMembership['rol'] ?? 'SIN_ROL'));
    }

    $targetUser = Usuario::findByCorreo($correo);

    if ($targetUser === null || $targetUser->getId() === null) {
        throw new InvalidArgumentException('No existe usuario registrado con el correo: ' . $correo);
    }

    $targetUserId = (int)$targetUser->getId();
    $diagnostico['usuario_objetivo'] = [
        'usuario_id' => $targetUserId,
        'correo' => $targetUser->getCorreo(),
        'nombre_usuario' => $targetUser->getNombreUsuario(),
    ];

    $targetMembership = Usuario::getMembership($targetUserId, $empresaId);
    $diagnostico['usuario_objetivo_membership_actual'] = $targetMembership;

    if ($targetMembership !== null && !$run) {
        echo json_encode([
            'success' => true,
            'modo' => 'diagnostico_sin_insertar',
            'message' => 'El modelo carga bien, pero este usuario ya pertenece a la empresa. No se intento insertar.',
            'diagnostico' => $diagnostico,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!$run) {
        echo json_encode([
            'success' => true,
            'modo' => 'diagnostico_sin_insertar',
            'message' => 'El modelo parece funcional. Para probar la insercion real agrega &run=1 a la URL.',
            'diagnostico' => $diagnostico,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    $resultado = Administrador::addExistingUserByEmail(
        $adminId,
        $empresaId,
        $correo,
        null,
        $telefono !== '' ? $telefono : null
    );

    echo json_encode([
        'success' => true,
        'modo' => 'insert_real',
        'message' => 'Administrador::addExistingUserByEmail funciona correctamente.',
        'resultado' => $resultado,
        'diagnostico' => $diagnostico,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'error_tipo' => get_class($e),
        'error_mensaje' => $e->getMessage(),
        'error_archivo' => $e->getFile(),
        'error_linea' => $e->getLine(),
        'trace' => explode("\n", $e->getTraceAsString()),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
