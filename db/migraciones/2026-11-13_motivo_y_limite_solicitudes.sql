-- Migracion 2026-11-13: motivo de la solicitud de cuenta y limite de solicitudes por conexion.
-- Agrega a usuarios el texto con el que la persona explica por que pide acceso y la direccion IP desde la
-- que se hizo la solicitud. No se agregan tablas. Haga primero un respaldo: php db/backup.php
-- Uso:  mysql -u root directorio < db/migraciones/2026-11-13_motivo_y_limite_solicitudes.sql
USE `directorio`;

ALTER TABLE `usuarios`
  ADD COLUMN `motivo_solicitud` varchar(500) DEFAULT NULL,
  ADD COLUMN `ip_solicitud` varchar(45) DEFAULT NULL,
  ADD KEY `idx_usuarios_ip_solicitud` (`ip_solicitud`);
