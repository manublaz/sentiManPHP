# README: Analizador de Sentimiento Multicapa (SentiManPHP)

[ PHP 7.4+ ] [ MySQL 5.7+ ] [ Licencia: MIT ] [ Idioma: Español ]

Analizador de sentimiento multicapa en español con 5 capas de análisis, diccionario unificado y visualizaciones interactivas. Proyecto educativo en PHP + MySQL, diseñado para que alumnos de scraping puedan integrarlo fácilmente.

---

## ✨ CARACTERÍSTICAS
- 🎚️ 5 capas de análisis simultáneas: general, emociones básicas, emociones complejas, intención y comercial.
- 📚 Diccionario unificado con más de 7.000 palabras y expresiones en español, cada una con pesos en hasta 49 categorías.
- 🔌 API ultrasimple — devuelve texto plano (clave:valor), perfecta para principiantes.
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
- XAMPP (o LAMP / WAMP / MAMP equivalente) con PHP 7.4+ y MySQL 5.7+.
- Navegador moderno.

### Pasos
1. Descarga o clona este repositorio en htdocs:
   cd C:\xampp\htdocs              # Windows
   cd /Applications/XAMPP/htdocs   # macOS
   git clone https://github.com/tu-usuario/sentimanphp.git sentiman

2. Arranca Apache y MySQL desde el panel de XAMPP.

3. Visita el instalador en tu navegador:
   http://localhost/sentiman/install.php

4. Completa los 6 pasos del instalador:
   - Verificación del entorno
   - Conexión a MySQL (host, puerto, usuario, contraseña, BD)
   - Creación de tablas
   - Importación del diccionario (~7.000 palabras)
   - Generación de config.php
   - Resumen final

5. Accede a la aplicación:
   http://localhost/sentiman/

💡 Si tu MySQL escucha en otro puerto (p. ej. 3307), el instalador te permite especificarlo en el paso 2.

---

## 🎚️ LAS 5 CAPAS DE ANÁLISIS

1. ⚖️ General: Polaridad clásica (positiva, negativa, neutral).
2. 🎭 Emociones básicas: 8 primarias de Plutchik (alegria, tristeza, ira, miedo, sorpresa, asco, confianza, anticipacion).
3. 🧠 Emociones complejas: Sociales (gratitud, orgullo, admiracion, compasion, esperanza, aceptacion, verguenza, culpa, envidia, placer_ajeno, apatia, ambivalencia, soledad, humildad).
4. 🎯 Intención: Propósito del texto (queja, elogio, amenaza, peticion, sarcasmo, urgencia, intensidad_alta, intensidad_baja).
5. 🛍️ Comercial: Marketing y ventas (intencion_compra, riesgo_abandono, fidelizacion, satisfaccion_alta, insatisfaccion, objecion_precio, objecion_valor, objecion_tiempo, objecion_necesidad, objecion_confianza, comparacion, escasez, calidad_alta, calidad_baja, servicio_bueno, servicio_malo).

Ejemplo:
"Llevo años con vosotros pero el servicio ha sido lamentable. Voy a cancelar mi suscripción."
- Capa General: Mixto
- Emociones básicas: ira alta, tristeza media
- Intención: queja, amenaza, urgencia
- Comercial: riesgo_abandono muy alto

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
└── README.txt

---

## 🔌 USO DE LA API
Devuelve texto plano en formato clave:valor.

Endpoint: http://localhost/sentiman/api.php?texto=TU_TEXTO&capa=todas

### Ejemplo de respuesta:
sentimiento:positivo
positivo:78.50
negativo:12.30
etiqueta:Positivo
palabras:4
encontradas:3

### Cliente Python:
import requests
from urllib.parse import urlencode
texto = "Estoy muy feliz"
url = "http://localhost/sentiman/api.php?" + urlencode({"texto": texto, "capa": "general"})
resp = requests.get(url).text
datos = dict(linea.split(":", 1) for linea in resp.strip().split("\n") if ":" in linea)
print(datos)

---

## 🗄️ ESQUEMA DE BASE DE DATOS
Tabla 'dictionary' con columnas: palabra, positiva, negativa, neutral, alegria, tristeza, ira, miedo, sorpresa, asco, confianza, anticipacion, gratitud, orgullo, admiracion, compasion, esperanza, aceptacion, verguenza, culpa, envidia, placer_ajeno, apatia, ambivalencia, soledad, humildad, queja, elogio, amenaza, peticion, sarcasmo, urgencia, intensidad_alta, intensidad_baja, intencion_compra, riesgo_abandono, fidelizacion, satisfaccion_alta, insatisfaccion, objecion_precio, objecion_valor, objecion_tiempo, objecion_necesidad, objecion_confianza, comparacion, escasez, calidad_alta, calidad_baja, servicio_bueno, servicio_malo.

---

## 📚 EDITAR EL DICCIONARIO
Suba un archivo .sql desde la interfaz (Pestaña Diccionario).
Ejemplo para añadir palabras:
USE `sentiman`;
REPLACE INTO `dictionary` (palabra, positiva, alegria) VALUES ('te quiero', 9, 8);

---

## 🛠️ TECNOLOGÍAS
- Backend: PHP 7.4+ (mysqli)
- Base de datos: MySQL / MariaDB
- Frontend: HTML + CSS + JS vanilla
- Visualizaciones: Chart.js

---

## 🎓 PROYECTO EDUCATIVO
Diseñado para facilitar la integración de scrapers. El formato clave:valor evita la complejidad del parseo JSON para alumnos iniciales.

---

## 📚 CITAS Y REFERENCIAS
Blázquez Ochando, M. (2026). SentiManPHP Full: Sistema de análisis de sentimiento multicapa en español [Software]. GitHub. https://github.com/manublaz/sentiManPHP

---

## 📜 LICENCIA
MIT — libre de usar, modificar y redistribuir.
