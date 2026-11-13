<?php
/**
 * Catalogo de etiquetas.
 *
 * Permite a un administrador crear y eliminar las etiquetas disponibles
 * para clasificar registros de cualquier modulo (nombre + color). Al
 * eliminar una etiqueta se desasigna automaticamente de todos los
 * registros (ON DELETE CASCADE en contacto_etiquetas). Requiere sesion
 * activa (auth.php) y rol de administrador.
 */
require "auth.php";
include "conexion.php";
require_once "includes/layout.php";
require_once "includes/csrf.php";
require_admin();

$error = "";
if ($_POST) {
    csrf_verify();

    if (isset($_POST['crear'])) {
        $nombre = trim($_POST['nombre']);
        $color = $_POST['color'] ?? '#2563eb';

        if ($nombre === '') {
            $error = "El nombre de la etiqueta es obligatorio.";
        } else {
            $check = $conn->prepare("SELECT id_etiqueta FROM etiquetas WHERE nombre = ?");
            $check->bind_param("s", $nombre);
            $check->execute();
            $check->store_result();
            if ($check->num_rows > 0) {
                $error = "Ya existe una etiqueta con ese nombre.";
            }
            $check->close();
        }

        if ($error === '') {
            $stmt = $conn->prepare("INSERT INTO etiquetas (nombre, color) VALUES (?, ?)");
            $stmt->bind_param("ss", $nombre, $color);
            $stmt->execute();
            $stmt->close();
            header("Location: etiquetas.php?msg=" . rawurlencode("Etiqueta creada") . "&tipo=success");
            exit;
        }
    } elseif (isset($_POST['eliminar_id'])) {
        $idEliminar = (int) $_POST['eliminar_id'];
        $stmt = $conn->prepare("DELETE FROM etiquetas WHERE id_etiqueta = ?");
        $stmt->bind_param("i", $idEliminar);
        $stmt->execute();
        $stmt->close();
        header("Location: etiquetas.php?msg=" . rawurlencode("Etiqueta eliminada") . "&tipo=success");
        exit;
    }
}

$r = $conn->query(
    "SELECT e.id_etiqueta AS id, e.nombre, e.color, (SELECT COUNT(*) FROM contacto_etiquetas ce WHERE ce.id_etiqueta = e.id_etiqueta) AS usos
     FROM etiquetas e ORDER BY e.nombre"
);
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Etiquetas - SIRAD</title>
  <link rel="stylesheet" href="css/styles.css?v=<?= @filemtime(__DIR__ . '/css/styles.css') ?>">
  <script src="js/animations.js?v=<?= @filemtime(__DIR__ . '/js/animations.js') ?>" defer></script>
  <script src="js/toast.js?v=<?= @filemtime(__DIR__ . '/js/toast.js') ?>" defer></script>
</head>

<body>

  <?php render_header(); ?>

  <main>
    <h1>Etiquetas</h1>

    <?php if ($error): ?>
      <p class="auth-error" style="margin-bottom: 16px;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form class="form-card" method="POST">
      <?php csrf_field(); ?>
      <label>Nombre<input name="nombre" required maxlength="50" placeholder="Ej. Prioritario"></label>
      <label>Color<input type="color" name="color" value="#2563eb"></label>
      <button type="submit" name="crear" value="1">Crear etiqueta</button>
    </form>

    <?php if ($r->num_rows === 0): ?>
      <p class="empty-state">Aún no hay etiquetas creadas.</p>
    <?php else: ?>
      <div class="table-card">
        <table>
          <tr>
            <th>Etiqueta</th>
            <th>Color</th>
            <th>Registros que la usan</th>
            <th>Acciones</th>
          </tr>
          <?php while ($e = $r->fetch_assoc()): ?>
            <tr>
              <td><span class="tag" style="background:<?= htmlspecialchars($e['color']) ?>"><?= htmlspecialchars($e['nombre']) ?></span></td>
              <td><?= htmlspecialchars($e['color']) ?></td>
              <td><?= (int) $e['usos'] ?></td>
              <td>
                <form method="POST" onsubmit="return confirm('¿Eliminar esta etiqueta? Se quitará de todos los registros que la tengan.');">
                  <?php csrf_field(); ?>
                  <input type="hidden" name="eliminar_id" value="<?= (int) $e['id'] ?>">
                  <button type="submit" class="btn-delete">Borrar</button>
                </form>
              </td>
            </tr>
          <?php endwhile; ?>
        </table>
      </div>
    <?php endif; ?>
  </main>

</body>

</html>
