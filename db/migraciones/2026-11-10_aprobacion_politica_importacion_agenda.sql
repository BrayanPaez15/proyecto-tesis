-- Migracion 2026-11-10: aprobacion de cuentas, politica de datos, permiso de importacion y agenda.
-- Se aplica sobre una base "directorio" que ya tiene los datos (no borra nada).
-- Haga primero un respaldo: php db/backup.php
-- Uso:  mysql -u root directorio < db/migraciones/2026-11-10_aprobacion_politica_importacion_agenda.sql
USE `directorio`;

-- 1) Usuarios: estado de la cuenta, aceptacion de la politica y permiso de importacion.
ALTER TABLE `usuarios`
  ADD COLUMN `estado` enum('pendiente','aprobado','rechazado') NOT NULL DEFAULT 'pendiente' AFTER `respuesta_seguridad_hash`,
  ADD COLUMN `politica_version` varchar(10) DEFAULT NULL AFTER `estado`,
  ADD COLUMN `politica_aceptada_en` datetime DEFAULT NULL AFTER `politica_version`,
  ADD COLUMN `puede_importar` tinyint(1) NOT NULL DEFAULT 0 AFTER `politica_aceptada_en`;

-- Las cuentas que ya existian conservan su acceso; no han aceptado la politica (queda en NULL).
UPDATE `usuarios` SET `estado` = 'aprobado';

-- 2) Agenda compartida por los modulos (modulo + registro_id, como registro_etiquetas).
CREATE TABLE IF NOT EXISTS `disponibilidad` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `modulo` varchar(30) NOT NULL,
  `registro_id` int(11) NOT NULL,
  `dia` enum('lunes','martes','miercoles','jueves','viernes','sabado','domingo') NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  `tipo` enum('disponible','ocupado') NOT NULL DEFAULT 'disponible',
  `nota` varchar(150) DEFAULT NULL,
  `creado_por` int(11) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_disponibilidad_registro` (`modulo`,`registro_id`),
  KEY `fk_disponibilidad_creado_por` (`creado_por`),
  CONSTRAINT `fk_disponibilidad_creado_por` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chk_disponibilidad_horas` CHECK (`hora_fin` > `hora_inicio`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
