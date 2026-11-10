<?php
/**
 * Modulo Artistas - Registrar.
 *
 * Formulario de registro y edicion para la tabla `artistas` y su agenda de disponibilidad (columna `disponibilidad`). Inserta un
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
require_permiso('artistas', 'registrar');
require_once "includes/agenda.php";

$editando = null;
if (isset($_GET['id'])) {
  require_admin();
  $stmt = $conn->prepare("SELECT * FROM artistas WHERE id = ?");
  $id = (int) $_GET['id'];
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $editando = $stmt->get_result()->fetch_assoc();
  $stmt->close();
}
$agendaFilas = $editando ? agenda_obtener($conn, 'artistas', (int) $editando['id']) : [];

$error = "";
if ($_POST) {
  csrf_verify();
  $usuarioId = (int) $_SESSION['usuario_id'];

  $esEdicion = !empty($_POST['id']);
  if ($esEdicion) {
    require_admin();
    $id = (int) $_POST['id'];
    [$agendaFilas, $errorAgenda] = agenda_leer_post();
    if ($errorAgenda === '') {
      $stmt = $conn->prepare("UPDATE artistas SET nombre = ?, perfil = ?, organizacion = ?, agenda = ?, telefono = ?, correo = ?, filbo = ?, actualizado_en = NOW(), actualizado_por = ? WHERE id = ?");
      $stmt->bind_param("sssssssii", $_POST['nombre'], $_POST['perfil'], $_POST['organizacion'], $_POST['agenda'], $_POST['telefono'], $_POST['correo'], $_POST['filbo'], $usuarioId, $id);
      $stmt->execute();
      $stmt->close();
      agenda_guardar($conn, 'artistas', $id, $agendaFilas, $usuarioId);
      header("Location: artistas_registros.php?msg=" . rawurlencode("Registro actualizado") . "&tipo=success");
      exit;
    }
    $error = $errorAgenda;
  }

  $correo = trim($_POST['correo'] ?? '');
  if ($esEdicion) {
    // la edicion ya se resolvio arriba; si hubo error de agenda se vuelve a mostrar el formulario
  } elseif ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    $error = "El correo no tiene un formato válido.";
  } elseif ($correo !== '') {
    $check = $conn->prepare("SELECT id FROM artistas WHERE correo = ?");
    $check->bind_param("s", $correo);
    $check->execute();
    $check->store_result();
    if ($check->num_rows > 0) {
      $error = "Ya existe un registro con este correo en este módulo.";
    }
    $check->close();
  }

  if (!$esEdicion && $error === '') {
    [$agendaFilas, $errorAgenda] = agenda_leer_post();
    if ($errorAgenda !== '') {
      $error = $errorAgenda;
    } else {
      $stmt = $conn->prepare("INSERT INTO artistas (nombre, perfil, organizacion, agenda, telefono, correo, filbo, creado_por) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
      $stmt->bind_param("sssssssi", $_POST['nombre'], $_POST['perfil'], $_POST['organizacion'], $_POST['agenda'], $_POST['telefono'], $_POST['correo'], $_POST['filbo'], $usuarioId);
      $stmt->execute();
      $nuevoId = $conn->insert_id;
      $stmt->close();
      agenda_guardar($conn, 'artistas', $nuevoId, $agendaFilas, $usuarioId);
      header("Location: " . (puede_modulo('artistas', 'ver') ? 'artistas_registros.php' : 'artistas.php') . "?msg=" . rawurlencode("Registro guardado") . "&tipo=success");
      exit;
    }
  }
}
$valores = ($error !== '' && $_POST) ? $_POST : ($editando ?? []);
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <title>Artistas - SIRAD</title>
  <link rel="stylesheet" href="css/styles.css">
  <script src="js/animations.js" defer></script>
  <script src="js/toast.js" defer></script>
</head>

<body>

  <?php render_header(); ?>

  <main>
    <h1>Artistas</h1>

    <?php render_page_actions('artistas', 'registrar'); ?>

    <?php if ($error): ?>
      <p class="auth-error" style="margin-bottom: 16px;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <?php if ($editando): ?>
      <p class="edit-notice">Editando registro #<?= (int) $editando['id'] ?> &mdash; <a href="artistas.php">Cancelar</a></p>
    <?php endif; ?>

    <form class="form-card" method="POST">
      <?php csrf_field(); ?>
      <?php if ($editando): ?>
        <input type="hidden" name="id" value="<?= (int) $editando['id'] ?>">
      <?php endif; ?>
      <label>Nombre<input name="nombre" required value="<?= htmlspecialchars($valores['nombre'] ?? '') ?>"></label>
      <label>Perfil<input name="perfil" value="<?= htmlspecialchars($valores['perfil'] ?? '') ?>"></label>
      <label>Organización<input name="organizacion" value="<?= htmlspecialchars($valores['organizacion'] ?? '') ?>"></label>
      <label>Agenda (notas)<input name="agenda" value="<?= htmlspecialchars($valores['agenda'] ?? '') ?>"></label>
      <label>Teléfono<input name="telefono" value="<?= htmlspecialchars($valores['telefono'] ?? '') ?>"></label>
      <label>Correo<input name="correo" type="email" value="<?= htmlspecialchars($valores['correo'] ?? '') ?>"></label>
      <label>Filbo<input name="filbo" value="<?= htmlspecialchars($valores['filbo'] ?? '') ?>"></label>
      <?php agenda_render_form($agendaFilas); ?>
      <button><?= $editando ? 'Actualizar' : 'Guardar' ?></button>
    </form>
  </main>

</body>

</html>
