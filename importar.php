<?php
/**
 * Importacion masiva desde Excel (.xlsx) o CSV.
 *
 * Permite elegir un modulo y subir un archivo cuyo encabezado coincida con
 * las etiquetas de sus campos (ver includes/modulos.php); cada fila se
 * inserta con sentencia preparada, omitiendo filas vacias y registros
 * duplicados (correo, NIT o cedula ya registrados). Incluye plantillas descargables por modulo. Requiere
 * sesion activa (auth.php) y permiso de importacion: administradores y
 * los usuarios a quienes un administrador se lo asigno (usuarios.php), que
 * ademas solo pueden importar a los modulos donde tienen permiso de registrar.
 */
require "auth.php";
include "conexion.php";
require_once "includes/layout.php";
require_once "includes/csrf.php";
require_once "includes/duplicados.php";
require_once "includes/importacion.php";
require_once "includes/xlsx.php";
require_importar();

$modulos = modulos_permitidos(require "includes/modulos.php", 'registrar');

if (isset($_GET['plantilla']) && is_string($_GET['plantilla']) && isset($modulos[$_GET['plantilla']])) {
    $m = $modulos[$_GET['plantilla']];
    $titulos = array_values($m['columnas']);
    if (($_GET['formato'] ?? '') === 'csv') {
        // Excel en espanol separa las columnas con punto y coma; la linea «sep=;» se lo indica
        header("Content-Type: text/csv; charset=utf-8");
        header("Content-Disposition: attachment; filename=\"plantilla_{$_GET['plantilla']}.csv\"");
        echo "\xEF\xBB\xBF" . "sep=;\r\n";
        echo implode(";", array_map(fn($l) => '"' . str_replace('"', '""', $l) . '"', $titulos)) . "\r\n";
        exit;
    }
    header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
    header("Content-Disposition: attachment; filename=\"plantilla_{$_GET['plantilla']}.xlsx\"");
    echo xlsx_crear($titulos, [], $m['titulo']);
    exit;
}

$moduloSel = $_POST['modulo'] ?? '';
$resumen = null;
$error = "";

