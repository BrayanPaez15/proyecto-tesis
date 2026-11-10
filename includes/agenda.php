<?php
/**
 * Agenda de disponibilidad compartida por los modulos.
 *
 * Guarda los horarios (dia, hora de inicio y de fin, disponible u ocupado,
 * nota) de un registro en la columna `disponibilidad` (JSON) de su propia fila,
 * en las tablas artistas y talleristas. No hay una tabla aparte: la agenda es
 * parte del registro y se elimina con el. El formato es el mismo en ambos
 * modulos y lo valida agenda_leer_post().
 */

function agenda_dias(): array
{
    return [
        'lunes' => 'Lunes', 'martes' => 'Martes', 'miercoles' => 'Miércoles', 'jueves' => 'Jueves',
        'viernes' => 'Viernes', 'sabado' => 'Sábado', 'domingo' => 'Domingo',
    ];
}

function agenda_tipos(): array
{
    return ['disponible' => 'Disponible', 'ocupado' => 'Ocupado'];
}

/** Tabla de cada modulo con agenda (lista blanca): la agenda se guarda en su columna `disponibilidad` (JSON). */
function agenda_tabla(string $modulo): string
{
    $tablas = ['artistas' => 'artistas', 'talleristas' => 'talleristas'];
    if (!isset($tablas[$modulo])) {
        throw new InvalidArgumentException('Modulo sin agenda');
    }
    return $tablas[$modulo];
}

/** Decodifica el JSON de la columna disponibilidad a filas [dia, desde, hasta, tipo, nota], en orden de semana y hora. */
function agenda_decodificar(?string $json): array
{
    $filas = [];
    $lista = $json ? json_decode($json, true) : null;
    if (is_array($lista)) {
        foreach ($lista as $f) {
            if (is_array($f) && isset($f['dia'], $f['desde'], $f['hasta'])) {
                $filas[] = ['dia' => (string) $f['dia'], 'desde' => (string) $f['desde'], 'hasta' => (string) $f['hasta'],
                    'tipo' => (string) ($f['tipo'] ?? 'disponible'), 'nota' => (string) ($f['nota'] ?? '')];
            }
        }
    }
    $orden = array_flip(array_keys(agenda_dias()));
    usort($filas, fn($a, $b) => [$orden[$a['dia']] ?? 9, $a['desde']] <=> [$orden[$b['dia']] ?? 9, $b['desde']]);
    return $filas;
}

