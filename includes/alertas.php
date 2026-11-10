<?php
/**
 * Alertas para el administrador.
 *
 * Registra en la tabla `alertas` los eventos que un administrador debe
 * conocer: una cuenta nueva (registro), un inicio de sesion de un usuario
 * (inicio_sesion) y un intento de entrar con una cuenta que aun no esta
 * aprobada (intento_pendiente). El encabezado muestra cuantas hay sin leer
 * y alertas.php las lista.
 */

function alerta_tipos(): array
{
    return [
        'registro' => 'Cuenta creada',
        'inicio_sesion' => 'Inicio de sesión',
        'intento_pendiente' => 'Intento de acceso sin aprobar',
    ];
}

function alerta_registrar(mysqli $conn, string $tipo, ?int $usuarioId, string $detalle): void
{
    $stmt = $conn->prepare("INSERT INTO alertas (tipo, usuario_id, detalle) VALUES (?, ?, ?)");
    $detalle = mb_substr($detalle, 0, 255);
    $stmt->bind_param("sis", $tipo, $usuarioId, $detalle);
    $stmt->execute();
    $stmt->close();
}

/** Igual que alerta_registrar, pero no repite una alerta del mismo tipo y usuario que siga sin leer. */
function alerta_registrar_una_vez(mysqli $conn, string $tipo, int $usuarioId, string $detalle): void
{
    $stmt = $conn->prepare("SELECT id FROM alertas WHERE tipo = ? AND usuario_id = ? AND leida = 0 LIMIT 1");
    $stmt->bind_param("si", $tipo, $usuarioId);
    $stmt->execute();
    $existe = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    if (!$existe) {
        alerta_registrar($conn, $tipo, $usuarioId, $detalle);
    }
}

function alertas_pendientes(mysqli $conn): int
{
    return (int) $conn->query("SELECT COUNT(*) AS total FROM alertas WHERE leida = 0")->fetch_assoc()['total'];
}

/** Marca como leidas las alertas de solicitud de un usuario (cuando el administrador ya la resolvio). */
function alertas_resolver_usuario(mysqli $conn, int $usuarioId): void
{
    $stmt = $conn->prepare("UPDATE alertas SET leida = 1 WHERE usuario_id = ? AND tipo IN ('registro', 'intento_pendiente')");
    $stmt->bind_param("i", $usuarioId);
    $stmt->execute();
    $stmt->close();
}
