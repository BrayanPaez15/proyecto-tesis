<?php
/**
 * Busqueda y paginacion en el servidor para las vistas «Ver registros».
 *
 * En lugar de enviar todos los registros del modulo y paginarlos en el navegador,
 * cada pagina trae solo POR_PAGINA filas (las mas recientes primero) y la busqueda
 * se hace en la base de datos sobre las columnas del modulo (includes/modulos.php).
 * Asi el tamano de la pagina y el tiempo de respuesta no dependen de cuantos
 * registros haya. Los parametros GET son «q» (texto en todas las columnas), «f_<columna>» (un texto por
 * columna, por ejemplo f_nombre=...; se combinan con Y) y «pagina».
 */

require_once __DIR__ . '/contactos.php';

const POR_PAGINA = 50;

function termino_busqueda(): string
{
    return mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
}

function pagina_solicitada(): int
{
    return max(1, (int) ($_GET['pagina'] ?? 1));
}

/** Clave del modulo de la pagina «Ver registros» actual (artistas_registros.php -> artistas), o null. */
function modulo_de_la_pagina(): ?string
{
    $clave = basename($_SERVER['SCRIPT_NAME'] ?? '', '_registros.php');
    try {
        modulo_def($clave);
    } catch (InvalidArgumentException $e) {
        return null;
    }
    return $clave;
}

/**
 * Filtros por columna pedidos en la URL (f_columna=texto). Solo se aceptan columnas del catalogo del modulo,
 * con un maximo de 100 caracteres; los vacios se ignoran. Son parametros de texto, no arreglos.
 */
function filtros_columnas(?string $clave): array
{
    if ($clave === null) {
        return [];
    }
    $filtros = [];
    foreach (array_keys(modulo_def($clave)['columnas']) as $col) {
        $valor = $_GET['f_' . $col] ?? null;
        if (is_string($valor)) {
            $valor = mb_substr(trim($valor), 0, 100);
            if ($valor !== '') {
                $filtros[$col] = $valor;
            }
        }
    }
    return $filtros;
}

/** Hay busqueda general o filtros por columna aplicados. */
function hay_busqueda(string $q): bool
{
    return $q !== '' || filtros_columnas(modulo_de_la_pagina()) !== [];
}

/** Texto (ya escapado) para el aviso de «sin resultados»: «para «texto»» y/o «con esos filtros». */
function descripcion_busqueda(string $q): string
{
    $partes = [];
    if ($q !== '') {
        $partes[] = 'para «' . htmlspecialchars($q) . '»';
    }
    if (filtros_columnas(modulo_de_la_pagina()) !== []) {
        $partes[] = 'con esos filtros';
    }
    return implode(' ', $partes);
}

/**
 * Devuelve [filas, total, pagina, paginas] del modulo indicado (clave de includes/modulos.php),
 * filtrando por $q en todas sus columnas. El origen de los datos y las columnas salen del catalogo de modulos,
 * nunca de la peticion.
 */
function consulta_paginada(mysqli $conn, string $clave, string $q, int $pagina): array
{
    $columnas = array_keys(modulo_def($clave)['columnas']);
    $origen = origen_modulo($clave);

    $donde = '';
    $tipos = '';
    $params = [];
    $where = [];
    if ($q !== '') {
        $like = '%' . addcslashes($q, '%_\\') . '%';
        $condiciones = [];
        foreach ($columnas as $c) {
            $condiciones[] = "`$c` LIKE ?";
            $tipos .= 's';
            $params[] = $like;
        }
        $where[] = '(' . implode(' OR ', $condiciones) . ')';
    }
    // filtros por columna: cada uno restringe aun mas el resultado (Y)
    foreach (filtros_columnas($clave) as $col => $texto) {
        $where[] = "`$col` LIKE ?";
        $tipos .= 's';
        $params[] = '%' . addcslashes($texto, '%_\\') . '%';
    }
    if ($where) {
        $donde = ' WHERE ' . implode(' AND ', $where);
    }

    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM $origen$donde");
    if ($params) {
        $stmt->bind_param($tipos, ...$params);
    }
    $stmt->execute();
    $total = (int) $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    $paginas = max(1, (int) ceil($total / POR_PAGINA));
    $pagina = min($pagina, $paginas);
    $offset = ($pagina - 1) * POR_PAGINA;

    $stmt = $conn->prepare("SELECT * FROM $origen$donde ORDER BY m.id DESC LIMIT " . POR_PAGINA . " OFFSET $offset");
    if ($params) {
        $stmt->bind_param($tipos, ...$params);
    }
    $stmt->execute();
    $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return [$filas, $total, $pagina, $paginas];
}

