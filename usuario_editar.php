<?php
/**
 * Administracion de usuarios - Editar.
 *
 * Permite a un administrador modificar el nombre, correo, rol, estado
 * (pendiente, aprobada o rechazada), permiso de importacion y permisos
 * por modulo (ver y registrar) de una
 * cuenta, desbloquearla si tiene un bloqueo activo por intentos
 * fallidos, o restablecerle la contrasena. Evita quitarle el rol o el
 * acceso al ultimo administrador del sistema y quitarse el acceso a si
 * mismo. Solo accesible para administradores. Requiere sesion activa
 * (auth.php).
 */
require "auth.php";
include "conexion.php";
require_once "includes/layout.php";
require_once "includes/csrf.php";
require_once "includes/permisos.php";
require_once "includes/alertas.php";
require_once "includes/password.php";
require_admin();

$modulos = require "includes/modulos.php";

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$stmt = $conn->prepare("SELECT *, id_usuario AS id FROM usuarios WHERE id_usuario = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$usuario) {
    header("Location: usuarios.php");
    exit;
}

$error = "";
if ($_POST) {
    csrf_verify();

    if (isset($_POST['guardar_datos'])) {
        $nombre = trim($_POST['nombre']);
        $correo = trim($_POST['correo']);
        $rol = $_POST['rol'] === 'admin' ? 'admin' : 'usuario';
        $estado = in_array($_POST['estado'] ?? '', ['pendiente', 'aprobado', 'rechazado', 'baja'], true) ? $_POST['estado'] : $usuario['estado'];
        $puedeImportar = !empty($_POST['puede_importar']) ? 1 : 0;

        if ($nombre === '' || $correo === '') {
            $error = "Nombre y correo son obligatorios.";
        } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $error = "El correo no tiene un formato válido.";
        } else {
            $check = $conn->prepare("SELECT id_usuario AS id FROM usuarios WHERE correo = ? AND id_usuario <> ?");
            $check->bind_param("si", $correo, $id);
            $check->execute();
            $check->store_result();
            if ($check->num_rows > 0) {
                $error = "Ya existe otra cuenta con ese correo.";
            }
            $check->close();
        }

        if ($error === '' && $usuario['rol'] === 'admin' && $rol !== 'admin') {
            $totalAdmins = (int) $conn->query("SELECT COUNT(*) AS total FROM usuarios WHERE rol = 'admin'")->fetch_assoc()['total'];
            if ($totalAdmins <= 1) {
                $error = "No puedes quitarle el rol de administrador al único administrador del sistema.";
            }
        }

        if ($error === '' && $estado !== 'aprobado' && $usuario['estado'] === 'aprobado') {
            if ($id === (int) $_SESSION['usuario_id']) {
                $error = "No puedes quitarle el acceso a tu propia cuenta.";
            } elseif ($usuario['rol'] === 'admin') {
                $otros = $conn->prepare("SELECT COUNT(*) AS total FROM usuarios WHERE rol = 'admin' AND estado = 'aprobado' AND id_usuario <> ?");
                $otros->bind_param("i", $id);
                $otros->execute();
                if ((int) $otros->get_result()->fetch_assoc()['total'] === 0) {
                    $error = "No puedes revocar el acceso al único administrador aprobado.";
                }
                $otros->close();
            }
        }

        if ($error === '') {
            $stmt = $conn->prepare("UPDATE usuarios SET nombre = ?, correo = ?, rol = ?, estado = ?, puede_importar = ? WHERE id_usuario = ?");
            $stmt->bind_param("ssssii", $nombre, $correo, $rol, $estado, $puedeImportar, $id);
            $stmt->execute();
            $stmt->close();

            if ($id === (int) $_SESSION['usuario_id']) {
                $_SESSION['usuario_nombre'] = $nombre;
                $_SESSION['usuario_rol'] = $rol;
            }

            header("Location: usuarios.php?msg=" . rawurlencode("Usuario actualizado") . "&tipo=success");
            exit;
        }

        $usuario['nombre'] = $nombre;
        $usuario['correo'] = $correo;
        $usuario['rol'] = $rol;
        $usuario['estado'] = $estado;
        $usuario['puede_importar'] = $puedeImportar;
    } elseif (isset($_POST['guardar_permisos'])) {
        if ($usuario['rol'] === 'admin') {
            $error = "Los administradores tienen acceso a todos los módulos; no necesitan permisos.";
        } else {
            $ver = array_map('strval', (array) ($_POST['ver'] ?? []));
            $registrar = array_map('strval', (array) ($_POST['registrar'] ?? []));
            permisos_guardar($conn, $id, array_keys($modulos), $ver, $registrar);
            header("Location: usuario_editar.php?id=$id&msg=" . rawurlencode("Permisos actualizados") . "&tipo=success");
            exit;
        }
    } elseif (isset($_POST['restablecer_password'])) {
        $nueva = $_POST['password_nueva'] ?? '';
        if (($error = validar_password($nueva, $usuario['correo'])) !== '') {
            // $error ya tiene el mensaje
        } else {
            $hash = password_hash($nueva, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE usuarios SET password = ?, intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id_usuario = ?");
            $stmt->bind_param("si", $hash, $id);
            $stmt->execute();
            $stmt->close();
            header("Location: usuarios.php?msg=" . rawurlencode("Contraseña restablecida") . "&tipo=success");
            exit;
        }
    } elseif (isset($_POST['desbloquear'])) {
        $stmt = $conn->prepare("UPDATE usuarios SET intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id_usuario = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        header("Location: usuarios.php?msg=" . rawurlencode("Cuenta desbloqueada") . "&tipo=success");
        exit;
    }
}

$bloqueado = $usuario['bloqueado_hasta'] && strtotime($usuario['bloqueado_hasta']) > time();
$permisosUsuario = permisos_de_usuario($conn, $id);
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Editar usuario - SIRAD</title>
  <link rel="stylesheet" href="css/styles.css?v=<?= @filemtime(__DIR__ . '/css/styles.css') ?>">
  <script src="js/animations.js?v=<?= @filemtime(__DIR__ . '/js/animations.js') ?>" defer></script>
  <script src="js/toast.js?v=<?= @filemtime(__DIR__ . '/js/toast.js') ?>" defer></script>
  <script src="js/password.js?v=<?= @filemtime(__DIR__ . '/js/password.js') ?>" defer></script>
</head>

<body>

  <?php render_header(); ?>

  <main>
    <h1>Editar usuario</h1>

    <p class="edit-notice">Editando a <?= htmlspecialchars($usuario['nombre']) ?> &mdash; <a href="usuarios.php">Volver a usuarios</a></p>

    <?php if ($error): ?>
      <p class="auth-error" style="margin-bottom: 16px;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form class="form-card" method="POST">
      <?php csrf_field(); ?>
      <input type="hidden" name="id" value="<?= (int) $usuario['id'] ?>">
      <?php if (!empty($usuario['motivo_solicitud'])): ?>
        <p class="nota-motivo"><strong>Motivo de la solicitud:</strong> <?= htmlspecialchars($usuario['motivo_solicitud']) ?></p>
      <?php endif; ?>
      <?php if (acceso_texto($usuario['acceso_solicitado'] ?? null) !== ''): ?>
        <p class="nota-motivo"><strong>Rol solicitado:</strong> <?= htmlspecialchars(acceso_texto($usuario['acceso_solicitado'])) ?> (es solo lo que pidió; usted define los permisos reales en la matriz de abajo).</p>
      <?php endif; ?>
      <?php if ($usuario['estado'] === 'baja' && !empty($usuario['baja_motivo'])): ?>
        <p class="nota-motivo"><strong>Se dio de baja el <?= htmlspecialchars(date('Y-m-d H:i', strtotime((string) $usuario['baja_en']))) ?>:</strong> <?= htmlspecialchars($usuario['baja_motivo']) ?> (para que vuelva, cambie el estado a «Aprobada»).</p>
      <?php endif; ?>
      <label>Nombre<input name="nombre" required value="<?= htmlspecialchars($usuario['nombre']) ?>"></label>
      <label>Correo<input name="correo" type="email" required value="<?= htmlspecialchars($usuario['correo']) ?>"></label>
      <label>Rol
        <select name="rol">
          <option value="usuario" <?= $usuario['rol'] === 'usuario' ? 'selected' : '' ?>>Usuario</option>
          <option value="admin" <?= $usuario['rol'] === 'admin' ? 'selected' : '' ?>>Administrador</option>
        </select>
      </label>
      <label>Estado de la cuenta
        <select name="estado">
          <option value="pendiente" <?= $usuario['estado'] === 'pendiente' ? 'selected' : '' ?>>Pendiente de aprobación</option>
          <option value="aprobado" <?= $usuario['estado'] === 'aprobado' ? 'selected' : '' ?>>Aprobada</option>
          <option value="rechazado" <?= $usuario['estado'] === 'rechazado' ? 'selected' : '' ?>>Rechazada</option>
          <option value="baja" <?= $usuario['estado'] === 'baja' ? 'selected' : '' ?>>Baja (se retiró)</option>
        </select>
      </label>
      <label class="check-label"><input type="checkbox" name="puede_importar" value="1" <?= (int) $usuario['puede_importar'] === 1 ? 'checked' : '' ?>><span>Puede importar registros desde CSV (los administradores siempre pueden)</span></label>
      <button type="submit" name="guardar_datos" value="1">Guardar cambios</button>
    </form>

    <?php if ($usuario['rol'] === 'admin'): ?>
      <p class="edit-notice">Es administrador: tiene acceso a todos los módulos, así que no se le asignan permisos por módulo.</p>
    <?php else: ?>
      <form class="form-card" method="POST" id="form-permisos">
        <?php csrf_field(); ?>
        <input type="hidden" name="id" value="<?= (int) $usuario['id'] ?>">
        <h2 style="grid-column: 1 / -1; margin: 0; font-size: 17px;">Permisos por módulo</h2>
        <p class="agenda-help" style="grid-column: 1 / -1; margin: 0;">«Ver» permite consultar los registros del módulo; «Registrar» permite agregar registros nuevos. Sin ningún permiso el usuario no ve ni registra nada. Editar, eliminar y descargar son solo de administradores.</p>
        <?php if ($usuario['estado'] !== 'aprobado'): ?>
          <p class="pending-notice" style="grid-column: 1 / -1; margin: 0;">La cuenta no está aprobada: los permisos se aplicarán cuando lo esté.</p>
        <?php endif; ?>
        <div class="table-card" style="grid-column: 1 / -1;">
          <table>
            <tr>
              <th>Módulo</th>
              <th>Ver <label class="check-label" style="display:inline-flex"><input type="checkbox" data-todos="ver"><span>todos</span></label></th>
              <th>Registrar <label class="check-label" style="display:inline-flex"><input type="checkbox" data-todos="registrar"><span>todos</span></label></th>
            </tr>
            <?php foreach ($modulos as $clave => $m): ?>
              <tr>
                <td><?= htmlspecialchars($m['titulo']) ?></td>
                <td><input type="checkbox" name="ver[]" value="<?= htmlspecialchars($clave) ?>" <?= !empty($permisosUsuario[$clave]['ver']) ? 'checked' : '' ?>></td>
                <td><input type="checkbox" name="registrar[]" value="<?= htmlspecialchars($clave) ?>" <?= !empty($permisosUsuario[$clave]['registrar']) ? 'checked' : '' ?>></td>
              </tr>
            <?php endforeach; ?>
          </table>
        </div>
        <button type="submit" name="guardar_permisos" value="1">Guardar permisos</button>
      </form>
      <script>
        document.querySelectorAll('#form-permisos [data-todos]').forEach(function (c) {
          c.addEventListener('change', function () {
            document.querySelectorAll('#form-permisos input[name="' + c.dataset.todos + '[]"]').forEach(function (x) { x.checked = c.checked; });
          });
        });
      </script>
    <?php endif; ?>

    <?php if ($bloqueado): ?>
      <div class="export-bar">
        <form method="POST">
          <?php csrf_field(); ?>
          <input type="hidden" name="id" value="<?= (int) $usuario['id'] ?>">
          <p style="margin: 0;">Esta cuenta está bloqueada temporalmente por intentos fallidos de inicio de sesión.</p>
          <button type="submit" name="desbloquear" value="1">Desbloquear cuenta</button>
        </form>
      </div>
    <?php endif; ?>

    <div class="export-bar">
      <form method="POST">
        <?php csrf_field(); ?>
        <input type="hidden" name="id" value="<?= (int) $usuario['id'] ?>">
        <label>Nueva contraseña<input type="password" name="password_nueva" minlength="8" maxlength="64" required autocomplete="new-password" data-pw></label>
        <?php render_requisitos_password(); ?>
        <button type="submit" name="restablecer_password" value="1">Restablecer contraseña</button>
      </form>
    </div>
  </main>

</body>

</html>
