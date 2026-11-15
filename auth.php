<?php
/**
 * Guard de autenticacion.
 *
 * Inicia la sesion PHP y, si no existe una sesion activa
 * ($_SESSION['usuario_id']), redirige a login.php. En cada peticion
 * vuelve a leer la cuenta: si fue eliminada, rechazada o no esta
 * aprobada, cierra la sesion; y refresca el rol y el permiso de
 * importacion y los permisos por modulo, de modo que un cambio del
 * administrador rige de inmediato. Se incluye al inicio de index.php y de los once modulos
 * del directorio para proteger el acceso. Usa la sesion segura (cookie HttpOnly y
 * SameSite, cabeceras de seguridad) y evita que el navegador guarde las paginas.
 */
require_once __DIR__ . '/includes/sesion.php';
require_once __DIR__ . '/includes/permisos.php';
iniciar_sesion_segura();
sin_cache();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

include_once __DIR__ . '/conexion.php';
$stmtSesion = $conn->prepare("SELECT nombre, rol, estado, puede_importar, permisos_modulos, politica_version FROM usuarios WHERE id_usuario = ?");
$idSesion = (int) $_SESSION['usuario_id'];
$stmtSesion->bind_param("i", $idSesion);
$stmtSesion->execute();
$cuentaSesion = $stmtSesion->get_result()->fetch_assoc();
$stmtSesion->close();

if (!$cuentaSesion || $cuentaSesion['estado'] !== 'aprobado') {
    session_unset();
    session_destroy();
    // Rechazada o eliminada: el login explica que debe comunicarse con el administrador.
    $aviso = !$cuentaSesion ? 'eliminada' : ($cuentaSesion['estado'] === 'rechazado' ? 'rechazada' : ($cuentaSesion['estado'] === 'baja' ? 'baja' : ''));
    header("Location: login.php" . ($aviso !== '' ? '?aviso=' . $aviso : ''));
    exit;
}
// Politica de datos: si la version aceptada no es la vigente (por ejemplo, las cuentas anteriores a la 1.1), la
// persona debe leerla y aceptarla antes de seguir. Quedan libres la pantalla de aceptacion y la baja de la cuenta.
require_once __DIR__ . '/includes/politica.php';
if (POLITICA_EXIGIR_ACEPTACION && (string) ($cuentaSesion['politica_version'] ?? '') !== POLITICA_VERSION
    && !in_array(basename($_SERVER['SCRIPT_NAME'] ?? ''), ['aceptar_politica.php', 'baja.php'], true)) {
    header("Location: aceptar_politica.php");
    exit;
}
$_SESSION['usuario_nombre'] = $cuentaSesion['nombre'];
$_SESSION['usuario_rol'] = $cuentaSesion['rol'];
$_SESSION['usuario_puede_importar'] = (int) $cuentaSesion['puede_importar'];

$_SESSION['usuario_permisos'] = $cuentaSesion['rol'] === 'admin' ? [] : permisos_decodificar($cuentaSesion['permisos_modulos']);
