# README: Analizador de Sentimiento Multicapa (SentiManPHP)

[ PHP 7.4+ ] [ MySQL 5.7+ ] [ Licencia: MIT ] [ Idioma: Español ]

Analizador de sentimiento multicapa en español con 5 capas de análisis, diccionario unificado y visualizaciones interactivas. Proyecto educativo en PHP + MySQL, diseñado para que alumnos de scraping puedan integrarlo fácilmente.

---

## ✨ CARACTERÍSTICAS
- 🎚️ 5 capas de análisis simultáneas: general, emociones básicas, emociones complejas, intención y comercial.
- 📚 Diccionario unificado con más de 7.000 palabras y expresiones en español, cada una con pesos en hasta 49 categorías.
- 🔌 API ultrasimple — devuelve texto plano (`clave:valor`), perfecta para principiantes.
- 🧰 Instalador visual paso a paso, sin necesidad de tocar phpMyAdmin.
- 📁 Editor de diccionario con carga de archivos SQL (INSERT/REPLACE/UPDATE) desde la propia interfaz.
- 📊 Visualizaciones interactivas con Chart.js (radar, polar, donut, barras).
- 🇪🇸 Análisis específico de español con detección de negaciones, intensificadores, n-gramas (2, 3 y 4 palabras), modismos y sarcasmo.
- 🗄️ Histórico de todos los análisis con estadísticas globales.

---

## 📑 TABLA DE CONTENIDOS
- Instalación
- Las 5 capas de análisis
- Estructura del proyecto
- Uso de la API
- Esquema de base de datos
- Editar el diccionario
- Tecnologías
- Proyecto educativo
- Citas y referencias
- Licencia

---

## 🚀 INSTALACIÓN

### Requisitos
- XAMPP (o LAMP / WAMP / MAMP equivalente) con:
  - PHP 7.4 o superior
  - MySQL/MariaDB 5.7 o superior
  - Apache
- Navegador moderno

### Pasos
1. Descarga o clona este repositorio en `htdocs`:
   Windows: cd C:\xampp\htdocs
   macOS:   cd /Applications/XAMPP/htdocs
   Comando: git clone https://github.com/tu-usuario/sentimanphp.git sentiman

2. Arranca Apache y MySQL desde el panel de XAMPP.

3. Visita el instalador en tu navegador:
   http://localhost/sentiman/install.php

4. Completa los 6 pasos del instalador:
   1. Verificación del entorno
   2. Conexión a MySQL (host, puerto, usuario, contraseña, BD)
   3. Creación de tablas
   4. Importación del diccionario (~7.000 palabras)
   5. Generación de config.php
   6. Resumen final

5. Accede a la aplicación:
   http://localhost/sentiman/

💡 Si tu MySQL escucha en otro puerto (p. ej. 3307), el instalador permite especificarlo en el paso 2.

---

## 🎚️ LAS 5 CAPAS DE ANÁLISIS

| Capa | Descripción | Categorías |
|------|-------------|------------|
| ⚖️ General | Polaridad clásica | positiva, negativa, neutral |
| 🎭 Emociones básicas | 8 primarias (Plutchik) | alegria, tristeza, ira, miedo, sorpresa, asco, confianza, anticipacion |
| 🧠 Emociones complejas | Sociales | gratitud, orgullo, admiracion, compasion, esperanza, aceptacion, verguenza, culpa, envidia, placer_ajeno, apatia, ambivalencia, soledad, humildad |
| 🎯 Intención | Propósito del texto | queja, elogio, amenaza, peticion, sarcasmo, urgencia, intensidad_alta, intensidad_baja |
| 🛍️ Comercial | Marketing y ventas | intencion_compra, riesgo_abandono, fidelizacion, satisfaccion_alta, insatisfaccion, objecion_precio, objecion_valor, objecion_tiempo, objecion_necesidad, objecion_confianza, comparacion, escasez, calidad_alta, calidad_baja, servicio_bueno, servicio_malo |

### ¿Por qué multicapa?
Un texto puede ser positivo en sentimiento general y al mismo tiempo expresar queja en intención y riesgo de abandono en la capa comercial.

Ejemplo:
"Llevo años con vosotros pero el último mes el servicio ha sido lamentable. Voy a cancelar mi suscripción."

- Capa General: Mixto (palabras como años, cancelar, lamentable)
- Emociones básicas: ira alta, tristeza media
- Emociones complejas: humildad baja, apatia
- Intención: queja, amenaza, urgencia
- Comercial: riesgo_abandono muy alto, fidelizacion alta (paradoja típica), servicio_malo