/** Formulario de busqueda (GET) que reemplaza al filtro del navegador. */
function render_busqueda_servidor(string $q, int $total): void
{
    $clave = modulo_de_la_pagina();
    $filtros = filtros_columnas($clave);
    $activa = $q !== '' || $filtros !== [];
    ?>
      <form class="search-bar" method="GET">
        <input type="search" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Buscar en el módulo..." maxlength="100">
        <button type="submit">Buscar</button>
        <?php if ($activa): ?>
          <a href="?" class="btn-edit">Quitar filtro</a>
        <?php endif; ?>
        <span class="search-count"><?= $total ?> registro<?= $total === 1 ? '' : 's' ?><?= $activa ? ' encontrado' . ($total === 1 ? '' : 's') : '' ?></span>
        <?php if ($clave !== null): ?>
          <details class="filtros-columnas"<?= $filtros ? ' open' : '' ?>>
            <summary>Filtrar por columna<?= $filtros ? ' (' . count($filtros) . ' activo' . (count($filtros) === 1 ? '' : 's') . ')' : '' ?></summary>
            <div class="filtros-grid">
              <?php foreach (modulo_def($clave)['columnas'] as $col => $etiqueta): ?>
                <label><?= htmlspecialchars($etiqueta) ?>
                  <input type="search" name="f_<?= htmlspecialchars($col) ?>" value="<?= htmlspecialchars($filtros[$col] ?? '') ?>" maxlength="100" placeholder="Contiene…">
                </label>
              <?php endforeach; ?>
            </div>
            <p class="filtros-ayuda">Los filtros se combinan: se muestran solo los registros que cumplan todos. Pulsa «Buscar» para aplicarlos.</p>
          </details>
        <?php endif; ?>
      </form>
    <?php
}

/** Navegacion entre paginas (anterior, numeros cercanos, siguiente) conservando la busqueda. */
function render_paginacion(int $pagina, int $paginas, int $total, string $q): void
{
    if ($paginas <= 1) {
        return;
    }
    $filtros = filtros_columnas(modulo_de_la_pagina());
    $url = function (int $n) use ($q, $filtros) {
        $params = ['q' => $q];
        foreach ($filtros as $col => $texto) {
            $params['f_' . $col] = $texto;
        }
        $params['pagina'] = $n > 1 ? $n : null;
        return '?' . http_build_query(array_filter($params, fn($v) => $v !== null && $v !== ''));
    };
    echo '<div class="pagination">';
    if ($pagina > 1) {
        echo '<a href="' . htmlspecialchars($url($pagina - 1)) . '">&laquo; Anterior</a>';
    }
    $desde = max(1, $pagina - 2);
    $hasta = min($paginas, $pagina + 2);
    if ($desde > 1) {
        echo '<a href="' . htmlspecialchars($url(1)) . '">1</a>' . ($desde > 2 ? '<span>…</span>' : '');
    }
    for ($i = $desde; $i <= $hasta; $i++) {
        echo $i === $pagina ? '<span class="actual">' . $i . '</span>' : '<a href="' . htmlspecialchars($url($i)) . '">' . $i . '</a>';
    }
    if ($hasta < $paginas) {
        echo ($hasta < $paginas - 1 ? '<span>…</span>' : '') . '<a href="' . htmlspecialchars($url($paginas)) . '">' . $paginas . '</a>';
    }
    if ($pagina < $paginas) {
        echo '<a href="' . htmlspecialchars($url($pagina + 1)) . '">Siguiente &raquo;</a>';
    }
    echo '<span class="info">Página ' . $pagina . ' de ' . $paginas . ' (' . $total . ' registros)</span>';
    echo '</div>';
}
