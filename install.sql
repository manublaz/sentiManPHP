-- ============================================================
--  SentiManPHP v2 — Script de instalación de base de datos
--  Ejecuta este archivo en phpMyAdmin o desde la terminal MySQL
-- ============================================================

CREATE DATABASE IF NOT EXISTS `sentiman`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `sentiman`;

-- Tabla del diccionario de sentimientos
CREATE TABLE IF NOT EXISTS `dictionary` (
  `id`                int(11)      NOT NULL AUTO_INCREMENT,
  `fecharegistro`     timestamp    NOT NULL DEFAULT current_timestamp(),
  `fechamodificacion` varchar(255) NOT NULL DEFAULT '',
  `palabra`           varchar(500) NOT NULL,
  `positiva`          float        NOT NULL DEFAULT 0,
  `negativa`          float        NOT NULL DEFAULT 0,
  `neutral`           float        NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_palabra` (`palabra`(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla histórico de análisis
CREATE TABLE IF NOT EXISTS `analisis_historico` (
  `id`                  int(11)      NOT NULL AUTO_INCREMENT,
  `fecha`               timestamp    NOT NULL DEFAULT current_timestamp(),
  `fuente`              varchar(50)  NOT NULL DEFAULT 'web',   -- 'web' o 'api'
  `texto_original`      text         NOT NULL,
  `total_palabras`      int(11)      NOT NULL DEFAULT 0,
  `palabras_encontradas` int(11)     NOT NULL DEFAULT 0,
  `cobertura_pct`       float        NOT NULL DEFAULT 0,
  `puntaje_positivo`    float        NOT NULL DEFAULT 0,
  `puntaje_negativo`    float        NOT NULL DEFAULT 0,
  `puntaje_neutral`     float        NOT NULL DEFAULT 0,
  `porcentaje_positivo` float        NOT NULL DEFAULT 0,
  `porcentaje_negativo` float        NOT NULL DEFAULT 0,
  `porcentaje_neutral`  float        NOT NULL DEFAULT 0,
  `sentimiento_global`  varchar(20)  NOT NULL DEFAULT 'neutral',
  `polaridad`           float        NOT NULL DEFAULT 0,
  `subjetividad`        float        NOT NULL DEFAULT 0,
  `intensidad`          float        NOT NULL DEFAULT 0,
  `etiqueta`            varchar(100) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `idx_fecha` (`fecha`),
  KEY `idx_sentimiento` (`sentimiento_global`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Vista para estadísticas rápidas del histórico
CREATE OR REPLACE VIEW `v_estadisticas` AS
SELECT
  COUNT(*)                          AS total_analisis,
  AVG(porcentaje_positivo)          AS media_positivo,
  AVG(porcentaje_negativo)          AS media_negativo,
  AVG(porcentaje_neutral)           AS media_neutral,
  AVG(polaridad)                    AS media_polaridad,
  AVG(subjetividad)                 AS media_subjetividad,
  AVG(total_palabras)               AS media_palabras,
  AVG(cobertura_pct)                AS media_cobertura,
  SUM(sentimiento_global='positivo') AS total_positivos,
  SUM(sentimiento_global='negativo') AS total_negativos,
  SUM(sentimiento_global='neutral')  AS total_neutrales,
  MIN(fecha)                         AS primer_analisis,
  MAX(fecha)                         AS ultimo_analisis
FROM analisis_historico;

COMMIT;
