# SentiManPHP v2 🧠

Analizador de sentimiento en español con histórico, API REST y contenido didáctico.
Pensado para XAMPP local y para que alumnos que hacen scrapers puedan integrarlo fácilmente.

---

## 📁 Estructura de archivos

```
sentiManPHP-v2/
├── index.php               ← Interfaz web principal
├── api.php                 ← API de análisis (muy simple)
├── sentiman.php            ← Motor de análisis (núcleo)
├── config.php              ← Configuración de base de datos
├── install.sql             ← Script SQL de instalación
├── dictionary.sql          ← Diccionario con 6.500+ términos
├── chart.min.js            ← Chart.js
├── chartjs-plugin-datalabels.min.js
└── interface/
    └── logo.jpeg
```

---

## 🚀 Instalación en XAMPP

### 1. Copiar archivos

Copia toda la carpeta `sentiManPHP-v2` dentro de:
```
C:\xampp\htdocs\sentiman\       (Windows)
/Applications/XAMPP/htdocs/sentiman/  (macOS)
```

### 2. Crear la base de datos

1. Abre **phpMyAdmin**: http://localhost/phpmyadmin
2. Crea una base de datos llamada `sentiman` (charset: utf8mb4)
3. Importa `install.sql` (crea las tablas y la vista)
4. Importa `dictionary.sql` (carga el diccionario de 6.500 términos)

### 3. Configurar la conexión

Edita `config.php`:
```php
define('DB_HOST',     'localhost');
define('DB_USER',     'root');
define('DB_PASSWORD', '');        // En XAMPP suele estar vacío
define('DB_NAME',     'sentiman');
```

### 4. ¡Listo!

Abre: http://localhost/sentiman/

---

## 🔌 API — Uso para scrapers

### Petición GET (la más simple)
```
http://localhost/sentiman/api.php?texto=Tu+texto+aquí
```

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
$texto = "El gobierno anuncia nuevas medidas de apoyo";
$url   = "http://localhost/sentiman/api.php?texto=" . urlencode($texto);
$resp  = file_get_contents($url);

// Imprimir todo
echo $resp;

// O extraer solo el sentimiento
$lineas = explode("\n", trim($resp));
foreach ($lineas as $l) {
    if (str_starts_with($l, 'sentimiento:')) {
        echo "Sentimiento: " . explode(':', $l)[1];
    }
}
?>
```

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

---

## ✨ Novedades v2 respecto al original

- Detección de **negaciones** ("no es bueno" → invierte pesos)
- Detección de **intensificadores** ("muy feliz" → ×1.5)
- **Bigramas**: frases de 2 palabras como "abuso de poder"
- **Histórico** de todos los análisis en base de datos
- **4 gráficas** por análisis (pie, radar, barras, polar)
- **4 gráficas históricas** (línea, pie, barras, scatter)
- **API** en texto plano, trivial de usar desde scrapers
- **Sección didáctica** explicando el análisis de sentimiento
- Probador de API integrado en la interfaz
- Diseño moderno con modo oscuro

---

## 🧩 Diccionario

El diccionario (`dictionary.sql`) contiene **6.527 entradas** en español:
- Palabras simples: "alegría", "tristeza", "miedo"…
- Expresiones compuestas: "abuso de poder", "agenda política"…
- Cada entrada tiene peso **positivo**, **negativo** y **neutral** (0–10)

---

## 📄 Licencia

Proyecto educativo de libre uso y modificación.
