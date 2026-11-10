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

function render_header(bool $mostrarVolver = true): void
{
    global $conn;
    $rolLabel = is_admin() ? 'Administrador' : 'Usuario';
    ?>
  <header>
    <div class="logo">SIRAD</div>
    <div class="header-actions">
      <span>Hola, <?= htmlspecialchars($_SESSION['usuario_nombre']) ?> <span class="badge"><?= $rolLabel ?></span></span>
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
      <?php $sinLeer = alertas_pendientes($conn); ?>
      <a href="alertas.php">Alertas<?php if ($sinLeer > 0): ?> <span class="badge-count"><?= $sinLeer ?></span><?php endif; ?></a>
      <?php endif; ?>
      <?php if ($mostrarVolver): ?>
      <a href="index.php">&larr; Volver al directorio</a>
      <?php endif; ?>
      <a href="logout.php">Cerrar sesión</a>
    </div>
  </header>
    <?php
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
