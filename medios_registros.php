<?php
/**
 * Modulo Medios - Ver registros.
 *
 * Consulta y muestra todos los registros del modulo en una
 * tabla HTML, con la salida escapada (htmlspecialchars). Los botones
 * de editar/eliminar solo se muestran a administradores (require_admin
 * protege tambien el borrado del lado del servidor). Incluye busqueda
 * y paginacion en el servidor (includes/paginacion.php: 50 filas por pagina) y notificaciones toast
 * (js/toast.js). Requiere sesion activa (auth.php).
 */
require "auth.php";
include "conexion.php";
require_once "includes/layout.php";
require_once "includes/csrf.php";
require_once "includes/paginacion.php";
require_permiso('medios', 'ver');
require_once "includes/etiquetas.php";

if (isset($_POST['eliminar_id'])) {
    csrf_verify();
    require_admin();
    registro_eliminar($conn, 'medios', (int) $_POST['eliminar_id']);
    header("Location: medios_registros.php?msg=" . rawurlencode("Registro eliminado") . "&tipo=success");
    exit;
}

$esAdmin = is_admin();
$q = termino_busqueda();
[$filas, $total, $pagina, $paginas] = consulta_paginada($conn, 'medios', $q, pagina_solicitada());
$idsPagina = array_column($filas, 'id');
$etiquetasPorRegistro = etiquetas_por_registro($conn, 'medios', $idsPagina);
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <title>Medios - Registros - SIRAD</title>
  <link rel="stylesheet" href="css/styles.css?v=<?= @filemtime(__DIR__ . '/css/styles.css') ?>">
  <script src="js/animations.js?v=<?= @filemtime(__DIR__ . '/js/animations.js') ?>" defer></script>
  <script src="js/toast.js?v=<?= @filemtime(__DIR__ . '/js/toast.js') ?>" defer></script>
</head>

<body>

  <?php render_header(); ?>

  <main>
    <h1>Medios</h1>

    <?php render_page_actions('medios', 'registros'); ?>

    <?php if ($total === 0 && $q === ''): ?>
      <p class="empty-state">Aún no hay registros en este módulo.</p>
    <?php else: ?>
      <?php render_busqueda_servidor($q, $total); ?>
      <?php if ($total === 0): ?>
        <p class="empty-state">No se encontraron registros para «<?= htmlspecialchars($q) ?>».</p>
      <?php endif; ?>

      <div class="table-card">
        <table>
          <tr>
            <th>Categoría</th>
            <th>Medio</th>
            <th>Fuente</th>
            <th>Nombre</th>
            <th>Correo</th>
            <th>Teléfono</th>
            <th>Teléfono 2</th>
            <th>Dirección</th>
            <th>Etiquetas</th>
            <?php if ($esAdmin): ?><th>Acciones</th><?php endif; ?>
          </tr>
          <?php
          foreach ($filas as $f) {
            echo "<tr>"
              . "<td>" . htmlspecialchars($f['categoria']) . "</td>"
              . "<td>" . htmlspecialchars($f['medio']) . "</td>"
              . "<td>" . htmlspecialchars($f['fuente']) . "</td>"
              . "<td>" . htmlspecialchars($f['nombre']) . "</td>"
              . "<td>" . htmlspecialchars($f['correo']) . "</td>"
              . "<td>" . htmlspecialchars($f['telefono']) . "</td>"
              . "<td>" . htmlspecialchars($f['telefono2']) . "</td>"
              . "<td>" . htmlspecialchars($f['direccion']) . "</td>";
            echo "<td>";
            render_tags($etiquetasPorRegistro[(int) $f['id']] ?? []);
            if (puede_modulo('medios', 'registrar')) {
              echo "<a href=\"etiqueta_asignar.php?modulo=medios&id=" . (int) $f['id'] . "\" class=\"btn-edit\">Etiquetas</a>";
            }
            echo "</td>";
            if ($esAdmin) {
              echo "<td>"
                . "<a href=\"medios.php?id=" . (int) $f['id'] . "\" class=\"btn-edit\">Editar</a>"
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
      <?php render_paginacion($pagina, $paginas, $total, $q); ?>
    <?php endif; ?>
  </main>

</body>

</html>
