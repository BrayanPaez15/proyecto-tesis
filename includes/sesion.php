<?php
/**
 * Sesion y cabeceras de seguridad.
 *
 * iniciar_sesion_segura() reemplaza a session_start(): envia las cabeceras de
 * seguridad, configura la cookie de sesion (HttpOnly, SameSite=Lax y Secure si
 * se usa HTTPS), rechaza identificadores de sesion que el servidor no emitio
 * (use_strict_mode) y arranca la sesion. cabeceras_seguridad() se llama sola
 * desde ahi y tambien desde las paginas publicas que no abren sesion.
 * Los scripts (login, registro, recuperar, logout y auth.php) deben usar estas
 * funciones y no session_start() directamente.
 */

function cabeceras_seguridad(): void
{
    if (headers_sent()) {
        return;
    }
    header_remove('X-Powered-By');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self' data:; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'");
}

/** Evita que el navegador o un proxy guarde paginas con datos (por ejemplo, al volver atras tras cerrar sesion). */
function sin_cache(): void
{
    if (!headers_sent()) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }
}

function iniciar_sesion_segura(): void
{
    cabeceras_seguridad();
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/** Cambia el identificador de sesion tras iniciar sesion (evita la fijacion de sesion) y renueva el token CSRF. */
function renovar_sesion(): void
{
    session_regenerate_id(true);
    unset($_SESSION['csrf_token']);
}
