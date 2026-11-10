<?php
/**
 * Envio de correo.
 *
 * enviar_correo() manda un mensaje de texto plano segun MAIL_MODO (config.php):
 *  - 'smtp': conecta al servidor SMTP configurado (MAIL_HOST, MAIL_PUERTO, MAIL_SEGURIDAD
 *    'tls' = STARTTLS, 'ssl' o 'ninguna', y MAIL_USUARIO/MAIL_CLAVE para autenticarse).
 *    Es el modo de produccion; verifica el certificado del servidor.
 *  - 'archivo': no envia nada; guarda el mensaje en logs/correos/ (carpeta protegida de
 *    la web). Es el modo de desarrollo, sin servidor de correo.
 * Devuelve true si el mensaje se envio o se guardo. Los fallos se registran en el log de
 * errores sin incluir la clave. Las credenciales reales van en config.local.php, que no
 * se sube al repositorio.
 */

function correo_valido(string $direccion): bool
{
    return $direccion !== '' && strpbrk($direccion, "\r\n") === false && filter_var($direccion, FILTER_VALIDATE_EMAIL) !== false;
}

function enviar_correo(string $para, string $asunto, string $texto): bool
{
    if (!correo_valido($para) || strpbrk($asunto, "\r\n") !== false) {
        return false;
    }
    $mensaje = construir_mensaje($para, $asunto, $texto);
    return MAIL_MODO === 'smtp' ? enviar_smtp($para, $mensaje) : guardar_correo($para, $mensaje);
}

function construir_mensaje(string $para, string $asunto, string $texto): string
{
    $remitente = MAIL_REMITENTE;
    $nombre = '=?UTF-8?B?' . base64_encode(MAIL_NOMBRE_REMITENTE) . '?=';
    $cabeceras = [
        "Date: " . date('r'),
        "From: $nombre <$remitente>",
        "To: <$para>",
        "Subject: =?UTF-8?B?" . base64_encode($asunto) . "?=",
        "Message-ID: <" . bin2hex(random_bytes(12)) . "@" . (parse_url(APP_URL, PHP_URL_HOST) ?: 'sirad.local') . ">",
        "MIME-Version: 1.0",
        "Content-Type: text/plain; charset=UTF-8",
        "Content-Transfer-Encoding: base64",
    ];
    return implode("\r\n", $cabeceras) . "\r\n\r\n" . chunk_split(base64_encode($texto), 76, "\r\n");
}

function guardar_correo(string $para, string $mensaje): bool
{
    $dir = __DIR__ . '/../logs/correos';
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
        error_log('correo: no se pudo crear logs/correos');
        return false;
    }
    $archivo = $dir . '/' . date('Y-m-d_His') . '_' . bin2hex(random_bytes(3)) . '.eml';
    return file_put_contents($archivo, $mensaje) !== false;
}

/** Lee una respuesta SMTP (puede tener varias lineas «250-...») y devuelve [codigo, texto]. */
function smtp_leer($socket): array
{
    $texto = '';
    while (($linea = fgets($socket, 1024)) !== false) {
        $texto .= $linea;
        if (strlen($linea) < 4 || $linea[3] !== '-') {
            break;
        }
    }
    return [(int) substr($texto, 0, 3), $texto];
}

function smtp_cmd($socket, string $cmd, array $esperados): bool
{
    fwrite($socket, $cmd . "\r\n");
    [$codigo] = smtp_leer($socket);
    return in_array($codigo, $esperados, true);
}

function enviar_smtp(string $para, string $mensaje): bool
{
    $destino = (MAIL_SEGURIDAD === 'ssl' ? 'ssl://' : 'tcp://') . MAIL_HOST . ':' . MAIL_PUERTO;
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
    $socket = @stream_socket_client($destino, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$socket) {
        error_log("correo: no se pudo conectar a " . MAIL_HOST . ":" . MAIL_PUERTO . " ($errstr)");
        return false;
    }
    stream_set_timeout($socket, 15);
    $ok = false;
    try {
        [$codigo] = smtp_leer($socket);
        $host = parse_url(APP_URL, PHP_URL_HOST) ?: 'localhost';
        if ($codigo !== 220 || !smtp_cmd($socket, "EHLO $host", [250])) {
            throw new RuntimeException('saludo SMTP rechazado');
        }
        if (MAIL_SEGURIDAD === 'tls') {
            if (!smtp_cmd($socket, 'STARTTLS', [220]) || !stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('no se pudo iniciar TLS');
            }
            if (!smtp_cmd($socket, "EHLO $host", [250])) {
                throw new RuntimeException('EHLO tras TLS rechazado');
            }
        }
        if (MAIL_USUARIO !== '') {
            if (!smtp_cmd($socket, 'AUTH LOGIN', [334]) || !smtp_cmd($socket, base64_encode(MAIL_USUARIO), [334]) || !smtp_cmd($socket, base64_encode(MAIL_CLAVE), [235])) {
                throw new RuntimeException('autenticacion SMTP rechazada');
            }
        }
        if (!smtp_cmd($socket, 'MAIL FROM:<' . MAIL_REMITENTE . '>', [250]) || !smtp_cmd($socket, "RCPT TO:<$para>", [250, 251]) || !smtp_cmd($socket, 'DATA', [354])) {
            throw new RuntimeException('sobre SMTP rechazado');
        }
        $cuerpo = preg_replace('/^\./m', '..', $mensaje);
        fwrite($socket, $cuerpo . "\r\n.\r\n");
        [$codigo] = smtp_leer($socket);
        $ok = $codigo === 250;
        smtp_cmd($socket, 'QUIT', [221]);
    } catch (Throwable $e) {
        error_log('correo: ' . $e->getMessage());
    }
    fclose($socket);
    return $ok;
}
