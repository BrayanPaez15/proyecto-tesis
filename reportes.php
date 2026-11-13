<?php
/**
 * Reportes y estadisticas.
 *
 * Panel con graficas de barras construidas en CSS puro (sin librerias
 * externas como Chart.js, para no depender de conexion a Internet):
 * registros por modulo, registros nuevos por mes, usuarios por rol y
 * las etiquetas mas usadas. Solo accesible para administradores.
 * Requiere sesion activa (auth.php).
 */
require "auth.php";
include "conexion.php";
require_once "includes/layout.php";
require_admin();

require_once "includes/contactos.php";
$modulos = modulos_def();

$conteoPorModulo = [];
$totalRegistros = 0;
foreach ($modulos as $clave => $m) {
    $c = contar_modulo($conn, $clave);
    $conteoPorModulo[$m['titulo']] = $c;
    $totalRegistros += $c;
}
arsort($conteoPorModulo);

$porMes = [];
for ($i = 5; $i >= 0; $i--) {
    $clave = date('Y-m', strtotime("-$i months"));
    $porMes[$clave] = 0;
}
foreach ($modulos as $clave => $m) {
    $res = $conn->query(
        "SELECT DATE_FORMAT(c.fecha_registro, '%Y-%m') AS mes, COUNT(*) AS total
         FROM " . modulo_from($clave) . "
         WHERE c.fecha_registro >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
         GROUP BY mes"
    );
    while ($fila = $res->fetch_assoc()) {
        if (isset($porMes[$fila['mes']])) {
            $porMes[$fila['mes']] += (int) $fila['total'];
        }
    }
}
$mesesEs = [1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic'];
$porMesEtiquetado = [];
foreach ($porMes as $clave => $valor) {
    [$anio, $mes] = explode('-', $clave);
    $porMesEtiquetado["{$mesesEs[(int) $mes]} $anio"] = $valor;
}

$totalUsuarios = (int) $conn->query("SELECT COUNT(*) AS total FROM usuarios")->fetch_assoc()['total'];
$rolesRes = $conn->query("SELECT rol, COUNT(*) AS total FROM usuarios GROUP BY rol");
$porRol = ['Administrador' => 0, 'Usuario' => 0];
while ($fila = $rolesRes->fetch_assoc()) {
    $porRol[$fila['rol'] === 'admin' ? 'Administrador' : 'Usuario'] = (int) $fila['total'];
}

$totalEtiquetas = (int) $conn->query("SELECT COUNT(*) AS total FROM etiquetas")->fetch_assoc()['total'];
$topEtiquetas = [];
$res = $conn->query(
    "SELECT e.nombre, COUNT(*) AS total
     FROM contacto_etiquetas ce
     JOIN etiquetas e ON e.id_etiqueta = ce.id_etiqueta
     GROUP BY e.id_etiqueta
     ORDER BY total DESC
     LIMIT 8"
);
while ($fila = $res->fetch_assoc()) {
    $topEtiquetas[$fila['nombre']] = (int) $fila['total'];
}

/** Imprime una gráfica de barras horizontales a partir de un arreglo etiqueta => valor. */
function render_barras(array $datos): void
{
    if (count($datos) === 0) {
        echo '<p class="empty-state">Sin datos suficientes todavía.</p>';
        return;
    }
    $max = max($datos) ?: 1;
    foreach ($datos as $etiqueta => $valor) {
        $ancho = $valor > 0 ? max(4, round($valor / $max * 100)) : 0;
        echo '<div class="chart-bar-row">'
            . '<span class="chart-bar-label">' . htmlspecialchars((string) $etiqueta) . '</span>'
            . '<div class="chart-bar-track"><div class="chart-bar-fill" style="width:' . $ancho . '%"></div></div>'
            . '<span class="chart-bar-value">' . $valor . '</span>'
            . '</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Reportes - SIRAD</title>
  <link rel="stylesheet" href="css/styles.css?v=<?= @filemtime(__DIR__ . '/css/styles.css') ?>">
  <script src="js/animations.js?v=<?= @filemtime(__DIR__ . '/js/animations.js') ?>" defer></script>
</head>

<body>

  <?php render_header(); ?>

  <main>
    <h1>Reportes</h1>

    <div class="kpi-row">
      <div class="kpi-card">
        <div class="kpi-valor"><?= $totalRegistros ?></div>
        <div class="kpi-etiqueta">Registros en total (11 módulos)</div>
      </div>
      <div class="kpi-card">
        <div class="kpi-valor"><?= $totalUsuarios ?></div>
        <div class="kpi-etiqueta">Usuarios del sistema</div>
      </div>
      <div class="kpi-card">
        <div class="kpi-valor"><?= $totalEtiquetas ?></div>
        <div class="kpi-etiqueta">Etiquetas creadas</div>
      </div>
    </div>

    <div class="report-card">
      <h2>Registros por módulo</h2>
      <?php render_barras($conteoPorModulo); ?>
    </div>

    <div class="report-card">
      <h2>Registros nuevos por mes (últimos 6 meses)</h2>
      <?php render_barras($porMesEtiquetado); ?>
    </div>

    <div class="report-card">
      <h2>Usuarios por rol</h2>
      <?php render_barras($porRol); ?>
    </div>

    <div class="report-card">
      <h2>Etiquetas más usadas</h2>
      <?php render_barras($topEtiquetas); ?>
    </div>
  </main>

</body>

</html>
