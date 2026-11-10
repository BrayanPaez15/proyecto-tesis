<?php
/**
 * Lectura y escritura minima de archivos Excel (.xlsx) sin librerias externas.
 *
 * xlsx_crear() arma un libro de una hoja: encabezado en negrita con color, columnas con ancho,
 * fila de encabezado fija y todas las celdas en formato de texto (asi telefonos, cedulas y
 * NIT conservan sus ceros y un dato que empiece por = no se ejecuta como formula).
 * xlsx_leer() devuelve las filas de la primera hoja como arreglos de texto, resolviendo las
 * cadenas compartidas que guarda Excel. Un .xlsx es un ZIP: aqui se escribe sin comprimir y se
 * lee con gzinflate (zlib), por lo que no hace falta la extension zip de PHP. Al leer se
 * limita el tamano descomprimido para no abrir archivos «bomba» y no se resuelven entidades
 * externas del XML.
 */

const XLSX_MAX_DESCOMPRIMIDO = 100 * 1024 * 1024;

function xlsx_columna(int $indice): string
{
    $letras = '';
    for ($n = $indice + 1; $n > 0; $n = intdiv($n - 1, 26)) {
        $letras = chr(65 + ($n - 1) % 26) . $letras;
    }
    return $letras;
}

function xlsx_texto(string $t): string
{
    // caracteres de control no validos en XML 1.0 se descartan
    $t = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $t);
    return htmlspecialchars($t, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

/** ZIP sin compresion (metodo 0). $archivos: nombre => contenido. */
function zip_almacenar(array $archivos): string
{
    $datos = '';
    $central = '';
    $hora = 0;
    $fecha = ((2026 - 1980) << 9) | (1 << 5) | 1;
    foreach ($archivos as $nombre => $contenido) {
        $crc = crc32($contenido) & 0xFFFFFFFF;
        $tam = strlen($contenido);
        $offset = strlen($datos);
        $cab = pack('vvvvvVVVvv', 20, 0, 0, $hora, $fecha, $crc, $tam, $tam, strlen($nombre), 0);
        $datos .= "PK\x03\x04" . $cab . $nombre . $contenido;
        $central .= "PK\x01\x02" . pack('vvvvvvVVVvvvvvVV', 20, 20, 0, 0, $hora, $fecha, $crc, $tam, $tam, strlen($nombre), 0, 0, 0, 0, 0, $offset) . $nombre;
    }
    $fin = "PK\x05\x06" . pack('vvvvVVv', 0, 0, count($archivos), count($archivos), strlen($central), strlen($datos), 0);
    return $datos . $central . $fin;
}

/** Libro de una hoja con encabezados y filas opcionales; devuelve el contenido binario del .xlsx. */
function xlsx_crear(array $encabezados, array $filas = [], string $hoja = 'Plantilla'): string
{
    $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    $nCols = count($encabezados);

    $cols = '';
    foreach ($encabezados as $i => $titulo) {
        $ancho = max(14, min(40, mb_strlen((string) $titulo) + 6));
        $cols .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $ancho . '" style="2" customWidth="1"/>';
    }

    $filaEnc = '<row r="1" ht="22" customHeight="1">';
    foreach ($encabezados as $i => $titulo) {
        $filaEnc .= '<c r="' . xlsx_columna($i) . '1" s="1" t="inlineStr"><is><t>' . xlsx_texto((string) $titulo) . '</t></is></c>';
    }
    $filaEnc .= '</row>';

    $cuerpo = '';
    foreach (array_values($filas) as $r => $fila) {
        $cuerpo .= '<row r="' . ($r + 2) . '">';
        foreach (array_values($fila) as $i => $valor) {
            $cuerpo .= '<c r="' . xlsx_columna($i) . ($r + 2) . '" s="2" t="inlineStr"><is><t xml:space="preserve">' . xlsx_texto((string) $valor) . '</t></is></c>';
        }
        $cuerpo .= '</row>';
    }

    $hojaXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="' . $ns . '"><sheetViews><sheetView workbookViewId="0" tabSelected="1"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
        . '<sheetFormatPr defaultRowHeight="15"/><cols>' . $cols . '</cols><sheetData>' . $filaEnc . $cuerpo . '</sheetData></worksheet>';

    $estilos = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="' . $ns . '">'
        . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts>'
        . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF2563EB"/><bgColor indexed="64"/></patternFill></fill></fills>'
        . '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FFBFBFBF"/></left><right style="thin"><color rgb="FFBFBFBF"/></right><top style="thin"><color rgb="FFBFBFBF"/></top><bottom style="thin"><color rgb="FFBFBFBF"/></bottom><diagonal/></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
        . '<xf numFmtId="49" fontId="1" fillId="2" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>'
        . '<xf numFmtId="49" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"/></cellXfs>'
        . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';

    $rel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    return zip_almacenar([
        '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
        '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="' . $rel . '/officeDocument" Target="xl/workbook.xml"/></Relationships>',
        'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="' . $ns . '" xmlns:r="' . $rel . '"><sheets><sheet name="' . xlsx_texto(mb_substr(preg_replace('/[\\\\\/?*\[\]:]/', ' ', $hoja), 0, 31)) . '" sheetId="1" r:id="rId1"/></sheets></workbook>',
        'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="' . $rel . '/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="' . $rel . '/styles" Target="styles.xml"/></Relationships>',
        'xl/styles.xml' => $estilos,
        'xl/worksheets/sheet1.xml' => $hojaXml,
    ]);
}

/** Entradas de un ZIP: nombre => [metodo, tamano comprimido, desplazamiento del encabezado local]. */
function zip_entradas(string $zip): array
{
    $fin = strrpos($zip, "PK\x05\x06");
    if ($fin === false || strlen($zip) < $fin + 22) {
        throw new RuntimeException('El archivo no es un libro de Excel válido.');
    }
    $e = unpack('vdisco/vdiscoCentral/venDisco/vtotal/Vtamano/Voffset', substr($zip, $fin + 4, 16));
    $entradas = [];
    $pos = $e['offset'];
    for ($i = 0; $i < $e['total']; $i++) {
        if (substr($zip, $pos, 4) !== "PK\x01\x02") {
            throw new RuntimeException('El archivo no es un libro de Excel válido.');
        }
        $c = unpack('vver/vminver/vflags/vmetodo/vhora/vfecha/Vcrc/Vcomp/Vtam/vnlen/velen/vclen/vdisco/vintern/Vextern/Voffset', substr($zip, $pos + 4, 42));
        $nombre = substr($zip, $pos + 46, $c['nlen']);
        $entradas[$nombre] = [$c['metodo'], $c['comp'], $c['offset'], $c['tam']];
        $pos += 46 + $c['nlen'] + $c['elen'] + $c['clen'];
    }
    return $entradas;
}

function zip_leer_entrada(string $zip, array $entradas, string $nombre): ?string
{
    if (!isset($entradas[$nombre])) {
        return null;
    }
    [$metodo, $comp, $offset, $tam] = $entradas[$nombre];
    if ($tam > XLSX_MAX_DESCOMPRIMIDO || substr($zip, $offset, 4) !== "PK\x03\x04") {
        throw new RuntimeException('El archivo de Excel es demasiado grande o está dañado.');
    }
    $l = unpack('vnlen/velen', substr($zip, $offset + 26, 4));
    $datos = substr($zip, $offset + 30 + $l['nlen'] + $l['elen'], $comp);
    if ($metodo === 0) {
        return $datos;
    }
    if ($metodo === 8) {
        $sal = @gzinflate($datos, XLSX_MAX_DESCOMPRIMIDO);
        if ($sal === false) {
            throw new RuntimeException('El archivo de Excel es demasiado grande o está dañado.');
        }
        return $sal;
    }
    throw new RuntimeException('El archivo de Excel usa una compresión no admitida.');
}

function xlsx_xml(string $contenido): SimpleXMLElement
{
    $prev = libxml_use_internal_errors(true);
    $xml = simplexml_load_string($contenido, 'SimpleXMLElement', LIBXML_NONET | LIBXML_COMPACT);
    libxml_use_internal_errors($prev);
    if ($xml === false) {
        throw new RuntimeException('El archivo de Excel está dañado.');
    }
    return $xml;
}

/** Texto de una celda <si> o <is>: <t> directo o varias partes <r><t>. */
function xlsx_texto_celda(SimpleXMLElement $nodo): string
{
    $nodo->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    $partes = $nodo->xpath('.//m:t') ?: [];
    $t = '';
    foreach ($partes as $p) {
        $t .= (string) $p;
    }
    return $t;
}

/** Filas de la primera hoja del libro: arreglo de arreglos de texto (sin recortar). */
function xlsx_leer(string $ruta): array
{
    $zip = @file_get_contents($ruta);
    if ($zip === false) {
        throw new RuntimeException('No se pudo leer el archivo.');
    }
    $entradas = zip_entradas($zip);

    $hoja = 'xl/worksheets/sheet1.xml';
    $libro = zip_leer_entrada($zip, $entradas, 'xl/workbook.xml');
    $rels = zip_leer_entrada($zip, $entradas, 'xl/_rels/workbook.xml.rels');
    if ($libro !== null && $rels !== null) {
        $wb = xlsx_xml($libro);
        $wb->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $primera = $wb->xpath('//m:sheets/m:sheet[1]')[0] ?? null;
        $rid = $primera ? (string) $primera->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'] : '';
        foreach (xlsx_xml($rels)->Relationship as $r) {
            if ((string) $r['Id'] === $rid) {
                $destino = (string) $r['Target'];
                $hoja = ltrim($destino[0] === '/' ? $destino : 'xl/' . $destino, '/');
            }
        }
    }
    $hojaXml = zip_leer_entrada($zip, $entradas, $hoja);
    if ($hojaXml === null) {
        throw new RuntimeException('No se encontró la hoja del libro de Excel.');
    }

    $compartidas = [];
    $ss = zip_leer_entrada($zip, $entradas, 'xl/sharedStrings.xml');
    if ($ss !== null) {
        foreach (xlsx_xml($ss)->si as $si) {
            $compartidas[] = xlsx_texto_celda($si);
        }
    }

    $filas = [];
    foreach (xlsx_xml($hojaXml)->sheetData->row ?? [] as $fila) {
        $celdas = [];
        $max = -1;
        $auto = 0;
        foreach ($fila->c as $c) {
            $indice = $auto;
            if (preg_match('/^([A-Z]+)\d+$/', (string) $c['r'], $m)) {
                $indice = 0;
                foreach (str_split($m[1]) as $ch) {
                    $indice = $indice * 26 + (ord($ch) - 64);
                }
                $indice--;
            }
            $auto = $indice + 1;
            $tipo = (string) $c['t'];
            if ($tipo === 's') {
                $valor = $compartidas[(int) $c->v] ?? '';
            } elseif ($tipo === 'inlineStr') {
                $valor = xlsx_texto_celda($c->is);
            } elseif ($tipo === 'b') {
                $valor = ((string) $c->v) === '1' ? 'VERDADERO' : 'FALSO';
            } else {
                $valor = (string) $c->v;
            }
            $celdas[$indice] = $valor;
            $max = max($max, $indice);
        }
        $salida = [];
        for ($i = 0; $i <= $max; $i++) {
            $salida[] = $celdas[$i] ?? '';
        }
        $filas[] = $salida;
    }
    return $filas;
}
