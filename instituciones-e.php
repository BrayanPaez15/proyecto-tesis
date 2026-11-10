<?php
/**
 * Modulo Instituciones Educativas - Registrar.
 *
 * Formulario de registro y edicion para la tabla `instituciones_e`. Inserta un
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
require_permiso('instituciones-e', 'registrar');

$editando = null;
if (isset($_GET['id'])) {
  require_admin();
  $stmt = $conn->prepare("SELECT * FROM instituciones_e WHERE id = ?");
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
    $stmt = $conn->prepare("UPDATE instituciones_e SET clase = ?, nombre = ?, nit = ?, calidad = ?, jornada = ?, contacto = ?, cargo = ?, telefono = ?, direccion = ?, correo = ?, ciudad = ?, actualizado_en = NOW(), actualizado_por = ? WHERE id = ?");
    $stmt->bind_param("sssssssssssii", $_POST['clase'], $_POST['nombre'], $_POST['nit'], $_POST['calidad'], $_POST['jornada'], $_POST['contacto'], $_POST['cargo'], $_POST['telefono'], $_POST['direccion'], $_POST['correo'], $_POST['ciudad'], $usuarioId, $id);
    $stmt->execute();
    $stmt->close();
    header("Location: instituciones-e_registros.php?msg=" . rawurlencode("Registro actualizado") . "&tipo=success");
    exit;
  }

  $correo = trim($_POST['correo'] ?? '');
  if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    $error = "El correo no tiene un formato válido.";
  } elseif ($correo !== '') {
    $check = $conn->prepare("SELECT id FROM instituciones_e WHERE correo = ?");
    $check->bind_param("s", $correo);
    $check->execute();
    $check->store_result();
    if ($check->num_rows > 0) {
      $error = "Ya existe un registro con este correo en este módulo.";
    }
    $check->close();
  }

  if ($error === '') {
    $stmt = $conn->prepare("INSERT INTO instituciones_e (clase, nombre, nit, calidad, jornada, contacto, cargo, telefono, direccion, correo, ciudad, creado_por) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssssssssi", $_POST['clase'], $_POST['nombre'], $_POST['nit'], $_POST['calidad'], $_POST['jornada'], $_POST['contacto'], $_POST['cargo'], $_POST['telefono'], $_POST['direccion'], $_POST['correo'], $_POST['ciudad'], $usuarioId);
    $stmt->execute();
    $stmt->close();
    header("Location: " . (puede_modulo('instituciones-e', 'ver') ? 'instituciones-e_registros.php' : 'instituciones-e.php') . "?msg=" . rawurlencode("Registro guardado") . "&tipo=success");
    exit;
  }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <title>Instituciones Educativas - SIRAD</title>
  <link rel="stylesheet" href="css/styles.css">
  <script src="js/animations.js" defer></script>
  <script src="js/toast.js" defer></script>
</head>

<body>

  <?php render_header(); ?>

  <main>
    <h1>Instituciones Educativas</h1>

    <?php render_page_actions('instituciones-e', 'registrar'); ?>

    <?php if ($error): ?>
      <p class="auth-error" style="margin-bottom: 16px;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <?php if ($editando): ?>
      <p class="edit-notice">Editando registro #<?= (int) $editando['id'] ?> &mdash; <a href="instituciones-e.php">Cancelar</a></p>
    <?php endif; ?>

    <form class="form-card" method="POST">
      <?php csrf_field(); ?>
      <?php if ($editando): ?>
        <input type="hidden" name="id" value="<?= (int) $editando['id'] ?>">
      <?php endif; ?>
      <label>Clase<input name="clase" required value="<?= htmlspecialchars($editando['clase'] ?? '') ?>"></label>
      <label>Nombre<input name="nombre" value="<?= htmlspecialchars($editando['nombre'] ?? '') ?>"></label>
      <label>NIT<input name="nit" value="<?= htmlspecialchars($editando['nit'] ?? '') ?>"></label>
      <label>Calidad<input name="calidad" value="<?= htmlspecialchars($editando['calidad'] ?? '') ?>"></label>
      <label>Jornada<input name="jornada" value="<?= htmlspecialchars($editando['jornada'] ?? '') ?>"></label>
      <label>Contacto<input name="contacto" value="<?= htmlspecialchars($editando['contacto'] ?? '') ?>"></label>
      <label>Cargo<input name="cargo" value="<?= htmlspecialchars($editando['cargo'] ?? '') ?>"></label>
      <label>Teléfono<input name="telefono" value="<?= htmlspecialchars($editando['telefono'] ?? '') ?>"></label>
      <label>Dirección<input name="direccion" value="<?= htmlspecialchars($editando['direccion'] ?? '') ?>"></label>
      <label>Correo<input name="correo" type="email" value="<?= htmlspecialchars($editando['correo'] ?? '') ?>"></label>
      <label>Ciudad<input name="ciudad" value="<?= htmlspecialchars($editando['ciudad'] ?? '') ?>"></label>
      <button><?= $editando ? 'Actualizar' : 'Guardar' ?></button>
    </form>
  </main>

</body>

</html>
