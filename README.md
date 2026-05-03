# SentiManPHP Full

<div align="center">

[![PHP](https://img.shields.io/badge/PHP-7.4+-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![XAMPP](https://img.shields.io/badge/XAMPP-Compatible-FB7A24?style=for-the-badge&logo=apache&logoColor=white)](https://apachefriends.org)
[![License](https://img.shields.io/badge/License-MIT-green?style=for-the-badge)](LICENSE)
[![Español](https://img.shields.io/badge/Idioma-Español-red?style=for-the-badge)](.)

**Analizador de sentimiento multicapa en español con 5 capas de análisis, stemming,
panel de ajustes, API REST e instalador visual.
Pensado para XAMPP local y para que alumnos que hacen scrapers puedan integrarlo fácilmente.**

</div>

---

## 🎚️ Las 5 capas de análisis

Un análisis de sentimiento clásico dice "positivo" o "negativo". SentiManPHP Full va mucho más allá:

| # | Capa | Qué detecta | Categorías |
|---|------|-------------|------------|
| 1 | ⚖️ **General** | Polaridad clásica | `positiva`, `negativa`, `neutral` |
| 2 | 🎭 **Emociones básicas** | Las 8 de Plutchik | `alegria`, `tristeza`, `ira`, `miedo`, `sorpresa`, `asco`, `confianza`, `anticipacion` |
| 3 | 🧠 **Emociones complejas** | Emociones sociales | `gratitud`, `orgullo`, `admiracion`, `compasion`, `esperanza`, `aceptacion`, `verguenza`, `culpa`, `envidia`, `placer_ajeno`, `apatia`, `ambivalencia`, `soledad`, `humildad` |
| 4 | 🎯 **Intención** | Para qué se escribió | `queja`, `elogio`, `amenaza`, `peticion`, `sarcasmo`, `urgencia`, `intensidad_alta`, `intensidad_baja` |
| 5 | 🛍️ **Comercial** | Señales de marketing | `intencion_compra`, `riesgo_abandono`, `fidelizacion`, `satisfaccion_alta`, `insatisfaccion`, `objecion_precio`, `objecion_valor`, `objecion_tiempo`, `objecion_necesidad`, `objecion_confianza`, `comparacion`, `escasez`, `calidad_alta`, `calidad_baja`, `servicio_bueno`, `servicio_malo` |

> 💡 Un mismo texto puede ser *positivo* en sentimiento general pero indicar *queja* en intención y *riesgo de abandono* en la capa comercial. Esa es la utilidad de las capas.

---

## 📁 Estructura de archivos

```
sentiManPHP-v2/
├── install.php             ← Instalador visual (6 pasos)
├── install.sql             ← Esquema unificado de BD (49 columnas)
├── dictionary.sql          ← 7.000+ palabras con pesos multicapa
├── config.php              ← Configuración de BD (generado por el instalador)
├── config_ajustes.json     ← Factores de ajuste (generado por el panel)
├── index.php               ← Interfaz web con 6 pestañas
├── sentiman.php            ← Motor de análisis con stemming
├── api.php                 ← API REST (texto plano clave:valor)
├── upload_sql.php          ← Cargador de actualizaciones SQL
├── chart.min.js            ← Chart.js para visualizaciones
├── chartjs-plugin-datalabels.min.js
└── interface/
    └── logo.jpeg
```

---

## 🚀 Instalación en XAMPP

### 1. Copiar archivos

Copia toda la carpeta dentro de:
```
C:\xampp\htdocs\sentiman\       (Windows)
/Applications/XAMPP/htdocs/sentiman/  (macOS)
```

### 2. Ejecutar el instalador visual

Abre en tu navegador:
```
http://localhost/sentiman/install.php
```

El instalador te guía en **6 pasos**:
1. ✅ Verificación del entorno PHP
2. 🔌 Conexión a MySQL (host, puerto, usuario, contraseña, BD)
3. 🗄️ Creación de tablas (esquema unificado con 49 columnas)
4. 📚 Importación del diccionario (~7.000 palabras)
5. ⚙️ Generación de `config.php`
6. 🎉 Resumen final

> 💡 Si tu MySQL escucha en el puerto 3307 (conflicto típico en XAMPP), el instalador te permite especificarlo en el paso 2.

### 3. ¡Listo!

Abre: http://localhost/sentiman/

---

## 🔌 API — Uso para scrapers

### Petición GET (la más simple)
```
http://localhost/sentiman/api.php?texto=Tu+texto+aquí
```

### Seleccionar capa
```
http://localhost/sentiman/api.php?texto=...&capa=emociones_basicas
http://localhost/sentiman/api.php?texto=...&capa=comercial
http://localhost/sentiman/api.php?texto=...&capa=todas
```

Capas válidas: `general` (defecto), `emociones_basicas`, `emociones_complejas`, `intencion`, `comercial`, `todas`.

### Respuesta (texto plano)
```
sentimiento:positivo
positivo:72.50
negativo:15.30
neutral:12.20
polaridad:0.65
subjetividad:0.68
intensidad:7.40
etiqueta:Positivo
palabras:42
encontradas:18
cobertura:42.86
```

### Código PHP para el scraper del alumno
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

### Código Python para el scraper del alumno
```python
import requests
from urllib.parse import urlencode

texto = "Estoy harta del servicio, voy a darme de baja"
url = "http://localhost/sentiman/api.php?" + urlencode({
    "texto": texto,
    "capa": "comercial"
})

resp = requests.get(url).text
datos = dict(l.split(":", 1) for l in resp.strip().split("\n") if ":" in l)
print(datos)
```

---

## ✨ Novedades Full respecto a Medium

| Característica | Medium | Full |
|---|---|---|
| Capas de análisis | 1 (general) | **5** (general + emociones + intención + comercial) |
| Diccionario | 6.527 palabras (3 pesos) | **7.047 palabras (49 pesos por palabra)** |
| Stemming | ❌ | ✅ Snowball simplificado para español |
| Panel de ajustes | ❌ | ✅ Sliders en tiempo real |
| Corrección de sesgo | ❌ | ✅ Factores configurables |
| Editor de diccionario | ❌ | ✅ Subir SQL con INSERT/REPLACE/UPDATE |
| Instalador visual | ❌ | ✅ 6 pasos con detección de errores |
| Capas en API | ❌ | ✅ Parámetro `?capa=` |
| Negaciones e intensificadores | ✅ | ✅ |
| Bigramas / trigramas / tetragramas | Bigramas | **Hasta 4-gramas** |
| Gráficas interactivas | 8 | **8 + radar/polar por capa** |
| Histórico | ✅ | ✅ (con datos de todas las capas) |

---

## ⚙️ Panel de ajustes

La pestaña **Ajustes** permite modificar el comportamiento del motor en tiempo real:

| Ajuste | Qué hace | Rango |
|--------|----------|-------|
| Factor positivo / negativo / neutral | Multiplicadores para el puntaje general | 0 – 3.0 |
| Corrección de sesgo | Suma puntos al negativo para equilibrar diccionarios optimistas | 0 – 20 |
| Suavizado | < 1 aplana diferencias, > 1 las amplifica | 0.3 – 2.0 |
| Factor stemming | Peso heredado cuando se usa la raíz | 0 – 1.0 |
| Stemming on/off | Activa/desactiva la búsqueda por raíz | checkbox |
| Longitud mínima raíz | Cuántas letras mínimo para aceptar un stem | 3 – 6 |

Los ajustes se guardan en `config_ajustes.json` y se aplican inmediatamente.

---

## 🌿 Stemming

Cuando una palabra no se encuentra en el diccionario, el motor:
1. Calcula su raíz: `abandonar` → `abandon`
2. Busca palabras del diccionario con esa raíz: `abandono`
3. Hereda sus pesos multiplicados por el factor de stemming (0.7 por defecto)

En la tabla de detalle aparecen marcadas como: `abandonar — stem → abandono — ×0.70`

---

## 📚 Editar el diccionario

Tres formas de añadir o modificar palabras:

### Desde la interfaz web
Pestaña **Diccionario → Subir actualización SQL**. Acepta `.sql` con `INSERT`, `REPLACE`, `UPDATE` sobre `dictionary`.

### Añadir palabras nuevas
```sql
REPLACE INTO `dictionary` (palabra, positiva, negativa, alegria, gratitud) VALUES
('te quiero',          9, 0, 8, 0),
('muchísimas gracias', 8, 0, 0, 9);
```

### Modificar palabras existentes
```sql
UPDATE `dictionary` SET ira = 9, negativa = 9 WHERE palabra = 'odio';
UPDATE `dictionary` SET alegria = 9, positiva = 8 WHERE id = 1234;
```

Los pesos van de **0 a 10**. Las 49 columnas se listan en la pestaña Diccionario de la interfaz.

---

## 🤖 Generador automático de pesos (sentiBUILD.py)

El script `sentiBUILD.py` usa un LLM local (LM Studio) para asignar automáticamente
los pesos de las 46 categorías a cada palabra del diccionario:

```bash
python sentiBUILD.py dictionary.sql --port 1235 --batch 10 --skip-curadas
```

Genera un archivo `updates.sql` listo para subir desde la pestaña Diccionario.

---

## 📊 Métricas calculadas

| Métrica | Descripción | Rango |
|---|---|---|
| Positivo % | % del peso léxico positivo | 0–100 |
| Negativo % | % del peso léxico negativo | 0–100 |
| Neutral % | % del peso léxico neutral | 0–100 |
| Polaridad | Dirección del sentimiento | −1 a +1 |
| Subjetividad | Cuánto sentimiento hay | 0 a 1 |
| Intensidad | Fuerza media de las palabras | 0 a 10 |
| Cobertura | % palabras halladas en diccionario | 0–100 |
| Activación | Energía emocional (alta vs baja) | 0 a 1 |
| Etapa funnel | Posición en embudo comercial | atracción / consideración / conversión / retención / fuga |
| Sarcasmo | Detección de sarcasmo | sí / no |

---

## 📄 Licencia

Proyecto educativo de libre uso y modificación.

---

## 📚 Citas y referencias

Si utilizas **SentiManPHP Full** en tu investigación o docencia, por favor cítalo de la siguiente forma:

> Blázquez Ochando, M. (2026). *SentiManPHP Full: Sistema de análisis de sentimiento multicapa* [Software]. GitHub. [https://github.com/manublaz/sentiManPHP](https://github.com/manublaz/sentiManPHP/tree/full)
