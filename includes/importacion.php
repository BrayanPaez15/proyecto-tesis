<?php
/**
 * Lectura de los archivos de importacion: Excel (.xlsx) o CSV.
 *
 * filas_importacion() devuelve, fila por fila, los valores de texto del archivo. En CSV detecta
 * el separador (coma, punto y coma o tabulador: Excel en espanol guarda con punto y coma) y la
 * codificacion (UTF-8 o, si no lo es, Windows-1252, la de «CSV (delimitado por comas)» de
 * Excel), y quita la marca BOM. Cualquier otra extension se rechaza.
 */
require_once __DIR__ . '/xlsx.php';

function separador_csv(string $muestra): string
{
    $linea = '';
    foreach (preg_split('/\r\n|\r|\n/', $muestra) as $l) {
        if (trim($l) !== '' && stripos($l, 'sep=') !== 0) {
            $linea = $l;
            break;
        }
    }
    $cuenta = [',' => 0, ';' => 0, "\t" => 0];
    $entreComillas = false;
    foreach (str_split($linea) as $ch) {
        if ($ch === '"') {
            $entreComillas = !$entreComillas;
        } elseif (!$entreComillas && isset($cuenta[$ch])) {
            $cuenta[$ch]++;
        }
    }
    arsort($cuenta);
    return array_key_first($cuenta) !== null && reset($cuenta) > 0 ? array_key_first($cuenta) : ',';
}

/** @return Generator<array<int,string>> */
function filas_csv(string $ruta): Generator
{
    $contenido = file_get_contents($ruta);
    if ($contenido === false) {
        throw new RuntimeException('No se pudo leer el archivo.');
    }
    $contenido = preg_replace('/^\xEF\xBB\xBF/', '', $contenido);
    if (!mb_check_encoding($contenido, 'UTF-8')) {
        $contenido = iconv('Windows-1252', 'UTF-8//IGNORE', $contenido);
    }
    $sep = separador_csv(substr($contenido, 0, 65536));
    $h = fopen('php://temp/maxmemory:' . (8 * 1024 * 1024), 'r+');
    fwrite($h, $contenido);
    unset($contenido);
    rewind($h);
    $primera = true;
    while (($fila = fgetcsv($h, 0, $sep, '"', '')) !== false) {
        if ($primera) {
            $primera = false;
            if (stripos((string) $fila[0], 'sep=') === 0 && count(array_filter($fila, fn($v) => trim((string) $v) !== '')) === 1) {
                continue; // linea «sep=;» que agrega Excel
            }
        }
        yield $fila;
    }
    fclose($h);
}

/** @return Generator<array<int,string>> */
function filas_importacion(string $ruta, string $nombreOriginal): Generator
{
    $ext = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
    if ($ext === 'xlsx') {
        foreach (xlsx_leer($ruta) as $fila) {
            yield $fila;
        }
    } elseif ($ext === 'csv' || $ext === 'txt') {
        yield from filas_csv($ruta);
    } else {
        throw new RuntimeException('Formato no admitido. Suba un archivo de Excel (.xlsx) o un CSV (.csv).');
    }
}
