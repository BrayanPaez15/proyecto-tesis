<?php
/**
 * Modulo Practicantes - Ver registros.
 *
 * Consulta y muestra todos los registros de la tabla `practicantes` en una
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
require_permiso('practicantes', 'ver');
require_once "includes/etiquetas.php";

if (isset($_POST['eliminar_id'])) {
    csrf_verify();
    require_admin();
    $stmt = $conn->prepare("DELETE FROM practicantes WHERE id = ?");
    $id = (int) $_POST['eliminar_id'];
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: practicantes_registros.php?msg=" . rawurlencode("Registro eliminado") . "&tipo=success");
    exit;
}

$esAdmin = is_admin();
$etiquetasPorRegistro = etiquetas_por_registro($conn, 'practicantes');
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <title>Practicantes - Registros - SIRAD</title>
  <link rel="stylesheet" href="css/styles.css">
  <script src="js/animations.js" defer></script>
  <script src="js/toast.js" defer></script>
  <script src="js/table-tools.js" defer></script>
</head>

<body>

  <?php render_header(); ?>

  <main>
    <h1>Practicantes</h1>

    <?php render_page_actions('practicantes', 'registros'); ?>

    <?php
    $r = $conn->query("SELECT * FROM practicantes");
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
            <th>Dirección</th>
            <th>Disciplina</th>
            <th>Generación</th>
            <th>Fecha inicio</th>
            <th>Fecha fin</th>
            <th>Cumpleaños</th>
            <th>Contacto de emergencia</th>
            <th>Teléfono contacto</th>
            <th>Etiquetas</th>
            <?php if ($esAdmin): ?><th>Acciones</th><?php endif; ?>
          </tr>
          <?php
          while ($f = $r->fetch_assoc()) {
            echo "<tr>"
              . "<td>" . htmlspecialchars($f['nombre']) . "</td>"
              . "<td>" . htmlspecialchars($f['telefono']) . "</td>"
              . "<td>" . htmlspecialchars($f['correo']) . "</td>"
              . "<td>" . htmlspecialchars($f['direccion']) . "</td>"
              . "<td>" . htmlspecialchars($f['disciplina']) . "</td>"
              . "<td>" . htmlspecialchars($f['generacion']) . "</td>"
              . "<td>" . htmlspecialchars($f['inicio']) . "</td>"
              . "<td>" . htmlspecialchars($f['fin']) . "</td>"
              . "<td>" . htmlspecialchars($f['cumple']) . "</td>"
              . "<td>" . htmlspecialchars($f['contacto']) . "</td>"
              . "<td>" . htmlspecialchars($f['telefono_contacto']) . "</td>";
            echo "<td>";
            render_tags($etiquetasPorRegistro[(int) $f['id']] ?? []);
            if (puede_modulo('practicantes', 'registrar')) {
              echo "<a href=\"etiqueta_asignar.php?modulo=practicantes&id=" . (int) $f['id'] . "\" class=\"btn-edit\">Etiquetas</a>";
            }
            echo "</td>";
            if ($esAdmin) {
              echo "<td>"
                . "<a href=\"practicantes.php?id=" . (int) $f['id'] . "\" class=\"btn-edit\">Editar</a>"
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
