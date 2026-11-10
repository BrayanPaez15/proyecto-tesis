<?php
/**
 * Administracion de usuarios - Listado.
 *
 * Muestra todas las cuentas registradas en el sistema (nombre, correo,
 * rol, estado, permisos por modulo, permiso de importacion y politica
 * aceptada), con las
 * solicitudes pendientes primero. Al aprobar una cuenta lleva a editarla
 * para asignarle permisos. Permite aprobar, rechazar o revocar el
 * acceso, asignar o quitar el permiso de importacion y editar o eliminar
 * cada cuenta. No permite eliminar ni cambiar el estado de la propia
 * cuenta, ni revocar o eliminar al ultimo administrador. Solo accesible
 * para administradores. Requiere sesion activa (auth.php).
 */
require "auth.php";
include "conexion.php";
require_once "includes/layout.php";
require_once "includes/csrf.php";
require_admin();

if (isset($_POST['eliminar_id'])) {
    csrf_verify();
    $idEliminar = (int) $_POST['eliminar_id'];
    $errorEliminar = "";

    if ($idEliminar === (int) $_SESSION['usuario_id']) {
        $errorEliminar = "No puedes eliminar tu propia cuenta.";
    } else {
        $stmt = $conn->prepare("SELECT rol FROM usuarios WHERE id = ?");
        $stmt->bind_param("i", $idEliminar);
        $stmt->execute();
        $objetivo = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($objetivo && $objetivo['rol'] === 'admin') {
            $totalAdmins = (int) $conn->query("SELECT COUNT(*) AS total FROM usuarios WHERE rol = 'admin'")->fetch_assoc()['total'];
            if ($totalAdmins <= 1) {
                $errorEliminar = "No puedes eliminar al único administrador del sistema.";
            }
        }

        if ($errorEliminar === "" && $objetivo) {
            $stmt = $conn->prepare("DELETE FROM usuarios WHERE id = ?");
            $stmt->bind_param("i", $idEliminar);
            $stmt->execute();
            $stmt->close();
            header("Location: usuarios.php?msg=" . rawurlencode("Usuario eliminado") . "&tipo=success");
            exit;
        }
    }

    if ($errorEliminar !== "") {
        header("Location: usuarios.php?msg=" . rawurlencode($errorEliminar) . "&tipo=error");
        exit;
    }
}

if (isset($_POST['cambiar_estado'])) {
    csrf_verify();
    $idObjetivo = (int) ($_POST['usuario_id'] ?? 0);
    $nuevoEstado = $_POST['cambiar_estado'];
    $errorEstado = "";

    $stmt = $conn->prepare("SELECT rol, estado FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $idObjetivo);
    $stmt->execute();
    $objetivo = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$objetivo || !in_array($nuevoEstado, ['aprobado', 'rechazado'], true)) {
        $errorEstado = "La solicitud no es válida.";
    } elseif ($idObjetivo === (int) $_SESSION['usuario_id']) {
        $errorEstado = "No puedes cambiar el estado de tu propia cuenta.";
    } elseif ($objetivo['rol'] === 'admin' && $objetivo['estado'] === 'aprobado' && $nuevoEstado !== 'aprobado') {
        $otros = $conn->prepare("SELECT COUNT(*) AS total FROM usuarios WHERE rol = 'admin' AND estado = 'aprobado' AND id <> ?");
        $otros->bind_param("i", $idObjetivo);
        $otros->execute();
        if ((int) $otros->get_result()->fetch_assoc()['total'] === 0) {
            $errorEstado = "No puedes revocar el acceso al único administrador aprobado.";
        }
        $otros->close();
    }

    if ($errorEstado === "") {
        $stmt = $conn->prepare("UPDATE usuarios SET estado = ? WHERE id = ?");
        $stmt->bind_param("si", $nuevoEstado, $idObjetivo);
        $stmt->execute();
        $stmt->close();
        alertas_resolver_usuario($conn, $idObjetivo);
        if ($nuevoEstado === 'aprobado') {
            header("Location: usuario_editar.php?id=$idObjetivo&msg=" . rawurlencode("Cuenta aprobada. Asigna ahora sus permisos por módulo: sin permisos no podrá ver ni registrar nada.") . "&tipo=success");
        } else {
            header("Location: usuarios.php?msg=" . rawurlencode("Acceso rechazado") . "&tipo=success");
        }
    } else {
        header("Location: usuarios.php?msg=" . rawurlencode($errorEstado) . "&tipo=error");
    }
    exit;
}

if (isset($_POST['permiso_importar'])) {
    csrf_verify();
    $idObjetivo = (int) ($_POST['usuario_id'] ?? 0);
    $valor = $_POST['permiso_importar'] === '1' ? 1 : 0;
    $stmt = $conn->prepare("UPDATE usuarios SET puede_importar = ? WHERE id = ? AND rol <> 'admin'");
    $stmt->bind_param("ii", $valor, $idObjetivo);
    $stmt->execute();
    $stmt->close();
    header("Location: usuarios.php?msg=" . rawurlencode($valor ? "Permiso de importación asignado" : "Permiso de importación retirado") . "&tipo=success");
    exit;
}

