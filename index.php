<?php
/**
 * Panel principal de SIRAD.
 *
 * Pagina de entrada del sistema tras iniciar sesion: muestra una
 * tarjeta de acceso por cada modulo del directorio sobre el que el
 * usuario tiene permiso (todos, si es administrador), con la cantidad de
 * registros de cada uno; sin permisos no muestra ninguno. Requiere sesion
 * activa (auth.php).
 */
require "auth.php";
include "conexion.php";
require_once "includes/layout.php";

/**
 * Botones de la tarjeta de un modulo segun los permisos: «Ingresar» (formulario de registro) si el
 * usuario puede registrar, y «Ver registros» (consulta) si puede ver. Quien solo puede ver tiene
 * unicamente «Ver registros»; quien puede registrar y ver (y el administrador) tiene «Ingresar» y,
 * debajo, «Ver registros».
 */
function tarjeta_enlaces(string $modulo, string $color): void
{
    $registrar = puede_modulo($modulo, 'registrar');
    $ver = puede_modulo($modulo, 'ver');
    if ($registrar) {
        echo '<a href="' . htmlspecialchars($modulo) . '.php" class="' . $color . '">Ingresar</a>';
    }
    if ($ver) {
        echo '<a href="' . htmlspecialchars($modulo) . '_registros.php" class="' . ($registrar ? 'ver-registros' : $color) . '">Ver registros</a>';
    }
}

function contar(mysqli $conn, string $tabla): int
{
    $resultado = $conn->query("SELECT COUNT(*) AS total FROM `$tabla`");
    return (int) $resultado->fetch_assoc()['total'];
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>SIRAD - Sistema de Registro y Administración de Directorios</title>
    <link rel="stylesheet" href="css/styles.css">
    <script src="js/animations.js" defer></script>

    <style>
        * {
            box-sizing: border-box;
            font-family: Arial, Helvetica, sans-serif;
        }

        body {
            margin: 0;
            background:
                radial-gradient(circle at 15% 0%, rgba(99, 102, 241, 0.08), transparent 40%),
                radial-gradient(circle at 85% 10%, rgba(37, 99, 235, 0.08), transparent 40%),
                #f4f6fb;
            color: #1f2937;
        }

        h1 {
            text-align: center;
            margin: 40px 20px 6px;
            font-size: 32px;
            border-left: none;
            padding-left: 0;
        }

        .subtitle {
            text-align: center;
            margin: 0 20px 40px;
            color: #6b7280;
            font-size: 15px;
        }

        .container {
            max-width: 1300px;
            margin: auto;
            padding: 20px;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
        }

        .card {
            position: relative;
            overflow: hidden;
            background: white;
            border-radius: 14px;
            padding: 30px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
            text-align: center;
            transition: transform 0.25s ease, box-shadow 0.25s ease;
        }

        .card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #2563eb, #6366f1, #ec4899);
        }

        .card:hover {
            transform: translateY(-6px);
            box-shadow: 0 16px 30px rgba(0, 0, 0, 0.14);
        }

        .icon {
            font-size: 40px;
            margin-bottom: 15px;
            transition: transform 0.25s ease;
        }

        .card:hover .icon {
            transform: scale(1.15) rotate(-4deg);
        }

        .card h3 {
            margin: 10px 0;
            font-size: 22px;
        }

        .card .stat {
            display: inline-block;
            margin-bottom: 10px;
            font-size: 13px;
            font-weight: 700;
            color: #2563eb;
            background: #eef2ff;
            padding: 3px 10px;
            border-radius: 999px;
        }

        .card p {
            font-size: 15px;
            color: #6b7280;
            margin-bottom: 25px;
        }

        .card a {
            display: block;
            padding: 14px;
            border-radius: 8px;
            color: white;
            font-weight: bold;
            text-decoration: none;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .card a:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
        }

        .blue {
            background: #3b82f6;
        }

        .green {
            background: #10b981;
        }

        .orange {
            background: #f59e0b;
        }

        .red {
            background: #ef4444;
        }

        .purple {
            background: #6366f1;
        }

        .pink {
            background: #ec4899;
        }

        .gray {
            background: #64748b;
        }

        .blue:hover {
            background: #2563eb;
        }

        .green:hover {
            background: #059669;
        }

        .orange:hover {
            background: #d97706;
        }

        .red:hover {
            background: #dc2626;
        }

        .purple:hover {
            background: #4f46e5;
        }

        .pink:hover {
            background: #db2777;
        }

        .gray:hover {
            background: #475569;
        }
    </style>
</head>

