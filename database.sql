-- ============================================================
--  database.sql — Base de datos del formulario de inscripción
-- ------------------------------------------------------------
--  Importar con phpMyAdmin, o por consola:
--      mysql -u root -p < database.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS inscripciones
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE inscripciones;

CREATE TABLE IF NOT EXISTS inscripciones (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cedula         VARCHAR(20)  NOT NULL,
  nombre         VARCHAR(150) NOT NULL,
  celular        VARCHAR(20)  NOT NULL,
  email          VARCHAR(150) NOT NULL,
  fecha_registro TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_inscripciones_cedula (cedula),
  UNIQUE KEY uq_inscripciones_email (email)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;
