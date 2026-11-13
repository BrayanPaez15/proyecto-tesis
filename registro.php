<?php
/**
 * Registro de usuario.
 *
 * Crea una cuenta nueva en la tabla `usuarios`, validando campos
 * obligatorios, longitud minima de contrasena y correo duplicado. La
 * contrasena y la respuesta de seguridad se guardan encriptadas con
 * password_hash() (bcrypt). Exige aceptar la politica de tratamiento de
 * datos (se guarda la version y la fecha). El primer usuario que se
 * registra en el sistema queda automaticamente como administrador y
 * aprobado; los siguientes se crean con rol de usuario y estado
 * pendiente: no pueden iniciar sesion hasta que un administrador los
 * apruebe (usuarios.php), y el administrador recibe una alerta (alertas.php).
 * Para frenar los registros automaticos incluye una pregunta de suma y un campo
 * trampa oculto (sitio_web) que solo llenan los bots.
 */
require_once __DIR__ . '/includes/sesion.php';
iniciar_sesion_segura();
sin_cache();
include "conexion.php";
require_once "includes/csrf.php";
require_once "includes/ayuda.php";
require_once "includes/politica.php";
require_once "includes/password.php";
require_once "includes/contacto.php";
require_once "includes/accesos.php";

if (isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit;
}

$preguntas = [
    '¿Cuál es el nombre de tu primera mascota?',
    '¿En qué ciudad naciste?',
    '¿Cuál es tu comida favorita?',
    '¿Cuál es el nombre de tu mejor amigo de la infancia?',
];

$error = "";
$errorPersistente = false;
$ipSolicitud = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
if ($_POST) {
    csrf_verify();
    // filtro contra bots: campo trampa que una persona no ve y una pregunta sencilla guardada en la sesion
    $retoEsperado = $_SESSION['registro_reto'] ?? null;
    unset($_SESSION['registro_reto']);
    $nombre = trim($_POST['nombre']);
    $correo = trim($_POST['correo']);
    $password = $_POST['password'];
    $motivo = trim((string) ($_POST['motivo'] ?? ''));
    $acceso = trim((string) ($_POST['acceso'] ?? ''));
    $pregunta = trim($_POST['pregunta'] ?? '');
    $respuesta = trim($_POST['respuesta'] ?? '');

    if (trim((string) ($_POST['sitio_web'] ?? '')) !== '') {
        $error = "No se pudo enviar la solicitud. Intenta de nuevo.";
    } elseif ($retoEsperado === null || trim((string) ($_POST['reto'] ?? '')) !== (string) $retoEsperado) {
        $error = "La respuesta a la verificación no es correcta. Intenta con la nueva pregunta.";
    } elseif ($nombre === '' || $correo === '' || $password === '' || $pregunta === '' || $respuesta === '') {
        $error = "Todos los campos son obligatorios.";
    } elseif (mb_strlen($motivo) < 15 || mb_strlen($motivo) > 500) {
        $error = "Cuéntale al administrador quién eres y por qué necesitas acceso (entre 15 y 500 caracteres).";
    } elseif (!isset(accesos_solicitables()[$acceso])) {
        $error = "Selecciona el rol que necesitas: ver, registrar o ambos.";
    } elseif (!in_array($pregunta, $preguntas, true)) {
        $error = "Selecciona una pregunta de seguridad válida.";
    } elseif (($error = validar_password($password, $correo)) !== '') {
        // $error ya tiene el mensaje
    } elseif (empty($_POST['acepta_politica'])) {
        $error = "Debes aceptar la política de tratamiento de datos personales para crear la cuenta.";
    } else {
        $check = $conn->prepare("SELECT id_usuario AS id FROM usuarios WHERE correo = ?");
        $check->bind_param("s", $correo);
        $check->execute();
        $check->store_result();
        if ($check->num_rows > 0) {
            $error = "Ya existe una cuenta con ese correo.";
        }
        $check->close();

        // limites de solicitudes (no aplican a la primera cuenta del sistema, que queda como administrador)
        if ($error === '' && (int) $conn->query("SELECT COUNT(*) FROM usuarios")->fetch_row()[0] > 0) {
            $limite = $conn->prepare("SELECT COUNT(*) FROM usuarios WHERE rol = 'usuario' AND ip_solicitud = ? AND creado_en > DATE_SUB(NOW(), INTERVAL ? MINUTE)");
            $ventana = (int) SOLICITUDES_VENTANA_MIN;
            $limite->bind_param("si", $ipSolicitud, $ventana);
            $limite->execute();
            $porIp = (int) $limite->get_result()->fetch_row()[0];
            $limite->close();
            $pendientes = (int) $conn->query("SELECT COUNT(*) FROM usuarios WHERE estado = 'pendiente'")->fetch_row()[0];
            if ($porIp >= (int) SOLICITUDES_MAX_POR_IP) {
                $error = "Se recibieron demasiadas solicitudes desde tu conexión. Intenta de nuevo en " . $ventana . " minutos." . frase_contacto($conn);
                $errorPersistente = true;
            } elseif ($pendientes >= (int) SOLICITUDES_MAX_PENDIENTES) {
                $error = "El sistema no recibe más solicitudes por ahora porque hay muchas pendientes de revisión." . frase_contacto($conn);
                $errorPersistente = true;
            }
        }
    }

    if ($error === '') {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $respuestaHash = password_hash(mb_strtolower($respuesta), PASSWORD_DEFAULT);

        $totalUsuarios = $conn->query("SELECT COUNT(*) AS total FROM usuarios")->fetch_assoc()['total'];
        $esPrimero = ((int) $totalUsuarios === 0);
        $rol = $esPrimero ? 'admin' : 'usuario';
        $estado = $esPrimero ? 'aprobado' : 'pendiente';
        $version = POLITICA_VERSION;

        $stmt = $conn->prepare("INSERT INTO usuarios (nombre, correo, password, rol, estado, pregunta_seguridad, respuesta_seguridad_hash, politica_version, politica_aceptada_en, motivo_solicitud, acceso_solicitado, ip_solicitud) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?)");
        $stmt->bind_param("sssssssssss", $nombre, $correo, $hash, $rol, $estado, $pregunta, $respuestaHash, $version, $motivo, $acceso, $ipSolicitud);
        $stmt->execute();
        $nuevoId = $conn->insert_id;
        $stmt->close();
        header("Location: login.php?registrado=" . ($esPrimero ? "1" : "pendiente"));
        exit;
    }
}

