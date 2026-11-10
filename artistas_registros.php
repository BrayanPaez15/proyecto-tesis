<?php
/**
 * Modulo Artistas - Ver registros.
 *
 * Consulta y muestra todos los registros de la tabla `artistas` en una
 * tabla HTML, con la salida escapada (htmlspecialchars). Los botones
 * de editar/eliminar solo se muestran a administradores (require_admin
 * protege tambien el borrado del lado del servidor). Incluye busqueda
 * y paginacion client-side (js/table-tools.js) y notificaciones toast
 * (js/toast.js). Requiere sesion activa (auth.php).
 */
require "auth.php";
include "conexion.php";
require_once "includes/layout.php";
require_once "includes/csrf.php";
require_permiso('artistas', 'ver');
require_once "includes/etiquetas.php";
require_once "includes/agenda.php";

if (isset($_POST['eliminar_id'])) {
    csrf_verify();
    require_admin();
    $stmt = $conn->prepare("DELETE FROM artistas WHERE id = ?");
    $id = (int) $_POST['eliminar_id'];
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    agenda_eliminar($conn, 'artistas', $id);
    header("Location: artistas_registros.php?msg=" . rawurlencode("Registro eliminado") . "&tipo=success");
    exit;
}

$esAdmin = is_admin();
$etiquetasPorRegistro = etiquetas_por_registro($conn, 'artistas');
$agendaPorRegistro = agenda_por_registro($conn, 'artistas');
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <title>Artistas - Registros - SIRAD</title>
  <link rel="stylesheet" href="css/styles.css">
  <script src="js/animations.js" defer></script>
  <script src="js/toast.js" defer></script>
  <script src="js/table-tools.js" defer></script>
</head>

<body>

  <?php render_header(); ?>

  <main>
    <h1>Artistas</h1>

    <?php render_page_actions('artistas', 'registros'); ?>

    <?php
    $r = $conn->query("SELECT * FROM artistas");
    if ($r->num_rows === 0):
    ?>
      <p class="empty-state">Aún no hay registros en este módulo.</p>
    <?php else: ?>
      <div class="search-bar">
        <input type="text" placeholder="Buscar en la tabla...">
      </div>

      <div class="table-card">
        <table>
          <tr>
            <th>Nombre</th>
            <th>Perfil</th>
            <th>Organización</th>
            <th>Agenda (notas)</th>
            <th>Teléfono</th>
            <th>Correo</th>
            <th>Filbo</th>
            <th>Disponibilidad</th>
            <th>Etiquetas</th>
            <?php if ($esAdmin): ?><th>Acciones</th><?php endif; ?>
          </tr>
          <?php
          while ($f = $r->fetch_assoc()) {
            echo "<tr>"
              . "<td>" . htmlspecialchars($f['nombre']) . "</td>"
              . "<td>" . htmlspecialchars($f['perfil']) . "</td>"
              . "<td>" . htmlspecialchars($f['organizacion']) . "</td>"
              . "<td>" . htmlspecialchars($f['agenda']) . "</td>"
              . "<td>" . htmlspecialchars($f['telefono']) . "</td>"
              . "<td>" . htmlspecialchars($f['correo']) . "</td>"
              . "<td>" . htmlspecialchars($f['filbo']) . "</td>";
            echo "<td>" . htmlspecialchars(agenda_resumen($agendaPorRegistro[(int) $f['id']] ?? [])) . "</td>";
            echo "<td>";
            render_tags($etiquetasPorRegistro[(int) $f['id']] ?? []);
            if (puede_modulo('artistas', 'registrar')) {
              echo "<a href=\"etiqueta_asignar.php?modulo=artistas&id=" . (int) $f['id'] . "\" class=\"btn-edit\">Etiquetas</a>";
            }
            echo "</td>";
            if ($esAdmin) {
              echo "<td>"
                . "<a href=\"artistas.php?id=" . (int) $f['id'] . "\" class=\"btn-edit\">Editar</a>"
                . "<form method=\"POST\" style=\"display:inline\" onsubmit=\"return confirm('&iquest;Eliminar este registro?');\">"
                . "<input type=\"hidden\" name=\"csrf_token\" value=\"" . htmlspecialchars(csrf_token()) . "\">"
                . "<input type=\"hidden\" name=\"eliminar_id\" value=\"" . (int) $f['id'] . "\">"
                . "<button type=\"submit\" class=\"btn-delete\">Borrar</button>"
                . "</form></td>";
            }
            echo "</tr>";
          }
          ?>
        </table>
      </div>
      <div class="pagination"></div>
    <?php endif; ?>
  </main>

</body>

</html>
