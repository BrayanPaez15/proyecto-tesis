<?php
/**
 * Recuperacion de contrasena mediante pregunta de seguridad.
 *
 * Flujo de dos pasos, sin necesitar servidor de correo: (1) el usuario
 * ingresa su correo y, si existe, se le muestra su pregunta de
 * seguridad; (2) responde la pregunta y define una nueva contrasena,
 * verificada con password_verify() contra el hash de la respuesta
 * guardado al registrarse. Cada respuesta incorrecta cuenta como un intento
 * fallido de la cuenta: a los 5 la cuenta se bloquea 15 minutos (el mismo bloqueo
 * del inicio de sesion), para impedir adivinar la respuesta por fuerza bruta.
 */
require_once __DIR__ . '/includes/sesion.php';
iniciar_sesion_segura();
sin_cache();
include "conexion.php";
require_once "includes/csrf.php";

if (isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit;
}

if (isset($_GET['cancelar'])) {
    unset($_SESSION['recuperar_id'], $_SESSION['recuperar_pregunta']);
    header("Location: recuperar.php");
    exit;
}

$error = "";
$paso = isset($_SESSION['recuperar_id']) ? 2 : 1;

if ($_POST && isset($_POST['paso_1'])) {
    csrf_verify();
    $correo = trim($_POST['correo']);
    $stmt = $conn->prepare("SELECT id, pregunta_seguridad, bloqueado_hasta FROM usuarios WHERE correo = ?");
    $stmt->bind_param("s", $correo);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user || !$user['pregunta_seguridad']) {
        $error = "No encontramos una cuenta con ese correo.";
        $paso = 1;
    } elseif ($user['bloqueado_hasta'] && strtotime($user['bloqueado_hasta']) > time()) {
        $error = "La cuenta está bloqueada temporalmente por varios intentos fallidos. Intenta de nuevo en " . (int) ceil((strtotime($user['bloqueado_hasta']) - time()) / 60) . " minuto(s).";
        $paso = 1;
    } else {
        $_SESSION['recuperar_id'] = $user['id'];
        $_SESSION['recuperar_pregunta'] = $user['pregunta_seguridad'];
        $paso = 2;
    }
}

if ($_POST && isset($_POST['paso_2'])) {
    csrf_verify();
    $respuesta = trim($_POST['respuesta']);
    $password = $_POST['password'];
    $confirmar = $_POST['confirmar'];
    $id = (int) $_SESSION['recuperar_id'];

    $stmt = $conn->prepare("SELECT respuesta_seguridad_hash, intentos_fallidos, bloqueado_hasta FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($user && $user['bloqueado_hasta'] && strtotime($user['bloqueado_hasta']) > time()) {
        $error = "La cuenta está bloqueada temporalmente por varios intentos fallidos. Intenta de nuevo en " . (int) ceil((strtotime($user['bloqueado_hasta']) - time()) / 60) . " minuto(s).";
        $paso = 2;
    } elseif (!$user || !password_verify(mb_strtolower($respuesta), $user['respuesta_seguridad_hash'])) {
        $error = "La respuesta no es correcta.";
        $paso = 2;
        if ($user) {
            $intentos = (int) $user['intentos_fallidos'] + 1;
            $bloqueo = $intentos >= 5 ? date('Y-m-d H:i:s', time() + 15 * 60) : null;
            $upd = $conn->prepare("UPDATE usuarios SET intentos_fallidos = ?, bloqueado_hasta = ? WHERE id = ?");
            $upd->bind_param("isi", $intentos, $bloqueo, $id);
            $upd->execute();
            $upd->close();
            if ($bloqueo) {
                $error = "Demasiados intentos fallidos: la cuenta se bloqueó durante 15 minutos.";
                unset($_SESSION['recuperar_id'], $_SESSION['recuperar_pregunta']);
                $paso = 1;
            }
        }
    } elseif (strlen($password) < 6) {
        $error = "La contraseña debe tener al menos 6 caracteres.";
        $paso = 2;
    } elseif ($password !== $confirmar) {
        $error = "Las contraseñas no coinciden.";
        $paso = 2;
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $upd = $conn->prepare("UPDATE usuarios SET password = ?, intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = ?");
        $upd->bind_param("si", $hash, $id);
        $upd->execute();
        $upd->close();

        unset($_SESSION['recuperar_id'], $_SESSION['recuperar_pregunta']);
        header("Location: login.php?restablecido=1");
        exit;
    }
}

$pregunta = $_SESSION['recuperar_pregunta'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Recuperar contraseña - SIRAD</title>
    <link rel="stylesheet" href="css/styles.css">
  <script src="js/animations.js" defer></script>
  <script src="js/mensajes.js" defer></script>
</head>

<body>

    <div class="auth-page">
        <form class="form-card auth-card" method="POST">
            <h1>SIRAD</h1>
            <p class="auth-subtitle">Recuperar contraseña</p>

            <?php if ($error): ?>
                <p class="auth-error"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>

            <?php csrf_field(); ?>
            <?php if ($paso === 1): ?>
                <label>Correo<input type="email" name="correo" required></label>
                <button type="submit" name="paso_1" value="1">Continuar</button>
            <?php else: ?>
                <p class="auth-subtitle"><strong><?= htmlspecialchars($pregunta) ?></strong></p>
                <label>Respuesta<input name="respuesta" required></label>
                <label>Nueva contraseña<input type="password" name="password" required minlength="6"></label>
                <label>Confirmar contraseña<input type="password" name="confirmar" required minlength="6"></label>
                <button type="submit" name="paso_2" value="1">Restablecer contraseña</button>
                <p class="auth-link"><a href="recuperar.php?cancelar=1">Usar otro correo</a></p>
            <?php endif; ?>
            <p class="auth-link"><a href="login.php">&larr; Volver a inicio de sesión</a></p>
        </form>
    </div>

</body>

</html>
