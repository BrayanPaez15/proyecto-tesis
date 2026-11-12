<?php
/**
 * Restablecimiento de contrasena con el enlace enviado por correo (recuperar.php).
 *
 * El enlace trae un token aleatorio; en la base solo esta su hash SHA-256, asi que ni un
 * volcado de la base permite usarlo. El token vence a los 30 minutos y se invalida al usarse
 * (o al pedir uno nuevo). Un enlace valido tambien levanta el bloqueo por intentos fallidos,
 * porque demuestra que quien lo usa controla el correo de la cuenta. Cualquier token
 * invalido, vencido o ya usado muestra el mismo mensaje.
 */
require_once __DIR__ . '/includes/sesion.php';
iniciar_sesion_segura();
sin_cache();
include "conexion.php";
require_once "includes/csrf.php";
require_once "includes/ayuda.php";
require_once "includes/password.php";

if (isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit;
}

$token = (string) ($_POST['token'] ?? $_GET['token'] ?? '');
$usuarioId = 0;
if (preg_match('/^[0-9a-f]{64}$/', $token)) {
    $hash = hash('sha256', $token);
    $stmt = $conn->prepare("SELECT id_usuario AS id FROM usuarios WHERE reset_token_hash = ? AND reset_expira > NOW() AND estado = 'aprobado'");
    $stmt->bind_param("s", $hash);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $usuarioId = $fila ? (int) $fila['id'] : 0;
}

$error = "";
if ($usuarioId && $_POST && isset($_POST['restablecer'])) {
    csrf_verify();
    $password = (string) ($_POST['password'] ?? '');
    $confirmar = (string) ($_POST['confirmar'] ?? '');

    $stmtC = $conn->prepare("SELECT correo FROM usuarios WHERE id_usuario = ?");
    $stmtC->bind_param("i", $usuarioId);
    $stmtC->execute();
    $correoUsuario = (string) ($stmtC->get_result()->fetch_row()[0] ?? '');
    $stmtC->close();

    if (($error = validar_password($password, $correoUsuario)) !== '') {
        // $error ya tiene el mensaje
    } elseif ($password !== $confirmar) {
        $error = "Las contraseñas no coinciden.";
    } else {
        $nuevo = password_hash($password, PASSWORD_DEFAULT);
        // el token se invalida en la misma sentencia: dos envios simultaneos no pueden usarlo dos veces
        $upd = $conn->prepare("UPDATE usuarios SET password = ?, intentos_fallidos = 0, bloqueado_hasta = NULL, reset_token_hash = NULL, reset_expira = NULL WHERE id_usuario = ? AND reset_token_hash = ? AND reset_expira > NOW()");
        $upd->bind_param("sis", $nuevo, $usuarioId, $hash);
        $upd->execute();
        $cambiado = $upd->affected_rows === 1;
        $upd->close();
        if ($cambiado) {
            header("Location: login.php?restablecido=1");
            exit;
        }
        $usuarioId = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Restablecer contraseña - SIRAD</title>
    <link rel="stylesheet" href="css/styles.css?v=<?= @filemtime(__DIR__ . '/css/styles.css') ?>">
    <script src="js/animations.js?v=<?= @filemtime(__DIR__ . '/js/animations.js') ?>" defer></script>
    <script src="js/mensajes.js?v=<?= @filemtime(__DIR__ . '/js/mensajes.js') ?>" defer></script>
    <script src="js/password.js?v=<?= @filemtime(__DIR__ . '/js/password.js') ?>" defer></script>
</head>

<body>

    <div class="auth-page">
        <form class="form-card auth-card" method="POST">
            <h1>SIRAD</h1>
            <p class="auth-subtitle">Restablecer contraseña</p>

            <?php if ($error): ?>
                <p class="auth-error"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>

            <?php if ($usuarioId): ?>
                <?php csrf_field(); ?>
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <label>Nueva contraseña<input type="password" name="password" required minlength="8" maxlength="64" autocomplete="new-password" data-pw></label>
                <?php render_requisitos_password(); ?>
                <label>Confirmar contraseña<input type="password" name="confirmar" required minlength="8" maxlength="64" autocomplete="new-password"></label>
                <button type="submit" name="restablecer" value="1">Restablecer contraseña</button>
            <?php else: ?>
                <p class="auth-error">El enlace no es válido, ya se usó o venció. Pide uno nuevo.</p>
                <p class="auth-link"><a href="recuperar.php">Pedir un enlace nuevo</a></p>
            <?php endif; ?>
            <p class="auth-link"><a href="login.php">&larr; Volver a inicio de sesión</a></p>
        </form>
    </div>

    <?php render_ayuda(); ?>

</body>

</html>
