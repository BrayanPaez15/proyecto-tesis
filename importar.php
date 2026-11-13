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
require_once "includes/contactos.php";
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
    } elseif (in_array($_FILES['archivo']['error'] ?? 0, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
        || (!empty($_FILES['archivo']['tmp_name']) && filesize($_FILES['archivo']['tmp_name']) > IMPORTAR_MAX_MB * 1024 * 1024)) {
        $error = "El archivo pesa demasiado: el máximo es de " . IMPORTAR_MAX_MB . " MB. Divídelo en varios archivos más pequeños.";
    } elseif (empty($_FILES['archivo']['tmp_name']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
        $error = "Selecciona un archivo de Excel (.xlsx) o CSV.";
    } elseif (importacion_reservar($conn, (int) $_SESSION['usuario_id']) > 0) {
        // sin cupo: el aviso con la hora de regreso se muestra mas abajo (importacion_estado)
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
                $unicas = array_values(array_intersect(columnas_unicas($moduloSel), $columnasDestino));

                $importados = 0;
                $omitidos = 0;
                $filasConDatos = 0;
                $limiteSuperado = false;
                // todo el archivo se importa en una sola transaccion: si pasa el limite de filas no se guarda nada
                tx_iniciar($conn);
                try {
                while ($filasArchivo->valid()) {
                    $fila = $filasArchivo->current();
                    $filasArchivo->next();
                    $noVacia = array_filter($fila, fn($v) => trim((string) $v) !== '');
                    if (count($noVacia) === 0) {
                        continue;
                    }
                    if (++$filasConDatos > IMPORTAR_MAX_FILAS) {
                        $limiteSuperado = true;
                        break;
                    }

                    $valores = [];
                    foreach ($columnasDestino as $clave) {
                        $indiceOrigen = array_search($clave, $mapaColumnas, true);
                        $valores[$clave] = $indiceOrigen !== false ? trim((string) ($fila[$indiceOrigen] ?? '')) : '';
                    }

                    $duplicado = false;
                    foreach ($unicas as $u) {
                        if ($valores[$u] !== '' && existe_valor_unico($conn, $moduloSel, $u, $valores[$u])) {
                            $duplicado = true;
                            break;
                        }
                    }
                    if ($duplicado) {
                        $omitidos++;
                        continue;
                    }

                    try {
                        registro_guardar($conn, $moduloSel, $valores, (int) $_SESSION['usuario_id']);
                        $importados++;
                    } catch (mysqli_sql_exception $e) {
                        if (mensaje_duplicado($e) === null) {
                            throw $e;
                        }
                        $omitidos++;
                    }
                }

                } catch (Throwable $falla) {
                    tx_revertir($conn);
                    throw $falla;
                }
                if ($limiteSuperado) {
                    tx_revertir($conn);
                } else {
                    tx_confirmar($conn);
                }

                if ($limiteSuperado) {
                    $error = "El archivo tiene más de " . number_format(IMPORTAR_MAX_FILAS, 0, ',', '.') . " filas con datos. No se importó ningún registro: divídelo en archivos de hasta " . number_format(IMPORTAR_MAX_FILAS, 0, ',', '.') . " filas e impórtalos uno por uno.";
                } else {
                    $resumen = ['importados' => $importados, 'omitidos' => $omitidos];
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Importar registros - SIRAD</title>
  <link rel="stylesheet" href="css/styles.css?v=<?= @filemtime(__DIR__ . '/css/styles.css') ?>">
  <script src="js/animations.js?v=<?= @filemtime(__DIR__ . '/js/animations.js') ?>" defer></script>
</head>

<body>

  <?php render_header(true); ?>

  <main>
    <h1>Importar registros desde Excel o CSV</h1>
    <p class="subtitle" style="margin: -4px 0 14px;">Cada archivo puede pesar hasta <?= (int) IMPORTAR_MAX_MB ?> MB y tener hasta <?= number_format(IMPORTAR_MAX_FILAS, 0, ',', '.') ?> filas con datos; si es más grande, divídelo en varios archivos. Cada usuario puede importar hasta <?= (int) IMPORTAR_MAX_POR_HORA ?> archivos por hora.</p>

    <?php if ($error): ?>
      <p class="auth-error" style="margin-bottom: 16px;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <?php $cupo = importacion_estado($conn, (int) $_SESSION['usuario_id']); ?>
    <?php if ($cupo['agotado']): ?>
      <p class="auth-error" role="alert" style="margin-bottom: 16px;">Llegaste al límite de <?= (int) $cupo['maximo'] ?> importaciones por hora y por ahora no puedes subir más archivos. Podrás volver a importar a las <b><?= htmlspecialchars($cupo['hora']) ?></b> (en <?= (int) $cupo['minutos'] ?> minuto(s)).</p>
    <?php elseif ($cupo['usadas'] > 0): ?>
      <p class="pending-notice" style="margin-bottom: 16px;">Llevas <?= (int) $cupo['usadas'] ?> de <?= (int) $cupo['maximo'] ?> importaciones permitidas por hora. El contador se reinicia a las <b><?= htmlspecialchars($cupo['hora']) ?></b>.</p>
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
      <label>Archivo de Excel (.xlsx) o CSV<input type="file" name="archivo" accept=".xlsx,.csv" required<?= $cupo['agotado'] ? ' disabled' : '' ?>></label>
      <button type="submit" name="importar" value="1"<?= $cupo['agotado'] ? ' disabled title="Llegaste al límite de importaciones por hora"' : '' ?>>Importar</button>
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
