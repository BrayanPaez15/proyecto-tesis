<?php
/**
 * Modulo Directivos - Ver registros.
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
require_permiso('directivos', 'ver');
require_once "includes/etiquetas.php";

if (isset($_POST['eliminar_id'])) {
    csrf_verify();
    require_admin();
    registro_eliminar($conn, 'directivos', (int) $_POST['eliminar_id']);
    header("Location: directivos_registros.php?msg=" . rawurlencode("Registro eliminado") . "&tipo=success");
    exit;
}

$esAdmin = is_admin();
$q = termino_busqueda();
[$filas, $total, $pagina, $paginas] = consulta_paginada($conn, 'directivos', $q, pagina_solicitada());
$idsPagina = array_column($filas, 'id');
$etiquetasPorRegistro = etiquetas_por_registro($conn, 'directivos', $idsPagina);
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Directivos - Registros - SIRAD</title>
  <link rel="stylesheet" href="css/styles.css?v=<?= @filemtime(__DIR__ . '/css/styles.css') ?>">
  <script src="js/animations.js?v=<?= @filemtime(__DIR__ . '/js/animations.js') ?>" defer></script>
  <script src="js/toast.js?v=<?= @filemtime(__DIR__ . '/js/toast.js') ?>" defer></script>
</head>

<body>

  <?php render_header(); ?>

  <main>
    <h1>Directivos</h1>

    <?php render_page_actions('directivos', 'registros'); ?>

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
            <th>Título</th>
            <th>Nombre</th>
            <th>Apellido</th>
            <th>Cédula</th>
            <th>Calidad</th>
            <th>Estado</th>
            <th>Entidad</th>
            <th>Cargo</th>
            <th>Celular</th>
            <th>Teléfono</th>
            <th>Correo</th>
            <th>Integrante</th>
            <th>Vigencia</th>
            <th>Etiquetas</th>
            <?php if ($esAdmin): ?><th>Acciones</th><?php endif; ?>
          </tr>
          <?php
          foreach ($filas as $f) {
            echo "<tr>"
              . "<td>" . htmlspecialchars($f['titulo']) . "</td>"
              . "<td>" . htmlspecialchars($f['nombre']) . "</td>"
              . "<td>" . htmlspecialchars($f['apellido']) . "</td>"
              . "<td>" . htmlspecialchars($f['cedula']) . "</td>"
              . "<td>" . htmlspecialchars($f['calidad']) . "</td>"
              . "<td>" . htmlspecialchars($f['estado']) . "</td>"
              . "<td>" . htmlspecialchars($f['entidad']) . "</td>"
              . "<td>" . htmlspecialchars($f['cargo']) . "</td>"
              . "<td>" . htmlspecialchars($f['celular']) . "</td>"
              . "<td>" . htmlspecialchars($f['telefono']) . "</td>"
              . "<td>" . htmlspecialchars($f['correo']) . "</td>"
              . "<td>" . htmlspecialchars($f['integrante']) . "</td>"
              . "<td>" . htmlspecialchars($f['vigencia']) . "</td>";
            echo "<td>";
            render_tags($etiquetasPorRegistro[(int) $f['id']] ?? []);
            if (puede_modulo('directivos', 'registrar')) {
              echo "<a href=\"etiqueta_asignar.php?modulo=directivos&id=" . (int) $f['id'] . "\" class=\"btn-edit\">Etiquetas</a>";
            }
            echo "</td>";
            if ($esAdmin) {
              echo "<td>"
                . "<a href=\"directivos.php?id=" . (int) $f['id'] . "\" class=\"btn-edit\">Editar</a>"
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
