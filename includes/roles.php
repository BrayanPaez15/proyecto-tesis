<?php
/**
 * Control de acceso por rol y por permiso.
 *
 * is_admin() indica si el usuario en sesion tiene rol administrador.
 * require_admin() corta la ejecucion con 403 si no lo es. puede_importar()
 * indica si puede usar la importacion de CSV (los administradores siempre;
 * los demas solo si un administrador les asigno el permiso de importacion y
 * pueden registrar en algun modulo) y require_importar() corta con 403 si
 * no. Los permisos por modulo estan en permisos.php. Requiere que auth.php
 * ya haya iniciado la sesion.
 */

function is_admin(): bool
{
    return ($_SESSION['usuario_rol'] ?? '') === 'admin';
}

function puede_importar(): bool
{
    if (is_admin()) {
        return true;
    }
    if ((int) ($_SESSION['usuario_puede_importar'] ?? 0) !== 1) {
        return false;
    }
    foreach ($_SESSION['usuario_permisos'] ?? [] as $p) {
        if (!empty($p['registrar'])) {
            return true;
        }
    }
    return false;
}

function require_admin(): void
{
    if (is_admin()) {
        return;
    }
    acceso_denegado('Esta acción requiere permisos de administrador.');
}

function require_importar(): void
{
    if (puede_importar()) {
        return;
    }
    acceso_denegado('Esta acción requiere permiso de importación. Pídele a un administrador que te lo asigne.');
}

function acceso_denegado(string $mensaje): void
{
    http_response_code(403);
    ?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <title>Acceso denegado - SIRAD</title>
  <link rel="stylesheet" href="css/styles.css?v=<?= @filemtime(__DIR__ . '/../css/styles.css') ?>">
</head>

<body>
  <div class="auth-page">
    <div class="form-card auth-card">
      <h1>Acceso denegado</h1>
      <p class="auth-subtitle"><?= htmlspecialchars($mensaje) ?></p>
      <p class="auth-link"><a href="index.php">&larr; Volver al directorio</a></p>
    </div>
  </div>
</body>

</html>
    <?php
    exit;
}
