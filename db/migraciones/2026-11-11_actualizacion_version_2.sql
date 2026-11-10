-- Migracion 2026-11-11: de la version 1.0 (14 tablas) a la version 2.0 (las mismas 14 tablas, con columnas nuevas).
-- Para una base "directorio" creada con el esquema original (sin las columnas de aprobacion, permisos y agenda).
-- No agrega tablas ni borra datos. Haga primero un respaldo: php db/backup.php
-- Si su base ya paso por la migracion anterior de 17 tablas, use en su lugar
-- 2026-11-11_consolidar_desde_17_tablas.sql.
-- Uso:  mysql -u root directorio < db/migraciones/2026-11-11_actualizacion_version_2.sql
USE `directorio`;

ALTER TABLE `usuarios`
  ADD COLUMN `estado` enum('pendiente','aprobado','rechazado') NOT NULL DEFAULT 'pendiente' AFTER `respuesta_seguridad_hash`,
  ADD COLUMN `politica_version` varchar(10) DEFAULT NULL AFTER `estado`,
  ADD COLUMN `politica_aceptada_en` datetime DEFAULT NULL AFTER `politica_version`,
  ADD COLUMN `puede_importar` tinyint(1) NOT NULL DEFAULT 0 AFTER `politica_aceptada_en`,
  ADD COLUMN `permisos_modulos` text DEFAULT NULL AFTER `puede_importar`,
  ADD COLUMN `ultimo_acceso` datetime DEFAULT NULL AFTER `permisos_modulos`,
  ADD COLUMN `ultimo_intento_pendiente` datetime DEFAULT NULL AFTER `ultimo_acceso`,
  ADD COLUMN `alertas_vistas_hasta` datetime DEFAULT NULL AFTER `ultimo_intento_pendiente`;

-- Las cuentas que ya existian conservan su acceso; no han aceptado la politica (queda en NULL)
-- y no tienen permisos por modulo hasta que un administrador se los asigne.
UPDATE `usuarios` SET `estado` = 'aprobado';

ALTER TABLE `artistas` ADD COLUMN `disponibilidad` text DEFAULT NULL AFTER `filbo`;
ALTER TABLE `talleristas` ADD COLUMN `disponibilidad` text DEFAULT NULL AFTER `perfil`;
