<?php
/**
 * Politica de tratamiento de datos personales.
 *
 * Pagina publica (no requiere sesion) que registro.php enlaza y que la
 * persona debe aceptar para crear su cuenta. La version vigente esta en
 * includes/politica.php.
 */
require_once "includes/politica.php";
require_once "includes/sesion.php";
cabeceras_seguridad();
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <title>Política de tratamiento de datos - SIRAD</title>
  <link rel="stylesheet" href="css/styles.css">
</head>

<body>
  <main class="politica">
    <h1>Política de tratamiento de datos personales</h1>
    <p class="auth-subtitle">SIRAD – Sistema de Registro y Administración de Directorios. Versión <?= htmlspecialchars(POLITICA_VERSION) ?>.</p>

    <h2>1. Responsable</h2>
    <p>La Fundación que utiliza SIRAD es la responsable del tratamiento de los datos personales que se registran en el sistema. Para cualquier solicitud relacionada con esta política, comunícate con el administrador del sistema.</p>

    <h2>2. Datos que se tratan</h2>
    <p><strong>Usuarios del sistema:</strong> nombre, correo electrónico, contraseña y respuesta de seguridad (ambas almacenadas cifradas), rol, estado de la cuenta y fecha de aceptación de esta política.</p>
    <p><strong>Directorio:</strong> datos de contacto de instituciones educativas, practicantes, artistas, talleristas, editoriales, cajas de compensación, empresas de mercadeo, proveedores, medios de comunicación, equipo interno y directivos, como nombre, teléfono, correo, dirección, cargo y disponibilidad horaria.</p>

    <h2>3. Finalidades</h2>
    <p>Los datos se usan para administrar el acceso al sistema, mantener el directorio de la Fundación, contactar a las personas y organizaciones registradas para las actividades de la Fundación, y mantener la seguridad y la trazabilidad de los cambios (quién registró o modificó cada dato y cuándo).</p>

    <h2>4. Autorización</h2>
    <p>Al marcar la casilla de aceptación en el registro, autorizas de forma libre, previa e informada el tratamiento de tus datos para las finalidades descritas. La autorización es voluntaria: si no la das, no puedes crear la cuenta. La fecha y la versión de esta política que aceptaste quedan registradas.</p>

    <h2>5. Derechos del titular</h2>
    <p>De acuerdo con la Ley 1581 de 2012, puedes conocer, actualizar y rectificar tus datos; solicitar prueba de la autorización otorgada; ser informado del uso que se les da; presentar quejas ante la Superintendencia de Industria y Comercio; y revocar la autorización o solicitar la supresión de tus datos cuando no exista un deber legal o contractual de conservarlos.</p>

    <h2>6. Seguridad</h2>
    <p>El acceso al sistema requiere una cuenta aprobada por un administrador. Las contraseñas se almacenan cifradas, el acceso a las funciones sensibles se controla por rol y los cambios en los registros quedan asociados a quien los hizo. Los datos no se transfieren a terceros, salvo obligación legal.</p>

    <h2>7. Vigencia y cambios</h2>
    <p>Esta política rige desde su publicación. Si se modifica, se publicará una nueva versión y se indicará su número.</p>

    <p class="auth-link"><a href="registro.php">&larr; Volver al registro</a></p>
  </main>
</body>

</html>
