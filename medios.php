<?php
/**
 * Modulo Medios - Registrar.
 *
 * Formulario de registro y edicion para la tabla `medios`. Inserta un
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
require_permiso('medios', 'registrar');

$editando = null;
if (isset($_GET['id'])) {
  require_admin();
  $stmt = $conn->prepare("SELECT * FROM medios WHERE id = ?");
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
    $stmt = $conn->prepare("UPDATE medios SET categoria = ?, medio = ?, fuente = ?, nombre = ?, correo = ?, telefono = ?, telefono2 = ?, direccion = ?, actualizado_en = NOW(), actualizado_por = ? WHERE id = ?");
    $stmt->bind_param("ssssssssii", $_POST['categoria'], $_POST['medio'], $_POST['fuente'], $_POST['nombre'], $_POST['correo'], $_POST['telefono'], $_POST['telefono2'], $_POST['direccion'], $usuarioId, $id);
    $stmt->execute();
    $stmt->close();
    header("Location: medios_registros.php?msg=" . rawurlencode("Registro actualizado") . "&tipo=success");
    exit;
  }

  $correo = trim($_POST['correo'] ?? '');
  if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    $error = "El correo no tiene un formato válido.";
  } elseif ($correo !== '') {
    $check = $conn->prepare("SELECT id FROM medios WHERE correo = ?");
    $check->bind_param("s", $correo);
    $check->execute();
    $check->store_result();
    if ($check->num_rows > 0) {
      $error = "Ya existe un registro con este correo en este módulo.";
    }
    $check->close();
  }

  if ($error === '') {
    $stmt = $conn->prepare("INSERT INTO medios (categoria, medio, fuente, nombre, correo, telefono, telefono2, direccion, creado_por) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssssssi", $_POST['categoria'], $_POST['medio'], $_POST['fuente'], $_POST['nombre'], $_POST['correo'], $_POST['telefono'], $_POST['telefono2'], $_POST['direccion'], $usuarioId);
    $stmt->execute();
    $stmt->close();
    header("Location: medios_registros.php?msg=" . rawurlencode("Registro guardado") . "&tipo=success");
    exit;
  }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <title>Medios - SIRAD</title>
  <link rel="stylesheet" href="css/styles.css">
  <script src="js/animations.js" defer></script>
</head>

<body>

  <?php render_header(); ?>

  <main>
    <h1>Medios</h1>

    <?php render_page_actions('medios', 'registrar'); ?>

    <?php if ($error): ?>
      <p class="auth-error" style="margin-bottom: 16px;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <?php if ($editando): ?>
      <p class="edit-notice">Editando registro #<?= (int) $editando['id'] ?> &mdash; <a href="medios.php">Cancelar</a></p>
    <?php endif; ?>

    <form class="form-card" method="POST">
      <?php csrf_field(); ?>
      <?php if ($editando): ?>
        <input type="hidden" name="id" value="<?= (int) $editando['id'] ?>">
      <?php endif; ?>
      <label>Categoría<input name="categoria" required value="<?= htmlspecialchars($editando['categoria'] ?? '') ?>"></label>
      <label>Medio<input name="medio" value="<?= htmlspecialchars($editando['medio'] ?? '') ?>"></label>
      <label>Fuente<input name="fuente" value="<?= htmlspecialchars($editando['fuente'] ?? '') ?>"></label>
      <label>Nombre<input name="nombre" value="<?= htmlspecialchars($editando['nombre'] ?? '') ?>"></label>
      <label>Correo<input name="correo" type="email" value="<?= htmlspecialchars($editando['correo'] ?? '') ?>"></label>
      <label>Teléfono<input name="telefono" value="<?= htmlspecialchars($editando['telefono'] ?? '') ?>"></label>
      <label>Teléfono 2<input name="telefono2" value="<?= htmlspecialchars($editando['telefono2'] ?? '') ?>"></label>
      <label>Dirección<input name="direccion" value="<?= htmlspecialchars($editando['direccion'] ?? '') ?>"></label>
      <button><?= $editando ? 'Actualizar' : 'Guardar' ?></button>
    </form>
  </main>

</body>

</html>
