-- Migracion 2026-11-11: consolida en 14 tablas una base que ya tenia las 17 (con `disponibilidad`, `permisos` y `alertas`).
-- Las tres tablas se reemplazan por columnas y se copian sus datos; luego se eliminan.
-- Haga primero un respaldo: php db/backup.php
-- Uso:  mysql -u root directorio < db/migraciones/2026-11-11_consolidar_desde_17_tablas.sql
USE `directorio`;
SET SESSION group_concat_max_len = 1048576;

ALTER TABLE `usuarios`
  ADD COLUMN `permisos_modulos` text DEFAULT NULL AFTER `puede_importar`,
  ADD COLUMN `ultimo_acceso` datetime DEFAULT NULL AFTER `permisos_modulos`,
  ADD COLUMN `ultimo_intento_pendiente` datetime DEFAULT NULL AFTER `ultimo_acceso`,
  ADD COLUMN `alertas_vistas_hasta` datetime DEFAULT NULL AFTER `ultimo_intento_pendiente`;
ALTER TABLE `artistas` ADD COLUMN `disponibilidad` text DEFAULT NULL AFTER `filbo`;
ALTER TABLE `talleristas` ADD COLUMN `disponibilidad` text DEFAULT NULL AFTER `perfil`;

-- permisos -> usuarios.permisos_modulos, JSON como {"artistas":"vr","talleristas":"v"} ("v" ver, "r" registrar)
UPDATE `usuarios` u
JOIN (SELECT usuario_id,
             CONCAT('{', GROUP_CONCAT(CONCAT(JSON_QUOTE(modulo), ':', JSON_QUOTE(CONCAT(IF(puede_ver = 1, 'v', ''), IF(puede_registrar = 1, 'r', '')))) ORDER BY modulo SEPARATOR ','), '}') AS j
      FROM `permisos` WHERE puede_ver = 1 OR puede_registrar = 1 GROUP BY usuario_id) p ON p.usuario_id = u.id
SET u.`permisos_modulos` = p.j;

-- disponibilidad -> artistas.disponibilidad y talleristas.disponibilidad (lista JSON de horarios)
UPDATE `artistas` a
JOIN (SELECT registro_id,
             CONCAT('[', GROUP_CONCAT(JSON_OBJECT('dia', dia, 'desde', TIME_FORMAT(hora_inicio, '%H:%i'), 'hasta', TIME_FORMAT(hora_fin, '%H:%i'), 'tipo', tipo, 'nota', IFNULL(nota, ''))
                                      ORDER BY FIELD(dia, 'lunes','martes','miercoles','jueves','viernes','sabado','domingo'), hora_inicio SEPARATOR ','), ']') AS j
      FROM `disponibilidad` WHERE modulo = 'artistas' GROUP BY registro_id) d ON d.registro_id = a.id
SET a.`disponibilidad` = d.j;
UPDATE `talleristas` t
JOIN (SELECT registro_id,
             CONCAT('[', GROUP_CONCAT(JSON_OBJECT('dia', dia, 'desde', TIME_FORMAT(hora_inicio, '%H:%i'), 'hasta', TIME_FORMAT(hora_fin, '%H:%i'), 'tipo', tipo, 'nota', IFNULL(nota, ''))
                                      ORDER BY FIELD(dia, 'lunes','martes','miercoles','jueves','viernes','sabado','domingo'), hora_inicio SEPARATOR ','), ']') AS j
      FROM `disponibilidad` WHERE modulo = 'talleristas' GROUP BY registro_id) d ON d.registro_id = t.id
SET t.`disponibilidad` = d.j;

-- alertas -> ultimo acceso y ultimo intento sin aprobar de cada usuario
UPDATE `usuarios` u
JOIN (SELECT usuario_id, MAX(creado_en) AS m FROM `alertas` WHERE tipo = 'inicio_sesion' AND usuario_id IS NOT NULL GROUP BY usuario_id) a ON a.usuario_id = u.id
SET u.`ultimo_acceso` = a.m;
UPDATE `usuarios` u
JOIN (SELECT usuario_id, MAX(creado_en) AS m FROM `alertas` WHERE tipo = 'intento_pendiente' AND usuario_id IS NOT NULL GROUP BY usuario_id) a ON a.usuario_id = u.id
SET u.`ultimo_intento_pendiente` = a.m;

DROP TABLE `alertas`;
DROP TABLE `permisos`;
DROP TABLE `disponibilidad`;
