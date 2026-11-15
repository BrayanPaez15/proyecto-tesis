<?php
/**
 * Aceptacion de la version vigente de la politica de tratamiento de datos.
 *
 * auth.php envia aqui a quien tiene aceptada una version distinta de la vigente (o ninguna), por ejemplo las
 * cuentas anteriores a la version 1.1. Al marcar la casilla se guardan la version y la fecha de aceptacion
 * (usuarios.politica_version y politica_aceptada_en); hasta entonces no se puede usar el resto del sistema,
 * salvo cerrar la sesion o darse de baja. Protegida por sesion y CSRF.
 */
require "auth.php";
include "conexion.php";
require_once "includes/layout.php";
require_once "includes/csrf.php";

$yo = (int) $_SESSION['usuario_id'];
$stmt = $conn->prepare("SELECT politica_version FROM usuarios WHERE id_usuario = ?");
$stmt->bind_param("i", $yo);
$stmt->execute();
$versionActual = (string) ($stmt->get_result()->fetch_row()[0] ?? '');
$stmt->close();

if ($versionActual === POLITICA_VERSION) {
    header("Location: index.php");
    exit;
}

$error = "";
if ($_POST && isset($_POST['aceptar'])) {
    csrf_verify();
    if (empty($_POST['acepta_politica'])) {
        $error = "Marca la casilla para aceptar la política y poder continuar.";
    } else {
        $version = POLITICA_VERSION;
        $upd = $conn->prepare("UPDATE usuarios SET politica_version = ?, politica_aceptada_en = NOW() WHERE id_usuario = ?");
        $upd->bind_param("si", $version, $yo);
        $upd->execute();
        $upd->close();
        header("Location: index.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Aceptar la política de datos - SIRAD</title>
  <link rel="stylesheet" href="css/styles.css?v=<?= @filemtime(__DIR__ . '/css/styles.css') ?>">
  <script src="js/animations.js?v=<?= @filemtime(__DIR__ . '/js/animations.js') ?>" defer></script>
</head>

<body>

  <?php render_header(true); ?>

  <main>
    <h1>Política de tratamiento de datos</h1>
    <p class="subtitle" style="margin: -4px 0 14px;">
      <?= $versionActual === '' ? 'Todavía no has aceptado la política de tratamiento de datos.' : 'Tu cuenta aceptó la versión ' . htmlspecialchars($versionActual) . ' de la política.' ?>
      La versión vigente es la <?= htmlspecialchars(POLITICA_VERSION) ?>. Para seguir usando SIRAD, léela y acéptala.
    </p>

    <?php if ($error): ?>
      <p class="auth-error" style="margin-bottom: 16px;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form class="form-card" method="POST" autocomplete="off">
      <?php csrf_field(); ?>
      <p style="grid-column: 1 / -1; margin: 0;"><a href="politica.php" target="_blank" rel="noopener noreferrer">Leer la política de tratamiento de datos (versión <?= htmlspecialchars(POLITICA_VERSION) ?>)</a></p>
      <label class="check-label" style="grid-column: 1 / -1;"><input type="checkbox" name="acepta_politica" value="1" required><span>He leído la política de tratamiento de datos y autorizo el tratamiento de mis datos para las finalidades descritas.</span></label>
      <button type="submit" name="aceptar" value="1">Aceptar y continuar</button>
      <a href="baja.php" class="btn-edit" style="grid-column: 1 / -1; justify-self: start; padding: 8px 20px;">Si no la aceptas, puedes darte de baja</a>
    </form>
  </main>

</body>

</html>
