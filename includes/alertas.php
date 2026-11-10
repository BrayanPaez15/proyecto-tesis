<?php
/**
 * Alertas para el administrador.
 *
 * No hay una tabla de alertas: se calculan a partir de la tabla `usuarios`.
 * Hay tres tipos de evento: una cuenta creada (usuarios.creado_en), un inicio de
 * sesion de un usuario (usuarios.ultimo_acceso) y un intento de entrar con una
 * cuenta que no esta aprobada (usuarios.ultimo_intento_pendiente). Cada
 * administrador tiene su propia marca de lectura (usuarios.alertas_vistas_hasta):
 * un evento esta «sin leer» si es posterior a esa marca. Una cuenta creada o un
 * intento de acceso solo cuentan como sin leer mientras la cuenta siga pendiente,
 * y un inicio de sesion vuelve a aparecer sin leer cada vez que la persona entra.
 * Se muestra el ultimo evento de cada tipo por usuario.
 */

function alerta_tipos(): array
{
    return [
        'registro' => 'Cuenta creada',
        'inicio_sesion' => 'Inicio de sesión',
        'intento_pendiente' => 'Intento de acceso sin aprobar',
    ];
}

function alerta_detalle(array $e): string
{
    $quien = "{$e['nombre']} ({$e['correo']})";
    switch ($e['tipo']) {
        case 'registro':
            return "Nueva cuenta creada: $quien." . ($e['estado'] === 'pendiente' ? ' Pendiente de aprobación.' : '');
        case 'intento_pendiente':
            return "$quien intentó iniciar sesión, pero su cuenta está " . ($e['estado'] === 'pendiente' ? 'pendiente de aprobación.' : 'rechazada.');
        default:
            return "$quien inició sesión.";
    }
}

/** Eventos para el administrador dado, los sin leer primero, cada uno con su detalle y la marca sin_leer. */
function alertas_eventos(mysqli $conn, int $adminId): array
{
    $stmt = $conn->prepare("SELECT e.tipo, e.usuario_id, e.nombre, e.correo, e.estado, e.fecha,
            (e.fecha > COALESCE((SELECT alertas_vistas_hasta FROM usuarios WHERE id = ?), '1970-01-01 00:00:00')
             AND (e.tipo = 'inicio_sesion' OR e.estado = 'pendiente')) AS sin_leer
        FROM (
            SELECT 'registro' AS tipo, id AS usuario_id, nombre, correo, estado, creado_en AS fecha FROM usuarios WHERE rol <> 'admin'
            UNION ALL
            SELECT 'intento_pendiente', id, nombre, correo, estado, ultimo_intento_pendiente FROM usuarios WHERE ultimo_intento_pendiente IS NOT NULL AND estado <> 'aprobado'
            UNION ALL
            SELECT 'inicio_sesion', id, nombre, correo, estado, ultimo_acceso FROM usuarios WHERE ultimo_acceso IS NOT NULL AND rol <> 'admin'
        ) e
        ORDER BY sin_leer DESC, e.fecha DESC LIMIT 200");
    $stmt->bind_param("i", $adminId);
    $stmt->execute();
    $eventos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    foreach ($eventos as &$e) {
        $e['sin_leer'] = (int) $e['sin_leer'] === 1;
        $e['detalle'] = alerta_detalle($e);
    }
    unset($e);
    return $eventos;
}

/** Cantidad de alertas sin leer del administrador en sesion (o del indicado). */
function alertas_pendientes(mysqli $conn, ?int $adminId = null): int
{
    $adminId = $adminId ?? (int) ($_SESSION['usuario_id'] ?? 0);
    $n = 0;
    foreach (alertas_eventos($conn, $adminId) as $e) {
        if ($e['sin_leer']) {
            $n++;
        }
    }
    return $n;
}

/** Marca todo como leido para ese administrador (guarda el momento actual como marca de lectura). */
function alertas_marcar_vistas(mysqli $conn, int $adminId): void
{
    $stmt = $conn->prepare("UPDATE usuarios SET alertas_vistas_hasta = NOW() WHERE id = ?");
    $stmt->bind_param("i", $adminId);
    $stmt->execute();
    $stmt->close();
}
