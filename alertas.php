<?php
/**
 * Alertas del administrador.
 *
 * Lista las cuentas creadas, los inicios de sesion de usuarios y los
 * intentos de acceso con cuentas sin aprobar, con las no leidas primero
 * (ver includes/alertas.php: se calculan desde la tabla `usuarios`).
 * Para las cuentas pendientes permite aprobarlas o rechazarlas desde la
 * misma alerta, y permite marcar todas como leidas. Solo accesible para
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
    alertas_marcar_vistas($conn, (int) $_SESSION['usuario_id']);
    header("Location: alertas.php?msg=" . rawurlencode("Alertas marcadas como leídas") . "&tipo=success");
    exit;
}

$eventos = alertas_eventos($conn, (int) $_SESSION['usuario_id']);
$tipos = alerta_tipos();
$sinLeer = 0;
foreach ($eventos as $e) {
    if ($e['sin_leer']) {
        $sinLeer++;
    }
}
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

    <?php if (count($eventos) === 0): ?>
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
          <?php foreach ($eventos as $a): ?>
            <tr>
              <td><?= htmlspecialchars($a['fecha']) ?></td>
              <td><span class="pill <?= $a['sin_leer'] ? 'pill-pending' : '' ?>"><?= htmlspecialchars($tipos[$a['tipo']] ?? $a['tipo']) ?></span></td>
              <td class="detalle"><?= htmlspecialchars($a['detalle']) ?></td>
              <td class="acciones">
                <?php if ($a['estado'] === 'pendiente' && $a['tipo'] !== 'inicio_sesion'): ?>
                  <form method="POST" action="usuarios.php" style="display:inline">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="usuario_id" value="<?= (int) $a['usuario_id'] ?>">
                    <button type="submit" name="cambiar_estado" value="aprobado" class="btn-edit">Aprobar</button>
                    <button type="submit" name="cambiar_estado" value="rechazado" class="btn-delete">Rechazar</button>
                  </form>
                <?php endif; ?>
                <a href="usuario_editar.php?id=<?= (int) $a['usuario_id'] ?>" class="btn-edit"><?= $a['estado'] === 'pendiente' ? 'Revisar solicitud' : 'Ver usuario' ?></a>
              </td>
            </tr>
          <?php endforeach; ?>
        </table>
      </div>
      <div class="pagination"></div>
    <?php endif; ?>
  </main>

</body>

</html>
