<?php
/**
 * Busqueda global.
 *
 * Busca un termino en el campo principal (nombre o equivalente) de las
 * once tablas del directorio, usando includes/modulos.php, y enlaza
 * cada resultado a su modulo de "Ver registros". Solo busca en los
 * modulos que el usuario puede ver. Requiere sesion activa (auth.php).
 */
require "auth.php";
include "conexion.php";
require_once "includes/layout.php";

$modulos = modulos_permitidos(require "includes/modulos.php", 'ver');
if (!$modulos) {
    acceso_denegado('No tienes permiso para ver registros de ningún módulo. Pídele a un administrador que te lo asigne.');
}
$termino = trim($_GET['q'] ?? '');
$resultados = [];

if ($termino !== '') {
    $like = '%' . $termino . '%';
    foreach ($modulos as $m) {
        $campo = $m['campo_busqueda'];
        $stmt = $conn->prepare("SELECT * FROM `{$m['tabla']}` WHERE `$campo` LIKE ? ORDER BY `$campo` LIMIT 20");
        $stmt->bind_param("s", $like);
        $stmt->execute();
        $r = $stmt->get_result();
        while ($fila = $r->fetch_assoc()) {
            $resultados[] = [
                'modulo' => $m['titulo'],
                'texto' => $fila[$campo] ?? '',
                'correo' => $fila['correo'] ?? '',
                'enlace' => "{$m['archivo']}_registros.php",
            ];
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <title>Búsqueda global - SIRAD</title>
  <link rel="stylesheet" href="css/styles.css">
  <script src="js/animations.js" defer></script>
</head>

<body>

  <?php render_header(true); ?>

  <main>
    <h1>Búsqueda global</h1>

    <form class="search-bar" method="GET">
      <input type="text" name="q" placeholder="Buscar en los módulos a los que tienes acceso..." value="<?= htmlspecialchars($termino) ?>">
      <button type="submit">Buscar</button>
    </form>

    <?php if ($termino !== ''): ?>
      <?php if (count($resultados) === 0): ?>
        <p class="empty-state">No se encontraron resultados para "<?= htmlspecialchars($termino) ?>".</p>
      <?php else: ?>
        <div class="table-card">
          <table>
            <tr>
              <th>Módulo</th>
              <th>Nombre</th>
              <th>Correo</th>
              <th>Acciones</th>
            </tr>
            <?php foreach ($resultados as $res): ?>
              <tr>
                <td><?= htmlspecialchars($res['modulo']) ?></td>
                <td><?= htmlspecialchars($res['texto']) ?></td>
                <td><?= htmlspecialchars($res['correo']) ?></td>
                <td><a href="<?= htmlspecialchars($res['enlace']) ?>" class="btn-edit">Ver módulo</a></td>
              </tr>
            <?php endforeach; ?>
          </table>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </main>

</body>

</html>
