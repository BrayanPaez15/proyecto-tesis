<?php
/**
 * Baja de la propia cuenta.
 *
 * Cuando una persona ya no tiene mas que hacer con el directorio puede retirarse: escribe el motivo (15 a 500
 * caracteres), confirma con su contrasena y la cuenta queda en estado «baja» (usuarios.estado) con el motivo y la
 * fecha (baja_motivo, baja_en). No se borra: asi queda la trazabilidad de quien se retiro y por que, el
 * administrador recibe una alerta (alertas.php) y puede reactivarla o eliminarla (usuarios.php). Protegida por
 * sesion, CSRF y la contrasena; no permite que se de de baja el unico administrador.
 */
require "auth.php";
include "conexion.php";
require_once "includes/layout.php";
require_once "includes/csrf.php";

$error = "";
$motivo = "";
$yo = (int) $_SESSION['usuario_id'];

if ($_POST && isset($_POST['darme_de_baja'])) {
    csrf_verify();
    $motivo = trim((string) ($_POST['motivo'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    $stmt = $conn->prepare("SELECT password, rol, intentos_fallidos, bloqueado_hasta FROM usuarios WHERE id_usuario = ?");
    $stmt->bind_param("i", $yo);
    $stmt->execute();
    $cuenta = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (mb_strlen($motivo) < 15 || mb_strlen($motivo) > 500) {
        $error = "Cuéntanos por qué te retiras (entre 15 y 500 caracteres).";
    } elseif (empty($_POST['confirmo'])) {
        $error = "Marca la casilla para confirmar que entiendes que perderás el acceso.";
    } elseif (!empty($cuenta['bloqueado_hasta']) && strtotime($cuenta['bloqueado_hasta']) > time()) {
        $error = "Demasiados intentos con la contraseña. Espera unos minutos e inténtalo de nuevo.";
    } elseif (!password_verify($password, (string) $cuenta['password'])) {
        $intentos = (int) $cuenta['intentos_fallidos'] + 1;
        $bloqueo = $intentos >= 5 ? date('Y-m-d H:i:s', time() + 15 * 60) : null;
        $upd = $conn->prepare("UPDATE usuarios SET intentos_fallidos = ?, bloqueado_hasta = ? WHERE id_usuario = ?");
        $upd->bind_param("isi", $intentos, $bloqueo, $yo);
        $upd->execute();
        $upd->close();
        $error = "La contraseña no es correcta.";
    } else {
        if ($cuenta['rol'] === 'admin') {
            $otros = $conn->prepare("SELECT COUNT(*) FROM usuarios WHERE rol = 'admin' AND estado = 'aprobado' AND id_usuario <> ?");
            $otros->bind_param("i", $yo);
            $otros->execute();
            $quedan = (int) $otros->get_result()->fetch_row()[0];
            $otros->close();
            if ($quedan === 0) {
                $error = "Eres el único administrador: antes de darte de baja, asigna a otra persona el rol de administrador.";
            }
        }
        if ($error === '') {
            $baja = $conn->prepare("UPDATE usuarios SET estado = 'baja', baja_motivo = ?, baja_en = NOW(), intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id_usuario = ?");
            $baja->bind_param("si", $motivo, $yo);
            $baja->execute();
            $baja->close();
            session_unset();
            session_destroy();
            header("Location: login.php?aviso=baja");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Darme de baja - SIRAD</title>
  <link rel="stylesheet" href="css/styles.css?v=<?= @filemtime(__DIR__ . '/css/styles.css') ?>">
  <script src="js/animations.js?v=<?= @filemtime(__DIR__ . '/js/animations.js') ?>" defer></script>
</head>

<body>

  <?php render_header(true); ?>

  <main>
    <h1>Darme de baja</h1>
    <p class="subtitle" style="margin: -4px 0 14px;">Si ya no tienes más que hacer con el directorio, puedes retirarte. Tu cuenta quedará inactiva y no podrás iniciar sesión; el administrador verá que te retiraste y el motivo que escribas, para tener la trazabilidad de quién sale del directorio y por qué.</p>

    <?php if ($error): ?>
      <p class="auth-error" style="margin-bottom: 16px;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form class="form-card" method="POST" autocomplete="off">
      <?php csrf_field(); ?>
      <label style="grid-column: 1 / -1;">¿Por qué te retiras?
        <textarea name="motivo" style="width: 100%; min-height: 90px;" required minlength="15" maxlength="500" rows="3" placeholder="Ej.: Terminé mi práctica en la Fundación y ya no necesito registrar información."><?= htmlspecialchars($motivo) ?></textarea>
      </label>
      <label style="grid-column: 1 / -1; max-width: 420px;">Tu contraseña (para confirmar que eres tú)
        <input type="password" name="password" required autocomplete="current-password">
      </label>
      <label class="check-label" style="grid-column: 1 / -1;"><input type="checkbox" name="confirmo" value="1" required><span>Entiendo que mi cuenta quedará inactiva y que perderé el acceso al sistema. Para volver tendré que pedirle al administrador que la reactive.</span></label>
      <button type="submit" name="darme_de_baja" value="1" style="background: #dc2626; box-shadow: none;">Darme de baja</button>
      <a href="index.php" class="btn-edit" style="grid-column: 1 / -1; justify-self: start; padding: 8px 20px;">Cancelar</a>
    </form>
  </main>

</body>

</html>
