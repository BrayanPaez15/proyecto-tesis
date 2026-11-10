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
 * del directorio para proteger el acceso.
 */
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

include_once __DIR__ . '/conexion.php';
$stmtSesion = $conn->prepare("SELECT nombre, rol, estado, puede_importar FROM usuarios WHERE id = ?");
$idSesion = (int) $_SESSION['usuario_id'];
$stmtSesion->bind_param("i", $idSesion);
$stmtSesion->execute();
$cuentaSesion = $stmtSesion->get_result()->fetch_assoc();
$stmtSesion->close();

if (!$cuentaSesion || $cuentaSesion['estado'] !== 'aprobado') {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit;
}
$_SESSION['usuario_nombre'] = $cuentaSesion['nombre'];
$_SESSION['usuario_rol'] = $cuentaSesion['rol'];
$_SESSION['usuario_puede_importar'] = (int) $cuentaSesion['puede_importar'];

$_SESSION['usuario_permisos'] = [];
if ($cuentaSesion['rol'] !== 'admin') {
    $stmtPermisos = $conn->prepare("SELECT modulo, puede_ver, puede_registrar FROM permisos WHERE usuario_id = ?");
    $stmtPermisos->bind_param("i", $idSesion);
    $stmtPermisos->execute();
    $resPermisos = $stmtPermisos->get_result();
    while ($fp = $resPermisos->fetch_assoc()) {
        $_SESSION['usuario_permisos'][$fp['modulo']] = ['ver' => (int) $fp['puede_ver'] === 1, 'registrar' => (int) $fp['puede_registrar'] === 1];
    }
    $stmtPermisos->close();
}
