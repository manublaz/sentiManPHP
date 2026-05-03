# 🧠 SentiManPHP

> **Analizador de sentimiento multicapa en español** — proyecto educativo en PHP + MySQL pensado para alumnos que aprenden web scraping, bases de datos y procesamiento de texto.

[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4?logo=php)]()
[![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-4479a1?logo=mysql)]()
[![License](https://img.shields.io/badge/license-MIT-green)]()
[![Idioma](https://img.shields.io/badge/idioma-Español-red)]()

---

## ✨ Características

- 🎚️ **5 capas de análisis** simultáneas: sentimiento general, emociones básicas, emociones complejas, intención y comercial
- 📚 **Diccionario unificado** con más de **7.000 palabras y expresiones** en español, cada una con pesos en hasta 49 categorías
- 🔌 **API ultrasimple** — devuelve `texto plano` (clave:valor), perfecta para alumnos que empiezan con scrapers
- 🧰 **Instalador visual paso a paso**, sin necesidad de tocar phpMyAdmin
- 📁 **Editor de diccionario** con carga de SQL `INSERT/REPLACE/UPDATE` desde la propia interfaz
- 📊 **Visualizaciones interactivas** con Chart.js (radar, polar, donut, barras)
- 🇪🇸 **Análisis específico de español** con detección de negaciones, intensificadores, n-gramas (2, 3 y 4 palabras), modismos y sarcasmo
- 🗄️ **Histórico** de todos los análisis con estadísticas globales

---

## 📑 Tabla de contenidos

- [Instalación](#-instalación)
- [Las 5 capas de análisis](#-las-5-capas-de-análisis)
- [Estructura del proyecto](#-estructura-del-proyecto)
- [Uso de la API](#-uso-de-la-api)
- [Esquema de base de datos](#-esquema-de-base-de-datos)
- [Editar el diccionario](#-editar-el-diccionario)
- [Tecnologías](#-tecnologías)
- [Proyecto educativo](#-proyecto-educativo)
- [Licencia](#-licencia)

---

## 🚀 Instalación

### Requisitos

- **XAMPP** (o LAMP / WAMP / MAMP equivalente) con:
  - PHP 7.4 o superior
  - MySQL/MariaDB 5.7 o superior
  - Apache
- Navegador moderno

### Pasos

1. **Descarga** o clona este repositorio en `htdocs`:
   ```bash
   cd C:\xampp\htdocs           # Windows
   cd /Applications/XAMPP/htdocs # macOS
   git clone https://github.com/tu-usuario/sentimanphp.git sentiman
   ```

2. **Arranca** Apache y MySQL desde el panel de XAMPP.

3. **Visita el instalador** en tu navegador:
   ```
   http://localhost/sentiman/install.php
   ```

4. Completa los **6 pasos** del instalador:
   - Verificación del entorno
   - Conexión a MySQL (host, puerto, usuario, contraseña, BD)
   - Creación de tablas
   - Importación del diccionario (~7.000 palabras)
   - Generación de `config.php`
   - Resumen final

5. **Listo.** Accede a la aplicación:
   ```
   http://localhost/sentiman/
   ```

> 💡 Si tu MySQL escucha en otro puerto (p. ej. 3307 cuando hay conflicto con otro servicio), el instalador te lo permite especificar en el paso 2.

---

## 🎚️ Las 5 capas de análisis

| # | Capa | Descripción | Categorías |
|---|------|-------------|------------|
| 1 | ⚖️ **General** | Polaridad clásica positiva/negativa/neutral | `positiva`, `negativa`, `neutral` |
| 2 | 🎭 **Emociones básicas** | Las 8 emociones primarias de Robert Plutchik | `alegria`, `tristeza`, `ira`, `miedo`, `sorpresa`, `asco`, `confianza`, `anticipacion` |
| 3 | 🧠 **Emociones complejas** | Emociones sociales y autorreferenciales | `gratitud`, `orgullo`, `admiracion`, `compasion`, `esperanza`, `aceptacion`, `verguenza`, `culpa`, `envidia`, `placer_ajeno`, `apatia`, `ambivalencia`, `soledad`, `humildad` |
| 4 | 🎯 **Intención** | Para qué fue escrito el texto | `queja`, `elogio`, `amenaza`, `peticion`, `sarcasmo`, `urgencia`, `intensidad_alta`, `intensidad_baja` |
| 5 | 🛍️ **Comercial** | Señales útiles para marketing y ventas | `intencion_compra`, `riesgo_abandono`, `fidelizacion`, `satisfaccion_alta`, `insatisfaccion`, `objecion_precio`, `objecion_valor`, `objecion_tiempo`, `objecion_necesidad`, `objecion_confianza`, `comparacion`, `escasez`, `calidad_alta`, `calidad_baja`, `servicio_bueno`, `servicio_malo` |

### ¿Por qué multicapa?

Un texto puede ser **positivo en sentimiento general** y al mismo tiempo expresar **queja** en intención y **riesgo de abandono** en la capa comercial. Las capas revelan dimensiones que un análisis simple positivo/negativo pasa por alto.

**Ejemplo:**
> "Llevo años con vosotros pero el último mes el servicio ha sido lamentable. Voy a cancelar mi suscripción."

| Capa | Resultado |
|------|-----------|
| General | Mixto (palabras como *años*, *cancelar*, *lamentable*) |
| Emociones básicas | `ira` alta, `tristeza` media |
| Emociones complejas | `humildad` baja, `apatia` |
| Intención | `queja`, `amenaza`, `urgencia` |
| Comercial | `riesgo_abandono` muy alto, `fidelizacion` alta (paradoja típica), `servicio_malo` |

---

## 📁 Estructura del proyecto

```
sentiman/
├── install.php                    # Instalador visual (6 pasos)
├── install.sql                    # Esquema unificado de BD
├── dictionary.sql                 # ~7.000 palabras con todos los pesos
├── config.php                     # Generado por el instalador
├── index.php                      # Interfaz web principal
├── sentiman.php                   # Motor de análisis multicapa
├── api.php                        # Endpoint público (texto plano)
├── upload_sql.php                 # Cargador de actualizaciones SQL
├── chart.min.js                   # Chart.js para visualizaciones
├── chartjs-plugin-datalabels.min.js
├── interface/
│   └── logo.jpeg
└── README.md
```

---

## 🔌 Uso de la API

La API devuelve **texto plano** con formato `clave:valor` (un par por línea). Sin JSON, sin headers complicados — pensada para que un alumno con conocimientos básicos pueda integrarla en cualquier lenguaje en 3 líneas.

### Endpoint

```
http://localhost/sentiman/api.php?texto=TU_TEXTO
```

### Parámetros

| Parámetro | Obligatorio | Valores | Descripción |
|-----------|-------------|---------|-------------|
| `texto` | ✅ | string (máx 10.000 chars) | Texto a analizar |
| `capa` | ❌ | `general` (defecto), `emociones_basicas`, `emociones_complejas`, `intencion`, `comercial`, `todas` | Capa a devolver |

### Ejemplo: capa general

**Petición:**
```
GET /sentiman/api.php?texto=Esta+película+es+maravillosa
```

**Respuesta:**
```
sentimiento:positivo
positivo:78.50
negativo:12.30
neutral:9.20
polaridad:0.73
subjetividad:0.85
intensidad:7.40
etiqueta:Positivo
palabras:4
encontradas:3
cobertura:75.00
```

### Ejemplo: capa comercial

**Petición:**
```
GET /sentiman/api.php?texto=Quiero+cancelar+mi+suscripción&capa=comercial
```

**Respuesta:**
```
capa:comercial
categorias:1
riesgo_abandono:9.0
dominante:riesgo_abandono
```

### Ejemplo: todas las capas

```
GET /sentiman/api.php?texto=Estoy+muy+feliz&capa=todas
```

### Cliente PHP

```php
<?php
$texto = "Estoy muy decepcionado, voy a cancelar el servicio";
$url   = "http://localhost/sentiman/api.php?capa=comercial&texto=" . urlencode($texto);
$resp  = file_get_contents($url);

// Parsear respuesta clave:valor
$datos = [];
foreach (explode("\n", trim($resp)) as $linea) {
    if (str_contains($linea, ':')) {
        [$k, $v] = explode(':', $linea, 2);
        $datos[trim($k)] = trim($v);
    }
}

print_r($datos);
```

### Cliente Python

```python
import requests
from urllib.parse import urlencode

texto = "Estoy harta del servicio, voy a darme de baja"
url = "http://localhost/sentiman/api.php?" + urlencode({
    "texto": texto,
    "capa": "comercial"
})

resp = requests.get(url).text
datos = dict(linea.split(":", 1) for linea in resp.strip().split("\n") if ":" in linea)
print(datos)
```

### Cliente curl (línea de comandos)

```bash
curl "http://localhost/sentiman/api.php?texto=Me%20encanta&capa=emociones_basicas"
```

---

## 🗄️ Esquema de base de datos

### Tabla `dictionary`

Una sola tabla con una columna por categoría. Cada palabra puede tener pesos en varias capas a la vez.

```sql
CREATE TABLE dictionary (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    palabra     VARCHAR(255) UNIQUE NOT NULL,

    -- Capa general
    positiva    FLOAT DEFAULT 0,
    negativa    FLOAT DEFAULT 0,
    neutral     FLOAT DEFAULT 0,

    -- Capa emociones básicas
    alegria     FLOAT DEFAULT 0,
    tristeza    FLOAT DEFAULT 0,
    ira         FLOAT DEFAULT 0,
    miedo       FLOAT DEFAULT 0,
    -- ... 45 columnas más, una por categoría
);
```

### Tabla `analisis_historico`

Guarda cada análisis ejecutado (desde web o API) con todos los puntajes de todas las capas.

### Vista `v_estadisticas`

Agregaciones globales (medias, totales por sentimiento) listas para mostrar en el dashboard.

---

## 📚 Editar el diccionario

Hay tres formas de añadir o modificar palabras:

### 1) Desde la interfaz web

Pestaña **Diccionario → Subir actualización SQL**. Sube un archivo `.sql` y el sistema:
- Acepta `INSERT`, `REPLACE`, `UPDATE` sobre la tabla `dictionary`
- Rechaza automáticamente `DROP`, `DELETE`, `ALTER`, `TRUNCATE` y otras operaciones peligrosas

### 2) Plantilla para añadir palabras nuevas

```sql
USE `sentiman`;

REPLACE INTO `dictionary` (palabra, positiva, negativa, alegria, gratitud) VALUES
('te quiero',          9, 0, 8, 0),
('muchísimas gracias', 8, 0, 0, 9);
```

> Solo se listan las columnas relevantes — las demás se quedan a 0 por defecto.

### 3) Plantilla para modificar palabras existentes

```sql
USE `sentiman`;

-- Por id (rápido si conoces el id)
UPDATE `dictionary`
SET alegria = 9, positiva = 8
WHERE id = 1234;

-- Por palabra exacta
UPDATE `dictionary`
SET ira = 9, negativa = 9
WHERE palabra = 'odio';

-- Modificar varias columnas a la vez
UPDATE `dictionary`
SET intencion_compra = 8, satisfaccion_alta = 7
WHERE palabra = 'me lo llevo';
```

### Columnas válidas

```
positiva, negativa, neutral,
alegria, tristeza, ira, miedo, sorpresa, asco, confianza, anticipacion,
gratitud, orgullo, admiracion, compasion, esperanza, aceptacion,
verguenza, culpa, envidia, placer_ajeno, apatia, ambivalencia, soledad, humildad,
queja, elogio, amenaza, peticion, sarcasmo, urgencia, intensidad_alta, intensidad_baja,
intencion_compra, riesgo_abandono, fidelizacion, satisfaccion_alta, insatisfaccion,
objecion_precio, objecion_valor, objecion_tiempo, objecion_necesidad, objecion_confianza,
comparacion, escasez, calidad_alta, calidad_baja, servicio_bueno, servicio_malo
```

Los pesos van de **0 a 10**.

---

## 🛠️ Tecnologías

- **Backend**: PHP 7.4+ (mysqli)
- **Base de datos**: MySQL / MariaDB
- **Frontend**: HTML + CSS + JS vanilla
- **Visualizaciones**: Chart.js + chartjs-plugin-datalabels
- **Servidor recomendado**: XAMPP / Apache

---

## 🎓 Proyecto educativo

SentiManPHP está pensado para que los alumnos:

1. **Construyan un scraper** (Python, PHP, JS, lo que sea)
2. **Envíen el texto extraído a la API** de SentiManPHP
3. **Reciban un análisis** estructurado y visual
4. **Aprendan a interpretar** las distintas capas
5. **Mejoren el diccionario** subiendo sus propios pesos en SQL

El formato `clave:valor` de la API se eligió a propósito: es **trivial de parsear** en cualquier lenguaje sin necesidad de librerías para JSON ni de configurar headers.

---

## 📜 Licencia

MIT — libre de usar, modificar y redistribuir.

---

<p align="center">
  Hecho con ❤️ para enseñar a programar y a pensar en datos
</p>
