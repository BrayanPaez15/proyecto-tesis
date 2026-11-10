-- Migracion 2026-11-10 (2): permisos por modulo y alertas para el administrador.
-- Se aplica despues de 2026-11-10_aprobacion_politica_importacion_agenda.sql; no borra nada.
-- Haga primero un respaldo: php db/backup.php
-- Uso:  mysql -u root directorio < db/migraciones/2026-11-10_permisos_y_alertas.sql
USE `directorio`;

-- Permisos de un usuario sobre cada modulo (ver registros y/o registrar). Los administradores
-- tienen acceso a todo y no necesitan filas. Sin filas, un usuario aprobado no ve ni registra nada.
CREATE TABLE IF NOT EXISTS `permisos` (
  `usuario_id` int(11) NOT NULL,
  `modulo` varchar(30) NOT NULL,
  `puede_ver` tinyint(1) NOT NULL DEFAULT 0,
  `puede_registrar` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`usuario_id`,`modulo`),
  CONSTRAINT `fk_permisos_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Alertas para el administrador: cuentas creadas, inicios de sesion e intentos de acceso sin aprobar.
CREATE TABLE IF NOT EXISTS `alertas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tipo` enum('registro','inicio_sesion','intento_pendiente') NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `detalle` varchar(255) NOT NULL,
  `leida` tinyint(1) NOT NULL DEFAULT 0,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_alertas_leida` (`leida`,`creado_en`),
  KEY `fk_alertas_usuario` (`usuario_id`),
  CONSTRAINT `fk_alertas_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