// pregunta de verificacion nueva en cada vez que se muestra el formulario
$retoA = random_int(2, 9);
$retoB = random_int(2, 9);
$_SESSION['registro_reto'] = (string) ($retoA + $retoB);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Crear usuario - SIRAD</title>
    <link rel="stylesheet" href="css/styles.css?v=<?= @filemtime(__DIR__ . '/css/styles.css') ?>">
  <script src="js/animations.js?v=<?= @filemtime(__DIR__ . '/js/animations.js') ?>" defer></script>
  <script src="js/mensajes.js?v=<?= @filemtime(__DIR__ . '/js/mensajes.js') ?>" defer></script>
  <script src="js/password.js?v=<?= @filemtime(__DIR__ . '/js/password.js') ?>" defer></script>
</head>

<body>

    <div class="auth-page">
        <form class="form-card auth-card auth-ancho" method="POST">
            <h1>SIRAD</h1>
            <p class="auth-subtitle">Crear cuenta</p>

            <?php if ($error): ?>
                <p class="auth-error"<?= $errorPersistente ? ' data-persistente' : '' ?>><?= aviso_con_enlaces($error) ?></p>
            <?php endif; ?>

            <?php csrf_field(); ?>
            <label>Nombre<input name="nombre" required value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>"></label>
            <label>Correo<input type="email" name="correo" required value="<?= htmlspecialchars($_POST['correo'] ?? '') ?>"></label>
            <label class="campo-ancho">¿Quién eres y por qué necesitas acceso?<textarea name="motivo" required minlength="15" maxlength="500" rows="2" placeholder="Ej.: Soy practicante de la Fundación y necesito registrar artistas del programa de lectura."><?= htmlspecialchars($_POST['motivo'] ?? '') ?></textarea></label>
            <label class="campo-ancho">Rol
                <select name="acceso" required>
                    <option value="">Selecciona qué necesitas hacer...</option>
                    <?php foreach (accesos_solicitables() as $clave => $texto): ?>
                        <option value="<?= htmlspecialchars($clave) ?>" <?= (($_POST['acceso'] ?? '') === $clave) ? 'selected' : '' ?>><?= htmlspecialchars($texto) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="campo-pw">
                <label>Contraseña<input type="password" name="password" required minlength="8" maxlength="64" autocomplete="new-password" data-pw></label>
                <?php render_requisitos_password(); ?>
            </div>
            <label>Pregunta de seguridad
                <select name="pregunta" required>
                    <option value="">Selecciona una pregunta...</option>
                    <?php foreach ($preguntas as $p): ?>
                        <option value="<?= htmlspecialchars($p) ?>" <?= (($_POST['pregunta'] ?? '') === $p) ? 'selected' : '' ?>><?= htmlspecialchars($p) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="campo-ancho">Respuesta<input name="respuesta" required></label>
            <p class="auth-subtitle">La pregunta de seguridad sirve para recuperar tu contraseña si la olvidas.</p>
            <label class="campo-ancho">Verificación: ¿cuánto es <?= $retoA ?> + <?= $retoB ?>?<input name="reto" required inputmode="numeric" autocomplete="off" maxlength="3"></label>
            <div class="campo-trampa" aria-hidden="true"><label>No llenes este campo<input name="sitio_web" tabindex="-1" autocomplete="off"></label></div>
            <label class="check-label"><input type="checkbox" name="acepta_politica" value="1" required <?= !empty($_POST['acepta_politica']) ? 'checked' : '' ?>><span>He leído y acepto la <a href="politica.php" target="_blank" rel="noopener">política de tratamiento de datos personales</a> (versión <?= htmlspecialchars(POLITICA_VERSION) ?>).</span></label>
            <p class="pending-notice">Tu cuenta requiere la aprobación de un administrador antes de que puedas iniciar sesión.</p>
            <button>Solicitar cuenta</button>
            <p class="auth-link">¿Ya tienes cuenta? <a href="login.php">Iniciar sesión</a></p>
        </form>
    </div>

    <?php render_ayuda(); ?>

</body>

</html>
