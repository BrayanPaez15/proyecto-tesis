-- Migracion 2026-11-17: rol (tipo de acceso) que la persona pide al solicitar su cuenta.
-- Agrega a usuarios la columna acceso_solicitado ('ver', 'registrar' o 'ver_registrar'); es solo una peticion que el
-- administrador ve junto al motivo, los permisos reales se siguen asignando por modulo. No se agregan tablas.
-- Haga primero un respaldo: php db/backup.php
-- Uso:  mysql -u root directorio < db/migraciones/2026-11-17_acceso_solicitado.sql
USE `directorio`;

ALTER TABLE `usuarios`
  ADD COLUMN `acceso_solicitado` varchar(20) DEFAULT NULL;