function agenda_obtener(mysqli $conn, string $modulo, int $id): array
{
    $tabla = agenda_tabla($modulo);
    $stmt = $conn->prepare("SELECT disponibilidad FROM `$tabla` WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return agenda_decodificar($fila['disponibilidad'] ?? null);
}

/** Devuelve [registro_id => filas] del modulo (solo los ids dados, si se indican), para listarlo sin una consulta por fila. */
function agenda_por_registro(mysqli $conn, string $modulo, ?array $ids = null): array
{
    if ($ids !== null && !$ids) {
        return [];
    }
    $tabla = agenda_tabla($modulo);
    $filtro = $ids !== null ? ' AND id IN (' . implode(',', array_map('intval', $ids)) . ')' : '';
    $res = $conn->query("SELECT id, disponibilidad FROM `$tabla` WHERE disponibilidad IS NOT NULL$filtro");
    $mapa = [];
    while ($f = $res->fetch_assoc()) {
        $filas = agenda_decodificar($f['disponibilidad']);
        if ($filas) {
            $mapa[(int) $f['id']] = $filas;
        }
    }
    return $mapa;
}

/**
 * Lee y valida las filas de agenda enviadas por POST (agenda_dia[], agenda_desde[],
 * agenda_hasta[], agenda_tipo[], agenda_nota[]). Devuelve [filas, error]; si hay error
 * las filas conservan lo escrito para volver a mostrarlo en el formulario.
 */
function agenda_leer_post(): array
{
    $dias = $_POST['agenda_dia'] ?? [];
    $desde = $_POST['agenda_desde'] ?? [];
    $hasta = $_POST['agenda_hasta'] ?? [];
    $tipos = $_POST['agenda_tipo'] ?? [];
    $notas = $_POST['agenda_nota'] ?? [];
    if (!is_array($dias) || !is_array($desde) || !is_array($hasta) || !is_array($tipos) || !is_array($notas)) {
        return [[], "La agenda enviada no es válida."];
    }

    $filas = [];
    foreach ($dias as $i => $dia) {
        $fila = [
            'dia' => (string) $dia,
            'desde' => trim((string) ($desde[$i] ?? '')),
            'hasta' => trim((string) ($hasta[$i] ?? '')),
            'tipo' => (string) ($tipos[$i] ?? 'disponible'),
            'nota' => mb_substr(trim((string) ($notas[$i] ?? '')), 0, 150),
        ];
        if ($fila['dia'] === '' && $fila['desde'] === '' && $fila['hasta'] === '' && $fila['nota'] === '') {
            continue;
        }
        $filas[] = $fila;
    }
    if (count($filas) > 30) {
        return [array_slice($filas, 0, 30), "La agenda admite hasta 30 horarios."];
    }

    $hora = '/^([01]\d|2[0-3]):[0-5]\d$/';
    foreach ($filas as $f) {
        $error = '';
        if (!isset(agenda_dias()[$f['dia']])) {
            $error = "Cada horario debe tener un día de la semana.";
        } elseif (!preg_match($hora, $f['desde']) || !preg_match($hora, $f['hasta'])) {
            $error = "Cada horario debe tener hora de inicio y de fin válidas.";
        } elseif ($f['desde'] >= $f['hasta']) {
            $error = "En la agenda, la hora de fin debe ser posterior a la de inicio.";
        } elseif (!isset(agenda_tipos()[$f['tipo']])) {
            $error = "El tipo de horario no es válido.";
        }
        if ($error !== '') {
            return [$filas, $error];
        }
    }
    foreach ($filas as $a => $fa) {
        foreach ($filas as $b => $fb) {
            if ($a < $b && $fa['dia'] === $fb['dia'] && $fa['desde'] < $fb['hasta'] && $fb['desde'] < $fa['hasta']) {
                return [$filas, "Los horarios del " . mb_strtolower(agenda_dias()[$fa['dia']]) . " se cruzan."];
            }
        }
    }
    return [$filas, ''];
}

/** Guarda la agenda del registro en su columna disponibilidad (JSON); sin filas queda NULL. Viaja con el registro: al eliminarlo desaparece. */
function agenda_guardar(mysqli $conn, string $modulo, int $id, array $filas, int $usuarioId): void
{
    $tabla = agenda_tabla($modulo);
    $limpias = [];
    foreach ($filas as $f) {
        $limpias[] = ['dia' => $f['dia'], 'desde' => $f['desde'], 'hasta' => $f['hasta'], 'tipo' => $f['tipo'], 'nota' => $f['nota']];
    }
    $json = $limpias ? json_encode($limpias, JSON_UNESCAPED_UNICODE) : null;
    $stmt = $conn->prepare("UPDATE `$tabla` SET disponibilidad = ? WHERE id = ?");
    $stmt->bind_param("si", $json, $id);
    $stmt->execute();
    $stmt->close();
}

/** Texto corto para las tablas: "Lun 08:00-12:00 (disp.); Mar 14:00-18:00 (ocup.)". */
function agenda_resumen(array $filas): string
{
    $abrev = ['lunes' => 'Lun', 'martes' => 'Mar', 'miercoles' => 'Mié', 'jueves' => 'Jue', 'viernes' => 'Vie', 'sabado' => 'Sáb', 'domingo' => 'Dom'];
    $partes = [];
    foreach ($filas as $f) {
        $partes[] = ($abrev[$f['dia']] ?? $f['dia']) . ' ' . $f['desde'] . '-' . $f['hasta'] . ($f['tipo'] === 'ocupado' ? ' (ocup.)' : ' (disp.)')
            . ($f['nota'] !== null && $f['nota'] !== '' ? ' ' . $f['nota'] : '');
    }
    return implode('; ', $partes);
}

function agenda_fila_html(array $f): void
{
    ?>
      <div class="agenda-row">
        <select name="agenda_dia[]">
          <option value="">Día...</option>
          <?php foreach (agenda_dias() as $clave => $etiqueta): ?>
            <option value="<?= $clave ?>" <?= ($f['dia'] ?? '') === $clave ? 'selected' : '' ?>><?= $etiqueta ?></option>
          <?php endforeach; ?>
        </select>
        <input type="time" name="agenda_desde[]" value="<?= htmlspecialchars($f['desde'] ?? '') ?>" aria-label="Desde">
        <input type="time" name="agenda_hasta[]" value="<?= htmlspecialchars($f['hasta'] ?? '') ?>" aria-label="Hasta">
        <select name="agenda_tipo[]">
          <?php foreach (agenda_tipos() as $clave => $etiqueta): ?>
            <option value="<?= $clave ?>" <?= ($f['tipo'] ?? 'disponible') === $clave ? 'selected' : '' ?>><?= $etiqueta ?></option>
          <?php endforeach; ?>
        </select>
        <input name="agenda_nota[]" maxlength="150" placeholder="Nota (opcional)" value="<?= htmlspecialchars($f['nota'] ?? '') ?>">
        <button type="button" class="agenda-quitar" title="Quitar horario">&times;</button>
      </div>
    <?php
}

/** Bloque del formulario: filas existentes mas una plantilla para agregar horarios (js/agenda.js). */
function agenda_render_form(array $filas): void
{
    ?>
    <fieldset class="agenda-box" data-agenda>
      <legend>Disponibilidad (día y hora)</legend>
      <p class="agenda-help">Agrega los días y las horas en que la persona está disponible u ocupada (por ejemplo, si trabaja en otro lugar). Los horarios de un mismo día no pueden cruzarse.</p>
      <div class="agenda-rows">
        <?php foreach ($filas as $f) { agenda_fila_html($f); } ?>
      </div>
      <template class="agenda-template"><?php agenda_fila_html([]); ?></template>
      <button type="button" class="agenda-add">+ Agregar horario</button>
    </fieldset>
    <script src="js/agenda.js" defer></script>
    <?php
}
