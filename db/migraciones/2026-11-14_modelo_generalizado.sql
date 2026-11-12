-- Migracion 2026-11-14: de las 14 tablas (una por modulo) al modelo generalizado de 12 tablas.
--
-- Las once tablas de modulos (artistas, asocajas, directivos, editoriales, instituciones_e, medios, mercadeo,
-- practicantes, proveedores, talleristas, team_pombo) y registro_etiquetas se reemplazan por:
--   tipos_actor, contactos, detalle_directivo, detalle_institucion, detalle_personal, detalle_mercadeo,
--   detalle_medio, detalle_artista, detalle_editorial y contacto_etiquetas.
-- usuarios.id pasa a ser usuarios.id_usuario y etiquetas.id pasa a ser etiquetas.id_etiqueta.
-- Los datos se conservan: cada registro pasa a contactos (con su detalle y sus etiquetas) con un id nuevo.
--
-- Correspondencia de campos (campo del formulario -> columna nueva):
--   asocajas.caja -> contactos.nombre | directivos.entidad y mercadeo.empresa -> contactos.organizacion
--   medios.telefono2 y team_pombo.celular -> contactos.telefono_alterno
--   practicantes/team_pombo.contacto -> detalle_personal.contacto_emergencia; practicantes.telefono_contacto -> telefono_emergencia
--   artistas.filbo (texto) -> detalle_artista.filbo (1 = Si, 0 = No, vacio = NULL)
--   la disponibilidad (agenda) de artistas y talleristas -> contactos.disponibilidad
--
-- Haga primero un respaldo: php db/backup.php
-- Uso:  mysql -u root directorio < db/migraciones/2026-11-14_modelo_generalizado.sql
-- No es repetible: si ya existe la tabla contactos, el script falla sin cambiar nada.
USE `directorio`;
SET @FK_ANTERIOR = @@FOREIGN_KEY_CHECKS;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------- tablas nuevas
CREATE TABLE `tipos_actor` (
  `id_tipo_actor` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `descripcion` varchar(150) DEFAULT NULL,
  PRIMARY KEY (`id_tipo_actor`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `tipos_actor` (`nombre`, `descripcion`) VALUES
  ('Directivo', 'Miembros directivos y vigencias'),
  ('Institución educativa', 'Universidades, colegios y jardines vinculados'),
  ('Practicante', 'Estudiantes en práctica académica'),
  ('Equipo Pombo', 'Equipo interno de la fundación'),
  ('Mercadeo', 'Empresas y patrocinios'),
  ('Medio', 'Prensa, radio y medios digitales'),
  ('Artista', 'Ilustradores, escritores y creadores'),
  ('Editorial', 'Editoriales aliadas'),
  ('Asocaja', 'Cajas de compensación'),
  ('Tallerista', 'Facilitadores y formadores'),
  ('Proveedor', 'Proveedores nacionales e internacionales');

-- _origen y _id_origen son temporales: guardan de que modulo y con que id venia cada registro y se eliminan al final.
CREATE TABLE `contactos` (
  `id_contacto` int(11) NOT NULL AUTO_INCREMENT,
  `id_tipo_actor` int(11) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `apellido` varchar(100) DEFAULT NULL,
  `cargo` varchar(100) DEFAULT NULL,
  `organizacion` varchar(150) DEFAULT NULL,
  `nit` varchar(50) DEFAULT NULL,
  `correo` varchar(150) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `telefono_alterno` varchar(50) DEFAULT NULL,
  `direccion` varchar(200) DEFAULT NULL,
  `ciudad` varchar(100) DEFAULT NULL,
  `departamento` varchar(100) DEFAULT NULL,
  `pais` varchar(100) DEFAULT NULL,
  `perfil` text DEFAULT NULL,
  `contacto` varchar(150) DEFAULT NULL,
  `disponibilidad` text DEFAULT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NULL DEFAULT NULL,
  `actualizado_por` int(11) DEFAULT NULL,
  `_origen` varchar(30) DEFAULT NULL,
  `_id_origen` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_contacto`),
  UNIQUE KEY `uq_contactos_tipo_correo` (`id_tipo_actor`,`correo`),
  UNIQUE KEY `uq_contactos_tipo_nit` (`id_tipo_actor`,`nit`),
  KEY `fk_contactos_creado_por` (`creado_por`),
  KEY `fk_contactos_actualizado_por` (`actualizado_por`),
  KEY `idx_contactos_origen` (`_origen`,`_id_origen`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `contacto_etiquetas` (
  `id_contacto` int(11) NOT NULL,
  `id_etiqueta` int(11) NOT NULL,
  PRIMARY KEY (`id_contacto`,`id_etiqueta`),
  KEY `fk_contacto_etiquetas_etiqueta` (`id_etiqueta`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `detalle_directivo` (
  `id_contacto` int(11) NOT NULL,
  `titulo` varchar(50) DEFAULT NULL,
  `cedula` varchar(30) DEFAULT NULL,
  `calidad` varchar(50) DEFAULT NULL,
  `estado` varchar(30) DEFAULT NULL,
  `integrante` varchar(100) DEFAULT NULL,
  `vigencia` varchar(50) DEFAULT NULL,
  `celular` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id_contacto`),
  UNIQUE KEY `uq_detalle_directivo_cedula` (`cedula`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `detalle_institucion` (
  `id_contacto` int(11) NOT NULL,
  `clase` varchar(100) DEFAULT NULL,
  `calidad` varchar(100) DEFAULT NULL,
  `jornada` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id_contacto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `detalle_personal` (
  `id_contacto` int(11) NOT NULL,
  `vinculacion` enum('equipo','practicante') NOT NULL,
  `disciplina` varchar(100) DEFAULT NULL,
  `generacion` varchar(50) DEFAULT NULL,
  `inicio` date DEFAULT NULL,
  `fin` date DEFAULT NULL,
  `cumple` date DEFAULT NULL,
  `contacto_emergencia` varchar(100) DEFAULT NULL,
  `telefono_emergencia` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id_contacto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `detalle_mercadeo` (
  `id_contacto` int(11) NOT NULL,
  `tema` varchar(150) DEFAULT NULL,
  `proyecto` varchar(150) DEFAULT NULL,
  `patrocinio` varchar(150) DEFAULT NULL,
  PRIMARY KEY (`id_contacto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `detalle_medio` (
  `id_contacto` int(11) NOT NULL,
  `categoria` varchar(100) DEFAULT NULL,
  `medio` varchar(100) DEFAULT NULL,
  `fuente` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id_contacto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `detalle_artista` (
  `id_contacto` int(11) NOT NULL,
  `agenda` text DEFAULT NULL,
  `filbo` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id_contacto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `detalle_editorial` (
  `id_contacto` int(11) NOT NULL,
  `descuento` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id_contacto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------- copia de datos (un bloque por modulo)
-- artistas
INSERT INTO `contactos` (`id_tipo_actor`, `nombre`, `perfil`, `organizacion`, `telefono`, `correo`, `disponibilidad`, `fecha_registro`, `creado_por`, `actualizado_en`, `actualizado_por`, `_origen`, `_id_origen`)
SELECT (SELECT `id_tipo_actor` FROM `tipos_actor` WHERE `nombre` = 'Artista'), COALESCE(`nombre`, ''), `perfil`, `organizacion`, `telefono`, NULLIF(TRIM(`correo`), ''), `disponibilidad`, `fecha_registro`, `creado_por`, `actualizado_en`, `actualizado_por`, 'artistas', `id` FROM `artistas`;
INSERT INTO `detalle_artista` (`id_contacto`, `agenda`, `filbo`)
SELECT c.`id_contacto`, a.`agenda`, CASE WHEN LOWER(TRIM(a.`filbo`)) IN ('sí','si','1','x','true','yes') THEN 1 WHEN LOWER(TRIM(a.`filbo`)) IN ('no','0','false') THEN 0 ELSE NULL END
FROM `artistas` a JOIN `contactos` c ON c.`_origen` = 'artistas' AND c.`_id_origen` = a.`id`;

-- asocajas (la caja pasa a ser el nombre del contacto)
INSERT INTO `contactos` (`id_tipo_actor`, `nombre`, `departamento`, `cargo`, `contacto`, `telefono`, `direccion`, `correo`, `fecha_registro`, `creado_por`, `actualizado_en`, `actualizado_por`, `_origen`, `_id_origen`)
SELECT (SELECT `id_tipo_actor` FROM `tipos_actor` WHERE `nombre` = 'Asocaja'), COALESCE(`caja`, ''), `departamento`, `cargo`, `contacto`, `telefono`, `direccion`, NULLIF(TRIM(`correo`), ''), `fecha_registro`, `creado_por`, `actualizado_en`, `actualizado_por`, 'asocajas', `id` FROM `asocajas`;

-- directivos
INSERT INTO `contactos` (`id_tipo_actor`, `nombre`, `apellido`, `organizacion`, `cargo`, `telefono`, `correo`, `fecha_registro`, `creado_por`, `actualizado_en`, `actualizado_por`, `_origen`, `_id_origen`)
SELECT (SELECT `id_tipo_actor` FROM `tipos_actor` WHERE `nombre` = 'Directivo'), COALESCE(`nombre`, ''), `apellido`, `entidad`, `cargo`, `telefono`, NULLIF(TRIM(`correo`), ''), `fecha_registro`, `creado_por`, `actualizado_en`, `actualizado_por`, 'directivos', `id` FROM `directivos`;
INSERT INTO `detalle_directivo` (`id_contacto`, `titulo`, `cedula`, `calidad`, `estado`, `integrante`, `vigencia`, `celular`)
SELECT c.`id_contacto`, d.`titulo`, NULLIF(TRIM(d.`cedula`), ''), d.`calidad`, d.`estado`, d.`integrante`, d.`vigencia`, d.`celular`
FROM `directivos` d JOIN `contactos` c ON c.`_origen` = 'directivos' AND c.`_id_origen` = d.`id`;

-- editoriales
INSERT INTO `contactos` (`id_tipo_actor`, `nombre`, `nit`, `contacto`, `telefono`, `direccion`, `correo`, `fecha_registro`, `creado_por`, `actualizado_en`, `actualizado_por`, `_origen`, `_id_origen`)
SELECT (SELECT `id_tipo_actor` FROM `tipos_actor` WHERE `nombre` = 'Editorial'), COALESCE(`nombre`, ''), NULLIF(TRIM(`nit`), ''), `contacto`, `telefono`, `direccion`, NULLIF(TRIM(`correo`), ''), `fecha_registro`, `creado_por`, `actualizado_en`, `actualizado_por`, 'editoriales', `id` FROM `editoriales`;
INSERT INTO `detalle_editorial` (`id_contacto`, `descuento`)
SELECT c.`id_contacto`, e.`descuento` FROM `editoriales` e JOIN `contactos` c ON c.`_origen` = 'editoriales' AND c.`_id_origen` = e.`id`;

-- instituciones educativas
INSERT INTO `contactos` (`id_tipo_actor`, `nombre`, `nit`, `contacto`, `cargo`, `telefono`, `direccion`, `correo`, `ciudad`, `fecha_registro`, `creado_por`, `actualizado_en`, `actualizado_por`, `_origen`, `_id_origen`)
SELECT (SELECT `id_tipo_actor` FROM `tipos_actor` WHERE `nombre` = 'Institución educativa'), COALESCE(`nombre`, ''), NULLIF(TRIM(`nit`), ''), `contacto`, `cargo`, `telefono`, `direccion`, NULLIF(TRIM(`correo`), ''), `ciudad`, `fecha_registro`, `creado_por`, `actualizado_en`, `actualizado_por`, 'instituciones-e', `id` FROM `instituciones_e`;
INSERT INTO `detalle_institucion` (`id_contacto`, `clase`, `calidad`, `jornada`)
SELECT c.`id_contacto`, i.`clase`, i.`calidad`, i.`jornada` FROM `instituciones_e` i JOIN `contactos` c ON c.`_origen` = 'instituciones-e' AND c.`_id_origen` = i.`id`;

-- medios
INSERT INTO `contactos` (`id_tipo_actor`, `nombre`, `correo`, `telefono`, `telefono_alterno`, `direccion`, `fecha_registro`, `creado_por`, `actualizado_en`, `actualizado_por`, `_origen`, `_id_origen`)
SELECT (SELECT `id_tipo_actor` FROM `tipos_actor` WHERE `nombre` = 'Medio'), COALESCE(`nombre`, ''), NULLIF(TRIM(`correo`), ''), `telefono`, `telefono2`, `direccion`, `fecha_registro`, `creado_por`, `actualizado_en`, `actualizado_por`, 'medios', `id` FROM `medios`;
INSERT INTO `detalle_medio` (`id_contacto`, `categoria`, `medio`, `fuente`)
SELECT c.`id_contacto`, m.`categoria`, m.`medio`, m.`fuente` FROM `medios` m JOIN `contactos` c ON c.`_origen` = 'medios' AND c.`_id_origen` = m.`id`;

-- mercadeo (la empresa pasa a ser la organizacion del contacto)
INSERT INTO `contactos` (`id_tipo_actor`, `nombre`, `organizacion`, `cargo`, `contacto`, `telefono`, `correo`, `direccion`, `fecha_registro`, `creado_por`, `actualizado_en`, `actualizado_por`, `_origen`, `_id_origen`)
SELECT (SELECT `id_tipo_actor` FROM `tipos_actor` WHERE `nombre` = 'Mercadeo'), COALESCE(`nombre`, ''), `empresa`, `cargo`, `contacto`, `telefono`, NULLIF(TRIM(`correo`), ''), `direccion`, `fecha_registro`, `creado_por`, `actualizado_en`, `actualizado_por`, 'mercadeo', `id` FROM `mercadeo`;
INSERT INTO `detalle_mercadeo` (`id_contacto`, `tema`, `proyecto`, `patrocinio`)
SELECT c.`id_contacto`, m.`tema`, m.`proyecto`, m.`patrocinio` FROM `mercadeo` m JOIN `contactos` c ON c.`_origen` = 'mercadeo' AND c.`_id_origen` = m.`id`;

-- practicantes
INSERT INTO `contactos` (`id_tipo_actor`, `nombre`, `telefono`, `correo`, `direccion`, `fecha_registro`, `creado_por`, `actualizado_en`, `actualizado_por`, `_origen`, `_id_origen`)
SELECT (SELECT `id_tipo_actor` FROM `tipos_actor` WHERE `nombre` = 'Practicante'), COALESCE(`nombre`, ''), `telefono`, NULLIF(TRIM(`correo`), ''), `direccion`, `fecha_registro`, `creado_por`, `actualizado_en`, `actualizado_por`, 'practicantes', `id` FROM `practicantes`;
INSERT INTO `detalle_personal` (`id_contacto`, `vinculacion`, `disciplina`, `generacion`, `inicio`, `fin`, `cumple`, `contacto_emergencia`, `telefono_emergencia`)
SELECT c.`id_contacto`, 'practicante', p.`disciplina`, p.`generacion`, p.`inicio`, p.`fin`, p.`cumple`, p.`contacto`, p.`telefono_contacto`
FROM `practicantes` p JOIN `contactos` c ON c.`_origen` = 'practicantes' AND c.`_id_origen` = p.`id`;

-- proveedores
INSERT INTO `contactos` (`id_tipo_actor`, `nombre`, `pais`, `direccion`, `telefono`, `correo`, `fecha_registro`, `creado_por`, `actualizado_en`, `actualizado_por`, `_origen`, `_id_origen`)
SELECT (SELECT `id_tipo_actor` FROM `tipos_actor` WHERE `nombre` = 'Proveedor'), COALESCE(`nombre`, ''), `pais`, `direccion`, `telefono`, NULLIF(TRIM(`correo`), ''), `fecha_registro`, `creado_por`, `actualizado_en`, `actualizado_por`, 'proveedores', `id` FROM `proveedores`;

-- talleristas
INSERT INTO `contactos` (`id_tipo_actor`, `nombre`, `telefono`, `correo`, `cargo`, `perfil`, `disponibilidad`, `fecha_registro`, `creado_por`, `actualizado_en`, `actualizado_por`, `_origen`, `_id_origen`)
SELECT (SELECT `id_tipo_actor` FROM `tipos_actor` WHERE `nombre` = 'Tallerista'), COALESCE(`nombre`, ''), `telefono`, NULLIF(TRIM(`correo`), ''), `cargo`, `perfil`, `disponibilidad`, `fecha_registro`, `creado_por`, `actualizado_en`, `actualizado_por`, 'talleristas', `id` FROM `talleristas`;

-- equipo (team_pombo): el celular pasa a ser el telefono alterno
INSERT INTO `contactos` (`id_tipo_actor`, `nombre`, `apellido`, `telefono_alterno`, `correo`, `cargo`, `telefono`, `fecha_registro`, `creado_por`, `actualizado_en`, `actualizado_por`, `_origen`, `_id_origen`)
SELECT (SELECT `id_tipo_actor` FROM `tipos_actor` WHERE `nombre` = 'Equipo Pombo'), COALESCE(`nombre`, ''), `apellido`, `celular`, NULLIF(TRIM(`correo`), ''), `cargo`, `telefono`, `fecha_registro`, `creado_por`, `actualizado_en`, `actualizado_por`, 'team', `id` FROM `team_pombo`;
INSERT INTO `detalle_personal` (`id_contacto`, `vinculacion`, `inicio`, `fin`, `cumple`, `contacto_emergencia`)
SELECT c.`id_contacto`, 'equipo', t.`inicio`, t.`fin`, t.`cumple`, t.`contacto`
FROM `team_pombo` t JOIN `contactos` c ON c.`_origen` = 'team' AND c.`_id_origen` = t.`id`;

-- etiquetas asignadas
INSERT INTO `contacto_etiquetas` (`id_contacto`, `id_etiqueta`)
SELECT DISTINCT c.`id_contacto`, re.`etiqueta_id` FROM `registro_etiquetas` re JOIN `contactos` c ON c.`_origen` = re.`modulo` AND c.`_id_origen` = re.`registro_id`;

-- ---------------------------------------------------------------- se eliminan las tablas anteriores
DROP TABLE `registro_etiquetas`, `artistas`, `asocajas`, `directivos`, `editoriales`, `instituciones_e`, `medios`, `mercadeo`, `practicantes`, `proveedores`, `talleristas`, `team_pombo`;

-- ---------------------------------------------------------------- nombres de llaves y llaves foraneas
ALTER TABLE `usuarios` CHANGE `id` `id_usuario` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `etiquetas` CHANGE `id` `id_etiqueta` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `contactos`
  DROP INDEX `idx_contactos_origen`, DROP COLUMN `_origen`, DROP COLUMN `_id_origen`,
  ADD CONSTRAINT `fk_contactos_tipo_actor` FOREIGN KEY (`id_tipo_actor`) REFERENCES `tipos_actor` (`id_tipo_actor`) ON DELETE NO ACTION,
  ADD CONSTRAINT `fk_contactos_creado_por` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_contactos_actualizado_por` FOREIGN KEY (`actualizado_por`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL;

ALTER TABLE `contacto_etiquetas`
  ADD CONSTRAINT `fk_contacto_etiquetas_contacto` FOREIGN KEY (`id_contacto`) REFERENCES `contactos` (`id_contacto`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_contacto_etiquetas_etiqueta` FOREIGN KEY (`id_etiqueta`) REFERENCES `etiquetas` (`id_etiqueta`) ON DELETE CASCADE;

ALTER TABLE `detalle_directivo` ADD CONSTRAINT `fk_detalle_directivo_contacto` FOREIGN KEY (`id_contacto`) REFERENCES `contactos` (`id_contacto`) ON DELETE CASCADE;
ALTER TABLE `detalle_institucion` ADD CONSTRAINT `fk_detalle_institucion_contacto` FOREIGN KEY (`id_contacto`) REFERENCES `contactos` (`id_contacto`) ON DELETE CASCADE;
ALTER TABLE `detalle_personal` ADD CONSTRAINT `fk_detalle_personal_contacto` FOREIGN KEY (`id_contacto`) REFERENCES `contactos` (`id_contacto`) ON DELETE CASCADE;
ALTER TABLE `detalle_mercadeo` ADD CONSTRAINT `fk_detalle_mercadeo_contacto` FOREIGN KEY (`id_contacto`) REFERENCES `contactos` (`id_contacto`) ON DELETE CASCADE;
ALTER TABLE `detalle_medio` ADD CONSTRAINT `fk_detalle_medio_contacto` FOREIGN KEY (`id_contacto`) REFERENCES `contactos` (`id_contacto`) ON DELETE CASCADE;
ALTER TABLE `detalle_artista` ADD CONSTRAINT `fk_detalle_artista_contacto` FOREIGN KEY (`id_contacto`) REFERENCES `contactos` (`id_contacto`) ON DELETE CASCADE;
ALTER TABLE `detalle_editorial` ADD CONSTRAINT `fk_detalle_editorial_contacto` FOREIGN KEY (`id_contacto`) REFERENCES `contactos` (`id_contacto`) ON DELETE CASCADE;

SET FOREIGN_KEY_CHECKS = @FK_ANTERIOR;
