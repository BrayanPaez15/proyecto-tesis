<?php
/**
 * Acceso a los datos de los modulos (modelo generalizado).
 *
 * Los once modulos del directorio son tipos de actor: sus datos comunes viven en
 * `contactos`, y los propios de cada tipo en su tabla `detalle_*` (ver db/schema.sql y la
 * descripcion de cada modulo en includes/modulos.php). Este archivo traduce entre el
 * formulario de un modulo (sus campos de siempre: «caja», «empresa», «celular»...) y las
 * tablas:
 *  - origen_modulo(): consulta derivada que devuelve las filas del modulo con los nombres
 *    de campo del formulario, mas id, fecha_registro, creado_por, actualizado_en y
 *    actualizado_por. Sirve como si fuera la tabla del modulo (listas, busqueda, exportar).
 *  - registro_obtener(), registro_guardar() y registro_eliminar(): leen, crean/editan
 *    (contacto y detalle en una sola transaccion) y borran un registro, comprobando
 *    siempre que pertenezca al modulo (un id de otro modulo se trata como inexistente).
 *  - modulo_procesar(): el formulario completo de un modulo (validar, guardar, redirigir).
 */
require_once __DIR__ . '/duplicados.php';

function modulos_def(): array
{
    static $modulos = null;
    return $modulos ??= require __DIR__ . '/modulos.php';
}

function modulo_def(string $modulo): array
{
    $m = modulos_def();
    if (!isset($m[$modulo])) {
        throw new InvalidArgumentException('Modulo desconocido');
    }
    return $m[$modulo];
}

function modulo_sql_texto(string $texto): string
{
    return "'" . str_replace(["\\", "'"], ["\\\\", "''"], $texto) . "'";
}

/** FROM del modulo: contactos del tipo (y, en personal, de la vinculacion) con su detalle. */
function modulo_from(string $modulo): string
{
    $def = modulo_def($modulo);
    $sql = 'contactos c JOIN tipos_actor ta ON ta.id_tipo_actor = c.id_tipo_actor AND ta.nombre = ' . modulo_sql_texto($def['tipo']);
    if ($def['detalle']) {
        $condicion = 'd.id_contacto = c.id_contacto';
        $union = 'LEFT JOIN';
        if (isset($def['vinculacion'])) {
            $condicion .= ' AND d.vinculacion = ' . modulo_sql_texto($def['vinculacion']);
            $union = 'JOIN';
        }
        $sql .= " $union `{$def['detalle']}` d ON $condicion";
    }
    return $sql;
}

/** Lista de columnas del modulo con los nombres de campo del formulario. */
function modulo_select(string $modulo): string
{
    $def = modulo_def($modulo);
    $cols = ['c.id_contacto AS `id`'];
    foreach ($def['mapa'] as $campo => $ref) {
        $expr = $ref;
        if (in_array($campo, $def['booleanos'] ?? [], true)) {
            $expr = "CASE $ref WHEN 1 THEN 'Sí' WHEN 0 THEN 'No' ELSE '' END";
        }
        $cols[] = "$expr AS `$campo`";
    }
    array_push($cols, 'c.fecha_registro', 'c.creado_por', 'c.actualizado_en', 'c.actualizado_por');
    if (!empty($def['agenda'])) {
        $cols[] = 'c.disponibilidad';
    }
    return implode(', ', $cols);
}

/** Consulta derivada con alias `m`, para usar como «FROM (...) AS m WHERE m.campo ...». */
function origen_modulo(string $modulo): string
{
    return '(SELECT ' . modulo_select($modulo) . ' FROM ' . modulo_from($modulo) . ') AS m';
}

function contar_modulo(mysqli $conn, string $modulo): int
{
    return (int) $conn->query('SELECT COUNT(*) FROM ' . modulo_from($modulo))->fetch_row()[0];
}

/**
 * Transacciones anidadas: la primera abre una transaccion de verdad y las siguientes usan
 * un punto de guardado, de modo que revertir un registro (por ejemplo un duplicado al
 * importar) no deshace lo anterior y revertir la externa deshace todo.
 */
function tx_nivel(mysqli $conn, int $delta = 0): int
{
    static $niveles = [];
    $id = spl_object_id($conn);
    $niveles[$id] = ($niveles[$id] ?? 0) + $delta;
    return $niveles[$id];
}

function tx_iniciar(mysqli $conn): void
{
    $nivel = tx_nivel($conn, 1);
    if ($nivel === 1) {
        $conn->begin_transaction();
    } else {
        $conn->query('SAVEPOINT sp_' . $nivel);
    }
}

