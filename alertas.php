<?php
/**
 * Alertas del administrador.
 *
 * Lista las cuentas creadas, los inicios de sesion de usuarios y los
 * intentos de acceso con cuentas sin aprobar, con las no leidas primero.
 * Permite marcar una alerta o todas como leidas. Solo accesible para
 * administradores. Requiere sesion activa (auth.php).
 */
require "auth.php";
include "conexion.php";
require_once "includes/layout.php";
require_once "includes/csrf.php";
require_once "includes/alertas.php";
require_admin();

if (isset($_POST['marcar_todas'])) {
    csrf_verify();
    $conn->query("UPDATE alertas SET leida = 1 WHERE leida = 0");
    header("Location: alertas.php?msg=" . rawurlencode("Alertas marcadas como leídas") . "&tipo=success");
    exit;
}
if (isset($_POST['marcar_una'])) {
    csrf_verify();
    $idAlerta = (int) $_POST['marcar_una'];
    $stmt = $conn->prepare("UPDATE alertas SET leida = 1 WHERE id = ?");
    $stmt->bind_param("i", $idAlerta);
    $stmt->execute();
    $stmt->close();
    header("Location: alertas.php");
    exit;
}

$r = $conn->query("SELECT a.id, a.tipo, a.usuario_id, a.detalle, a.leida, a.creado_en, u.estado
    FROM alertas a LEFT JOIN usuarios u ON u.id = a.usuario_id
    ORDER BY a.leida ASC, a.creado_en DESC, a.id DESC LIMIT 200");
$tipos = alerta_tipos();
$sinLeer = alertas_pendientes($conn);
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <title>Alertas - SIRAD</title>
  <link rel="stylesheet" href="css/styles.css">
  <script src="js/animations.js" defer></script>
  <script src="js/toast.js" defer></script>
  <script src="js/table-tools.js" defer></script>
</head>

<body>

  <?php render_header(); ?>

  <main>
    <h1>Alertas</h1>

    <?php if ($sinLeer > 0): ?>
      <form method="POST" class="export-bar" style="margin-bottom: 16px;">
        <?php csrf_field(); ?>
        <p style="margin: 0;"><?= $sinLeer ?> alerta<?= $sinLeer === 1 ? '' : 's' ?> sin leer.</p>
        <button type="submit" name="marcar_todas" value="1">Marcar todas como leídas</button>
      </form>
    <?php endif; ?>

    <?php if ($r->num_rows === 0): ?>
      <p class="empty-state">Aún no hay alertas.</p>
    <?php else: ?>
      <div class="search-bar">
        <input type="text" placeholder="Buscar en la tabla...">
      </div>
      <div class="table-card">
        <table>
          <tr>
            <th>Fecha</th>
            <th>Tipo</th>
            <th>Detalle</th>
            <th>Acciones</th>
          </tr>
          <?php while ($a = $r->fetch_assoc()): ?>
            <tr>
              <td><?= htmlspecialchars($a['creado_en']) ?></td>
              <td><span class="pill <?= (int) $a['leida'] === 0 ? 'pill-pending' : '' ?>"><?= htmlspecialchars($tipos[$a['tipo']] ?? $a['tipo']) ?></span></td>
              <td class="detalle"><?= htmlspecialchars($a['detalle']) ?></td>
              <td class="acciones">
                <?php if ($a['usuario_id']): ?>
                  <a href="usuario_editar.php?id=<?= (int) $a['usuario_id'] ?>" class="btn-edit"><?= $a['estado'] === 'pendiente' ? 'Revisar solicitud' : 'Ver usuario' ?></a>
                <?php endif; ?>
                <?php if ((int) $a['leida'] === 0): ?>
                  <form method="POST" style="display:inline">
                    <?php csrf_field(); ?>
                    <button type="submit" name="marcar_una" value="<?= (int) $a['id'] ?>" class="btn-edit">Marcar leída</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endwhile; ?>
        </table>
      </div>
      <div class="pagination"></div>
    <?php endif; ?>
  </main>

</body>

</html>
