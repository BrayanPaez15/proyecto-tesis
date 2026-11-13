<?php
/**
 * Asignar etiquetas a un registro.
 *
 * Muestra el catalogo de etiquetas como casillas de verificacion para
 * marcar/desmarcar cuales aplican al registro (modulo + id) indicado,
 * usando includes/modulos.php como lista blanca de modulos validos.
 * Requiere sesion activa (auth.php) y permiso de registrar en el modulo
 * (los administradores tienen todos).
 */
require "auth.php";
include "conexion.php";
require_once "includes/layout.php";
require_once "includes/csrf.php";
require_once "includes/contactos.php";

$modulos = require "includes/modulos.php";
$modulo = $_GET['modulo'] ?? $_POST['modulo'] ?? '';
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

if (!isset($modulos[$modulo]) || $id <= 0) {
    header("Location: index.php");
    exit;
}

$info = $modulos[$modulo];
require_permiso($modulo, 'registrar');
$campoNombre = $info['campo_busqueda'];
$archivoRegistros = "{$info['archivo']}_registros.php";

$registro = registro_obtener($conn, $modulo, $id);

if (!$registro) {
    header("Location: $archivoRegistros");
    exit;
}

if ($_POST && isset($_POST['guardar'])) {
    csrf_verify();

    $del = $conn->prepare("DELETE FROM contacto_etiquetas WHERE id_contacto = ?");
    $del->bind_param("i", $id);
    $del->execute();
    $del->close();

    $seleccionadas = $_POST['etiquetas'] ?? [];
    if ($seleccionadas) {
        // solo se asignan etiquetas que existen (un id inventado se ignora)
        $stmt = $conn->prepare("INSERT IGNORE INTO contacto_etiquetas (id_contacto, id_etiqueta) SELECT ?, id_etiqueta FROM etiquetas WHERE id_etiqueta = ?");
        foreach ((array) $seleccionadas as $etiquetaId) {
            $etiquetaIdInt = (int) $etiquetaId;
            $stmt->bind_param("ii", $id, $etiquetaIdInt);
            $stmt->execute();
        }
        $stmt->close();
    }

    header("Location: $archivoRegistros?msg=" . rawurlencode("Etiquetas actualizadas") . "&tipo=success");
    exit;
}

$todas = $conn->query("SELECT id_etiqueta AS id, nombre, color FROM etiquetas ORDER BY nombre");

$asignadas = [];
$stmt = $conn->prepare("SELECT id_etiqueta AS etiqueta_id FROM contacto_etiquetas WHERE id_contacto = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$r = $stmt->get_result();
while ($fila = $r->fetch_assoc()) {
    $asignadas[(int) $fila['etiqueta_id']] = true;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Etiquetas de <?= htmlspecialchars($registro[$campoNombre] ?? '') ?> - SIRAD</title>
  <link rel="stylesheet" href="css/styles.css?v=<?= @filemtime(__DIR__ . '/css/styles.css') ?>">
  <script src="js/animations.js?v=<?= @filemtime(__DIR__ . '/js/animations.js') ?>" defer></script>
</head>

<body>

  <?php render_header(); ?>

  <main>
    <h1>Etiquetas</h1>

    <p class="edit-notice">
      Editando etiquetas de <strong><?= htmlspecialchars($registro[$campoNombre] ?? "#$id") ?></strong>
      (<?= htmlspecialchars($info['titulo']) ?>) &mdash; <a href="<?= htmlspecialchars($archivoRegistros) ?>">Volver a Ver registros</a>
    </p>

    <?php if ($todas->num_rows === 0): ?>
      <p class="empty-state">Aún no hay etiquetas creadas. <a href="etiquetas.php">Crea una primero</a>.</p>
    <?php else: ?>
      <form class="form-card" method="POST">
        <?php csrf_field(); ?>
        <input type="hidden" name="modulo" value="<?= htmlspecialchars($modulo) ?>">
        <input type="hidden" name="id" value="<?= (int) $id ?>">
        <div class="tag-checklist">
          <?php while ($e = $todas->fetch_assoc()): ?>
            <label class="tag-check">
              <input type="checkbox" name="etiquetas[]" value="<?= (int) $e['id'] ?>" <?= isset($asignadas[(int) $e['id']]) ? 'checked' : '' ?>>
              <span class="tag" style="background:<?= htmlspecialchars($e['color']) ?>"><?= htmlspecialchars($e['nombre']) ?></span>
            </label>
          <?php endwhile; ?>
        </div>
        <button type="submit" name="guardar" value="1">Guardar etiquetas</button>
      </form>
    <?php endif; ?>
  </main>

</body>

</html>
