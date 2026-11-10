<?php
/**
 * Modulo Mercadeo - Registrar.
 *
 * Formulario de registro y edicion para la tabla `mercadeo`. Inserta un
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
require_permiso('mercadeo', 'registrar');

$editando = null;
if (isset($_GET['id'])) {
  require_admin();
  $stmt = $conn->prepare("SELECT * FROM mercadeo WHERE id = ?");
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
    $error = error_duplicado($conn, 'mercadeo', $id);
    if ($error === '') {
      $stmt = $conn->prepare("UPDATE mercadeo SET empresa = ?, nombre = ?, cargo = ?, tema = ?, contacto = ?, telefono = ?, correo = ?, direccion = ?, proyecto = ?, patrocinio = ?, actualizado_en = NOW(), actualizado_por = ? WHERE id = ?");
      $stmt->bind_param("ssssssssssii", $_POST['empresa'], $_POST['nombre'], $_POST['cargo'], $_POST['tema'], $_POST['contacto'], $_POST['telefono'], $_POST['correo'], $_POST['direccion'], $_POST['proyecto'], $_POST['patrocinio'], $usuarioId, $id);
      try {
        $stmt->execute();
        $stmt->close();
        header("Location: mercadeo_registros.php?msg=" . rawurlencode("Registro actualizado") . "&tipo=success");
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
    $check = $conn->prepare("SELECT id FROM mercadeo WHERE correo = ?");
    $check->bind_param("s", $correo);
    $check->execute();
    $check->store_result();
    if ($check->num_rows > 0) {
      $error = "Ya existe un registro con este correo en este módulo.";
    }
    $check->close();
  }

  if (!$esEdicion && $error === '') {
    $error = error_duplicado($conn, 'mercadeo');
  }

  if (!$esEdicion && $error === '') {
    $stmt = $conn->prepare("INSERT INTO mercadeo (empresa, nombre, cargo, tema, contacto, telefono, correo, direccion, proyecto, patrocinio, creado_por) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssssssssi", $_POST['empresa'], $_POST['nombre'], $_POST['cargo'], $_POST['tema'], $_POST['contacto'], $_POST['telefono'], $_POST['correo'], $_POST['direccion'], $_POST['proyecto'], $_POST['patrocinio'], $usuarioId);
    try {
      $stmt->execute();
      $stmt->close();
      header("Location: " . (puede_modulo('mercadeo', 'ver') ? 'mercadeo_registros.php' : 'mercadeo.php') . "?msg=" . rawurlencode("Registro guardado") . "&tipo=success");
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
  <title>Mercadeo - SIRAD</title>
  <link rel="stylesheet" href="css/styles.css">
  <script src="js/animations.js" defer></script>
  <script src="js/toast.js" defer></script>
</head>

<body>

  <?php render_header(); ?>

  <main>
    <h1>Mercadeo</h1>

    <?php render_page_actions('mercadeo', 'registrar'); ?>

    <?php if ($error): ?>
      <p class="auth-error" style="margin-bottom: 16px;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <?php if ($editando): ?>
      <p class="edit-notice">Editando registro #<?= (int) $editando['id'] ?> &mdash; <a href="mercadeo.php">Cancelar</a></p>
    <?php endif; ?>

    <form class="form-card" method="POST">
      <?php csrf_field(); ?>
      <?php if ($editando): ?>
        <input type="hidden" name="id" value="<?= (int) $editando['id'] ?>">
      <?php endif; ?>
      <label>Empresa<input name="empresa" required value="<?= htmlspecialchars($editando['empresa'] ?? '') ?>"></label>
      <label>Nombre<input name="nombre" value="<?= htmlspecialchars($editando['nombre'] ?? '') ?>"></label>
      <label>Cargo<input name="cargo" value="<?= htmlspecialchars($editando['cargo'] ?? '') ?>"></label>
      <label>Tema<input name="tema" value="<?= htmlspecialchars($editando['tema'] ?? '') ?>"></label>
      <label>Contacto<input name="contacto" value="<?= htmlspecialchars($editando['contacto'] ?? '') ?>"></label>
      <label>Teléfono<input name="telefono" value="<?= htmlspecialchars($editando['telefono'] ?? '') ?>"></label>
      <label>Correo<input name="correo" type="email" value="<?= htmlspecialchars($editando['correo'] ?? '') ?>"></label>
      <label>Dirección<input name="direccion" value="<?= htmlspecialchars($editando['direccion'] ?? '') ?>"></label>
      <label>Proyecto<input name="proyecto" value="<?= htmlspecialchars($editando['proyecto'] ?? '') ?>"></label>
      <label>Patrocinio<input name="patrocinio" value="<?= htmlspecialchars($editando['patrocinio'] ?? '') ?>"></label>
      <button><?= $editando ? 'Actualizar' : 'Guardar' ?></button>
    </form>
  </main>

</body>

</html>
