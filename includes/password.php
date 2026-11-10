<?php
/**
 * Politica de contrasenas.
 *
 * Toda contrasena nueva (al crear una cuenta, al recuperarla por correo o por pregunta de
 * seguridad y al restablecerla un administrador) debe tener entre 8 y 64 caracteres, con
 * mayuscula, minuscula, numero y simbolo, no ser una clave muy comun ni contener el nombre
 * de usuario del correo. La regla se valida siempre en el servidor; el medidor de
 * js/password.js solo orienta a la persona mientras escribe. Las contrasenas ya guardadas no
 * se tocan: la regla aplica cuando se crea o cambia una.
 */

const PASSWORD_MIN = 8;
const PASSWORD_MAX = 64;

/** Reglas de composicion: clave => [texto para la lista, expresion que debe cumplirse]. */
function reglas_password(): array
{
    return [
        'largo'   => ['Entre ' . PASSWORD_MIN . ' y ' . PASSWORD_MAX . ' caracteres', '/^.{' . PASSWORD_MIN . ',' . PASSWORD_MAX . '}$/su'],
        'mayus'   => ['Una letra mayúscula', '/\p{Lu}/u'],
        'minus'   => ['Una letra minúscula', '/\p{Ll}/u'],
        'numero'  => ['Un número', '/\d/'],
        'simbolo' => ['Un símbolo (por ejemplo ! ? - _ . @ #)', '/[^\p{L}\d]/u'],
    ];
}

/** Devuelve '' si la contrasena es aceptable, o el mensaje de error para la persona. */
function validar_password(string $password, string $correo = ''): string
{
    $faltan = [];
    foreach (reglas_password() as $clave => [$texto, $regex]) {
        if (!preg_match($regex, $password)) {
            $faltan[$clave] = mb_strtolower($texto);
        }
    }
    if (isset($faltan['largo'])) {
        return 'La contraseña debe tener entre ' . PASSWORD_MIN . ' y ' . PASSWORD_MAX . ' caracteres, con una mayúscula, una minúscula, un número y un símbolo.';
    }
    if ($faltan) {
        return 'La contraseña es muy débil. Le falta: ' . implode(', ', $faltan) . '.';
    }
    $comunes = ['password', 'contrasena', 'contraseña', 'qwerty', 'abc123', '12345678', 'admin123', 'bienvenido', 'sirad', 'pombo'];
    $minuscula = mb_strtolower($password);
    foreach ($comunes as $c) {
        if (str_contains($minuscula, $c)) {
            return 'La contraseña es muy común o predecible. Elija otra que no incluya palabras como «' . $c . '».';
        }
    }
    $usuario = mb_strtolower(strstr($correo, '@', true) ?: '');
    if (mb_strlen($usuario) >= 4 && str_contains($minuscula, $usuario)) {
        return 'La contraseña no puede contener el nombre de usuario de su correo.';
    }
    return '';
}

/** Lista de requisitos y medidor de nivel (Debil, Media, Fuerte) para un campo de contrasena; js/password.js los muestra al hacer clic en el campo. */
function render_requisitos_password(): void
{
    ?>
            <div class="pw-meter" data-pw-meter hidden>
                <div class="pw-bar"><span></span></div>
                <p class="pw-level">Nivel de seguridad: <strong>—</strong></p>
            </div>
            <ul class="pw-rules" data-pw-rules hidden>
                <?php foreach (reglas_password() as $clave => [$texto, $regex]): ?>
                    <li data-regla="<?= htmlspecialchars($clave) ?>" data-regex="<?= htmlspecialchars(trim(substr($regex, 1, strrpos($regex, '/') - 1))) ?>"><?= htmlspecialchars($texto) ?></li>
                <?php endforeach; ?>
            </ul>
    <?php
}