---

## 📁 ESTRUCTURA DEL PROYECTO
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

---

## 🔌 USO DE LA API
Devuelve texto plano con formato clave:valor (un par por línea). Sin JSON, sin headers complicados.

Endpoint: http://localhost/sentiman/api.php?texto=TU_TEXTO

Parámetros:
- texto (Obligatorio): String (máx 10.000 chars)
- capa (Opcional): general (defecto), emociones_basicas, emociones_complejas, intencion, comercial, todas.

### Ejemplos:
1. Capa general: GET /sentiman/api.php?texto=Esta+película+es+maravillosa
   Respuesta:
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

2. Capa comercial: GET /sentiman/api.php?texto=Quiero+cancelar+mi+suscripción&capa=comercial
   Respuesta:
   capa:comercial
   categorias:1
   riesgo_abandono:9.0
   dominante:riesgo_abandono

### Clientes de código:

#### Cliente PHP:
<?php
$texto = "Estoy muy decepcionado, voy a cancelar el servicio";
$url   = "http://localhost/sentiman/api.php?capa=comercial&texto=" . urlencode($texto);
$resp  = file_get_contents($url);
$datos = [];
foreach (explode("\n", trim($resp)) as $linea) {
    if (str_contains($linea, ':')) {
        [$k, $v] = explode(':', $linea, 2);
        $datos[trim($k)] = trim($v);
    }
}
print_r($datos);
?>

#### Cliente Python:
import requests
from urllib.parse import urlencode
texto = "Estoy harta del servicio, voy a darme de baja"
url = "http://localhost/sentiman/api.php?" + urlencode({"texto": texto, "capa": "comercial"})
resp = requests.get(url).text
datos = dict(linea.split(":", 1) for linea in resp.strip().split("\n") if ":" in linea)
print(datos)

#### Cliente Curl:
curl "http://localhost/sentiman/api.php?texto=Me%20encanta&capa=emociones_basicas"

---

## 🗄️ ESQUEMA DE BASE DE DATOS

### Tabla `dictionary`
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

- Tabla `analisis_historico`: Guarda cada análisis ejecutado con todos los puntajes.
- Vista `v_estadisticas`: Agregaciones globales listas para el dashboard.

---

## 📚 EDITAR EL DICCIONARIO
Hay tres formas:

1) Interfaz web: Pestaña Diccionario -> Subir actualización SQL (.sql). Soporta INSERT, REPLACE, UPDATE.
2) Plantilla nueva: 
   REPLACE INTO `dictionary` (palabra, positiva, negativa, alegria, gratitud) VALUES('te quiero', 9, 0, 8, 0);
3) Plantilla modificación:
   UPDATE `dictionary` SET ira = 9, negativa = 9 WHERE palabra = 'odio';

### Columnas válidas (Pesos de 0 a 10):
positiva, negativa, neutral,
alegria, tristeza, ira, miedo, sorpresa, asco, confianza, anticipacion,
gratitud, orgullo, admiracion, compasion, esperanza, aceptacion, verguenza, culpa, envidia, placer_ajeno, apatia, ambivalencia, soledad, humildad,
queja, elogio, amenaza, peticion, sarcasmo, urgencia, intensidad_alta, intensidad_baja,
intencion_compra, riesgo_abandono, fidelizacion, satisfaccion_alta, insatisfaccion, objecion_precio, objecion_valor, objecion_tiempo, objecion_necesidad, objecion_confianza, comparacion, escasez, calidad_alta, calidad_baja, servicio_bueno, servicio_malo.

---

## 🛠️ TECNOLOGÍAS
- Backend: PHP 7.4+ (mysqli)
- Base de datos: MySQL / MariaDB
- Frontend: HTML + CSS + JS vanilla
- Visualizaciones: Chart.js + chartjs-plugin-datalabels

---

## 🎓 PROYECTO EDUCATIVO
SentiManPHP permite a los alumnos:
- Construir scrapers.
- Enviar texto a la API.
- Recibir análisis estructurados.
- Mejorar el diccionario vía SQL.

---

## 📚 CITAS Y REFERENCIAS
Blázquez Ochando, M. (2026). SentiManPHP Full: Sistema de análisis de sentimiento multicapa en español [Software]. GitHub. https://github.com/manublaz/sentiManPHP

---

## 📜 LICENCIA
MIT — libre de usar, modificar y redistribuir.
