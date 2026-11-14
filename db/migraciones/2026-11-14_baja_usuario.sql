-- Migracion 2026-11-14: baja de la cuenta por el propio usuario, con el motivo y la fecha.
-- Agrega el estado 'baja' a usuarios.estado y las columnas baja_motivo y baja_en (trazabilidad: la cuenta no se
-- borra, queda inactiva y el administrador ve quien se retiro, cuando y por que). No se agregan tablas.
-- Haga primero un respaldo: php db/backup.php
-- Uso:  mysql -u root directorio < db/migraciones/2026-11-14_baja_usuario.sql
USE `directorio`;

ALTER TABLE `usuarios`
  MODIFY COLUMN `estado` enum('pendiente','aprobado','rechazado','baja') NOT NULL DEFAULT 'pendiente',
  ADD COLUMN `baja_motivo` varchar(500) DEFAULT NULL,
  ADD COLUMN `baja_en` datetime DEFAULT NULL;
