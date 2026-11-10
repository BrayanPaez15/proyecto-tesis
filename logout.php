<?php
/**
 * Cierre de sesion.
 *
 * Vacia y destruye la sesion activa, borra la cookie de sesion y redirige a
 * login.php.
 */
require_once __DIR__ . '/includes/sesion.php';
iniciar_sesion_segura();
sin_cache();
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $c = session_get_cookie_params();
    setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $c['path'], 'secure' => $c['secure'], 'httponly' => true, 'samesite' => 'Lax']);
}
session_destroy();
header("Location: login.php");
exit;
