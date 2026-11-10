<?php
/**
 * Configuracion central del sistema.
 *
 * Credenciales de conexion a MySQL y manejo de errores: en vez de
 * mostrar errores de PHP en pantalla, se registran en logs/php-error.log.
 */
// Ajustes propios de cada instalacion (credenciales de correo, URL publica): config.local.php,
// que no se sube al repositorio. Lo que defina alli tiene prioridad sobre los valores de abajo.
if (is_file(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'directorio');

// Correo (recuperacion de contrasena). MAIL_MODO 'archivo' guarda los mensajes en logs/correos/
// (desarrollo); 'smtp' los envia por el servidor indicado (produccion). RECUPERAR_POR_CORREO: si es
// true, la contrasena se recupera con un enlace enviado al correo (recomendado); si es false se usa
// la pregunta de seguridad (para instalaciones sin servidor de correo). Por defecto sigue a MAIL_MODO.
// APP_URL es la direccion base de los enlaces del correo.
defined('APP_URL') || define('APP_URL', 'http://localhost/proyecto');
defined('MAIL_MODO') || define('MAIL_MODO', 'archivo');
defined('MAIL_HOST') || define('MAIL_HOST', '');
defined('MAIL_PUERTO') || define('MAIL_PUERTO', 587);
defined('MAIL_SEGURIDAD') || define('MAIL_SEGURIDAD', 'tls');
defined('MAIL_USUARIO') || define('MAIL_USUARIO', '');
defined('MAIL_CLAVE') || define('MAIL_CLAVE', '');
defined('RECUPERAR_POR_CORREO') || define('RECUPERAR_POR_CORREO', MAIL_MODO === 'smtp');
defined('MAIL_REMITENTE') || define('MAIL_REMITENTE', 'no-responder@sirad.local');
defined('MAIL_NOMBRE_REMITENTE') || define('MAIL_NOMBRE_REMITENTE', 'SIRAD');

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/logs/php-error.log');
