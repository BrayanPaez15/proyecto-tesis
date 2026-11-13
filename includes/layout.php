<?php
/**
 * Elementos de layout compartidos por los modulos.
 *
 * render_header() imprime la barra superior (logo, saludo, insignia de
 * rol, volver al directorio, cerrar sesion), identica antes en las 33
 * paginas; solo muestra Buscar e Importar a quien puede usarlos y las
 * alertas sin leer al administrador. render_page_actions() imprime las
 * pestanas Registrar/Ver registros/Descargar Excel, mostrando cada una
 * solo si el usuario en sesion tiene ese permiso en el modulo.
 */
require_once __DIR__ . '/roles.php';
require_once __DIR__ . '/permisos.php';
require_once __DIR__ . '/alertas.php';
require_once __DIR__ . '/ayuda.php';

function render_header(bool $mostrarVolver = true): void
{
    global $conn;
    $rolLabel = is_admin() ? 'Administrador' : 'Usuario';
    $sinLeer = is_admin() ? alertas_pendientes($conn) : 0;
    ?>
  <header>
    <div class="logo">SIRAD</div>
    <div class="header-usuario">
      <span class="saludo"><span class="saludo-linea">Hola, <span class="saludo-nombre"><?= htmlspecialchars($_SESSION['usuario_nombre']) ?></span></span> <span class="badge"><?= $rolLabel ?></span></span>
      <button type="button" class="menu-toggle" aria-label="Abrir menú" aria-expanded="false" aria-controls="menu-principal">
        <svg class="icono-abrir" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" fill="none"/></svg>
        <svg class="icono-cerrar" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" fill="none"/></svg>
        <?php if ($sinLeer > 0): ?><span class="badge-count menu-aviso"><?= $sinLeer ?></span><?php endif; ?>
      </button>
    </div>
    <div class="header-actions" id="menu-principal">
      <?php if (tiene_algun_permiso()): ?>
      <a href="buscar.php">Buscar</a>
      <?php endif; ?>
      <?php if (puede_importar()): ?>
      <a href="importar.php">Importar</a>
      <?php endif; ?>
      <?php if (is_admin()): ?>
      <a href="usuarios.php">Usuarios</a>
      <a href="etiquetas.php">Etiquetas</a>
      <a href="reportes.php">Reportes</a>
      <a href="alertas.php">Alertas<?php if ($sinLeer > 0): ?> <span class="badge-count"><?= $sinLeer ?></span><?php endif; ?></a>
      <?php endif; ?>
      <?php if ($mostrarVolver): ?>
      <a href="index.php">&larr; Volver al directorio</a>
      <?php endif; ?>
      <a href="logout.php">Cerrar sesión</a>
    </div>
  </header>
  <script src="js/menu.js?v=<?= @filemtime(dirname(__DIR__) . '/js/menu.js') ?>" defer></script>
    <?php
    if (is_admin() && ($cola = cola_solicitudes($conn))):
        ?>
  <p class="aviso-cola<?= $cola['llena'] ? ' llena' : '' ?>" role="alert"><?= $cola['llena']
      ? 'La cola de solicitudes está llena (' . $cola['pendientes'] . ' de ' . $cola['maximo'] . '): el registro no recibe más solicitudes hasta que apruebes o rechaces algunas.'
      : 'Hay ' . $cola['pendientes'] . ' de ' . $cola['maximo'] . ' solicitudes de cuenta pendientes: cuando se llene, el registro dejará de recibir nuevas.' ?>
    <a href="usuarios.php">Revisar solicitudes</a></p>
    <?php
    endif;
    render_ayuda();
}

function render_page_actions(string $archivo, string $activo): void
{
    $tabs = [];
    if (puede_modulo($archivo, 'registrar')) {
        $tabs['registrar'] = ["$archivo.php", 'Registrar'];
    }
    if (puede_modulo($archivo, 'ver')) {
        $tabs['registros'] = ["{$archivo}_registros.php", 'Ver registros'];
    }
    if (is_admin()) {
        $tabs['exportar'] = ["{$archivo}_exportar.php", 'Descargar Excel'];
    }
    echo '<div class="page-actions">';
    foreach ($tabs as $clave => $tab) {
        [$href, $label] = $tab;
        $clase = $clave === $activo ? ' class="active"' : '';
        echo '<a href="' . htmlspecialchars($href) . '"' . $clase . '>' . htmlspecialchars($label) . '</a>';
    }
    echo '</div>';
}
