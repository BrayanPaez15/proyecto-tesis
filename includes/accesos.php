<?php
/**
 * Rol (tipo de acceso) que la persona pide al solicitar su cuenta.
 *
 * Es solo una peticion: el administrador ve lo que pidio junto al motivo y decide los permisos reales por
 * modulo (usuario_editar.php). Nunca se puede pedir el rol de administrador. Se guarda en
 * usuarios.acceso_solicitado ('ver', 'registrar' o 'ver_registrar').
 */

/** Opciones del formulario de registro: clave => texto. */
function accesos_solicitables(): array
{
    return [
        'ver' => 'Ver (consultar registros)',
        'registrar' => 'Registrar (agregar registros)',
        'ver_registrar' => 'Ver y registrar',
    ];
}

/** Texto de un acceso solicitado, o '' si no hay (cuentas anteriores al campo) o no es valido. */
function acceso_texto(?string $clave): string
{
    return accesos_solicitables()[(string) $clave] ?? '';
}
