<?php
/**
 * Prevencion de registros duplicados en los modulos.
 *
 * Dentro de cada modulo no puede haber dos registros con el mismo correo; ademas, no
 * puede repetirse el NIT en Instituciones Educativas y Editoriales ni la cedula en
 * Directivos. La regla esta en la base de datos (llaves UNIQUE, que tambien protegen
 * contra dos peticiones simultaneas) y se revisa antes de guardar para dar un mensaje
 * claro. Un correo, NIT o cedula vacio se guarda como NULL (varios NULL no cuentan como
 * duplicados). Dos modulos distintos si pueden tener el mismo correo.
 */

/**
 * Campos con valor unico dentro de un modulo: los que el modulo tenga entre correo, nit y cedula.
 * Las llaves UNIQUE de db/schema.sql (contactos por tipo de actor y detalle_directivo.cedula) lo hacen cumplir.
 */
function columnas_unicas(string $modulo): array
{
    return array_values(array_intersect(['correo', 'nit', 'cedula'], array_keys(modulo_def($modulo)['mapa'])));
}

/** true si otro registro del modulo (distinto de $excluirId) ya tiene ese valor en el campo. */
function existe_valor_unico(mysqli $conn, string $modulo, string $campo, string $valor, int $excluirId = 0): bool
{
    $stmt = contactos_preparar($conn, 'SELECT m.id FROM ' . origen_modulo($modulo) . " WHERE m.`$campo` = ? AND m.id <> ? LIMIT 1");
    $stmt->execute([$valor, $excluirId]);
    return $stmt->get_result()->num_rows > 0;
}

/** Texto con articulo para los mensajes: «este correo», «este NIT», «esta cédula». */
function etiqueta_unica(string $columna): string
{
    return ['correo' => 'este correo', 'nit' => 'este NIT', 'cedula' => 'esta cédula'][$columna] ?? 'este dato';
}

/** Recorta los campos unicos de $_POST y convierte los vacios en NULL, para que no choquen entre si. */
function normalizar_unicos(): void
{
    foreach (['correo', 'nit', 'cedula'] as $campo) {
        if (array_key_exists($campo, $_POST)) {
            $valor = trim((string) $_POST[$campo]);
            $_POST[$campo] = $valor === '' ? null : $valor;
        }
    }
}

/** Mensaje de error si algun valor unico de $_POST ya existe en otro registro del modulo; '' si no hay problema. */
function error_duplicado(mysqli $conn, string $modulo, int $excluirId = 0): string
{
    foreach (columnas_unicas($modulo) as $columna) {
        $valor = $_POST[$columna] ?? null;
        if ($valor === null || $valor === '') {
            continue;
        }
        if (existe_valor_unico($conn, $modulo, $columna, (string) $valor, $excluirId)) {
            return ($excluirId > 0 ? "Ya existe otro registro con " : "Ya existe un registro con ") . etiqueta_unica($columna) . " en este módulo.";
        }
    }
    return '';
}

/** Si la excepcion es una violacion de llave unica (1062), devuelve el mensaje para el usuario; si no, null. */
function mensaje_duplicado(Throwable $e): ?string
{
    if ((int) $e->getCode() !== 1062) {
        return null;
    }
    foreach (['correo', 'nit', 'cedula'] as $columna) {
        if (stripos($e->getMessage(), $columna) !== false) {
            return "Ya existe un registro con " . etiqueta_unica($columna) . " en este módulo.";
        }
    }
    return "Ya existe un registro con esos datos en este módulo.";
}