<body>

    <?php render_header(false); ?>

    <h1>SIRAD</h1>
    <?php if (is_admin() && ($solicitudesPendientes = (int) $conn->query("SELECT COUNT(*) AS total FROM usuarios WHERE estado = 'pendiente'")->fetch_assoc()['total']) > 0): ?>
        <p class="pending-notice" style="max-width: 1260px; margin: 0 auto 20px;"><?= $solicitudesPendientes ?> solicitud<?= $solicitudesPendientes === 1 ? '' : 'es' ?> de cuenta pendiente<?= $solicitudesPendientes === 1 ? '' : 's' ?> de tu aprobación. <a href="usuarios.php">Aprobar o rechazar</a></p>
    <?php endif; ?>

    <?php if (is_admin() && ($alertasSinLeer = alertas_pendientes($conn)) > 0): ?>
        <p class="pending-notice" style="max-width: 1260px; margin: 0 auto 20px;">Tienes <?= $alertasSinLeer ?> alerta<?= $alertasSinLeer === 1 ? '' : 's' ?> sin leer (cuentas nuevas e inicios de sesión). <a href="alertas.php">Ver alertas</a></p>
    <?php endif; ?>

    <?php if (!tiene_algun_permiso()): ?>
        <p class="subtitle">Tu cuenta está aprobada, pero todavía no tiene permisos asignados. Pídele a un administrador que te dé acceso a los módulos que necesites.</p>
    <?php else: ?>
    <p class="subtitle"><?= puede_registrar_alguno() ? 'Selecciona un módulo para registrar o consultar información institucional' : 'Selecciona un módulo para consultar información institucional' ?></p>
    <?php endif; ?>

    <div class="container">

        <?php if (puede_modulo('instituciones-e', 'alguna')): ?>
        <div class="card">
            <div class="icon">🏫</div>
            <h3>Instituciones Educativas</h3>
            <span class="stat"><?= contar($conn, 'instituciones_e') ?> registros</span>
            <p>Universidades, colegios y jardines vinculados</p>
            <?php tarjeta_enlaces('instituciones-e', 'blue'); ?>
        </div>
        <?php endif; ?>

        <?php if (puede_modulo('practicantes', 'alguna')): ?>
        <div class="card">
            <div class="icon">🎓</div>
            <h3>Practicantes</h3>
            <span class="stat"><?= contar($conn, 'practicantes') ?> registros</span>
            <p>Estudiantes en práctica académica</p>
            <?php tarjeta_enlaces('practicantes', 'green'); ?>
        </div>
        <?php endif; ?>

        <?php if (puede_modulo('artistas', 'alguna')): ?>
        <div class="card">
            <div class="icon">🎨</div>
            <h3>Artistas</h3>
            <span class="stat"><?= contar($conn, 'artistas') ?> registros</span>
            <p>Ilustradores, escritores y creadores</p>
            <?php tarjeta_enlaces('artistas', 'orange'); ?>
        </div>
        <?php endif; ?>

        <?php if (puede_modulo('talleristas', 'alguna')): ?>
        <div class="card">
            <div class="icon">🧑‍🏫</div>
            <h3>Talleristas</h3>
            <span class="stat"><?= contar($conn, 'talleristas') ?> registros</span>
            <p>Facilitadores y formadores</p>
            <?php tarjeta_enlaces('talleristas', 'purple'); ?>
        </div>
        <?php endif; ?>

        <?php if (puede_modulo('editoriales', 'alguna')): ?>
        <div class="card">
            <div class="icon">📚</div>
            <h3>Editoriales</h3>
            <span class="stat"><?= contar($conn, 'editoriales') ?> registros</span>
            <p>Editoriales aliadas</p>
            <?php tarjeta_enlaces('editoriales', 'pink'); ?>
        </div>
        <?php endif; ?>

        <?php if (puede_modulo('asocajas', 'alguna')): ?>
        <div class="card">
            <div class="icon">🤝</div>
            <h3>Asocajas</h3>
            <span class="stat"><?= contar($conn, 'asocajas') ?> registros</span>
            <p>Cajas de compensación</p>
            <?php tarjeta_enlaces('asocajas', 'gray'); ?>
        </div>
        <?php endif; ?>

        <?php if (puede_modulo('mercadeo', 'alguna')): ?>
        <div class="card">
            <div class="icon">📢</div>
            <h3>Mercadeo</h3>
            <span class="stat"><?= contar($conn, 'mercadeo') ?> registros</span>
            <p>Empresas y patrocinios</p>
            <?php tarjeta_enlaces('mercadeo', 'blue'); ?>
        </div>
        <?php endif; ?>

        <?php if (puede_modulo('proveedores', 'alguna')): ?>
        <div class="card">
            <div class="icon">🚚</div>
            <h3>Proveedores</h3>
            <span class="stat"><?= contar($conn, 'proveedores') ?> registros</span>
            <p>Proveedores nacionales e internacionales</p>
            <?php tarjeta_enlaces('proveedores', 'green'); ?>
        </div>
        <?php endif; ?>

        <?php if (puede_modulo('medios', 'alguna')): ?>
        <div class="card">
            <div class="icon">📰</div>
            <h3>Medios</h3>
            <span class="stat"><?= contar($conn, 'medios') ?> registros</span>
            <p>Prensa, radio y medios digitales</p>
            <?php tarjeta_enlaces('medios', 'orange'); ?>
        </div>
        <?php endif; ?>

        <?php if (puede_modulo('team', 'alguna')): ?>
        <div class="card">
            <div class="icon">👥</div>
            <h3>Team</h3>
            <span class="stat"><?= contar($conn, 'team_pombo') ?> registros</span>
            <p>Equipo interno de la fundación</p>
            <?php tarjeta_enlaces('team', 'purple'); ?>
        </div>
        <?php endif; ?>

        <?php if (puede_modulo('directivos', 'alguna')): ?>
        <div class="card">
            <div class="icon">🏛️</div>
            <h3>Directivos</h3>
            <span class="stat"><?= contar($conn, 'directivos') ?> registros</span>
            <p>Miembros directivos y vigencias</p>
            <?php tarjeta_enlaces('directivos', 'red'); ?>
        </div>
        <?php endif; ?>

    </div>

</body>

</html>
