<?php
/**
 * Helpers para el sistema de etiquetas.
 *
 * Las etiquetas son transversales a los once modulos: contacto_etiquetas une un
 * contacto (id_contacto) con una etiqueta (id_etiqueta), con llaves foraneas a las
 * dos tablas; como todos los actores viven en `contactos`, no hace falta indicar el modulo.
 */
require_once __DIR__ . '/contactos.php';

/** Etiquetas asignadas a cada registro de un modulo, indexadas por id (solo los ids dados, si se indican). */
function etiquetas_por_registro(mysqli $conn, string $modulo, ?array $ids = null): array
{
    if ($ids !== null && !$ids) {
        return [];
    }
    $filtro = $ids !== null
        ? 'ce.id_contacto IN (' . implode(',', array_map('intval', $ids)) . ')'
        : 'ce.id_contacto IN (SELECT c.id_contacto FROM ' . modulo_from($modulo) . ')';
    $stmt = $conn->prepare(
        "SELECT ce.id_contacto AS registro_id, e.id_etiqueta AS id, e.nombre, e.color
         FROM contacto_etiquetas ce
         JOIN etiquetas e ON e.id_etiqueta = ce.id_etiqueta
         WHERE $filtro
         ORDER BY e.nombre"
    );
    $stmt->execute();
    $resultado = $stmt->get_result();

    $porRegistro = [];
    while ($fila = $resultado->fetch_assoc()) {
        $porRegistro[(int) $fila['registro_id']][] = $fila;
    }
    $stmt->close();
    return $porRegistro;
}

/** Imprime las etiquetas de un registro como pastillas de color. */
function render_tags(array $etiquetas): void
{
    foreach ($etiquetas as $etiqueta) {
        echo '<span class="tag" style="background:' . htmlspecialchars($etiqueta['color']) . '">'
            . htmlspecialchars($etiqueta['nombre']) . '</span>';
    }
}
