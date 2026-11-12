<?php
/**
 * Permisos por modulo.
 *
 * Cada usuario no administrador tiene, por cada uno de los once modulos, dos
 * permisos que le asigna un administrador (columna `usuarios.permisos_modulos`): ver los registros
 * y registrar. Sin permisos no ve ni registra nada. Los administradores tienen
 * acceso a todo. Los permisos se guardan en la columna usuarios.permisos_modulos (JSON). auth.php carga los permisos del usuario en
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

/**
 * Decodifica usuarios.permisos_modulos, un JSON como {"artistas":"vr","talleristas":"v"} ("v" = ver,
 * "r" = registrar), a [modulo => ['ver' => bool, 'registrar' => bool]].
 */
function permisos_decodificar(?string $json): array
{
    $permisos = [];
    $mapa = $json ? json_decode($json, true) : null;
    if (is_array($mapa)) {
        foreach ($mapa as $modulo => $letras) {
            $letras = (string) $letras;
            $permisos[(string) $modulo] = ['ver' => str_contains($letras, 'v'), 'registrar' => str_contains($letras, 'r')];
        }
    }
    return $permisos;
}

/** Permisos de un usuario como [modulo => ['ver' => bool, 'registrar' => bool]]. */
function permisos_de_usuario(mysqli $conn, int $usuarioId): array
{
    $stmt = $conn->prepare("SELECT permisos_modulos FROM usuarios WHERE id_usuario = ?");
    $stmt->bind_param("i", $usuarioId);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return permisos_decodificar($fila['permisos_modulos'] ?? null);
}

/** Guarda los permisos del usuario (columna usuarios.permisos_modulos); $ver y $registrar son listas de claves de modulo. */
function permisos_guardar(mysqli $conn, int $usuarioId, array $modulosValidos, array $ver, array $registrar): void
{
    $mapa = [];
    foreach ($modulosValidos as $clave) {
        $letras = (in_array($clave, $ver, true) ? 'v' : '') . (in_array($clave, $registrar, true) ? 'r' : '');
        if ($letras !== '') {
            $mapa[$clave] = $letras;
        }
    }
    $json = $mapa ? json_encode($mapa, JSON_UNESCAPED_UNICODE) : null;
    $stmt = $conn->prepare("UPDATE usuarios SET permisos_modulos = ? WHERE id_usuario = ?");
    $stmt->bind_param("si", $json, $usuarioId);
    $stmt->execute();
    $stmt->close();
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
