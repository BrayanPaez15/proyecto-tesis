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

/**
 * Reserva un cupo de importacion para el usuario: cuenta una importacion mas dentro de la ventana
 * (IMPORTAR_VENTANA_MIN minutos desde la primera) y devuelve 0 si puede seguir, o los minutos que
 * faltan para que se libere el cupo si ya alcanzo IMPORTAR_MAX_POR_HORA. Usa la hora de la base de
 * datos y una sola sentencia, asi dos importaciones a la vez no se saltan el limite.
 */
function importacion_reservar(mysqli $conn, int $usuarioId): int
{
    $ventana = (int) IMPORTAR_VENTANA_MIN;
    $maximo = (int) IMPORTAR_MAX_POR_HORA;
    $stmt = $conn->prepare("UPDATE usuarios SET
            importaciones_cuenta = IF(importaciones_desde IS NULL OR importaciones_desde < DATE_SUB(NOW(), INTERVAL ? MINUTE), 1, importaciones_cuenta + 1),
            importaciones_desde = IF(importaciones_desde IS NULL OR importaciones_desde < DATE_SUB(NOW(), INTERVAL ? MINUTE), NOW(), importaciones_desde)
        WHERE id_usuario = ? AND (importaciones_desde IS NULL OR importaciones_desde < DATE_SUB(NOW(), INTERVAL ? MINUTE) OR importaciones_cuenta < ?)");
    $stmt->bind_param("iiiii", $ventana, $ventana, $usuarioId, $ventana, $maximo);
    $stmt->execute();
    $ok = $stmt->affected_rows > 0;
    $stmt->close();
    if ($ok) {
        return 0;
    }
    $q = $conn->prepare("SELECT GREATEST(1, TIMESTAMPDIFF(MINUTE, NOW(), DATE_ADD(importaciones_desde, INTERVAL ? MINUTE))) FROM usuarios WHERE id_usuario = ?");
    $q->bind_param("ii", $ventana, $usuarioId);
    $q->execute();
    $min = (int) ($q->get_result()->fetch_row()[0] ?? 1);
    $q->close();
    return max(1, $min);
}

/**
 * Estado del cupo de importaciones del usuario para mostrarlo en pantalla: cuantas lleva en la ventana
 * vigente, el maximo, si ya lo agoto y a que hora (hora de la base de datos, HH:MM) y en cuantos minutos
 * se libera.
 */
function importacion_estado(mysqli $conn, int $usuarioId): array
{
    $ventana = (int) IMPORTAR_VENTANA_MIN;
    $maximo = (int) IMPORTAR_MAX_POR_HORA;
    $q = $conn->prepare("SELECT importaciones_cuenta, DATE_FORMAT(DATE_ADD(importaciones_desde, INTERVAL ? MINUTE), '%H:%i'),
            GREATEST(1, TIMESTAMPDIFF(MINUTE, NOW(), DATE_ADD(importaciones_desde, INTERVAL ? MINUTE)))
        FROM usuarios WHERE id_usuario = ? AND importaciones_desde IS NOT NULL AND importaciones_desde >= DATE_SUB(NOW(), INTERVAL ? MINUTE)");
    $q->bind_param("iiii", $ventana, $ventana, $usuarioId, $ventana);
    $q->execute();
    $f = $q->get_result()->fetch_row();
    $q->close();
    $usadas = $f ? (int) $f[0] : 0;
    return ['usadas' => $usadas, 'maximo' => $maximo, 'agotado' => $usadas >= $maximo, 'hora' => $f ? (string) $f[1] : '', 'minutos' => $f ? (int) $f[2] : 0];
}
