<?php
/**
 * Manual de uso dentro de la pagina.
 *
 * render_ayuda() imprime un boton fijo «Manual» en la esquina inferior derecha que abre un
 * panel lateral con el manual de uso. El panel se abre en la seccion que corresponde a la
 * pagina donde esta la persona, se puede buscar dentro de el y se cierra con Esc o con la
 * equis; la pagina sigue usandose mientras esta abierto. Las secciones dependen de quien
 * mira: sin sesion solo las de acceso a la cuenta; con sesion, las de uso, y las de
 * administracion solo para administradores (y la de importar para quien tiene ese permiso).
 * Los textos son los mismos del manual de usuario en Word. Estilos en css/styles.css y
 * comportamiento en js/ayuda.js.
 */

/** Seccion del manual que corresponde al archivo actual. */
function ayuda_seccion_actual(): string
{
    $archivo = basename($_SERVER['SCRIPT_NAME'] ?? '', '.php');
    $mapa = [
        'index' => 'panel', 'buscar' => 'buscar', 'importar' => 'importar', 'exportar' => 'exportar',
        'usuarios' => 'usuarios', 'usuario_editar' => 'usuarios', 'alertas' => 'alertas', 'reportes' => 'reportes',
        'etiquetas' => 'etiquetas', 'etiqueta_asignar' => 'etiquetas', 'login' => 'ingresar', 'registro' => 'cuenta',
        'recuperar' => 'recuperar', 'restablecer' => 'recuperar',
    ];
    if (isset($mapa[$archivo])) {
        return $mapa[$archivo];
    }
    if (str_ends_with($archivo, '_registros')) {
        return 'consultar';
    }
    if (str_ends_with($archivo, '_exportar')) {
        return 'exportar';
    }
    return 'registrar';
}

