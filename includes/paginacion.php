<?php
/**
 * Busqueda y paginacion en el servidor para las vistas «Ver registros».
 *
 * En lugar de enviar todos los registros del modulo y paginarlos en el navegador,
 * cada pagina trae solo POR_PAGINA filas (las mas recientes primero) y la busqueda
 * se hace en la base de datos sobre las columnas del modulo (includes/modulos.php).
 * Asi el tamano de la pagina y el tiempo de respuesta no dependen de cuantos
 * registros haya. Los parametros GET son «q» (texto) y «pagina».
 */

const POR_PAGINA = 50;

function termino_busqueda(): string
{
    return mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
}

function pagina_solicitada(): int
{
    return max(1, (int) ($_GET['pagina'] ?? 1));
}

/**
 * Devuelve [filas, total, pagina, paginas] del modulo indicado (clave de includes/modulos.php),
 * filtrando por $q en todas sus columnas. La tabla y las columnas salen del catalogo de modulos,
 * nunca de la peticion.
 */
function consulta_paginada(mysqli $conn, string $clave, string $q, int $pagina): array
{
    $modulos = require __DIR__ . '/modulos.php';
    if (!isset($modulos[$clave])) {
        throw new InvalidArgumentException('Modulo no valido');
    }
    $tabla = $modulos[$clave]['tabla'];
    $columnas = array_keys($modulos[$clave]['columnas']);

    $donde = '';
    $tipos = '';
    $params = [];
    if ($q !== '') {
        $like = '%' . addcslashes($q, '%_\\') . '%';
        $condiciones = [];
        foreach ($columnas as $c) {
            $condiciones[] = "`$c` LIKE ?";
            $tipos .= 's';
            $params[] = $like;
        }
        $donde = ' WHERE ' . implode(' OR ', $condiciones);
    }

    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM `$tabla`$donde");
    if ($params) {
        $stmt->bind_param($tipos, ...$params);
    }
    $stmt->execute();
    $total = (int) $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    $paginas = max(1, (int) ceil($total / POR_PAGINA));
    $pagina = min($pagina, $paginas);
    $offset = ($pagina - 1) * POR_PAGINA;

    $stmt = $conn->prepare("SELECT * FROM `$tabla`$donde ORDER BY id DESC LIMIT " . POR_PAGINA . " OFFSET $offset");
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
    ?>
      <form class="search-bar" method="GET">
        <input type="search" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Buscar en el módulo..." maxlength="100">
        <button type="submit">Buscar</button>
        <?php if ($q !== ''): ?>
          <a href="?" class="btn-edit">Quitar filtro</a>
        <?php endif; ?>
        <span class="search-count"><?= $total ?> registro<?= $total === 1 ? '' : 's' ?><?= $q !== '' ? ' encontrado' . ($total === 1 ? '' : 's') : '' ?></span>
      </form>
    <?php
}

/** Navegacion entre paginas (anterior, numeros cercanos, siguiente) conservando la busqueda. */
function render_paginacion(int $pagina, int $paginas, int $total, string $q): void
{
    if ($paginas <= 1) {
        return;
    }
    $url = function (int $n) use ($q) {
        return '?' . http_build_query(array_filter(['q' => $q, 'pagina' => $n > 1 ? $n : null], fn($v) => $v !== null && $v !== ''));
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
