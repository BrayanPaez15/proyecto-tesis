-- Migracion 2026-11-12: impide registros duplicados con llaves UNIQUE.
-- Correo en los once modulos, NIT en instituciones_e y editoriales y cedula en directivos.
-- Un valor vacio se convierte en NULL (varios NULL no cuentan como duplicados).
-- Haga primero un respaldo: php db/backup.php
-- Si ya hay datos repetidos, el ALTER TABLE falla con «Duplicate entry»: busque los repetidos con
--   SELECT correo, COUNT(*) FROM <tabla> WHERE correo IS NOT NULL GROUP BY correo HAVING COUNT(*) > 1;
-- depure o elimine las copias y vuelva a ejecutar este script.
-- Uso:  mysql -u root directorio < db/migraciones/2026-11-12_unicidad.sql
USE `directorio`;

UPDATE `artistas` SET `correo` = NULL WHERE TRIM(`correo`) = '';
ALTER TABLE `artistas` ADD UNIQUE KEY `uq_artistas_correo` (`correo`);

UPDATE `asocajas` SET `correo` = NULL WHERE TRIM(`correo`) = '';
ALTER TABLE `asocajas` ADD UNIQUE KEY `uq_asocajas_correo` (`correo`);

UPDATE `directivos` SET `correo` = NULL WHERE TRIM(`correo`) = '';
UPDATE `directivos` SET `cedula` = NULL WHERE TRIM(`cedula`) = '';
ALTER TABLE `directivos` ADD UNIQUE KEY `uq_directivos_correo` (`correo`), ADD UNIQUE KEY `uq_directivos_cedula` (`cedula`);

UPDATE `editoriales` SET `correo` = NULL WHERE TRIM(`correo`) = '';
UPDATE `editoriales` SET `nit` = NULL WHERE TRIM(`nit`) = '';
ALTER TABLE `editoriales` ADD UNIQUE KEY `uq_editoriales_correo` (`correo`), ADD UNIQUE KEY `uq_editoriales_nit` (`nit`);

UPDATE `instituciones_e` SET `correo` = NULL WHERE TRIM(`correo`) = '';
UPDATE `instituciones_e` SET `nit` = NULL WHERE TRIM(`nit`) = '';
ALTER TABLE `instituciones_e` ADD UNIQUE KEY `uq_instituciones_e_correo` (`correo`), ADD UNIQUE KEY `uq_instituciones_e_nit` (`nit`);

UPDATE `medios` SET `correo` = NULL WHERE TRIM(`correo`) = '';
ALTER TABLE `medios` ADD UNIQUE KEY `uq_medios_correo` (`correo`);

UPDATE `mercadeo` SET `correo` = NULL WHERE TRIM(`correo`) = '';
ALTER TABLE `mercadeo` ADD UNIQUE KEY `uq_mercadeo_correo` (`correo`);

UPDATE `practicantes` SET `correo` = NULL WHERE TRIM(`correo`) = '';
ALTER TABLE `practicantes` ADD UNIQUE KEY `uq_practicantes_correo` (`correo`);

UPDATE `proveedores` SET `correo` = NULL WHERE TRIM(`correo`) = '';
ALTER TABLE `proveedores` ADD UNIQUE KEY `uq_proveedores_correo` (`correo`);

UPDATE `talleristas` SET `correo` = NULL WHERE TRIM(`correo`) = '';
ALTER TABLE `talleristas` ADD UNIQUE KEY `uq_talleristas_correo` (`correo`);

UPDATE `team_pombo` SET `correo` = NULL WHERE TRIM(`correo`) = '';
ALTER TABLE `team_pombo` ADD UNIQUE KEY `uq_team_pombo_correo` (`correo`);
