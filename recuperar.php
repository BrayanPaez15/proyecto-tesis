<?php
/**
 * Recuperacion de contrasena.
 *
 * Con RECUPERAR_POR_CORREO (config.php, el modo recomendado) el usuario escribe su correo y,
 * si pertenece a una cuenta aprobada, recibe un enlace de un solo uso que vence en 30
 * minutos (restablecer.php). La respuesta de esta pagina es siempre la misma exista o no la
 * cuenta, para no revelar que correos estan registrados; ademas, a una misma cuenta no se le
 * envia otro enlace antes de 2 minutos. El token aleatorio de 256 bits solo se guarda como
 * hash SHA-256.
 *
 * Sin servidor de correo (RECUPERAR_POR_CORREO = false) se usa la pregunta de seguridad, en
 * dos pasos: (1) el usuario ingresa su correo y, si existe, se le muestra su pregunta;
 * (2) responde la pregunta y define una nueva contrasena, verificada con password_verify()
 * contra el hash de la respuesta guardado al registrarse. Cada respuesta incorrecta cuenta
 * como un intento fallido de la cuenta: a los 5 la cuenta se bloquea 15 minutos (el mismo
 * bloqueo del inicio de sesion), para impedir adivinar la respuesta por fuerza bruta.
 */
require_once __DIR__ . '/includes/sesion.php';
iniciar_sesion_segura();
sin_cache();
include "conexion.php";
require_once "includes/csrf.php";
require_once "includes/ayuda.php";
require_once "includes/password.php";
require_once "includes/correo.php";

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
$enviado = false;
$paso = isset($_SESSION['recuperar_id']) ? 2 : 1;

if (RECUPERAR_POR_CORREO && $_POST && isset($_POST['paso_1'])) {
    csrf_verify();
    $correo = trim((string) ($_POST['correo'] ?? ''));
    // las fechas se calculan con el reloj de la base de datos (NOW()), no con el de PHP
    $stmt = $conn->prepare("SELECT id_usuario AS id, nombre, estado, (reset_enviado_en > DATE_SUB(NOW(), INTERVAL 2 MINUTE)) AS reciente FROM usuarios WHERE correo = ?");
    $stmt->bind_param("s", $correo);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($user && $user['estado'] === 'aprobado' && !$user['reciente']) {
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $upd = $conn->prepare("UPDATE usuarios SET reset_token_hash = ?, reset_expira = DATE_ADD(NOW(), INTERVAL 30 MINUTE), reset_enviado_en = NOW() WHERE id_usuario = ?");
        $upd->bind_param("si", $hash, $user['id']);
        $upd->execute();
        $upd->close();

        $enlace = rtrim(APP_URL, '/') . '/restablecer.php?token=' . $token;
        $texto = "Hola " . $user['nombre'] . ",

"
            . "Recibimos una solicitud para restablecer la contraseña de tu cuenta en SIRAD.
"
            . "Abre este enlace para elegir una nueva (vence en 30 minutos y solo sirve una vez):

"
            . $enlace . "

"
            . "Si no la pediste tú, ignora este mensaje: tu contraseña no cambiará.
";
        enviar_correo($correo, "Restablecer tu contraseña de SIRAD", $texto);
    }
    $enviado = true;
}

if (!RECUPERAR_POR_CORREO && $_POST && isset($_POST['paso_1'])) {
    csrf_verify();
    $correo = trim($_POST['correo']);
    $stmt = $conn->prepare("SELECT id_usuario AS id, pregunta_seguridad, bloqueado_hasta FROM usuarios WHERE correo = ?");
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

if (!RECUPERAR_POR_CORREO && $_POST && isset($_POST['paso_2'])) {
    csrf_verify();
    $respuesta = trim($_POST['respuesta']);
    $password = $_POST['password'];
    $confirmar = $_POST['confirmar'];
    $id = (int) $_SESSION['recuperar_id'];

    $stmt = $conn->prepare("SELECT correo, respuesta_seguridad_hash, intentos_fallidos, bloqueado_hasta FROM usuarios WHERE id_usuario = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $correoRecuperar = (string) ($user['correo'] ?? '');
    if ($user && $user['bloqueado_hasta'] && strtotime($user['bloqueado_hasta']) > time()) {
        $error = "La cuenta está bloqueada temporalmente por varios intentos fallidos. Intenta de nuevo en " . (int) ceil((strtotime($user['bloqueado_hasta']) - time()) / 60) . " minuto(s).";
        $paso = 2;
    } elseif (!$user || !password_verify(mb_strtolower($respuesta), $user['respuesta_seguridad_hash'])) {
        $error = "La respuesta no es correcta.";
        $paso = 2;
        if ($user) {
            $intentos = (int) $user['intentos_fallidos'] + 1;
            $bloqueo = $intentos >= 5 ? date('Y-m-d H:i:s', time() + 15 * 60) : null;
            $upd = $conn->prepare("UPDATE usuarios SET intentos_fallidos = ?, bloqueado_hasta = ? WHERE id_usuario = ?");
            $upd->bind_param("isi", $intentos, $bloqueo, $id);
            $upd->execute();
            $upd->close();
            if ($bloqueo) {
                $error = "Demasiados intentos fallidos: la cuenta se bloqueó durante 15 minutos.";
                unset($_SESSION['recuperar_id'], $_SESSION['recuperar_pregunta']);
                $paso = 1;
            }
        }
    } elseif (($error = validar_password($password, $correoRecuperar)) !== '') {
        $paso = 2;
    } elseif ($password !== $confirmar) {
        $error = "Las contraseñas no coinciden.";
        $paso = 2;
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $upd = $conn->prepare("UPDATE usuarios SET password = ?, intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id_usuario = ?");
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
    <link rel="stylesheet" href="css/styles.css?v=<?= @filemtime(__DIR__ . '/css/styles.css') ?>">
  <script src="js/animations.js?v=<?= @filemtime(__DIR__ . '/js/animations.js') ?>" defer></script>
  <script src="js/mensajes.js?v=<?= @filemtime(__DIR__ . '/js/mensajes.js') ?>" defer></script>
  <script src="js/password.js?v=<?= @filemtime(__DIR__ . '/js/password.js') ?>" defer></script>
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
            <?php if ($enviado): ?>
                <p class="auth-success">Si el correo pertenece a una cuenta aprobada, te enviamos un enlace para restablecer la contraseña. Revisa tu bandeja de entrada (el enlace vence en 30 minutos).</p>
            <?php elseif ($paso === 1): ?>
                <label>Correo<input type="email" name="correo" required></label>
                <button type="submit" name="paso_1" value="1"><?= RECUPERAR_POR_CORREO ? 'Enviar enlace' : 'Continuar' ?></button>
            <?php else: ?>
                <p class="auth-subtitle"><strong><?= htmlspecialchars($pregunta) ?></strong></p>
                <label>Respuesta<input name="respuesta" required></label>
                <label>Nueva contraseña<input type="password" name="password" required minlength="8" maxlength="64" autocomplete="new-password" data-pw></label>
                <?php render_requisitos_password(); ?>
                <label>Confirmar contraseña<input type="password" name="confirmar" required minlength="8" maxlength="64" autocomplete="new-password"></label>
                <button type="submit" name="paso_2" value="1">Restablecer contraseña</button>
                <p class="auth-link"><a href="recuperar.php?cancelar=1">Usar otro correo</a></p>
            <?php endif; ?>
            <p class="auth-link"><a href="login.php">&larr; Volver a inicio de sesión</a></p>
        </form>
    </div>

    <?php render_ayuda(); ?>

</body>

</html>
