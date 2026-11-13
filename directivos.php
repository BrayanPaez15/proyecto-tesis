<?php
/**
 * Modulo Directivos - Registrar.
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
require_permiso('directivos', 'registrar');

[$editando, $error, $valores, $agendaFilas] = modulo_procesar($conn, 'directivos');
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Directivos - SIRAD</title>
  <link rel="stylesheet" href="css/styles.css?v=<?= @filemtime(__DIR__ . '/css/styles.css') ?>">
  <script src="js/animations.js?v=<?= @filemtime(__DIR__ . '/js/animations.js') ?>" defer></script>
  <script src="js/toast.js?v=<?= @filemtime(__DIR__ . '/js/toast.js') ?>" defer></script>
</head>

<body>

  <?php render_header(); ?>

  <main>
    <h1>Directivos</h1>

    <?php render_page_actions('directivos', 'registrar'); ?>

    <?php if ($error): ?>
      <p class="auth-error" style="margin-bottom: 16px;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <?php if ($editando): ?>
      <p class="edit-notice">Editando registro #<?= (int) $editando['id'] ?> &mdash; <a href="directivos.php">Cancelar</a></p>
    <?php endif; ?>

    <form class="form-card" method="POST">
      <?php csrf_field(); ?>
      <?php if ($editando): ?>
        <input type="hidden" name="id" value="<?= (int) $editando['id'] ?>">
      <?php endif; ?>
      <label>Título<input name="titulo" required value="<?= htmlspecialchars($valores['titulo'] ?? '') ?>"></label>
      <label>Nombre<input name="nombre" value="<?= htmlspecialchars($valores['nombre'] ?? '') ?>"></label>
      <label>Apellido<input name="apellido" value="<?= htmlspecialchars($valores['apellido'] ?? '') ?>"></label>
      <label>Cédula<input name="cedula" value="<?= htmlspecialchars($valores['cedula'] ?? '') ?>"></label>
      <label>Calidad<input name="calidad" value="<?= htmlspecialchars($valores['calidad'] ?? '') ?>"></label>
      <label>Estado<input name="estado" value="<?= htmlspecialchars($valores['estado'] ?? '') ?>"></label>
      <label>Entidad<input name="entidad" value="<?= htmlspecialchars($valores['entidad'] ?? '') ?>"></label>
      <label>Cargo<input name="cargo" value="<?= htmlspecialchars($valores['cargo'] ?? '') ?>"></label>
      <label>Celular<input name="celular" value="<?= htmlspecialchars($valores['celular'] ?? '') ?>"></label>
      <label>Teléfono<input name="telefono" value="<?= htmlspecialchars($valores['telefono'] ?? '') ?>"></label>
      <label>Correo<input name="correo" type="email" value="<?= htmlspecialchars($valores['correo'] ?? '') ?>"></label>
      <label>Integrante<input name="integrante" value="<?= htmlspecialchars($valores['integrante'] ?? '') ?>"></label>
      <label>Vigencia<input name="vigencia" value="<?= htmlspecialchars($valores['vigencia'] ?? '') ?>"></label>
      <button><?= $editando ? 'Actualizar' : 'Guardar' ?></button>
    </form>
  </main>

</body>

</html>
