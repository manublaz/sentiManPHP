# SentiManPHP Main

<div align="center">

[![PHP](https://img.shields.io/badge/PHP-7.4+-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![XAMPP](https://img.shields.io/badge/XAMPP-Compatible-FB7A24?style=for-the-badge&logo=apache&logoColor=white)](https://apachefriends.org)
[![License](https://img.shields.io/badge/License-MIT-green?style=for-the-badge)](LICENSE)

**Analizador de sentimiento en español — versión básica.
Pensado como primer contacto con el análisis de sentimiento basado en diccionario.**

</div>

---

## 📖 ¿Qué es?

SentiManPHP Main es la versión más sencilla del analizador. Recibe un texto en español,
lo compara contra un diccionario de 6.527 palabras y devuelve un resultado
positivo, negativo o neutral con su porcentaje de distribución.

Es el punto de partida ideal para alumnos que quieren entender **cómo funciona
por dentro** un analizador de sentimiento antes de pasar a versiones más avanzadas.

---

## 📁 Estructura de archivos

```
sentiManPHP-main/
├── index.php               ← Formulario web y resultado
├── sentiman.php            ← Motor de análisis (núcleo)
├── config.php              ← Configuración de base de datos
├── install.sql             ← Script SQL de instalación
├── dictionary.sql          ← Diccionario con 6.527 términos
├── dictionary.php          ← Visor del diccionario
├── style.css               ← Estilos del formulario
├── style-dictionary.css    ← Estilos del visor
├── chart.min.js            ← Chart.js
├── chartjs-plugin-datalabels.min.js
└── interface/
    └── logo.jpeg
```

---

## 🚀 Instalación en XAMPP

### 1. Copiar archivos

Copia toda la carpeta `sentiManPHP-main` dentro de:
```
C:\xampp\htdocs\sentiman\       (Windows)
/Applications/XAMPP/htdocs/sentiman/  (macOS)
```

### 2. Crear la base de datos

1. Abre **phpMyAdmin**: http://localhost/phpmyadmin
2. Crea una base de datos llamada `sentiman` (charset: utf8mb4)
3. Importa `install.sql` (crea la tabla `dictionary`)
4. Importa `dictionary.sql` (carga los 6.527 términos)

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

## 🧩 ¿Cómo funciona?

El motor (`sentiman.php`) sigue estos pasos:

1. **Tokeniza** el texto: lo convierte a minúsculas, elimina puntuación y divide en palabras
2. **Busca** cada palabra en la tabla `dictionary`
3. **Suma** los pesos positivos, negativos y neutrales de las palabras encontradas
4. **Calcula** los porcentajes y determina el sentimiento global

```
Texto: "La película es maravillosa"
  → "película" → pos:5 neg:0 neu:5
  → "maravillosa" → pos:9 neg:0 neu:1
  → Total: pos:14 neg:0 neu:6
  → Resultado: POSITIVO (70%)
```

---

## 🧩 Diccionario

El diccionario (`dictionary.sql`) contiene **6.527 entradas** en español:
- Palabras simples: "alegría", "tristeza", "miedo"…
- Expresiones compuestas: "abuso de poder", "agenda política"…
- Cada entrada tiene peso **positivo**, **negativo** y **neutral** (0–10)

Puedes consultar el diccionario completo abriendo `dictionary.php` en tu navegador.

---

## 📊 Métricas calculadas

| Métrica | Descripción | Rango |
|---|---|---|
| Positivo % | Porcentaje del peso léxico positivo | 0–100 |
| Negativo % | Porcentaje del peso léxico negativo | 0–100 |
| Neutral % | Porcentaje del peso léxico neutral | 0–100 |

---

## 🔄 Versiones de SentiManPHP

| Versión | Nivel | Características |
|---------|-------|----------------|
| **Main** (esta) | 🟢 Básico | Formulario + diccionario + resultado |
| **Medium** | 🟡 Intermedio | + API REST + histórico + gráficas + negaciones + bigramas |
| **Full** | 🔴 Avanzado | + 5 capas multicapa + stemming + panel de ajustes + instalador visual + editor SQL |

---

## 📄 Licencia

Proyecto educativo de libre uso y modificación.

---

## 📚 Citas y referencias

Si utilizas **SentiManPHP Main** en tu investigación o docencia, por favor cítalo de la siguiente forma:

> Blázquez Ochando, M. (2026). *SentiManPHP Main: Sistema de análisis de sentimiento* [Software]. GitHub. [https://github.com/manublaz/sentiManPHP](https://github.com/manublaz/sentiManPHP/tree/main)
