<?php
/**
 * Permisos por modulo.
 *
 * Cada usuario no administrador tiene, por cada uno de los once modulos, dos
 * permisos que le asigna un administrador (tabla `permisos`): ver los registros
 * y registrar. Sin permisos no ve ni registra nada. Los administradores tienen
 * acceso a todo. auth.php carga los permisos del usuario en
 * $_SESSION['usuario_permisos'] en cada peticion, de modo que un cambio del
 * administrador rige de inmediato. Editar, eliminar y exportar siguen siendo
 * solo de administradores. Requiere roles.php.
 */
require_once __DIR__ . '/roles.php';

/** $accion: 'ver', 'registrar' o 'alguna' (ver o registrar). */
function puede_modulo(string $modulo, string $accion = 'ver'): bool
{
    if (is_admin()) {
        return true;
    }
    $p = $_SESSION['usuario_permisos'][$modulo] ?? null;
    if (!$p) {
        return false;
    }
    if ($accion === 'registrar') {
        return !empty($p['registrar']);
    }
    if ($accion === 'alguna') {
        return !empty($p['ver']) || !empty($p['registrar']);
    }
    return !empty($p['ver']);
}

function require_permiso(string $modulo, string $accion): void
{
    if (puede_modulo($modulo, $accion)) {
        return;
    }
    acceso_denegado($accion === 'registrar'
        ? 'No tienes permiso para registrar en este módulo. Pídele a un administrador que te lo asigne.'
        : 'No tienes permiso para ver los registros de este módulo. Pídele a un administrador que te lo asigne.');
}

/** Modulos (de includes/modulos.php) sobre los que el usuario tiene la accion indicada. */
function modulos_permitidos(array $modulos, string $accion): array
{
    return array_filter($modulos, fn($clave) => puede_modulo($clave, $accion), ARRAY_FILTER_USE_KEY);
}

function tiene_algun_permiso(): bool
{
    if (is_admin()) {
        return true;
    }
    foreach ($_SESSION['usuario_permisos'] ?? [] as $p) {
        if (!empty($p['ver']) || !empty($p['registrar'])) {
            return true;
        }
    }
    return false;
}

/** Permisos de un usuario como [modulo => ['ver' => bool, 'registrar' => bool]]. */
function permisos_de_usuario(mysqli $conn, int $usuarioId): array
{
    $stmt = $conn->prepare("SELECT modulo, puede_ver, puede_registrar FROM permisos WHERE usuario_id = ?");
    $stmt->bind_param("i", $usuarioId);
    $stmt->execute();
    $res = $stmt->get_result();
    $permisos = [];
    while ($f = $res->fetch_assoc()) {
        $permisos[$f['modulo']] = ['ver' => (int) $f['puede_ver'] === 1, 'registrar' => (int) $f['puede_registrar'] === 1];
    }
    $stmt->close();
    return $permisos;
}

/** Reemplaza los permisos del usuario por los marcados ($ver y $registrar son listas de claves de modulo). */
function permisos_guardar(mysqli $conn, int $usuarioId, array $modulosValidos, array $ver, array $registrar): void
{
    $conn->begin_transaction();
    $del = $conn->prepare("DELETE FROM permisos WHERE usuario_id = ?");
    $del->bind_param("i", $usuarioId);
    $del->execute();
    $del->close();
    $ins = $conn->prepare("INSERT INTO permisos (usuario_id, modulo, puede_ver, puede_registrar) VALUES (?, ?, ?, ?)");
    foreach ($modulosValidos as $clave) {
        $v = in_array($clave, $ver, true) ? 1 : 0;
        $r = in_array($clave, $registrar, true) ? 1 : 0;
        if ($v || $r) {
            $ins->bind_param("isii", $usuarioId, $clave, $v, $r);
            $ins->execute();
        }
    }
    $ins->close();
    $conn->commit();
}

function puede_registrar_alguno(): bool
{
    if (is_admin()) {
        return true;
    }
    foreach ($_SESSION['usuario_permisos'] ?? [] as $p) {
        if (!empty($p['registrar'])) {
            return true;
        }
    }
    return false;
}
