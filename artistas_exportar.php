<?php
/**
 * Modulo Artistas - Descargar Excel.
 *
 * Formulario para elegir un rango de fechas y descargar en Excel los
 * registros de la tabla `artistas` cuya fecha_registro este en ese
 * rango. El archivo lo genera exportar.php. Solo administradores.
 * Requiere sesion activa (auth.php).
 */
require "auth.php";
require_once "includes/layout.php";
require_admin();
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <title>Artistas - Descargar Excel - SIRAD</title>
  <link rel="stylesheet" href="css/styles.css?v=<?= @filemtime(__DIR__ . '/css/styles.css') ?>">
  <script src="js/animations.js?v=<?= @filemtime(__DIR__ . '/js/animations.js') ?>" defer></script>
</head>

<body>

  <?php render_header(); ?>

  <main>
    <h1>Artistas</h1>

    <?php render_page_actions('artistas', 'exportar'); ?>

    <div class="export-bar">
      <form method="GET" action="exportar.php">
        <input type="hidden" name="modulo" value="artistas">
        <label>Desde<input type="date" name="desde" required></label>
        <label>Hasta<input type="date" name="hasta" required></label>
        <button type="submit">Descargar Excel</button>
      </form>
    </div>
  </main>

</body>

</html>
