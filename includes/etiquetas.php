<?php
/**
 * Helpers para el sistema de etiquetas.
 *
 * Las etiquetas son transversales a los once modulos: registro_etiquetas
 * guarda (modulo, registro_id, etiqueta_id) en vez de una llave foranea
 * directa a cada tabla de dominio, ya que "registro_id" puede apuntar a
 * cualquiera de las once tablas segun el valor de "modulo".
 */

/** Etiquetas asignadas a cada registro de un modulo, indexadas por id (solo los ids dados, si se indican). */
function etiquetas_por_registro(mysqli $conn, string $modulo, ?array $ids = null): array
{
    if ($ids !== null && !$ids) {
        return [];
    }
    $filtro = $ids !== null ? ' AND re.registro_id IN (' . implode(',', array_map('intval', $ids)) . ')' : '';
    $stmt = $conn->prepare(
        "SELECT re.registro_id, e.id, e.nombre, e.color
         FROM registro_etiquetas re
         JOIN etiquetas e ON e.id = re.etiqueta_id
         WHERE re.modulo = ?$filtro
         ORDER BY e.nombre"
    );
    $stmt->bind_param("s", $modulo);
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
