<?php
/**
 * Modulo Team - Registrar.
 *
 * Formulario de registro y edicion para la tabla `team_pombo`. Inserta un
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
require_once "includes/duplicados.php";
require_permiso('team', 'registrar');

$editando = null;
if (isset($_GET['id'])) {
  require_admin();
  $stmt = $conn->prepare("SELECT * FROM team_pombo WHERE id = ?");
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
  normalizar_unicos();

  $esEdicion = !empty($_POST['id']);
  if ($esEdicion) {
    require_admin();
    $id = (int) $_POST['id'];
    $error = error_duplicado($conn, 'team_pombo', $id);
    if ($error === '') {
      $stmt = $conn->prepare("UPDATE team_pombo SET nombre = ?, apellido = ?, celular = ?, correo = ?, cargo = ?, cumple = ?, contacto = ?, telefono = ?, inicio = ?, fin = ?, actualizado_en = NOW(), actualizado_por = ? WHERE id = ?");
      $stmt->bind_param("ssssssssssii", $_POST['nombre'], $_POST['apellido'], $_POST['celular'], $_POST['correo'], $_POST['cargo'], $_POST['cumple'], $_POST['contacto'], $_POST['telefono'], $_POST['inicio'], $_POST['fin'], $usuarioId, $id);
      try {
        $stmt->execute();
        $stmt->close();
        header("Location: team_registros.php?msg=" . rawurlencode("Registro actualizado") . "&tipo=success");
        exit;
      } catch (mysqli_sql_exception $e) {
        $error = mensaje_duplicado($e) ?? throw $e;
      }
    }
  }

  $correo = trim($_POST['correo'] ?? '');
  if ($esEdicion) {
    // la edicion ya se resolvio arriba; si hubo un error se vuelve a mostrar el formulario
  } elseif ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    $error = "El correo no tiene un formato válido.";
  } elseif ($correo !== '') {
    $check = $conn->prepare("SELECT id FROM team_pombo WHERE correo = ?");
    $check->bind_param("s", $correo);
    $check->execute();
    $check->store_result();
    if ($check->num_rows > 0) {
      $error = "Ya existe un registro con este correo en este módulo.";
    }
    $check->close();
  }

  if (!$esEdicion && $error === '') {
    $error = error_duplicado($conn, 'team_pombo');
  }

  if (!$esEdicion && $error === '') {
    $stmt = $conn->prepare("INSERT INTO team_pombo (nombre, apellido, celular, correo, cargo, cumple, contacto, telefono, inicio, fin, creado_por) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssssssssi", $_POST['nombre'], $_POST['apellido'], $_POST['celular'], $_POST['correo'], $_POST['cargo'], $_POST['cumple'], $_POST['contacto'], $_POST['telefono'], $_POST['inicio'], $_POST['fin'], $usuarioId);
    try {
      $stmt->execute();
      $stmt->close();
      header("Location: " . (puede_modulo('team', 'ver') ? 'team_registros.php' : 'team.php') . "?msg=" . rawurlencode("Registro guardado") . "&tipo=success");
      exit;
    } catch (mysqli_sql_exception $e) {
      $error = mensaje_duplicado($e) ?? throw $e;
    }
  }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <title>Team - SIRAD</title>
  <link rel="stylesheet" href="css/styles.css">
  <script src="js/animations.js" defer></script>
  <script src="js/toast.js" defer></script>
</head>

<body>

  <?php render_header(); ?>

  <main>
    <h1>Team</h1>

    <?php render_page_actions('team', 'registrar'); ?>

    <?php if ($error): ?>
      <p class="auth-error" style="margin-bottom: 16px;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <?php if ($editando): ?>
      <p class="edit-notice">Editando registro #<?= (int) $editando['id'] ?> &mdash; <a href="team.php">Cancelar</a></p>
    <?php endif; ?>

    <form class="form-card" method="POST">
      <?php csrf_field(); ?>
      <?php if ($editando): ?>
        <input type="hidden" name="id" value="<?= (int) $editando['id'] ?>">
      <?php endif; ?>
      <label>Nombre<input name="nombre" required value="<?= htmlspecialchars($editando['nombre'] ?? '') ?>"></label>
      <label>Apellido<input name="apellido" value="<?= htmlspecialchars($editando['apellido'] ?? '') ?>"></label>
      <label>Celular<input name="celular" value="<?= htmlspecialchars($editando['celular'] ?? '') ?>"></label>
      <label>Correo<input name="correo" type="email" value="<?= htmlspecialchars($editando['correo'] ?? '') ?>"></label>
      <label>Cargo<input name="cargo" value="<?= htmlspecialchars($editando['cargo'] ?? '') ?>"></label>
      <label>Cumpleaños<input name="cumple" type="date" value="<?= htmlspecialchars($editando['cumple'] ?? '') ?>"></label>
      <label>Contacto de emergencia<input name="contacto" value="<?= htmlspecialchars($editando['contacto'] ?? '') ?>"></label>
      <label>Teléfono<input name="telefono" value="<?= htmlspecialchars($editando['telefono'] ?? '') ?>"></label>
      <label>Fecha inicio<input name="inicio" type="date" value="<?= htmlspecialchars($editando['inicio'] ?? '') ?>"></label>
      <label>Fecha fin<input name="fin" type="date" value="<?= htmlspecialchars($editando['fin'] ?? '') ?>"></label>
      <button><?= $editando ? 'Actualizar' : 'Guardar' ?></button>
    </form>
  </main>

</body>

</html>
