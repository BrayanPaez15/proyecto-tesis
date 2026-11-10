-- Migracion 2026-11-12: recuperacion de contrasena por correo (enlace de un solo uso).
-- Agrega a usuarios el hash del token, su vencimiento y la fecha del ultimo envio.
-- No se agregan tablas. Haga primero un respaldo: php db/backup.php
-- Uso:  mysql -u root directorio < db/migraciones/2026-11-12_recuperacion_por_correo.sql
USE `directorio`;

ALTER TABLE `usuarios`
  ADD COLUMN `reset_token_hash` char(64) DEFAULT NULL,
  ADD COLUMN `reset_expira` datetime DEFAULT NULL,
  ADD COLUMN `reset_enviado_en` datetime DEFAULT NULL,
  ADD KEY `idx_usuarios_reset_token` (`reset_token_hash`);
