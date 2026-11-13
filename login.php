<?php
/**
 * Inicio de sesion.
 *
 * Verifica las credenciales del usuario contra la tabla `usuarios`
 * mediante sentencia preparada y password_verify() (nunca se compara
 * la contrasena en texto plano). Bloquea la cuenta 15 minutos tras 5
 * intentos fallidos consecutivos. Si las credenciales son validas pero la
 * cuenta esta pendiente o rechazada, informa el estado y no inicia sesion.
 * Si la cuenta esta aprobada, crea la sesion (incluyendo el rol y el permiso
 * de importacion), cambia el identificador de sesion (renovar_sesion) y redirige a index.php. Cuando entra un usuario no
 * administrador, o intenta entrar uno sin aprobar, se registra una alerta
 * para los administradores (alertas.php).
 */
require_once __DIR__ . '/includes/sesion.php';
iniciar_sesion_segura();
sin_cache();
include "conexion.php";
require_once "includes/csrf.php";
require_once "includes/ayuda.php";
require_once "includes/contacto.php";

if (isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit;
}

$error = "";
$errorPersistente = false;
// Cuando auth.php cierra la sesion porque la cuenta fue rechazada o eliminada, llega con ?aviso=...
$aviso = $_GET['aviso'] ?? '';
if ($aviso === 'rechazada') {
    $error = "Tu solicitud de acceso fue rechazada." . frase_contacto($conn);
    $errorPersistente = true;
} elseif ($aviso === 'eliminada') {
    $error = "Tu usuario fue eliminado y ya no tienes acceso al sistema." . frase_contacto($conn);
    $errorPersistente = true;
}
if ($_POST) {
    csrf_verify();
    $correo = $_POST['correo'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id_usuario AS id, nombre, password, rol, estado, puede_importar, intentos_fallidos, bloqueado_hasta FROM usuarios WHERE correo = ?");
    $stmt->bind_param("s", $correo);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $bloqueadoHasta = $user['bloqueado_hasta'] ?? null;
    if ($user && $bloqueadoHasta && strtotime($bloqueadoHasta) > time()) {
        $minutos = (int) ceil((strtotime($bloqueadoHasta) - time()) / 60);
        $error = "Cuenta bloqueada temporalmente por varios intentos fallidos. Intenta de nuevo en $minutos minuto(s).";
    } elseif ($user && password_verify($password, $user['password']) && $user['estado'] !== 'aprobado') {
        $error = $user['estado'] === 'pendiente'
            ? "Tu cuenta está pendiente de aprobación. Un administrador debe aprobarla antes de que puedas iniciar sesión."
            : "Tu solicitud de acceso fue rechazada." . frase_contacto($conn);
        $errorPersistente = $user['estado'] !== 'pendiente';
        $marca = $conn->prepare("UPDATE usuarios SET ultimo_intento_pendiente = NOW() WHERE id_usuario = ?");
        $marca->bind_param("i", $user['id']);
        $marca->execute();
        $marca->close();
    } elseif ($user && password_verify($password, $user['password'])) {
        $reset = $conn->prepare("UPDATE usuarios SET intentos_fallidos = 0, bloqueado_hasta = NULL, ultimo_acceso = NOW() WHERE id_usuario = ?");
        $reset->bind_param("i", $user['id']);
        $reset->execute();
        $reset->close();

        renovar_sesion();
        $_SESSION['usuario_id'] = $user['id'];
        $_SESSION['usuario_nombre'] = $user['nombre'];
        $_SESSION['usuario_rol'] = $user['rol'];
        $_SESSION['usuario_puede_importar'] = (int) $user['puede_importar'];
        header("Location: index.php");
        exit;
    } else {
        $error = "Correo o contraseña incorrectos.";
        if ($user) {
            $intentos = (int) $user['intentos_fallidos'] + 1;
            $bloqueo = $intentos >= 5 ? date('Y-m-d H:i:s', time() + 15 * 60) : null;
            $upd = $conn->prepare("UPDATE usuarios SET intentos_fallidos = ?, bloqueado_hasta = ? WHERE id_usuario = ?");
            $upd->bind_param("isi", $intentos, $bloqueo, $user['id']);
            $upd->execute();
            $upd->close();
            if ($bloqueo) {
                $error = "Cuenta bloqueada temporalmente por varios intentos fallidos. Intenta de nuevo en 15 minuto(s).";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión - SIRAD</title>
    <link rel="stylesheet" href="css/styles.css?v=<?= @filemtime(__DIR__ . '/css/styles.css') ?>">
  <script src="js/animations.js?v=<?= @filemtime(__DIR__ . '/js/animations.js') ?>" defer></script>
  <script src="js/mensajes.js?v=<?= @filemtime(__DIR__ . '/js/mensajes.js') ?>" defer></script>
</head>

<body>

    <div class="auth-page">
        <form class="form-card auth-card" method="POST">
            <h1>SIRAD</h1>
            <p class="auth-subtitle">Sistema de Registro y Administración de Directorios</p>

            <?php if ($error): ?>
                <p class="auth-error"<?= !empty($errorPersistente) ? ' data-persistente' : '' ?>><?= aviso_con_enlaces($error) ?></p>
            <?php endif; ?>
            <?php if (($_GET['registrado'] ?? '') === 'pendiente'): ?>
                <p class="auth-success"><strong>Solicitud enviada.</strong> Tu cuenta requiere la aprobación de un administrador. Cuando la apruebe podrás iniciar sesión con tu correo y contraseña; si no la aprueba, no tendrás acceso al sistema.</p>
            <?php elseif (isset($_GET['registrado'])): ?>
                <p class="auth-success">Cuenta creada. Ya puedes iniciar sesión.</p>
            <?php endif; ?>
            <?php if (isset($_GET['restablecido'])): ?>
                <p class="auth-success">Contraseña actualizada. Ya puedes iniciar sesión.</p>
            <?php endif; ?>

            <?php csrf_field(); ?>
            <label>Correo<input type="email" name="correo" required></label>
            <label>Contraseña<input type="password" name="password" required></label>
            <button>Ingresar</button>
            <p class="auth-link">¿No tienes cuenta? <a href="registro.php">Crear usuario</a></p>
            <p class="auth-link">¿Olvidaste tu contraseña? <a href="recuperar.php">Recuperarla</a></p>
        </form>
    </div>

    <?php render_ayuda(); ?>

</body>

</html>
