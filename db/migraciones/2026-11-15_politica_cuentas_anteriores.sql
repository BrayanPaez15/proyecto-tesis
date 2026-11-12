-- Migracion 2026-11-15: marca como aceptada la politica de tratamiento de datos (version 1.0) en las cuentas
-- creadas antes de que existiera la casilla de aceptacion (administrador y usuarios anteriores al 2026-11-11).
-- La fecha de aceptacion es la de esta migracion. No cambia la estructura. Haga primero un respaldo: php db/backup.php
-- Uso:  mysql -u root directorio < db/migraciones/2026-11-15_politica_cuentas_anteriores.sql
USE `directorio`;

UPDATE `usuarios`
   SET `politica_version` = '1.0', `politica_aceptada_en` = NOW()
 WHERE `politica_aceptada_en` IS NULL;
