<?php
/**
 * Politica de tratamiento de datos personales.
 *
 * Pagina publica (no requiere sesion) que registro.php enlaza y que la
 * persona debe aceptar para crear su cuenta. La version vigente esta en
 * includes/politica.php.
 */
require_once "config.php";
require_once "includes/politica.php";
require_once "includes/sesion.php";
require_once "includes/contacto.php";
cabeceras_seguridad();

// Medio de contacto del administrador, tomado de la configuracion (sin consultar la base de datos).
$medios = [];
if (trim((string) CONTACTO_WHATSAPP) !== '') {
  $medios[] = 'WhatsApp ' . trim((string) CONTACTO_WHATSAPP);
}
if (trim((string) CONTACTO_CORREO) !== '') {
  $medios[] = 'correo ' . trim((string) CONTACTO_CORREO);
}
if (trim((string) CONTACTO_TELEFONO) !== '') {
  $medios[] = 'teléfono ' . trim((string) CONTACTO_TELEFONO);
}
$contacto = $medios ? aviso_con_enlaces(implode(' · ', $medios)) : 'el administrador del sistema';
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Política de tratamiento de datos - SIRAD</title>
  <link rel="stylesheet" href="css/styles.css?v=<?= @filemtime(__DIR__ . '/css/styles.css') ?>">
</head>

<body>
  <main class="politica">
    <h1>Política de tratamiento de datos personales</h1>
    <p class="auth-subtitle">SIRAD – Sistema de Registro y Administración de Directorios. Versión <?= htmlspecialchars(POLITICA_VERSION) ?>.</p>

    <h2>1. Responsable</h2>
    <p><?= POLITICA_RESPONSABLE !== '' ? htmlspecialchars(POLITICA_RESPONSABLE) . ', que utiliza SIRAD,' : 'La Fundación que utiliza SIRAD' ?> es la responsable del tratamiento de los datos personales que se registran en el sistema. Para cualquier solicitud relacionada con esta política, comunícate con el administrador del sistema por este medio: <?= $contacto ?>.</p>

    <h2>2. Datos que se tratan</h2>
    <p><strong>Usuarios del sistema:</strong> nombre, correo electrónico, contraseña y respuesta de seguridad (ambas se guardan con un resumen irreversible, llamado hash, que no permite leerlas), rol, estado de la cuenta, motivo de la solicitud de cuenta y acceso solicitado, dirección IP desde la que se envió la solicitud (solo para limitar las solicitudes repetidas), fecha del último acceso, versión y fecha de la política aceptada y, si la persona se retira, el motivo y la fecha de la baja.</p>
    <p><strong>Directorio:</strong> datos de contacto de instituciones educativas, practicantes, artistas, talleristas, editoriales, cajas de compensación, empresas de mercadeo, proveedores, medios de comunicación, equipo interno y directivos, como nombre, teléfono, correo, dirección, cargo y disponibilidad horaria. SIRAD no solicita datos sensibles; no los escribas en los campos de texto libre (perfil, contacto, agenda ni motivo de la solicitud).</p>

    <h2>3. Finalidades</h2>
    <p>Los datos se usan para administrar el acceso al sistema, mantener el directorio de la Fundación, contactar a las personas y organizaciones registradas para las actividades de la Fundación, y mantener la seguridad y la trazabilidad de los cambios (quién registró o modificó cada dato y cuándo).</p>

    <h2>4. Autorización</h2>
    <p>Al marcar la casilla de aceptación en el registro, autorizas de forma libre, previa e informada el tratamiento de tus datos para las finalidades descritas. La autorización es voluntaria: si no la das, no puedes crear la cuenta. La fecha y la versión de esta política que aceptaste quedan registradas.</p>

    <h2>5. Derechos del titular</h2>
    <p>De acuerdo con la Ley 1581 de 2012, puedes conocer, actualizar y rectificar tus datos; solicitar prueba de la autorización otorgada; ser informado del uso que se les da; presentar quejas ante la Superintendencia de Industria y Comercio; y revocar la autorización o solicitar la supresión de tus datos cuando no exista un deber legal o contractual de conservarlos.</p>

    <h2>6. Consultas y reclamos</h2>
    <p>Puedes presentar una consulta o un reclamo al administrador por el medio indicado en el numeral 1. Indica tu nombre y tu correo, describe los hechos y el dato que quieres consultar, corregir, actualizar o suprimir, y deja una dirección o un correo de respuesta. Según la Ley 1581 de 2012 (arts. 14 y 15), una consulta se atiende en un máximo de diez (10) días hábiles y un reclamo en un máximo de quince (15) días hábiles; si no es posible, se te informará el motivo y la nueva fecha, que no superará cinco (5) días hábiles más para una consulta ni ocho (8) para un reclamo. Si el reclamo está incompleto, se te pedirá completarlo dentro de los cinco (5) días siguientes a recibirlo.</p>

    <h2>7. Baja de la cuenta y conservación</h2>
    <p>Puedes retirarte desde el menú, con la opción «Darme de baja», indicando el motivo. La cuenta queda inactiva y no puedes volver a entrar; no se borra de inmediato: se conservan tu nombre, tu correo, el motivo y la fecha de la baja como trazabilidad, hasta que el administrador la reactive o la elimine. Puedes pedir al administrador que la elimine; al hacerlo se borran los datos de tu cuenta y los registros del directorio que creaste se conservan sin tu nombre asociado.</p>

    <h2>8. Seguridad y almacenamiento</h2>
    <p>El acceso al sistema requiere una cuenta aprobada por un administrador. Las contraseñas se guardan con un resumen irreversible (hash), el acceso a las funciones sensibles se controla por rol y los cambios en los registros quedan asociados a quien los hizo. Los datos no se entregan a terceros para que los usen con fines propios, salvo obligación legal. Se guardan en el servidor donde se instala el sistema; si ese servidor es de un proveedor de alojamiento externo, el proveedor los almacena por cuenta del responsable. La demostración pública del prototipo usa un alojamiento gratuito y solo debe contener datos ficticios.</p>

    <h2>9. Vigencia y cambios</h2>
    <p>Esta versión (<?= htmlspecialchars(POLITICA_VERSION) ?>) rige desde el <?= htmlspecialchars(POLITICA_VIGENCIA) ?>. La versión 1.0 era la inicial; la 1.1 agrega la identificación del responsable y el medio de contacto, el procedimiento de consultas y reclamos, la baja de la cuenta y la conservación de datos, y aclara el almacenamiento y el alojamiento. Las cuentas que aceptaron la versión 1.0 conservan registrada esa aceptación. Si la política se modifica, se publicará una nueva versión y se indicará su número.</p>

    <p class="auth-link"><a href="registro.php">&larr; Volver al registro</a></p>
  </main>
</body>

</html>
