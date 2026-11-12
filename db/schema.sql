-- ============================================================================
-- SIRAD - Esquema de la base de datos `directorio` (modelo generalizado)
-- 12 tablas y 12 llaves foraneas. MariaDB 10.4 (InnoDB, utf8mb4).
--
-- Modelo: la tabla `contactos` guarda los datos comunes de los once actores del
-- directorio; `tipos_actor` indica de cual se trata y cada tabla `detalle_*` guarda
-- solo los atributos propios de su tipo (relacion 1 a 0..1: su llave primaria es a la
-- vez llave foranea de contactos). Talleristas, proveedores y asocajas no necesitan
-- detalle. Las etiquetas se asignan a un contacto con `contacto_etiquetas`.
--
-- Llaves unicas (impiden registros repetidos dentro de un tipo de actor):
--   contactos (id_tipo_actor, correo), contactos (id_tipo_actor, nit) y
--   detalle_directivo (cedula). Un valor vacio se guarda como NULL.
--
-- Para una base con el esquema anterior (14 tablas, una por modulo) use la migracion
-- db/migraciones/2026-11-14_modelo_generalizado.sql, que conserva los datos.
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `directorio` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `directorio`;

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8mb4 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;

-- ----------------------------------------------------------------------------
-- usuarios: cuentas del sistema (aprobacion, permisos por modulo, alertas y
-- recuperacion de contrasena viajan en columnas de esta misma tabla)
-- ----------------------------------------------------------------------------
CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) NOT NULL,
  `correo` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `rol` enum('admin','usuario') NOT NULL DEFAULT 'usuario',
  `intentos_fallidos` int(11) NOT NULL DEFAULT 0,
  `bloqueado_hasta` datetime DEFAULT NULL,
  `pregunta_seguridad` varchar(255) DEFAULT NULL,
  `respuesta_seguridad_hash` varchar(255) DEFAULT NULL,
  `estado` enum('pendiente','aprobado','rechazado') NOT NULL DEFAULT 'pendiente',
  `politica_version` varchar(10) DEFAULT NULL,
  `politica_aceptada_en` datetime DEFAULT NULL,
  `puede_importar` tinyint(1) NOT NULL DEFAULT 0,
  `permisos_modulos` text DEFAULT NULL,
  `ultimo_acceso` datetime DEFAULT NULL,
  `ultimo_intento_pendiente` datetime DEFAULT NULL,
  `alertas_vistas_hasta` datetime DEFAULT NULL,
  `reset_token_hash` char(64) DEFAULT NULL,
  `reset_expira` datetime DEFAULT NULL,
  `reset_enviado_en` datetime DEFAULT NULL,
  `motivo_solicitud` varchar(500) DEFAULT NULL,
  `ip_solicitud` varchar(45) DEFAULT NULL,
  `importaciones_desde` datetime DEFAULT NULL,
  `importaciones_cuenta` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_usuario`),
  UNIQUE KEY `correo` (`correo`),
  KEY `idx_usuarios_reset_token` (`reset_token_hash`),
  KEY `idx_usuarios_ip_solicitud` (`ip_solicitud`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------------------
-- tipos_actor: los once actores del directorio
-- ----------------------------------------------------------------------------
CREATE TABLE `tipos_actor` (
  `id_tipo_actor` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `descripcion` varchar(150) DEFAULT NULL,
  PRIMARY KEY (`id_tipo_actor`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------------------
-- contactos: datos comunes de todos los actores
-- ----------------------------------------------------------------------------
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
  PRIMARY KEY (`id_contacto`),
  UNIQUE KEY `uq_contactos_tipo_correo` (`id_tipo_actor`,`correo`),
  UNIQUE KEY `uq_contactos_tipo_nit` (`id_tipo_actor`,`nit`),
  KEY `fk_contactos_creado_por` (`creado_por`),
  KEY `fk_contactos_actualizado_por` (`actualizado_por`),
  CONSTRAINT `fk_contactos_tipo_actor` FOREIGN KEY (`id_tipo_actor`) REFERENCES `tipos_actor` (`id_tipo_actor`) ON DELETE NO ACTION,
  CONSTRAINT `fk_contactos_creado_por` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL,
  CONSTRAINT `fk_contactos_actualizado_por` FOREIGN KEY (`actualizado_por`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------------------
-- etiquetas y su asignacion a contactos
-- ----------------------------------------------------------------------------
CREATE TABLE `etiquetas` (
  `id_etiqueta` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `color` varchar(20) NOT NULL DEFAULT '#2563eb',
  PRIMARY KEY (`id_etiqueta`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `contacto_etiquetas` (
  `id_contacto` int(11) NOT NULL,
  `id_etiqueta` int(11) NOT NULL,
  PRIMARY KEY (`id_contacto`,`id_etiqueta`),
  KEY `fk_contacto_etiquetas_etiqueta` (`id_etiqueta`),
  CONSTRAINT `fk_contacto_etiquetas_contacto` FOREIGN KEY (`id_contacto`) REFERENCES `contactos` (`id_contacto`) ON DELETE CASCADE,
  CONSTRAINT `fk_contacto_etiquetas_etiqueta` FOREIGN KEY (`id_etiqueta`) REFERENCES `etiquetas` (`id_etiqueta`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------------------
-- detalle_*: atributos propios de cada tipo (1 a 0..1 con contactos)
-- ----------------------------------------------------------------------------
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
  UNIQUE KEY `uq_detalle_directivo_cedula` (`cedula`),
  CONSTRAINT `fk_detalle_directivo_contacto` FOREIGN KEY (`id_contacto`) REFERENCES `contactos` (`id_contacto`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `detalle_institucion` (
  `id_contacto` int(11) NOT NULL,
  `clase` varchar(100) DEFAULT NULL,
  `calidad` varchar(100) DEFAULT NULL,
  `jornada` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id_contacto`),
  CONSTRAINT `fk_detalle_institucion_contacto` FOREIGN KEY (`id_contacto`) REFERENCES `contactos` (`id_contacto`) ON DELETE CASCADE
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
  PRIMARY KEY (`id_contacto`),
  CONSTRAINT `fk_detalle_personal_contacto` FOREIGN KEY (`id_contacto`) REFERENCES `contactos` (`id_contacto`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `detalle_mercadeo` (
  `id_contacto` int(11) NOT NULL,
  `tema` varchar(150) DEFAULT NULL,
  `proyecto` varchar(150) DEFAULT NULL,
  `patrocinio` varchar(150) DEFAULT NULL,
  PRIMARY KEY (`id_contacto`),
  CONSTRAINT `fk_detalle_mercadeo_contacto` FOREIGN KEY (`id_contacto`) REFERENCES `contactos` (`id_contacto`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `detalle_medio` (
  `id_contacto` int(11) NOT NULL,
  `categoria` varchar(100) DEFAULT NULL,
  `medio` varchar(100) DEFAULT NULL,
  `fuente` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id_contacto`),
  CONSTRAINT `fk_detalle_medio_contacto` FOREIGN KEY (`id_contacto`) REFERENCES `contactos` (`id_contacto`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `detalle_artista` (
  `id_contacto` int(11) NOT NULL,
  `agenda` text DEFAULT NULL,
  `filbo` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id_contacto`),
  CONSTRAINT `fk_detalle_artista_contacto` FOREIGN KEY (`id_contacto`) REFERENCES `contactos` (`id_contacto`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `detalle_editorial` (
  `id_contacto` int(11) NOT NULL,
  `descuento` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id_contacto`),
  CONSTRAINT `fk_detalle_editorial_contacto` FOREIGN KEY (`id_contacto`) REFERENCES `contactos` (`id_contacto`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------------------
-- Datos base: los once tipos de actor
-- ----------------------------------------------------------------------------
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

/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