$r = $conn->query("SELECT u.id, u.nombre, u.correo, u.rol, u.estado, u.puede_importar, u.politica_version, u.politica_aceptada_en, u.intentos_fallidos, u.bloqueado_hasta, u.creado_en,
        (SELECT COUNT(*) FROM permisos p WHERE p.usuario_id = u.id AND p.puede_ver = 1) AS n_ver,
        (SELECT COUNT(*) FROM permisos p WHERE p.usuario_id = u.id AND p.puede_registrar = 1) AS n_registrar
    FROM usuarios u ORDER BY FIELD(u.estado, 'pendiente', 'aprobado', 'rechazado'), u.nombre");
$totalPendientes = (int) $conn->query("SELECT COUNT(*) AS total FROM usuarios WHERE estado = 'pendiente'")->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <title>Usuarios - SIRAD</title>
  <link rel="stylesheet" href="css/styles.css">
  <script src="js/animations.js" defer></script>
  <script src="js/toast.js" defer></script>
  <script src="js/table-tools.js" defer></script>
</head>

<body>

  <?php render_header(); ?>

  <main>
    <h1>Usuarios registrados</h1>

    <?php if ($totalPendientes > 0): ?>
      <p class="pending-notice"><?= $totalPendientes ?> solicitud<?= $totalPendientes === 1 ? '' : 'es' ?> de cuenta pendiente<?= $totalPendientes === 1 ? '' : 's' ?> de aprobación.</p>
    <?php endif; ?>

    <?php if ($r->num_rows === 0): ?>
      <p class="empty-state">No hay usuarios registrados.</p>
    <?php else: ?>
      <div class="search-bar">
        <input type="text" placeholder="Buscar en la tabla...">
      </div>

      <div class="table-card">
        <table>
          <tr>
            <th>Nombre</th>
            <th>Correo</th>
            <th>Rol</th>
            <th>Estado</th>
            <th>Permisos</th>
            <th>Importar</th>
            <th>Política</th>
            <th>Creado</th>
            <th>Acciones</th>
          </tr>
          <?php while ($u = $r->fetch_assoc()):
            $bloqueado = $u['bloqueado_hasta'] && strtotime($u['bloqueado_hasta']) > time();
            $esAdminFila = $u['rol'] === 'admin';
            $esYo = (int) $u['id'] === (int) $_SESSION['usuario_id'];
          ?>
            <tr>
              <td><?= htmlspecialchars($u['nombre']) ?></td>
              <td><?= htmlspecialchars($u['correo']) ?></td>
              <td><span class="pill <?= $esAdminFila ? 'pill-admin' : '' ?>"><?= $esAdminFila ? 'Administrador' : 'Usuario' ?></span></td>
              <td>
                <?php if ($u['estado'] === 'pendiente'): ?>
                  <span class="pill pill-pending">Pendiente</span>
                <?php elseif ($u['estado'] === 'rechazado'): ?>
                  <span class="pill pill-blocked">Rechazada</span>
                <?php else: ?>
                  <span class="pill <?= $bloqueado ? 'pill-blocked' : '' ?>"><?= $bloqueado ? 'Bloqueada' : 'Activa' ?></span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($esAdminFila): ?>
                  Todos
                <?php elseif ($u['estado'] !== 'aprobado'): ?>
                  &mdash;
                <?php elseif ((int) $u['n_ver'] === 0 && (int) $u['n_registrar'] === 0): ?>
                  <span class="pill pill-pending">Sin permisos</span>
                <?php else: ?>
                  Ver: <?= (int) $u['n_ver'] ?> · Registrar: <?= (int) $u['n_registrar'] ?>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($esAdminFila): ?>
                  Sí (admin)
                <?php elseif ($u['estado'] !== 'aprobado'): ?>
                  &mdash;
                <?php else: ?>
                  <form method="POST" style="display:inline">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="usuario_id" value="<?= (int) $u['id'] ?>">
                    <?php if ((int) $u['puede_importar'] === 1): ?>
                      Sí <button type="submit" name="permiso_importar" value="0" class="btn-edit">Quitar</button>
                    <?php else: ?>
                      No <button type="submit" name="permiso_importar" value="1" class="btn-edit">Permitir</button>
                    <?php endif; ?>
                  </form>
                <?php endif; ?>
              </td>
              <td><?= $u['politica_aceptada_en'] ? 'v' . htmlspecialchars($u['politica_version']) . ' · ' . htmlspecialchars(date('Y-m-d', strtotime($u['politica_aceptada_en']))) : 'Sin aceptar' ?></td>
              <td><?= htmlspecialchars(date('Y-m-d', strtotime($u['creado_en']))) ?></td>
              <td>
                <?php if (!$esYo && $u['estado'] !== 'aprobado'): ?>
                  <form method="POST" style="display:inline">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="usuario_id" value="<?= (int) $u['id'] ?>">
                    <button type="submit" name="cambiar_estado" value="aprobado" class="btn-edit">Aprobar</button>
                    <?php if ($u['estado'] === 'pendiente'): ?>
                      <button type="submit" name="cambiar_estado" value="rechazado" class="btn-delete">Rechazar</button>
                    <?php endif; ?>
                  </form>
                <?php elseif (!$esYo): ?>
                  <form method="POST" style="display:inline" onsubmit="return confirm('¿Revocar el acceso de esta cuenta?');">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="usuario_id" value="<?= (int) $u['id'] ?>">
                    <button type="submit" name="cambiar_estado" value="rechazado" class="btn-delete">Revocar acceso</button>
                  </form>
                <?php endif; ?>
                <a href="usuario_editar.php?id=<?= (int) $u['id'] ?>" class="btn-edit">Editar</a>
                <?php if ((int) $u['id'] !== (int) $_SESSION['usuario_id']): ?>
                  <form method="POST" style="display:inline" onsubmit="return confirm('¿Eliminar esta cuenta? Esta acción no se puede deshacer.');">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="eliminar_id" value="<?= (int) $u['id'] ?>">
                    <button type="submit" class="btn-delete">Borrar</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endwhile; ?>
        </table>
      </div>
      <div class="pagination"></div>
    <?php endif; ?>
  </main>

</body>

</html>
