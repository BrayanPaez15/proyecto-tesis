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
 */
require_once __DIR__ . '/includes/sesion.php';
iniciar_sesion_segura();
sin_cache();
include "conexion.php";
require_once "includes/csrf.php";
require_once "includes/politica.php";
require_once "includes/alertas.php";

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
if ($_POST) {
    csrf_verify();
    $nombre = trim($_POST['nombre']);
    $correo = trim($_POST['correo']);
    $password = $_POST['password'];
    $pregunta = trim($_POST['pregunta'] ?? '');
    $respuesta = trim($_POST['respuesta'] ?? '');

    if ($nombre === '' || $correo === '' || $password === '' || $pregunta === '' || $respuesta === '') {
        $error = "Todos los campos son obligatorios.";
    } elseif (!in_array($pregunta, $preguntas, true)) {
        $error = "Selecciona una pregunta de seguridad válida.";
    } elseif (strlen($password) < 6) {
        $error = "La contraseña debe tener al menos 6 caracteres.";
    } elseif (empty($_POST['acepta_politica'])) {
        $error = "Debes aceptar la política de tratamiento de datos personales para crear la cuenta.";
    } else {
        $check = $conn->prepare("SELECT id FROM usuarios WHERE correo = ?");
        $check->bind_param("s", $correo);
        $check->execute();
        $check->store_result();
        if ($check->num_rows > 0) {
            $error = "Ya existe una cuenta con ese correo.";
        }
        $check->close();
    }

    if ($error === '') {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $respuestaHash = password_hash(mb_strtolower($respuesta), PASSWORD_DEFAULT);

        $totalUsuarios = $conn->query("SELECT COUNT(*) AS total FROM usuarios")->fetch_assoc()['total'];
        $esPrimero = ((int) $totalUsuarios === 0);
        $rol = $esPrimero ? 'admin' : 'usuario';
        $estado = $esPrimero ? 'aprobado' : 'pendiente';
        $version = POLITICA_VERSION;

        $stmt = $conn->prepare("INSERT INTO usuarios (nombre, correo, password, rol, estado, pregunta_seguridad, respuesta_seguridad_hash, politica_version, politica_aceptada_en) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssssssss", $nombre, $correo, $hash, $rol, $estado, $pregunta, $respuestaHash, $version);
        $stmt->execute();
        $nuevoId = $conn->insert_id;
        $stmt->close();
        if (!$esPrimero) {
            alerta_registrar($conn, 'registro', $nuevoId, "Nueva cuenta creada: $nombre ($correo). Pendiente de aprobación.");
        }
        header("Location: login.php?registrado=" . ($esPrimero ? "1" : "pendiente"));
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Crear usuario - SIRAD</title>
    <link rel="stylesheet" href="css/styles.css">
  <script src="js/animations.js" defer></script>
  <script src="js/mensajes.js" defer></script>
</head>

<body>

    <div class="auth-page">
        <form class="form-card auth-card" method="POST">
            <h1>SIRAD</h1>
            <p class="auth-subtitle">Crear cuenta</p>

            <?php if ($error): ?>
                <p class="auth-error"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>

            <?php csrf_field(); ?>
            <label>Nombre<input name="nombre" required value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>"></label>
            <label>Correo<input type="email" name="correo" required value="<?= htmlspecialchars($_POST['correo'] ?? '') ?>"></label>
            <label>Contraseña<input type="password" name="password" required minlength="6"></label>
            <label>Pregunta de seguridad
                <select name="pregunta" required>
                    <option value="">Selecciona una pregunta...</option>
                    <?php foreach ($preguntas as $p): ?>
                        <option value="<?= htmlspecialchars($p) ?>" <?= (($_POST['pregunta'] ?? '') === $p) ? 'selected' : '' ?>><?= htmlspecialchars($p) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Respuesta<input name="respuesta" required></label>
            <p class="auth-subtitle">La pregunta de seguridad sirve para recuperar tu contraseña si la olvidas.</p>
            <label class="check-label"><input type="checkbox" name="acepta_politica" value="1" required <?= !empty($_POST['acepta_politica']) ? 'checked' : '' ?>><span>He leído y acepto la <a href="politica.php" target="_blank" rel="noopener">política de tratamiento de datos personales</a> (versión <?= htmlspecialchars(POLITICA_VERSION) ?>).</span></label>
            <p class="pending-notice">Tu cuenta requiere la aprobación de un administrador antes de que puedas iniciar sesión.</p>
            <button>Solicitar cuenta</button>
            <p class="auth-link">¿Ya tienes cuenta? <a href="login.php">Iniciar sesión</a></p>
        </form>
    </div>

</body>

</html>
