<?php
/**
 * Modulo Practicantes - Registrar.
 *
 * Formulario de registro y edicion para la tabla `practicantes`. Inserta un
 * nuevo registro (validando formato de correo y evitando duplicados por
 * correo) o actualiza uno existente (parametro GET "id", solo permitido
 * a administradores) mediante sentencias preparadas, dejando rastro de
 * auditoria (creado_por / actualizado_por / actualizado_en). Requiere
 * sesion activa (auth.php) y proteccion CSRF.
 */
require "auth.php";
include "conexion.php";
require_once "includes/layout.php";
require_once "includes/csrf.php";
require_permiso('practicantes', 'registrar');

$editando = null;
if (isset($_GET['id'])) {
  require_admin();
  $stmt = $conn->prepare("SELECT * FROM practicantes WHERE id = ?");
  $id = (int) $_GET['id'];
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $editando = $stmt->get_result()->fetch_assoc();
  $stmt->close();
}

$error = "";
if ($_POST) {
  csrf_verify();
  $usuarioId = (int) $_SESSION['usuario_id'];

  if (!empty($_POST['id'])) {
    require_admin();
    $id = (int) $_POST['id'];
    $stmt = $conn->prepare("UPDATE practicantes SET nombre = ?, telefono = ?, correo = ?, direccion = ?, disciplina = ?, generacion = ?, inicio = ?, fin = ?, cumple = ?, contacto = ?, telefono_contacto = ?, actualizado_en = NOW(), actualizado_por = ? WHERE id = ?");
    $stmt->bind_param("sssssssssssii", $_POST['nombre'], $_POST['telefono'], $_POST['correo'], $_POST['direccion'], $_POST['disciplina'], $_POST['generacion'], $_POST['inicio'], $_POST['fin'], $_POST['cumple'], $_POST['contacto'], $_POST['telefono_contacto'], $usuarioId, $id);
    $stmt->execute();
    $stmt->close();
    header("Location: practicantes_registros.php?msg=" . rawurlencode("Registro actualizado") . "&tipo=success");
    exit;
  }

  $correo = trim($_POST['correo'] ?? '');
  if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    $error = "El correo no tiene un formato válido.";
  } elseif ($correo !== '') {
    $check = $conn->prepare("SELECT id FROM practicantes WHERE correo = ?");
    $check->bind_param("s", $correo);
    $check->execute();
    $check->store_result();
    if ($check->num_rows > 0) {
      $error = "Ya existe un registro con este correo en este módulo.";
    }
    $check->close();
  }

  if ($error === '') {
    $stmt = $conn->prepare("INSERT INTO practicantes (nombre, telefono, correo, direccion, disciplina, generacion, inicio, fin, cumple, contacto, telefono_contacto, creado_por) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssssssssi", $_POST['nombre'], $_POST['telefono'], $_POST['correo'], $_POST['direccion'], $_POST['disciplina'], $_POST['generacion'], $_POST['inicio'], $_POST['fin'], $_POST['cumple'], $_POST['contacto'], $_POST['telefono_contacto'], $usuarioId);
    $stmt->execute();
    $stmt->close();
    header("Location: " . (puede_modulo('practicantes', 'ver') ? 'practicantes_registros.php' : 'practicantes.php') . "?msg=" . rawurlencode("Registro guardado") . "&tipo=success");
    exit;
  }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <title>Practicantes - SIRAD</title>
  <link rel="stylesheet" href="css/styles.css">
  <script src="js/animations.js" defer></script>
  <script src="js/toast.js" defer></script>
</head>

<body>

  <?php render_header(); ?>

  <main>
    <h1>Practicantes</h1>

    <?php render_page_actions('practicantes', 'registrar'); ?>

    <?php if ($error): ?>
      <p class="auth-error" style="margin-bottom: 16px;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <?php if ($editando): ?>
      <p class="edit-notice">Editando registro #<?= (int) $editando['id'] ?> &mdash; <a href="practicantes.php">Cancelar</a></p>
    <?php endif; ?>

    <form class="form-card" method="POST">
      <?php csrf_field(); ?>
      <?php if ($editando): ?>
        <input type="hidden" name="id" value="<?= (int) $editando['id'] ?>">
      <?php endif; ?>
      <label>Nombre<input name="nombre" required value="<?= htmlspecialchars($editando['nombre'] ?? '') ?>"></label>
      <label>Teléfono<input name="telefono" value="<?= htmlspecialchars($editando['telefono'] ?? '') ?>"></label>
      <label>Correo<input name="correo" type="email" value="<?= htmlspecialchars($editando['correo'] ?? '') ?>"></label>
      <label>Dirección<input name="direccion" value="<?= htmlspecialchars($editando['direccion'] ?? '') ?>"></label>
      <label>Disciplina<input name="disciplina" value="<?= htmlspecialchars($editando['disciplina'] ?? '') ?>"></label>
      <label>Generación<input name="generacion" value="<?= htmlspecialchars($editando['generacion'] ?? '') ?>"></label>
      <label>Fecha inicio<input name="inicio" type="date" value="<?= htmlspecialchars($editando['inicio'] ?? '') ?>"></label>
      <label>Fecha fin<input name="fin" type="date" value="<?= htmlspecialchars($editando['fin'] ?? '') ?>"></label>
      <label>Cumpleaños<input name="cumple" type="date" value="<?= htmlspecialchars($editando['cumple'] ?? '') ?>"></label>
      <label>Contacto de emergencia<input name="contacto" value="<?= htmlspecialchars($editando['contacto'] ?? '') ?>"></label>
      <label>Teléfono contacto<input name="telefono_contacto" value="<?= htmlspecialchars($editando['telefono_contacto'] ?? '') ?>"></label>
      <button><?= $editando ? 'Actualizar' : 'Guardar' ?></button>
    </form>
  </main>

</body>

</html>
