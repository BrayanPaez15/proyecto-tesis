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

/** Columnas con valor unico por tabla (deben coincidir con las llaves UNIQUE de db/schema.sql). */
function columnas_unicas(string $tabla): array
{
    $unicas = [
        'artistas' => ['correo'], 'asocajas' => ['correo'], 'directivos' => ['correo', 'cedula'],
        'editoriales' => ['correo', 'nit'], 'instituciones_e' => ['correo', 'nit'], 'medios' => ['correo'],
        'mercadeo' => ['correo'], 'practicantes' => ['correo'], 'proveedores' => ['correo'],
        'talleristas' => ['correo'], 'team_pombo' => ['correo'],
    ];
    return $unicas[$tabla] ?? [];
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

/** Mensaje de error si algun valor unico de $_POST ya existe en otro registro de la tabla; '' si no hay problema. */
function error_duplicado(mysqli $conn, string $tabla, int $excluirId = 0): string
{
    foreach (columnas_unicas($tabla) as $columna) {
        $valor = $_POST[$columna] ?? null;
        if ($valor === null || $valor === '') {
            continue;
        }
        $stmt = $conn->prepare("SELECT id FROM `$tabla` WHERE `$columna` = ? AND id <> ? LIMIT 1");
        $stmt->bind_param("si", $valor, $excluirId);
        $stmt->execute();
        $existe = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        if ($existe) {
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