/** Secciones visibles para quien mira: lista de [id, titulo, html]. */
function ayuda_secciones(): array
{
    $sesion = isset($_SESSION['usuario_id']);
    $admin = function_exists('is_admin') && $sesion && is_admin();
    $importa = function_exists('puede_importar') && $sesion && puede_importar();
    $mb = defined('IMPORTAR_MAX_MB') ? (int) IMPORTAR_MAX_MB : 5;
    $filas = defined('IMPORTAR_MAX_FILAS') ? number_format((int) IMPORTAR_MAX_FILAS, 0, ',', '.') : '5.000';
    $porIp = defined('SOLICITUDES_MAX_POR_IP') ? (int) SOLICITUDES_MAX_POR_IP : 3;

    $todas = [];
    $todas['cuenta'] = ['Solicitar una cuenta', "
        <p>Para usar SIRAD necesita una cuenta. Un administrador la revisa y, si la aprueba, le da los permisos que necesita; mientras tanto la cuenta queda <b>pendiente</b> y no puede iniciar sesión.</p>
        <ol>
          <li>En la pantalla de inicio de sesión haga clic en <b>Crear usuario</b>.</li>
          <li>Escriba su <b>nombre</b> y su <b>correo</b>.</li>
          <li>En <b>«¿Quién eres y por qué necesitas acceso?»</b> cuente en una o dos frases quién es, de qué organización y para qué usará el sistema (de 15 a 500 caracteres). El administrador lo lee para decidir si aprueba su cuenta.</li>
          <li>Escriba una <b>contraseña segura</b> (vea «Contraseñas seguras») y elija una <b>pregunta de seguridad</b> con su respuesta: sirve para recuperar la contraseña si la olvida.</li>
          <li>Lea la política de tratamiento de datos personales y marque la casilla de aceptación; sin aceptarla no se crea la cuenta.</li>
          <li>Haga clic en <b>Solicitar cuenta</b>. Verá el aviso «Solicitud enviada» y deberá esperar la aprobación.</li>
        </ol>
        <p class=\"ayuda-nota\">Si el correo ya está registrado, use «Iniciar sesión». Por seguridad se aceptan hasta $porIp solicitudes por hora desde una misma conexión; si ve un aviso de límite o de rechazo, escriba al administrador por el enlace de WhatsApp que aparece en el mismo aviso (también está en el botón verde «WhatsApp», junto al «Manual»).</p>"];

    $todas['ingresar'] = ['Iniciar y cerrar sesión', "
        <ol>
          <li>Escriba su correo y su contraseña y haga clic en <b>Ingresar</b>.</li>
          <li>Si su cuenta está <b>pendiente</b>, el sistema le avisa que debe esperar la aprobación. Si fue <b>rechazada</b>, el aviso incluye el enlace de WhatsApp del administrador.</li>
          <li>Para salir, haga clic en <b>Cerrar sesión</b> (arriba a la derecha) y haga esto siempre que use un equipo compartido.</li>
        </ol>
        <p class=\"ayuda-nota\">Si se equivoca de contraseña 5 veces seguidas, la cuenta se bloquea 15 minutos (o hasta que un administrador la desbloquee). Si no recuerda su contraseña, use «¿Olvidaste tu contraseña? Recuperarla».</p>"];

    $todas['contrasena'] = ['Contraseñas seguras', "
        <p>Cada vez que crea o cambia una contraseña, al pasar el cursor sobre el campo aparece un mensaje con los requisitos y, al hacer clic, una barra con el <b>nivel de seguridad</b> (Débil, Media o Fuerte).</p>
        <ul>
          <li>Entre 8 y 64 caracteres.</li>
          <li>Al menos una mayúscula, una minúscula, un número y un símbolo (por ejemplo ! ? - _ . @ #).</li>
          <li>No puede ser una clave muy común («password», «12345678», «sirad»…) ni contener el nombre de usuario de su correo.</li>
        </ul>
        <p class=\"ayuda-nota\">Una frase larga con letras, números y símbolos, como «Lluvia.Azul-2026», es más segura y más fácil de recordar que una clave corta y complicada. No comparta su contraseña con nadie.</p>"];

    $todas['recuperar'] = ['Olvidé mi contraseña', "
        <p>Puede definir una nueva sin ayuda de un administrador:</p>
        <ol>
          <li>En el inicio de sesión haga clic en <b>Recuperarla</b>.</li>
          <li>Escriba el correo de su cuenta y haga clic en <b>Continuar</b>.</li>
          <li>Responda la <b>pregunta de seguridad</b> que eligió al crear la cuenta y escriba la nueva contraseña dos veces.</li>
          <li>Haga clic en <b>Restablecer contraseña</b> e inicie sesión con la nueva.</li>
        </ol>
        <p class=\"ayuda-nota\">Si el administrador activó el envío de correos, el sistema le enviará un enlace (válido 30 minutos y de un solo uso) en lugar de mostrar la pregunta: ábralo, escriba la nueva contraseña y listo. Cinco respuestas incorrectas bloquean la cuenta 15 minutos.</p>"];

    $todas['panel'] = ['Panel principal', "
        <p>Después de iniciar sesión verá una tarjeta por cada módulo al que tiene acceso, con el número de registros que tiene.</p>
        <ul>
          <li><b>Ingresar</b> abre el formulario para registrar información nueva (solo si tiene el permiso «Registrar»).</li>
          <li><b>Ver registros</b> abre la tabla de lo ya guardado (solo si tiene el permiso «Ver»).</li>
          <li>Si su cuenta está aprobada pero no ve ningún módulo, todavía no tiene permisos: pídaselos a un administrador.</li>
        </ul>
        <p>El menú superior da acceso a <b>Buscar</b>, <b>Importar</b> (si tiene ese permiso) y, para administradores, <b>Usuarios</b>, <b>Etiquetas</b>, <b>Reportes</b> y <b>Alertas</b>. El botón «← Volver al directorio» regresa a este panel.</p>"];

    $todas['registrar'] = ['Registrar información', "
        <p>Todos los módulos funcionan igual; cambian solo los campos del formulario.</p>
        <ol>
          <li>En el panel haga clic en <b>Ingresar</b> en el módulo que necesite.</li>
          <li>Complete los campos (nombre, teléfono, correo y los demás del módulo).</li>
          <li>En Artistas y Talleristas, use <b>+ Agregar horario</b> para indicar la disponibilidad por día y hora (Disponible u Ocupado); los horarios de un mismo día no pueden cruzarse.</li>
          <li>Haga clic en <b>Guardar</b>. Verá el aviso «Registro guardado» o pasará a la lista de registros.</li>
        </ol>
        <p class=\"ayuda-nota\">En cada módulo no puede haber dos registros con el mismo <b>correo</b> (ni el mismo NIT en Instituciones Educativas y Editoriales, ni la misma cédula en Directivos). El mismo correo sí puede estar en módulos distintos, y puede dejar el correo en blanco. Si repite un dato, el aviso le dice cuál y conserva lo que escribió.</p>"];

    $todas['consultar'] = ['Consultar y buscar en un módulo', "
        <ol>
          <li>Haga clic en <b>Ver registros</b> (en la tarjeta del módulo o en la pestaña del módulo).</li>
          <li>La tabla muestra <b>50 registros por página</b>, los más recientes primero. Abajo están <b>Anterior</b>, <b>Siguiente</b> y el total de páginas y de registros.</li>
          <li>Para buscar, escriba en «Buscar en el módulo…» y haga clic en <b>Buscar</b>: se busca en todas las columnas y se indica cuántos registros se encontraron. <b>Quitar filtro</b> vuelve a mostrar todos.</li>
          <li>Los administradores ven además los botones <b>Editar</b> y <b>Borrar</b> de cada fila (el borrado pide confirmación y no se puede deshacer).</li>
        </ol>"];

    $todas['buscar'] = ['Búsqueda global', "
        <p>Si no recuerda en qué módulo está un dato, haga clic en <b>Buscar</b> (menú superior), escriba el nombre u otro dato y haga clic en <b>Buscar</b>. El sistema busca en todos los módulos a los que usted tiene permiso de ver y le muestra los resultados agrupados por módulo, con un enlace a cada uno.</p>"];

    $todas['etiquetas'] = ['Etiquetas', "
        <p>Las etiquetas sirven para agrupar registros (por ejemplo «Prioritario» o «FILBO 2026»).</p>
        <ul>
          <li>Al registrar o editar, en <b>Etiquetas</b> marque las que correspondan al registro.</li>
          <li>Los administradores crean, colorean y eliminan etiquetas desde <b>Etiquetas</b> (menú superior). Eliminar una etiqueta la quita de todos los registros.</li>
        </ul>"];

    if ($admin || $importa) {
        $todas['importar'] = ['Importar desde Excel o CSV', "
            <p>Si ya tiene los datos en una hoja de cálculo, puede cargarlos de una vez.</p>
            <ol>
              <li>Haga clic en <b>Importar</b> (menú superior).</li>
              <li>Descargue la <b>plantilla (Excel)</b> del módulo: es un libro con una columna por cada campo.</li>
              <li>Escriba un registro por fila, desde la fila 2, <b>sin cambiar los encabezados</b> de la fila 1. Guárdelo como Excel (.xlsx) o como CSV.</li>
              <li>Elija el módulo, seleccione el archivo y haga clic en <b>Importar</b>.</li>
            </ol>
            <p class=\"ayuda-nota\">Cada archivo puede pesar hasta <b>$mb MB</b> y tener hasta <b>$filas filas</b>; si lo supera, no se importa nada y debe dividirlo. Cada usuario puede importar hasta <b>" . (defined('IMPORTAR_MAX_POR_HORA') ? (int) IMPORTAR_MAX_POR_HORA : 5) . " archivos por hora</b>. Los registros repetidos (mismo correo, NIT o cédula) se omiten y el resumen le dice cuántos se importaron y cuántos se omitieron. Solo puede importar a módulos donde tiene permiso de registrar.</p>"];
    }

    if ($admin) {
        $todas['exportar'] = ['Descargar a Excel', "
            <p>En el módulo, haga clic en la pestaña <b>Descargar Excel</b>, indique las fechas «Desde» y «Hasta» y haga clic en <b>Descargar Excel</b>. Se genera un archivo con los registros creados en ese rango. Solo los administradores pueden descargar.</p>"];

        $todas['usuarios'] = ['Usuarios y permisos', "
            <p>En <b>Usuarios</b> (menú superior) están todas las cuentas, con las solicitudes pendientes primero.</p>
            <ol>
              <li>Lea el <b>Motivo</b> que la persona escribió bajo su nombre y haga clic en <b>Aprobar</b> o <b>Rechazar</b>. Al aprobar se abre la edición para asignar los permisos.</li>
              <li>En <b>Editar</b>, la matriz de permisos define en qué módulos puede <b>Ver</b> y en cuáles <b>Registrar</b>; los cambios rigen de inmediato, aunque la persona tenga la sesión abierta.</li>
              <li><b>Permitir / Quitar</b> en la columna «Importar» da o retira el permiso de importar.</li>
              <li>Desde «Editar» también puede cambiar nombre, correo, rol y estado, <b>desbloquear</b> una cuenta bloqueada o <b>restablecer</b> su contraseña (la nueva debe cumplir la política).</li>
              <li><b>Revocar acceso</b> deja la cuenta rechazada; <b>Borrar</b> la elimina (los registros que creó se conservan).</li>
            </ol>"];

        $todas['alertas'] = ['Alertas', "
            <p>En <b>Alertas</b> aparecen las cuentas nuevas (con su motivo), los intentos de ingreso de cuentas sin aprobar y los inicios de sesión. El número rojo junto a «Alertas» son las que no ha leído. Las solicitudes pendientes se pueden aprobar o rechazar desde ahí, y <b>Marcar todas como leídas</b> limpia el contador.</p>"];

        $todas['reportes'] = ['Reportes', "
            <p>En <b>Reportes</b> hay estadísticas del sistema: registros por módulo, registros nuevos de los últimos 6 meses, usuarios por rol y etiquetas más usadas.</p>"];
    }

    $todas['faq'] = ['Preguntas frecuentes', "
        <details class=\"ayuda-faq\"><summary>No veo el botón «Registrar» o «Ver registros».</summary><p>Su cuenta no tiene ese permiso en el módulo. Pídaselo a un administrador.</p></details>
        <details class=\"ayuda-faq\"><summary>Creé mi cuenta y no puedo entrar.</summary><p>Las cuentas nuevas quedan pendientes hasta que un administrador las apruebe. Si el aviso dice «rechazada», use el contacto que muestra.</p></details>
        <details class=\"ayuda-faq\"><summary>Me dice que ya existe un registro con ese correo.</summary><p>Dentro de un módulo no se repiten correos, NIT ni cédulas. Búsquelo en «Ver registros»; si necesita corregirlo, un administrador puede editarlo.</p></details>
        <details class=\"ayuda-faq\"><summary>Importé un archivo y no se guardó nada.</summary><p>Revise que no supere el tamaño o las filas permitidas, que los encabezados sean los de la plantilla y que el archivo sea .xlsx o .csv.</p></details>
        <details class=\"ayuda-faq\"><summary>Mi cuenta quedó bloqueada.</summary><p>Espere 15 minutos o pida a un administrador que la desbloquee desde Usuarios → Editar.</p></details>"];

    $publicas = ['cuenta', 'ingresar', 'contrasena', 'recuperar', 'faq'];
    $internas = ['panel', 'registrar', 'consultar', 'buscar', 'etiquetas', 'importar', 'exportar', 'usuarios', 'alertas', 'reportes', 'contrasena', 'faq'];
    $orden = $sesion ? $internas : $publicas;
    $salida = [];
    foreach ($orden as $id) {
        if (isset($todas[$id])) {
            $salida[] = [$id, $todas[$id][0], $todas[$id][1]];
        }
    }
    return $salida;
}

function render_ayuda(): void
{
    if (defined('AYUDA_ACTIVA') && !AYUDA_ACTIVA) {
        return;
    }
    $secciones = ayuda_secciones();
    $actual = ayuda_seccion_actual();
    if (!in_array($actual, array_column($secciones, 0), true)) {
        $actual = $secciones[0][0] ?? '';
    }
    ?>
  <div class="ayuda-barra">
    <?php if (trim((string) CONTACTO_WHATSAPP) !== ''): ?>
    <a class="ayuda-whatsapp" href="<?= htmlspecialchars(trim((string) CONTACTO_WHATSAPP)) ?>" target="_blank" rel="noopener noreferrer" aria-label="Escribir al administrador por WhatsApp">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M17.47 14.38c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.17-.17.2-.35.22-.65.07-.3-.15-1.26-.46-2.4-1.48-.89-.79-1.49-1.77-1.66-2.07-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.08-.15-.67-1.62-.92-2.22-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.8.37-.27.3-1.04 1.02-1.04 2.48s1.07 2.88 1.22 3.08c.15.2 2.1 3.2 5.08 4.49.71.31 1.26.49 1.69.63.71.23 1.36.2 1.87.12.57-.08 1.76-.72 2.01-1.41.25-.7.25-1.29.17-1.41-.07-.12-.27-.2-.57-.35zM12.05 21.8h-.01a9.87 9.87 0 0 1-5.03-1.38l-.36-.21-3.74.98 1-3.65-.24-.37a9.86 9.86 0 0 1-1.51-5.26c0-5.45 4.44-9.88 9.89-9.88 2.64 0 5.12 1.03 6.99 2.9a9.82 9.82 0 0 1 2.89 6.99c0 5.45-4.44 9.88-9.88 9.88zM20.52 3.45A11.8 11.8 0 0 0 12.05 0C5.5 0 .16 5.34.16 11.89c0 2.1.55 4.14 1.59 5.95L.06 24l6.3-1.65a11.88 11.88 0 0 0 5.68 1.45h.01c6.55 0 11.89-5.34 11.89-11.89 0-3.18-1.24-6.16-3.48-8.4z"/></svg><span class="ayuda-texto">WhatsApp</span>
    </a>
    <?php endif; ?>
    <button type="button" id="ayuda-boton" class="ayuda-boton" aria-controls="ayuda-panel" aria-expanded="false" aria-label="Manual de uso">
      <span class="ayuda-icono" aria-hidden="true">?</span><span class="ayuda-texto">Manual</span>
    </button>
  </div>
  <aside id="ayuda-panel" class="ayuda-panel" role="complementary" aria-label="Manual de uso" data-actual="<?= htmlspecialchars($actual) ?>" hidden>
    <div class="ayuda-cabecera">
      <strong>Manual de uso</strong>
      <button type="button" class="ayuda-cerrar" aria-label="Cerrar el manual">&times;</button>
    </div>
    <div class="ayuda-buscar"><input type="search" id="ayuda-buscar" placeholder="Buscar en el manual…" aria-label="Buscar en el manual" autocomplete="off"></div>
    <div class="ayuda-cuerpo">
      <?php foreach ($secciones as [$id, $titulo, $html]): ?>
        <details class="ayuda-seccion" id="ayuda-sec-<?= htmlspecialchars($id) ?>" data-id="<?= htmlspecialchars($id) ?>">
          <summary><?= htmlspecialchars($titulo) ?></summary>
          <div class="ayuda-contenido"><?= $html ?></div>
        </details>
      <?php endforeach; ?>
      <p class="ayuda-vacio" hidden>No se encontró nada con esa palabra.</p>
    </div>
  </aside>
  <script src="js/ayuda.js?v=<?= @filemtime(__DIR__ . '/../js/ayuda.js') ?>" defer></script>
    <?php
}
