-- Migracion 2026-11-16: limite de importaciones por usuario y hora.
-- Agrega a usuarios el inicio de la ventana y el numero de importaciones hechas en ella. No se agregan tablas.
-- Haga primero un respaldo: php db/backup.php
-- Uso:  mysql -u root directorio < db/migraciones/2026-11-16_limite_importaciones.sql
USE `directorio`;

ALTER TABLE `usuarios`
  ADD COLUMN `importaciones_desde` datetime DEFAULT NULL,
  ADD COLUMN `importaciones_cuenta` int(11) NOT NULL DEFAULT 0;
