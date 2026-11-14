<?php
/**
 * Conexion a la base de datos.
 *
 * Abre la conexion mysqli a la base de datos `directorio` (credenciales
 * en config.php) y fija el charset utf8mb4 para evitar problemas de
 * codificacion con tildes y enies. Incluido por todos los modulos y por
 * las paginas de autenticacion.
 */
require_once __DIR__ . "/config.php";
// Con PHP 8.1 o superior mysqli lanza una excepcion si no puede conectar (clave o servidor mal escritos,
// base sin crear): se captura para mostrar un aviso breve y dejar el motivo en el registro, en vez de un
// error 500 en blanco. El motivo no se muestra en pantalla.
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
} catch (mysqli_sql_exception $e) {
    error_log('SIRAD: no se pudo conectar a la base de datos (' . $e->getCode() . ')');
    http_response_code(503);
    die("Error de conexión");
}
if ($conn->connect_error) {
    die("Error de conexión");
}
$conn->set_charset("utf8mb4");
