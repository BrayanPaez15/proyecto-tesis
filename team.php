<?php
/**
 * Modulo Team - Registrar.
 *
 * Formulario de registro y edicion del modulo (contacto + detalle). Inserta un
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
require_once "includes/contactos.php";
require_permiso('team', 'registrar');

[$editando, $error, $valores, $agendaFilas] = modulo_procesar($conn, 'team');
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <title>Team - SIRAD</title>
  <link rel="stylesheet" href="css/styles.css?v=<?= @filemtime(__DIR__ . '/css/styles.css') ?>">
  <script src="js/animations.js?v=<?= @filemtime(__DIR__ . '/js/animations.js') ?>" defer></script>
  <script src="js/toast.js?v=<?= @filemtime(__DIR__ . '/js/toast.js') ?>" defer></script>
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
      <label>Nombre<input name="nombre" required value="<?= htmlspecialchars($valores['nombre'] ?? '') ?>"></label>
      <label>Apellido<input name="apellido" value="<?= htmlspecialchars($valores['apellido'] ?? '') ?>"></label>
      <label>Celular<input name="celular" value="<?= htmlspecialchars($valores['celular'] ?? '') ?>"></label>
      <label>Correo<input name="correo" type="email" value="<?= htmlspecialchars($valores['correo'] ?? '') ?>"></label>
      <label>Cargo<input name="cargo" value="<?= htmlspecialchars($valores['cargo'] ?? '') ?>"></label>
      <label>Cumpleaños<input name="cumple" type="date" value="<?= htmlspecialchars($valores['cumple'] ?? '') ?>"></label>
      <label>Contacto de emergencia<input name="contacto" value="<?= htmlspecialchars($valores['contacto'] ?? '') ?>"></label>
      <label>Teléfono<input name="telefono" value="<?= htmlspecialchars($valores['telefono'] ?? '') ?>"></label>
      <label>Fecha inicio<input name="inicio" type="date" value="<?= htmlspecialchars($valores['inicio'] ?? '') ?>"></label>
      <label>Fecha fin<input name="fin" type="date" value="<?= htmlspecialchars($valores['fin'] ?? '') ?>"></label>
      <button><?= $editando ? 'Actualizar' : 'Guardar' ?></button>
    </form>
  </main>

</body>

</html>
