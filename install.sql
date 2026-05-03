-- ============================================================
--  SentiManPHP v3 — Esquema unificado de base de datos
--  Una sola tabla `dictionary` con una columna por categoría.
-- ============================================================

CREATE DATABASE IF NOT EXISTS `sentiman`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `sentiman`;

CREATE TABLE IF NOT EXISTS `dictionary` (
  `id`                int(11)      NOT NULL AUTO_INCREMENT,
  `palabra`           varchar(255) NOT NULL,
  `fecha_registro`    timestamp    NOT NULL DEFAULT current_timestamp(),
  `fecha_modificacion` timestamp   NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),

  -- ===== CAPA 1: SENTIMIENTO GENERAL =====
  `positiva`          float NOT NULL DEFAULT 0,
  `negativa`          float NOT NULL DEFAULT 0,
  `neutral`           float NOT NULL DEFAULT 0,

  -- ===== CAPA 2: EMOCIONES BÁSICAS (Plutchik) =====
  `alegria`           float NOT NULL DEFAULT 0,
  `tristeza`          float NOT NULL DEFAULT 0,
  `ira`               float NOT NULL DEFAULT 0,
  `miedo`             float NOT NULL DEFAULT 0,
  `sorpresa`          float NOT NULL DEFAULT 0,
  `asco`              float NOT NULL DEFAULT 0,
  `confianza`         float NOT NULL DEFAULT 0,
  `anticipacion`      float NOT NULL DEFAULT 0,

  -- ===== CAPA 3: EMOCIONES COMPLEJAS / SOCIALES =====
  `gratitud`          float NOT NULL DEFAULT 0,
  `orgullo`           float NOT NULL DEFAULT 0,
  `admiracion`        float NOT NULL DEFAULT 0,
  `compasion`         float NOT NULL DEFAULT 0,
  `esperanza`         float NOT NULL DEFAULT 0,
  `aceptacion`        float NOT NULL DEFAULT 0,
  `verguenza`         float NOT NULL DEFAULT 0,
  `culpa`             float NOT NULL DEFAULT 0,
  `envidia`           float NOT NULL DEFAULT 0,
  `placer_ajeno`      float NOT NULL DEFAULT 0,
  `apatia`            float NOT NULL DEFAULT 0,
  `ambivalencia`      float NOT NULL DEFAULT 0,
  `soledad`           float NOT NULL DEFAULT 0,
  `humildad`          float NOT NULL DEFAULT 0,

  -- ===== CAPA 4: INTENCIÓN, SARCASMO, INTENSIDAD =====
  `queja`             float NOT NULL DEFAULT 0,
  `elogio`            float NOT NULL DEFAULT 0,
  `amenaza`           float NOT NULL DEFAULT 0,
  `peticion`          float NOT NULL DEFAULT 0,
  `sarcasmo`          float NOT NULL DEFAULT 0,
  `urgencia`          float NOT NULL DEFAULT 0,
  `intensidad_alta`   float NOT NULL DEFAULT 0,
  `intensidad_baja`   float NOT NULL DEFAULT 0,

  -- ===== CAPA 5: ANÁLISIS COMERCIAL =====
  `intencion_compra`    float NOT NULL DEFAULT 0,
  `riesgo_abandono`     float NOT NULL DEFAULT 0,
  `fidelizacion`        float NOT NULL DEFAULT 0,
  `satisfaccion_alta`   float NOT NULL DEFAULT 0,
  `insatisfaccion`      float NOT NULL DEFAULT 0,
  `objecion_precio`     float NOT NULL DEFAULT 0,
  `objecion_valor`      float NOT NULL DEFAULT 0,
  `objecion_tiempo`     float NOT NULL DEFAULT 0,
  `objecion_necesidad`  float NOT NULL DEFAULT 0,
  `objecion_confianza`  float NOT NULL DEFAULT 0,
  `comparacion`         float NOT NULL DEFAULT 0,
  `escasez`             float NOT NULL DEFAULT 0,
  `calidad_alta`        float NOT NULL DEFAULT 0,
  `calidad_baja`        float NOT NULL DEFAULT 0,
  `servicio_bueno`      float NOT NULL DEFAULT 0,
  `servicio_malo`       float NOT NULL DEFAULT 0,

  PRIMARY KEY (`id`),
  UNIQUE KEY `unq_palabra` (`palabra`),
  KEY `idx_palabra_search` (`palabra`(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `analisis_historico` (
  `id`                   int(11)      NOT NULL AUTO_INCREMENT,
  `fecha`                timestamp    NOT NULL DEFAULT current_timestamp(),
  `fuente`               varchar(50)  NOT NULL DEFAULT 'web',
  `texto_original`       text         NOT NULL,
  `total_palabras`       int(11)      NOT NULL DEFAULT 0,
  `palabras_encontradas` int(11)      NOT NULL DEFAULT 0,
  `cobertura_pct`        float        NOT NULL DEFAULT 0,
  `puntaje_positivo`     float        NOT NULL DEFAULT 0,
  `puntaje_negativo`     float        NOT NULL DEFAULT 0,
  `puntaje_neutral`      float        NOT NULL DEFAULT 0,
  `porcentaje_positivo`  float        NOT NULL DEFAULT 0,
  `porcentaje_negativo`  float        NOT NULL DEFAULT 0,
  `porcentaje_neutral`   float        NOT NULL DEFAULT 0,
  `sentimiento_global`   varchar(20)  NOT NULL DEFAULT 'neutral',
  `polaridad`            float        NOT NULL DEFAULT 0,
  `subjetividad`         float        NOT NULL DEFAULT 0,
  `intensidad`           float        NOT NULL DEFAULT 0,
  `etiqueta`             varchar(100) NOT NULL DEFAULT '',
  `emociones_basicas`    text         NULL,
  `emociones_complejas`  text         NULL,
  `intencion_json`       text         NULL,
  `comercial`            text         NULL,
  `activacion`           float        NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_fecha` (`fecha`),
  KEY `idx_sentimiento` (`sentimiento_global`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE OR REPLACE VIEW `v_estadisticas` AS
SELECT
  COUNT(*)                            AS total_analisis,
  AVG(porcentaje_positivo)            AS media_positivo,
  AVG(porcentaje_negativo)            AS media_negativo,
  AVG(porcentaje_neutral)             AS media_neutral,
  AVG(polaridad)                      AS media_polaridad,
  AVG(subjetividad)                   AS media_subjetividad,
  AVG(total_palabras)                 AS media_palabras,
  AVG(cobertura_pct)                  AS media_cobertura,
  SUM(sentimiento_global='positivo')  AS total_positivos,
  SUM(sentimiento_global='negativo')  AS total_negativos,
  SUM(sentimiento_global='neutral')   AS total_neutrales,
  MIN(fecha)                          AS primer_analisis,
  MAX(fecha)                          AS ultimo_analisis
FROM analisis_historico;

COMMIT;
