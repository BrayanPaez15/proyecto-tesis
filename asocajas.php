<?php
/**
 * Modulo Asocajas - Registrar.
 *
 * Formulario de registro y edicion para la tabla `asocajas`. Inserta un
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
require_permiso('asocajas', 'registrar');

$editando = null;
if (isset($_GET['id'])) {
  require_admin();
  $stmt = $conn->prepare("SELECT * FROM asocajas WHERE id = ?");
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
    $stmt = $conn->prepare("UPDATE asocajas SET caja = ?, departamento = ?, cargo = ?, contacto = ?, telefono = ?, direccion = ?, correo = ?, actualizado_en = NOW(), actualizado_por = ? WHERE id = ?");
    $stmt->bind_param("sssssssii", $_POST['caja'], $_POST['departamento'], $_POST['cargo'], $_POST['contacto'], $_POST['telefono'], $_POST['direccion'], $_POST['correo'], $usuarioId, $id);
    $stmt->execute();
    $stmt->close();
    header("Location: asocajas_registros.php?msg=" . rawurlencode("Registro actualizado") . "&tipo=success");
    exit;
  }

  $correo = trim($_POST['correo'] ?? '');
  if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    $error = "El correo no tiene un formato válido.";
  } elseif ($correo !== '') {
    $check = $conn->prepare("SELECT id FROM asocajas WHERE correo = ?");
    $check->bind_param("s", $correo);
    $check->execute();
    $check->store_result();
    if ($check->num_rows > 0) {
      $error = "Ya existe un registro con este correo en este módulo.";
    }
    $check->close();
  }

  if ($error === '') {
    $stmt = $conn->prepare("INSERT INTO asocajas (caja, departamento, cargo, contacto, telefono, direccion, correo, creado_por) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssssi", $_POST['caja'], $_POST['departamento'], $_POST['cargo'], $_POST['contacto'], $_POST['telefono'], $_POST['direccion'], $_POST['correo'], $usuarioId);
    $stmt->execute();
    $stmt->close();
    header("Location: asocajas_registros.php?msg=" . rawurlencode("Registro guardado") . "&tipo=success");
    exit;
  }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <title>Asocajas - SIRAD</title>
  <link rel="stylesheet" href="css/styles.css">
  <script src="js/animations.js" defer></script>
</head>

<body>

  <?php render_header(); ?>

  <main>
    <h1>Asocajas</h1>

    <?php render_page_actions('asocajas', 'registrar'); ?>

    <?php if ($error): ?>
      <p class="auth-error" style="margin-bottom: 16px;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <?php if ($editando): ?>
      <p class="edit-notice">Editando registro #<?= (int) $editando['id'] ?> &mdash; <a href="asocajas.php">Cancelar</a></p>
    <?php endif; ?>

    <form class="form-card" method="POST">
      <?php csrf_field(); ?>
      <?php if ($editando): ?>
        <input type="hidden" name="id" value="<?= (int) $editando['id'] ?>">
      <?php endif; ?>
      <label>Caja<input name="caja" required value="<?= htmlspecialchars($editando['caja'] ?? '') ?>"></label>
      <label>Departamento<input name="departamento" value="<?= htmlspecialchars($editando['departamento'] ?? '') ?>"></label>
      <label>Cargo<input name="cargo" value="<?= htmlspecialchars($editando['cargo'] ?? '') ?>"></label>
      <label>Contacto<input name="contacto" value="<?= htmlspecialchars($editando['contacto'] ?? '') ?>"></label>
      <label>Teléfono<input name="telefono" value="<?= htmlspecialchars($editando['telefono'] ?? '') ?>"></label>
      <label>Dirección<input name="direccion" value="<?= htmlspecialchars($editando['direccion'] ?? '') ?>"></label>
      <label>Correo<input name="correo" type="email" value="<?= htmlspecialchars($editando['correo'] ?? '') ?>"></label>
      <button><?= $editando ? 'Actualizar' : 'Guardar' ?></button>
    </form>
  </main>

</body>

</html>