if ($_POST && isset($_POST['importar'])) {
    csrf_verify();

    if (!isset($modulos[$moduloSel])) {
        $error = "Selecciona un módulo válido.";
    } elseif (empty($_FILES['archivo']['tmp_name']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
        $error = "Selecciona un archivo de Excel (.xlsx) o CSV.";
    } else {
        $m = $modulos[$moduloSel];
        $columnas = $m['columnas'];
        $labelToKey = [];
        foreach ($columnas as $clave => $etiqueta) {
            $labelToKey[mb_strtolower($etiqueta)] = $clave;
        }

        $encabezado = false;
        $filasArchivo = null;
        try {
            $filasArchivo = filas_importacion($_FILES['archivo']['tmp_name'], (string) ($_FILES['archivo']['name'] ?? ''));
            $encabezado = $filasArchivo->valid() ? $filasArchivo->current() : false;
            $filasArchivo->next();
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        }

        if ($error !== '') {
            // ya se informo el problema del archivo
        } elseif ($encabezado === false) {
            $error = "El archivo está vacío o no se pudo leer.";
        } else {
            $mapaColumnas = [];
            foreach ($encabezado as $indice => $titulo) {
                $clave = $labelToKey[mb_strtolower(trim($titulo))] ?? null;
                if ($clave !== null) {
                    $mapaColumnas[$indice] = $clave;
                }
            }

            if (count($mapaColumnas) === 0) {
                $error = "Ninguna columna del archivo coincide con las del módulo. Descarga la plantilla e inténtalo de nuevo.";
            } else {
                $columnasDestino = array_values(array_unique($mapaColumnas));
                $placeholders = implode(",", array_fill(0, count($columnasDestino), "?"));
                $columnasSql = implode(",", array_map(fn($c) => "`$c`", $columnasDestino));
                $stmt = $conn->prepare(
                    "INSERT INTO `{$m['tabla']}` ($columnasSql, creado_por) VALUES ($placeholders, ?)"
                );

                $unicas = array_values(array_intersect(columnas_unicas($m['tabla']), $columnasDestino));
                $checks = [];
                foreach ($unicas as $u) {
                    $checks[$u] = $conn->prepare("SELECT id FROM `{$m['tabla']}` WHERE `$u` = ? LIMIT 1");
                }

                $importados = 0;
                $omitidos = 0;
                while ($filasArchivo->valid()) {
                    $fila = $filasArchivo->current();
                    $filasArchivo->next();
                    $noVacia = array_filter($fila, fn($v) => trim((string) $v) !== '');
                    if (count($noVacia) === 0) {
                        continue;
                    }

                    $valores = [];
                    foreach ($columnasDestino as $clave) {
                        $indiceOrigen = array_search($clave, $mapaColumnas, true);
                        $valores[$clave] = $indiceOrigen !== false ? trim((string) ($fila[$indiceOrigen] ?? '')) : '';
                    }

                    $duplicado = false;
                    foreach ($unicas as $u) {
                        if ($valores[$u] === '') {
                            $valores[$u] = null;
                        } else {
                            $checks[$u]->execute([$valores[$u]]);
                            if ($checks[$u]->get_result()->num_rows > 0) {
                                $duplicado = true;
                                break;
                            }
                        }
                    }
                    if ($duplicado) {
                        $omitidos++;
                        continue;
                    }

                    $params = array_values($valores);
                    $params[] = (int) $_SESSION['usuario_id'];
                    try {
                        $stmt->execute($params);
                        $importados++;
                    } catch (mysqli_sql_exception $e) {
                        if (mensaje_duplicado($e) === null) {
                            throw $e;
                        }
                        $omitidos++;
                    }
                }

                $stmt->close();
                foreach ($checks as $chk) {
                    $chk->close();
                }

                $resumen = ['importados' => $importados, 'omitidos' => $omitidos];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <title>Importar registros - SIRAD</title>
  <link rel="stylesheet" href="css/styles.css">
  <script src="js/animations.js" defer></script>
</head>

<body>

  <?php render_header(true); ?>

  <main>
    <h1>Importar registros desde Excel o CSV</h1>

    <?php if ($error): ?>
      <p class="auth-error" style="margin-bottom: 16px;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <?php if ($resumen): ?>
      <p class="auth-success" style="margin-bottom: 16px;">
        Importación completada: <?= (int) $resumen['importados'] ?> registro(s) importado(s),
        <?= (int) $resumen['omitidos'] ?> omitido(s) por duplicado (correo, NIT o cédula ya registrados).
      </p>
    <?php endif; ?>

    <form class="form-card" method="POST" enctype="multipart/form-data">
      <?php csrf_field(); ?>
      <label>Módulo
        <select name="modulo" required>
          <option value="">Selecciona un módulo...</option>
          <?php foreach ($modulos as $clave => $m): ?>
            <option value="<?= htmlspecialchars($clave) ?>" <?= $moduloSel === $clave ? 'selected' : '' ?>><?= htmlspecialchars($m['titulo']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Archivo de Excel (.xlsx) o CSV<input type="file" name="archivo" accept=".xlsx,.csv" required></label>
      <button type="submit" name="importar" value="1">Importar</button>
    </form>

    <div class="table-card">
      <table>
        <tr>
          <th>Módulo</th>
          <th>Plantilla</th>
        </tr>
        <?php foreach ($modulos as $clave => $m): ?>
          <tr>
            <td><?= htmlspecialchars($m['titulo']) ?></td>
            <td><a href="importar.php?plantilla=<?= urlencode($clave) ?>" class="btn-edit">Descargar plantilla (Excel)</a></td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
  </main>

</body>

</html>
