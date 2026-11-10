<?php
/**
 * Agenda de disponibilidad compartida por los modulos.
 *
 * Guarda en la tabla `disponibilidad` los horarios (dia, hora de inicio y de
 * fin, disponible u ocupado, nota) de cualquier registro, identificado por el
 * par (modulo, registro_id), igual que registro_etiquetas. Asi la agenda es
 * una sola estructura y no un campo repetido en cada modulo. Hoy la usan
 * Artistas y Talleristas.
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

function agenda_obtener(mysqli $conn, string $modulo, int $id): array
{
    $stmt = $conn->prepare("SELECT dia, TIME_FORMAT(hora_inicio, '%H:%i') AS desde, TIME_FORMAT(hora_fin, '%H:%i') AS hasta, tipo, nota
        FROM disponibilidad WHERE modulo = ? AND registro_id = ?
        ORDER BY FIELD(dia, 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'), hora_inicio");
    $stmt->bind_param("si", $modulo, $id);
    $stmt->execute();
    $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $filas;
}

/** Devuelve [registro_id => filas] de todo un modulo, para listarlo sin una consulta por fila. */
function agenda_por_registro(mysqli $conn, string $modulo): array
{
    $stmt = $conn->prepare("SELECT registro_id, dia, TIME_FORMAT(hora_inicio, '%H:%i') AS desde, TIME_FORMAT(hora_fin, '%H:%i') AS hasta, tipo, nota
        FROM disponibilidad WHERE modulo = ?
        ORDER BY FIELD(dia, 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'), hora_inicio");
    $stmt->bind_param("s", $modulo);
    $stmt->execute();
    $res = $stmt->get_result();
    $mapa = [];
    while ($f = $res->fetch_assoc()) {
        $mapa[(int) $f['registro_id']][] = $f;
    }
    $stmt->close();
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

/** Reemplaza la agenda del registro por las filas dadas (borra e inserta en una transaccion). */
function agenda_guardar(mysqli $conn, string $modulo, int $id, array $filas, int $usuarioId): void
{
    $conn->begin_transaction();
    $del = $conn->prepare("DELETE FROM disponibilidad WHERE modulo = ? AND registro_id = ?");
    $del->bind_param("si", $modulo, $id);
    $del->execute();
    $del->close();
    if ($filas) {
        $ins = $conn->prepare("INSERT INTO disponibilidad (modulo, registro_id, dia, hora_inicio, hora_fin, tipo, nota, creado_por) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($filas as $f) {
            $nota = $f['nota'] === '' ? null : $f['nota'];
            $ins->bind_param("sisssssi", $modulo, $id, $f['dia'], $f['desde'], $f['hasta'], $f['tipo'], $nota, $usuarioId);
            $ins->execute();
        }
        $ins->close();
    }
    $conn->commit();
}

/** Borra la agenda de un registro (se llama al eliminar el registro, porque (modulo, registro_id) no tiene llave foranea). */
function agenda_eliminar(mysqli $conn, string $modulo, int $id): void
{
    $stmt = $conn->prepare("DELETE FROM disponibilidad WHERE modulo = ? AND registro_id = ?");
    $stmt->bind_param("si", $modulo, $id);
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
