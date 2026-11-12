<?php
/**
 * Modulo Artistas - Registrar.
 *
 * Formulario de registro y edicion del modulo (contacto + detalle) y su agenda de disponibilidad (columna `disponibilidad`). Inserta un
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
require_permiso('artistas', 'registrar');
require_once "includes/agenda.php";

[$editando, $error, $valores, $agendaFilas] = modulo_procesar($conn, 'artistas');
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <title>Artistas - SIRAD</title>
  <link rel="stylesheet" href="css/styles.css?v=<?= @filemtime(__DIR__ . '/css/styles.css') ?>">
  <script src="js/animations.js?v=<?= @filemtime(__DIR__ . '/js/animations.js') ?>" defer></script>
  <script src="js/toast.js?v=<?= @filemtime(__DIR__ . '/js/toast.js') ?>" defer></script>
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
      <label>Filbo<select name="filbo"><option value="">—</option><option value="Sí"<?= ($valores['filbo'] ?? '') === 'Sí' ? ' selected' : '' ?>>Sí</option><option value="No"<?= ($valores['filbo'] ?? '') === 'No' ? ' selected' : '' ?>>No</option></select></label>
      <?php agenda_render_form($agendaFilas); ?>
      <button><?= $editando ? 'Actualizar' : 'Guardar' ?></button>
    </form>
  </main>

</body>

</html>
