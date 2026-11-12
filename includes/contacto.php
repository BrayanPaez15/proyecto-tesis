<?php
/**
 * Datos de contacto con el administrador, para los avisos de rechazo, de cuenta eliminada y de limite de solicitudes.
 *
 * El medio principal es el enlace de WhatsApp (CONTACTO_WHATSAPP). CONTACTO_CORREO y CONTACTO_TELEFONO
 * (config.php o config.local.php) se agregan si estan definidos. Si no hay WhatsApp ni correo configurado
 * se usa el correo del administrador aprobado mas antiguo.
 */

function texto_contacto(mysqli $conn): string
{
    $whatsapp = trim((string) CONTACTO_WHATSAPP);
    $correo = trim((string) CONTACTO_CORREO);
    if ($correo === '' && $whatsapp === '') {
        $fila = $conn->query("SELECT correo FROM usuarios WHERE rol = 'admin' AND estado = 'aprobado' ORDER BY id_usuario LIMIT 1")->fetch_row();
        $correo = (string) ($fila[0] ?? '');
    }
    $partes = [];
    if ($whatsapp !== '') {
        $partes[] = 'WhatsApp ' . $whatsapp;
    }
    if ($correo !== '') {
        $partes[] = 'correo ' . $correo;
    }
    if (trim((string) CONTACTO_TELEFONO) !== '') {
        $partes[] = 'teléfono ' . trim((string) CONTACTO_TELEFONO);
    }
    return $partes ? implode(' · ', $partes) : '';
}

/** Frase lista para agregar a un aviso: « Comunícate con el administrador por este medio: WhatsApp https://wa.me/... » (o solo la invitación si no hay datos). */
function frase_contacto(mysqli $conn): string
{
    $c = texto_contacto($conn);
    return $c === '' ? ' Comunícate con el administrador.' : ' Comunícate con el administrador por este medio: ' . $c;
}

/** Escapa el texto y vuelve clicables los enlaces http(s) que contenga (se abren en otra pestaña). */
function aviso_con_enlaces(string $texto): string
{
    $seguro = htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
    return preg_replace_callback('~https?://[^\s<]+~i', function ($m) {
        $url = rtrim($m[0], '.,;:)');
        $resto = substr($m[0], strlen($url));
        return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . $url . '</a>' . $resto;
    }, $seguro);
}
