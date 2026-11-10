<?php
/**
 * Modulo Talleristas - Ver registros.
 *
 * Consulta y muestra todos los registros de la tabla `talleristas` en una
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
require_permiso('talleristas', 'ver');
require_once "includes/etiquetas.php";
require_once "includes/agenda.php";

if (isset($_POST['eliminar_id'])) {
    csrf_verify();
    require_admin();
    $stmt = $conn->prepare("DELETE FROM talleristas WHERE id = ?");
    $id = (int) $_POST['eliminar_id'];
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: talleristas_registros.php?msg=" . rawurlencode("Registro eliminado") . "&tipo=success");
    exit;
}

$esAdmin = is_admin();
$etiquetasPorRegistro = etiquetas_por_registro($conn, 'talleristas');
$agendaPorRegistro = agenda_por_registro($conn, 'talleristas');
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <title>Talleristas - Registros - SIRAD</title>
  <link rel="stylesheet" href="css/styles.css">
  <script src="js/animations.js" defer></script>
  <script src="js/toast.js" defer></script>
  <script src="js/table-tools.js" defer></script>
</head>

<body>

  <?php render_header(); ?>

  <main>
    <h1>Talleristas</h1>

    <?php render_page_actions('talleristas', 'registros'); ?>

    <?php
    $r = $conn->query("SELECT * FROM talleristas");
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
            <th>Teléfono</th>
            <th>Correo</th>
            <th>Cargo</th>
            <th>Perfil</th>
            <th>Disponibilidad</th>
            <th>Etiquetas</th>
            <?php if ($esAdmin): ?><th>Acciones</th><?php endif; ?>
          </tr>
          <?php
          while ($f = $r->fetch_assoc()) {
            echo "<tr>"
              . "<td>" . htmlspecialchars($f['nombre']) . "</td>"
              . "<td>" . htmlspecialchars($f['telefono']) . "</td>"
              . "<td>" . htmlspecialchars($f['correo']) . "</td>"
              . "<td>" . htmlspecialchars($f['cargo']) . "</td>"
              . "<td>" . htmlspecialchars($f['perfil']) . "</td>";
            echo "<td>" . htmlspecialchars(agenda_resumen($agendaPorRegistro[(int) $f['id']] ?? [])) . "</td>";
            echo "<td>";
            render_tags($etiquetasPorRegistro[(int) $f['id']] ?? []);
            if (puede_modulo('talleristas', 'registrar')) {
              echo "<a href=\"etiqueta_asignar.php?modulo=talleristas&id=" . (int) $f['id'] . "\" class=\"btn-edit\">Etiquetas</a>";
            }
            echo "</td>";
            if ($esAdmin) {
              echo "<td>"
                . "<a href=\"talleristas.php?id=" . (int) $f['id'] . "\" class=\"btn-edit\">Editar</a>"
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