function tx_confirmar(mysqli $conn): void
{
    $nivel = tx_nivel($conn);
    if ($nivel === 1) {
        $conn->commit();
    } else {
        $conn->query('RELEASE SAVEPOINT sp_' . $nivel);
    }
    tx_nivel($conn, -1);
}

function tx_revertir(mysqli $conn): void
{
    $nivel = tx_nivel($conn);
    if ($nivel === 1) {
        $conn->rollback();
    } else {
        $conn->query('ROLLBACK TO SAVEPOINT sp_' . $nivel);
        $conn->query('RELEASE SAVEPOINT sp_' . $nivel);
    }
    tx_nivel($conn, -1);
}

/** Sentencia preparada reutilizable (importar guarda miles de filas con la misma consulta). */
function contactos_preparar(mysqli $conn, string $sql): mysqli_stmt
{
    static $cache = [];
    $clave = spl_object_id($conn) . '|' . $sql;
    return $cache[$clave] ??= $conn->prepare($sql);
}

function registro_obtener(mysqli $conn, string $modulo, int $id): ?array
{
    $stmt = $conn->prepare('SELECT * FROM ' . origen_modulo($modulo) . ' WHERE m.id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $fila ?: null;
}

/** Convierte lo escrito en el formulario al valor que se guarda en la columna. */
function valor_para_guardar(array $def, string $campo, string $columna, $valor)
{
    if ($valor === null) {
        return null;
    }
    $valor = is_array($valor) ? '' : (string) $valor;
    if (in_array($campo, $def['booleanos'] ?? [], true)) {
        $v = mb_strtolower(trim($valor));
        if (in_array($v, ['sí', 'si', '1', 'true', 'x', 'yes', 'verdadero'], true)) {
            return '1';
        }
        return in_array($v, ['no', '0', 'false', 'falso'], true) ? '0' : null;
    }
    if (in_array($campo, $def['fechas'] ?? [], true) || in_array($columna, ['correo', 'nit', 'cedula'], true)) {
        $valor = trim($valor);
        return $valor === '' ? null : $valor;
    }
    return $valor;
}

/**
 * Crea (si $id es null) o actualiza un registro del modulo y devuelve su id. Escribe el
 * contacto y su detalle en una transaccion. Solo se guardan los campos presentes en
 * $datos. Lanza RuntimeException si el id no pertenece al modulo.
 */
function registro_guardar(mysqli $conn, string $modulo, array $datos, int $usuarioId, ?int $id = null): int
{
    $def = modulo_def($modulo);
    $c = [];
    $d = [];
    foreach ($def['mapa'] as $campo => $ref) {
        if (!array_key_exists($campo, $datos)) {
            continue;
        }
        [$tabla, $columna] = explode('.', $ref, 2);
        $valor = valor_para_guardar($def, $campo, $columna, $datos[$campo]);
        if ($tabla === 'c') {
            $c[$columna] = $valor;
        } else {
            $d[$columna] = $valor;
        }
    }
    if (array_key_exists('nombre', $c) && $c['nombre'] === null) {
        $c['nombre'] = '';
    }

    if ($id !== null && registro_obtener($conn, $modulo, $id) === null) {
        throw new RuntimeException('El registro no existe en este módulo.');
    }

    $esNuevo = $id === null;
    tx_iniciar($conn);
    try {
        if ($esNuevo) {
            $c += ['nombre' => ''];
            $cols = array_keys($c);
            $sql = 'INSERT INTO contactos (id_tipo_actor, ' . implode(', ', array_map(fn($x) => "`$x`", $cols)) . ', creado_por) VALUES ((SELECT id_tipo_actor FROM tipos_actor WHERE nombre = ?), '
                . implode(', ', array_fill(0, count($cols), '?')) . ', ?)';
            contactos_preparar($conn, $sql)->execute(array_merge([$def['tipo']], array_values($c), [$usuarioId]));
            $id = (int) $conn->insert_id;
        } else {
            $asignaciones = array_map(fn($x) => "`$x` = ?", array_keys($c));
            $asignaciones[] = 'actualizado_en = NOW()';
            $asignaciones[] = 'actualizado_por = ?';
            $sql = 'UPDATE contactos SET ' . implode(', ', $asignaciones) . ' WHERE id_contacto = ?';
            contactos_preparar($conn, $sql)->execute(array_merge(array_values($c), [$usuarioId, $id]));
        }

        if ($def['detalle']) {
            $detalle = '`' . $def['detalle'] . '`';
            $existe = false;
            if ($esNuevo === false) {
                $q = contactos_preparar($conn, "SELECT 1 FROM $detalle WHERE id_contacto = ?");
                $q->execute([$id]);
                $existe = $q->get_result()->num_rows > 0;
            }
            if ($existe) {
                // se actualiza solo la fila de este contacto (nunca con ON DUPLICATE KEY: la cedula tambien es llave unica)
                if ($d) {
                    $sql = "UPDATE $detalle SET " . implode(', ', array_map(fn($x) => "`$x` = ?", array_keys($d))) . ' WHERE id_contacto = ?';
                    contactos_preparar($conn, $sql)->execute(array_merge(array_values($d), [$id]));
                }
            } else {
                if (isset($def['vinculacion'])) {
                    $d['vinculacion'] = $def['vinculacion'];
                }
                $cols = array_keys($d);
                $sql = "INSERT INTO $detalle (id_contacto" . ($cols ? ', ' : '') . implode(', ', array_map(fn($x) => "`$x`", $cols)) . ') VALUES (?' . str_repeat(', ?', count($cols)) . ')';
                contactos_preparar($conn, $sql)->execute(array_merge([$id], array_values($d)));
            }
        }
        tx_confirmar($conn);
    } catch (Throwable $e) {
        tx_revertir($conn);
        throw $e;
    }
    return $id;
}

/** Elimina un registro del modulo (su detalle y sus etiquetas se van con el, por ON DELETE CASCADE). */
function registro_eliminar(mysqli $conn, string $modulo, int $id): bool
{
    $stmt = $conn->prepare('DELETE c FROM ' . modulo_from($modulo) . ' WHERE c.id_contacto = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $borrado = $stmt->affected_rows > 0;
    $stmt->close();
    return $borrado;
}

/**
 * Formulario completo de un modulo: muestra el registro a editar (GET id, solo administradores),
 * valida y guarda el POST (crear o editar), y redirige al terminar. Devuelve
 * [$editando, $error, $valores, $agendaFilas] para pintar el formulario.
 */
function modulo_procesar(mysqli $conn, string $modulo): array
{
    $def = modulo_def($modulo);
    $conAgenda = !empty($def['agenda']);
    $editando = null;
    if (isset($_GET['id'])) {
        require_admin();
        $editando = registro_obtener($conn, $modulo, (int) $_GET['id']);
    }
    $agendaFilas = ($conAgenda && $editando) ? agenda_obtener($conn, $modulo, (int) $editando['id']) : [];

    $error = '';
    if ($_POST) {
        csrf_verify();
        $usuarioId = (int) $_SESSION['usuario_id'];
        normalizar_unicos();
        $esEdicion = !empty($_POST['id']);
        $id = 0;
        if ($esEdicion) {
            require_admin();
            $id = (int) $_POST['id'];
        }
        if ($conAgenda) {
            [$agendaFilas, $errorAgenda] = agenda_leer_post();
        } else {
            $errorAgenda = '';
        }

        $correo = trim((string) ($_POST['correo'] ?? ''));
        if ($errorAgenda !== '') {
            $error = $errorAgenda;
        } elseif ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $error = 'El correo no tiene un formato válido.';
        } elseif ($esEdicion && registro_obtener($conn, $modulo, $id) === null) {
            $error = 'El registro que intenta editar no existe en este módulo.';
        } else {
            $error = error_duplicado($conn, $modulo, $id);
        }

        if ($error === '') {
            try {
                $nuevoId = registro_guardar($conn, $modulo, $_POST, $usuarioId, $esEdicion ? $id : null);
                if ($conAgenda) {
                    agenda_guardar($conn, $modulo, $nuevoId, $agendaFilas, $usuarioId);
                }
                if ($esEdicion) {
                    $destino = $def['archivo'] . '_registros.php';
                    $mensaje = 'Registro actualizado';
                } else {
                    $destino = puede_modulo($modulo, 'ver') ? $def['archivo'] . '_registros.php' : $def['archivo'] . '.php';
                    $mensaje = 'Registro guardado';
                }
                header('Location: ' . $destino . '?msg=' . rawurlencode($mensaje) . '&tipo=success');
                exit;
            } catch (mysqli_sql_exception $e) {
                $error = mensaje_duplicado($e) ?? throw $e;
            }
        }
    }
    $valores = ($error !== '' && $_POST) ? $_POST : ($editando ?? []);
    return [$editando, $error, $valores, $agendaFilas];
}
