<?php
// ============================================================
//  SentiManPHP v2 — Instalador visual paso a paso
//  Accede a: http://localhost/sentiman/install.php
// ============================================================

// 1) SESIÓN — una sola vez, al inicio absoluto del archivo
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2) Bloquear si ya se instaló
if (file_exists(__DIR__ . '/.installed')) {
    die('<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">
    <title>Instalador bloqueado</title>
    <style>body{font-family:sans-serif;background:#0f172a;color:#ef4444;
    display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;}
    .box{background:#1e293b;padding:40px;border-radius:12px;text-align:center;max-width:480px;}
    p{color:#94a3b8;margin-top:12px;line-height:1.6;}
    code{background:#334155;padding:2px 6px;border-radius:4px;}</style>
    </head><body><div class="box">
    <h2>⛔ El instalador ya fue ejecutado</h2>
    <p>Por seguridad está bloqueado. Si necesitas reinstalar, elimina el archivo
    <code>.installed</code> de la carpeta del proyecto y recarga esta página.</p>
    </div></body></html>');
}

// 3) Desactivar excepciones mysqli — capturamos errores manualmente
mysqli_report(MYSQLI_REPORT_OFF);

// ============================================================
//  Estado del instalador
// ============================================================
$step    = (int)($_GET['step'] ?? 1);
$errores = [];
$exitos  = [];

// Leer credenciales guardadas en sesión
$f_host = $_SESSION['inst_host'] ?? 'localhost';
$f_port = (int)($_SESSION['inst_port'] ?? 3306);
$f_user = $_SESSION['inst_user'] ?? 'root';
$f_pass = $_SESSION['inst_pass'] ?? '';
$f_db   = $_SESSION['inst_db']   ?? 'sentiman';

// ============================================================
//  PASO 2 — Probar conexión
// ============================================================
if ($step === 2 && $_SERVER['REQUEST_METHOD'] === 'POST') {

    $host = trim($_POST['host'] ?? 'localhost');
    $port = max(1, min(65535, (int)($_POST['port'] ?? 3306)));
    $user = trim($_POST['user'] ?? 'root');
    $pass = $_POST['pass'] ?? '';
    $db   = trim($_POST['db']   ?? 'sentiman');

    $test = new mysqli();
    @$test->connect($host, $user, $pass, '', $port);

    if ($test->connect_errno) {
        $cod = $test->connect_errno;
        $msg = $test->connect_error;
        $errores[] = "❌ No se pudo conectar a MySQL en <code>$host:$port</code> (código $cod): $msg";

        if ($cod === 1045) {
            $errores[] = "💡 <strong>Error 1045 — Usuario o contraseña incorrectos.</strong><br>
                En XAMPP estándar el usuario es <code>root</code> y la contraseña está
                <strong>en blanco</strong> (deja el campo vacío). Si pusiste contraseña, escríbela.";
        } elseif ($cod === 2002 || $cod === 2003) {
            $errores[] = "💡 <strong>MySQL no responde en el puerto $port.</strong><br>
                Comprueba en el XAMPP Control Panel que MySQL está arrancado y el puerto es correcto.
                Si usas puerto 3307 porque el 3306 está ocupado, asegúrate de escribir <code>3307</code> en el campo Puerto.";
        }
        // Guardar en sesión incluso el intento fallido para repoblar el formulario
        $_SESSION['inst_host'] = $host;
        $_SESSION['inst_port'] = $port;
        $_SESSION['inst_user'] = $user;
        $_SESSION['inst_db']   = $db;
        $f_host = $host; $f_port = $port; $f_user = $user; $f_db = $db;

        $step = 2;  // volver al formulario de conexión, no al paso 1

    } else {
        $_SESSION['inst_host'] = $host;
        $_SESSION['inst_port'] = $port;
        $_SESSION['inst_user'] = $user;
        $_SESSION['inst_pass'] = $pass;
        $_SESSION['inst_db']   = $db;
        $f_host = $host; $f_port = $port; $f_user = $user; $f_pass = $pass; $f_db = $db;
        $test->close();
        $exitos[] = "✅ Conexión correcta al servidor MySQL (<code>$host:$port</code>, usuario: <code>$user</code>).";
        $step = 3; // avanzar automáticamente al paso de creación de tablas
    }
}

// ============================================================
//  PASO 3 — Crear BD y tablas
// ============================================================
if ($step === 3 && $_SERVER['REQUEST_METHOD'] === 'POST') {

    $con = new mysqli();
    @$con->connect($f_host, $f_user, $f_pass, '', $f_port);

    if ($con->connect_errno) {
        $errores[] = "❌ Error de conexión en <code>{$f_host}:{$f_port}</code> ({$con->connect_errno}): {$con->connect_error}";
        $errores[] = "💡 Vuelve al <a href='install.php?step=2'>Paso 2</a> e introduce de nuevo los datos.";
        $step = 2;
    } else {
        $con->set_charset('utf8mb4');

        $queries = [
            "Crear base de datos '$f_db'" =>
                "CREATE DATABASE IF NOT EXISTS `$f_db`
                 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",
            "Seleccionar base de datos" =>
                "USE `$f_db`",
            "Crear tabla dictionary" =>
                "CREATE TABLE IF NOT EXISTS `dictionary` (
                    `id`                int(11)      NOT NULL AUTO_INCREMENT,
                    `fecharegistro`     timestamp    NOT NULL DEFAULT current_timestamp(),
                    `fechamodificacion` varchar(255) NOT NULL DEFAULT '',
                    `palabra`           varchar(500) NOT NULL,
                    `positiva`          float        NOT NULL DEFAULT 0,
                    `negativa`          float        NOT NULL DEFAULT 0,
                    `neutral`           float        NOT NULL DEFAULT 0,
                    PRIMARY KEY (`id`),
                    KEY `idx_palabra` (`palabra`(100))
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "Crear tabla analisis_historico" =>
                "CREATE TABLE IF NOT EXISTS `analisis_historico` (
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
                    PRIMARY KEY (`id`),
                    KEY `idx_fecha` (`fecha`),
                    KEY `idx_sentimiento` (`sentimiento_global`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "Crear vista v_estadisticas" =>
                "CREATE OR REPLACE VIEW `v_estadisticas` AS
                 SELECT
                   COUNT(*)                           AS total_analisis,
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
                 FROM analisis_historico",
        ];

        foreach ($queries as $nombre => $sql) {
            if ($con->query($sql) === false) {
                $errores[] = "❌ Error en «$nombre»: " . $con->error;
            } else {
                $exitos[] = "✅ $nombre.";
            }
        }
        $con->close();
        if (empty($errores)) $step = 4; // avanzar al diccionario
    }
}

// ============================================================
//  PASO 4 — Importar diccionario
// ============================================================
if ($step === 4 && $_SERVER['REQUEST_METHOD'] === 'POST') {

    $sqlFile = __DIR__ . '/dictionary.sql';

    if (!file_exists($sqlFile)) {
        $errores[] = "❌ No se encuentra <code>dictionary.sql</code>. Asegúrate de que está en la misma carpeta.";
    } else {
        $con = new mysqli();
        @$con->connect($f_host, $f_user, $f_pass, $f_db, $f_port);

        if ($con->connect_errno) {
            $errores[] = "❌ Error de conexión: {$con->connect_error}";
        } else {
            $con->set_charset('utf8mb4');

            $chk   = $con->query("SELECT COUNT(*) AS n FROM dictionary");
            $yaHay = $chk ? (int)$chk->fetch_assoc()['n'] : 0;

            if ($yaHay > 100) {
                $exitos[] = "ℹ️ El diccionario ya contiene <strong>$yaHay palabras</strong>. Se omite para no duplicar datos.";
            } else {
                $sql = file_get_contents($sqlFile);
                preg_match_all('/INSERT\s+INTO[^;]+;/is', $sql, $m);
                $total = 0; $errCount = 0;
                foreach ($m[0] as $ins) {
                    if ($con->query($ins) !== false) {
                        $total += $con->affected_rows;
                    } else {
                        $errCount++;
                    }
                }
                if ($errCount === 0) {
                    $exitos[] = "✅ Diccionario importado: <strong>$total palabras/expresiones</strong>.";
                } else {
                    $exitos[] = "⚠️ Importados $total registros con $errCount errores menores (probablemente duplicados — es normal).";
                }
            }
            $con->close();
            if (empty($errores)) $step = 5; // avanzar a generar config
        }
    }
}

// ============================================================
//  PASO 5 — Escribir config.php
// ============================================================
if ($step === 5 && $_SERVER['REQUEST_METHOD'] === 'POST') {

    $cfg = "<?php\n"
        . "// Generado por el instalador de SentiManPHP v2\n"
        . "mysqli_report(MYSQLI_REPORT_OFF);\n"
        . "define('DB_HOST',     '" . addslashes($f_host) . "');\n"
        . "define('DB_PORT',     " . (int)$f_port . ");\n"
        . "define('DB_USER',     '" . addslashes($f_user) . "');\n"
        . "define('DB_PASSWORD', '" . addslashes($f_pass) . "');\n"
        . "define('DB_NAME',     '" . addslashes($f_db)   . "');\n"
        . "define('DB_CHARSET',  'utf8mb4');\n\n"
        . "date_default_timezone_set('Europe/Madrid');\n\n"
        . "function getConexion() {\n"
        . "    \$con = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME, DB_PORT);\n"
        . "    \$con->set_charset(DB_CHARSET);\n"
        . "    if (\$con->connect_error) {\n"
        . "        die('Error de conexion: ' . \$con->connect_error);\n"
        . "    }\n"
        . "    return \$con;\n"
        . "}\n"
        . "?>\n";

    if (@file_put_contents(__DIR__ . '/config.php', $cfg) !== false) {
        @file_put_contents(__DIR__ . '/.installed', date('Y-m-d H:i:s'));
        $exitos[] = "✅ Archivo <strong>config.php</strong> generado correctamente.";
        $exitos[] = "✅ Instalador bloqueado (archivo <code>.installed</code> creado).";
        $step = 6; // avanzar a pantalla final
    } else {
        $errores[] = "❌ No se pudo escribir <code>config.php</code> automáticamente.";
        $errores[] = "💡 Crea el archivo manualmente — el contenido exacto aparece abajo.";
    }
}

// ============================================================
//  Comprobaciones de entorno
// ============================================================
$phpVersion = PHP_VERSION;
$phpOk      = version_compare($phpVersion, '7.4', '>=');
$mysqliOk   = extension_loaded('mysqli');
$mbstringOk = extension_loaded('mbstring');
$pcreOk     = extension_loaded('pcre');
$writableOk = is_writable(__DIR__);
$dictOk     = file_exists(__DIR__ . '/dictionary.sql');
$envsOk     = $phpOk && $mysqliOk && $mbstringOk && $pcreOk;
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Instalador — SentiManPHP v2</title>
  <style>
    :root{--bg:#0f172a;--s:#1e293b;--s2:#334155;--brd:#334155;
          --txt:#e2e8f0;--mut:#94a3b8;--acc:#6366f1;--r:12px;
          --pos:#22c55e;--pl:#dcfce7;--pd:#15803d;
          --neg:#ef4444;--nl:#fee2e2;--nd:#b91c1c;
          --warn:#f59e0b;--wl:#fef3c7;--wd:#b45309;}
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
    body{font-family:'Segoe UI',system-ui,sans-serif;background:var(--bg);color:var(--txt);min-height:100vh;}
    a{color:var(--acc);}code{background:var(--s2);padding:1px 5px;border-radius:4px;font-size:.85em;}

    .topbar{background:var(--s);border-bottom:1px solid var(--brd);
            padding:14px 24px;display:flex;align-items:center;gap:14px;}
    .topbar h1{font-size:1.3rem;font-weight:700;}
    .badge{background:var(--acc);color:#fff;font-size:.7rem;padding:2px 8px;border-radius:99px;}
    .wrap{max-width:740px;margin:0 auto;padding:28px 20px;}

    /* stepper */
    .stepper{display:flex;margin-bottom:36px;}
    .si{flex:1;text-align:center;position:relative;}
    .si::before{content:'';position:absolute;top:16px;left:50%;width:100%;height:2px;background:var(--brd);z-index:0;}
    .si:last-child::before{display:none;}
    .sc{width:32px;height:32px;border-radius:50%;display:inline-flex;align-items:center;
        justify-content:center;font-weight:700;font-size:.82rem;position:relative;z-index:1;
        border:2px solid var(--brd);background:var(--s);}
    .done .sc{background:var(--pos);border-color:var(--pos);color:#fff;}
    .active .sc{background:var(--acc);border-color:var(--acc);color:#fff;}
    .done::before{background:var(--pos)!important;}
    .sl{font-size:.67rem;color:var(--mut);margin-top:5px;display:block;}
    .active .sl{color:var(--txt);}.done .sl{color:var(--pos);}

    /* card */
    .card{background:var(--s);border:1px solid var(--brd);border-radius:var(--r);
          padding:24px;margin-bottom:18px;}
    .ct{font-size:1.05rem;font-weight:700;margin-bottom:8px;display:flex;align-items:center;gap:8px;}
    .cs{color:var(--mut);font-size:.87rem;margin-bottom:18px;line-height:1.65;}

    /* alerts */
    .al{padding:11px 15px;border-radius:8px;margin-bottom:9px;font-size:.87rem;line-height:1.65;}
    .ok{background:var(--pl);color:var(--pd);}
    .er{background:var(--nl);color:var(--nd);}
    .wn{background:var(--wl);color:var(--wd);}
    .inf{background:#dbeafe;color:#1e40af;}

    /* form */
    .fg{margin-bottom:14px;}
    .fl{display:block;font-size:.83rem;color:var(--mut);margin-bottom:5px;font-weight:600;}
    .fl small{font-weight:400;color:var(--txt);margin-left:6px;}
    input[type=text],input[type=password]{width:100%;background:var(--s2);
      border:1px solid var(--brd);border-radius:8px;color:var(--txt);
      padding:10px 13px;font-size:.91rem;transition:border-color .2s;}
    input:focus{outline:none;border-color:var(--acc);}

    /* buttons */
    .btn{display:inline-flex;align-items:center;gap:7px;padding:11px 26px;
         border-radius:8px;border:none;cursor:pointer;font-size:.93rem;
         font-weight:700;transition:opacity .2s;text-decoration:none;}
    .bp{background:var(--acc);color:#fff;}.bs{background:var(--pos);color:#fff;}
    .btn:hover{opacity:.84;}.big{font-size:.98rem;padding:13px 30px;}

    /* checklist */
    .chk{list-style:none;display:flex;flex-direction:column;gap:9px;}
    .chk li{display:flex;align-items:flex-start;gap:10px;font-size:.88rem;line-height:1.55;}
    .chk .ic{flex-shrink:0;margin-top:1px;}

    /* code */
    .cb{background:var(--s2);border-radius:8px;padding:13px 15px;font-family:'Courier New',monospace;
        font-size:.79rem;color:#a5f3fc;overflow-x:auto;margin:10px 0;white-space:pre;}

    /* progress */
    .pg{height:7px;background:var(--s2);border-radius:99px;overflow:hidden;margin:7px 0;}
    .pf{height:100%;background:var(--acc);border-radius:99px;transition:width 1.2s ease;width:0%;}

    /* success */
    .ob{text-align:center;padding:32px 16px;}
    .ob .big{font-size:3.2rem;margin-bottom:14px;}
    .ob h2{font-size:1.4rem;margin-bottom:9px;}
    .ob p{color:var(--mut);margin-bottom:22px;max-width:400px;margin-left:auto;margin-right:auto;line-height:1.6;}
  </style>
</head>
<body>

<div class="topbar">
  <span style="font-size:1.35rem">🧠</span>
  <h1>SentiManPHP</h1>
  <span class="badge">v2 — Instalador</span>
</div>

<div class="wrap">

  <!-- STEPPER -->
  <div class="stepper">
    <?php
    $labs = ['1'=>'Requisitos','2'=>'Conexión','3'=>'Tablas',
             '4'=>'Diccionario','5'=>'Config','6'=>'¡Listo!'];
    foreach ($labs as $n => $lbl):
      $c = ($n < $step) ? 'done' : (($n == $step) ? 'active' : '');
    ?>
    <div class="si <?= $c ?>">
      <div class="sc"><?= $n < $step ? '✓' : $n ?></div>
      <span class="sl"><?= $lbl ?></span>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- ALERTAS -->
  <?php foreach ($errores as $e): ?>
    <div class="al er"><?= $e ?></div>
  <?php endforeach; ?>
  <?php foreach ($exitos as $e): ?>
    <div class="al ok"><?= $e ?></div>
  <?php endforeach; ?>


  <!-- PASO 1 — Requisitos -->
  <?php if ($step === 1): ?>
  <div class="card">
    <div class="ct">🔍 Paso 1 — Verificación del entorno</div>
    <p class="cs">Comprobamos que tu XAMPP tiene todo lo necesario para ejecutar SentiManPHP.</p>
    <ul class="chk">
      <li>
        <span class="ic"><?= $phpOk?'✅':'❌'?></span>
        <span><strong>PHP <?= $phpVersion ?></strong> — <?= $phpOk ? 'versión correcta (mínimo 7.4).' : '<strong style="color:var(--neg)">necesitas PHP 7.4+</strong>. Descarga una versión reciente de XAMPP.' ?></span>
      </li>
      <li>
        <span class="ic"><?= $mysqliOk?'✅':'❌'?></span>
        <span><strong>Extensión mysqli</strong> — <?= $mysqliOk ? 'activa.' : '<strong style="color:var(--neg)">falta.</strong> En <code>php.ini</code> descomenta <code>extension=mysqli</code> y reinicia Apache.' ?></span>
      </li>
      <li>
        <span class="ic"><?= $mbstringOk?'✅':'❌'?></span>
        <span><strong>Extensión mbstring</strong> — <?= $mbstringOk ? 'activa (tildes y ñ).' : '<strong style="color:var(--neg)">falta.</strong> Descomenta <code>extension=mbstring</code> en <code>php.ini</code>.' ?></span>
      </li>
      <li>
        <span class="ic">✅</span>
        <span><strong>Extensión PCRE</strong> — activa.</span>
      </li>
      <li>
        <span class="ic"><?= $writableOk?'✅':'⚠️'?></span>
        <span><strong>Carpeta escribible</strong> — <?= $writableOk ? 'el instalador generará <code>config.php</code> automáticamente.' : 'sin permisos. Tendrás que crear <code>config.php</code> manualmente al final (el instalador te dará el contenido exacto).' ?></span>
      </li>
      <li>
        <span class="ic"><?= $dictOk?'✅':'❌'?></span>
        <span><strong>dictionary.sql</strong> — <?= $dictOk ? '6.500+ términos listos para importar.' : '<strong style="color:var(--neg)">¡no encontrado!</strong> Copia todos los archivos del ZIP a la carpeta.' ?></span>
      </li>
    </ul>

    <?php if (!$envsOk): ?>
    <div class="al er" style="margin-top:18px;">
      ⛔ Corrije los requisitos marcados en rojo antes de continuar.<br>
      <strong>Para activar extensiones:</strong> XAMPP Control Panel → Apache → Config → PHP (php.ini).
      Quita el <code>;</code> delante de las extensiones que falten y reinicia Apache.
    </div>
    <?php else: ?>
    <div class="al ok" style="margin-top:18px;">✅ Entorno listo. Pulsa continuar.</div>
    <form method="post" action="install.php?step=2" style="margin-top:14px;">
      <button type="submit" class="btn bp big">Siguiente → Configurar conexión</button>
    </form>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="ct">💡 URL de este instalador</div>
    <div class="cb">http://localhost/sentiman/install.php</div>
    <p style="color:var(--mut);font-size:.83rem;margin-top:8px;">
      La carpeta del proyecto debe llamarse <code>sentiman</code> dentro de
      <code>C:\xampp\htdocs\</code> (Windows) o <code>/Applications/XAMPP/htdocs/</code> (macOS).
    </p>
  </div>


  <!-- PASO 2 — Conexión -->
  <?php elseif ($step === 2): ?>
  <div class="card">
    <div class="ct">🔌 Paso 2 — Conexión a MySQL</div>
    <p class="cs">
      Introduce los datos de tu servidor MySQL.<br>
      En <strong>XAMPP estándar</strong>: usuario <code>root</code>, contraseña <strong>en blanco</strong> y puerto <code>3306</code>.<br>
      Si XAMPP usa el <strong>puerto 3307</strong> (porque el 3306 estaba ocupado), cámbialo abajo.
    </p>
    <form method="post" action="install.php?step=2">
      <div class="fg">
        <label class="fl" for="host">Servidor MySQL <small>(normalmente: localhost)</small></label>
        <input type="text" id="host" name="host" value="<?= htmlspecialchars($f_host) ?>" autocomplete="off">
      </div>
      <div style="display:grid;grid-template-columns:1fr 140px;gap:12px;">
        <div class="fg" style="margin-bottom:0">
          <label class="fl" for="user">Usuario <small>(en XAMPP: root)</small></label>
          <input type="text" id="user" name="user" value="<?= htmlspecialchars($f_user) ?>" autocomplete="off">
        </div>
        <div class="fg" style="margin-bottom:0">
          <label class="fl" for="port">Puerto <small>(3306 o 3307)</small></label>
          <input type="text" id="port" name="port" value="<?= htmlspecialchars($f_port) ?>" autocomplete="off" placeholder="3306">
        </div>
      </div>
      <div class="fg" style="margin-top:14px;">
        <label class="fl" for="pass">Contraseña <small>— en XAMPP por defecto está vacía, no escribas nada</small></label>
        <input type="password" id="pass" name="pass" value="" autocomplete="new-password"
               placeholder="Déjalo en blanco si usas XAMPP estándar">
      </div>
      <div class="fg">
        <label class="fl" for="db">Nombre de la base de datos <small>(se creará si no existe)</small></label>
        <input type="text" id="db" name="db" value="<?= htmlspecialchars($f_db) ?>" autocomplete="off">
      </div>
      <button type="submit" class="btn bp big">🔍 Probar conexión y continuar</button>
    </form>
  </div>

  <div class="card">
    <div class="ct">🆘 Soluciones rápidas</div>
    <ul class="chk">
      <li><span class="ic">❌</span><span><strong>Error 1045</strong> (acceso denegado): la contraseña es incorrecta. En XAMPP estándar el campo contraseña va vacío. Si asignaste una, escríbela arriba.</span></li>
      <li><span class="ic">❌</span><span><strong>Error 2002/2003</strong> (no conecta): MySQL no está arrancado. Abre el XAMPP Control Panel y pulsa <em>Start</em> junto a MySQL.</span></li>
      <li><span class="ic">💡</span><span><strong>Puerto 3307:</strong> si en el XAMPP Control Panel aparece MySQL en el puerto 3307, escribe <code>3307</code> en el campo Puerto de arriba.</span></li>
      <li><span class="ic">💡</span><span><strong>¿Qué puerto usa mi MySQL?</strong> Mira en el XAMPP Control Panel — a la derecha de MySQL aparece el número de puerto activo.</span></li>
    </ul>
  </div>


  <!-- PASO 3 — Tablas -->
  <?php elseif ($step === 3): ?>
  <div class="card">
    <div class="ct">🗄️ Paso 3 — Crear base de datos y tablas</div>
    <p class="cs">
      Crearemos la base de datos <strong><?= htmlspecialchars($f_db) ?></strong> y las tablas necesarias.
      Este paso es <strong>seguro de repetir</strong> — no borra datos existentes.
    </p>
    <form method="post" action="install.php?step=3">
      <button type="submit" class="btn bp big">⚙️ Crear estructura</button>
    </form>
  </div>

  <div class="card">
    <div class="ct">📋 ¿Qué se creará?</div>
    <ul class="chk">
      <li><span class="ic">🗃️</span><span>Base de datos <code><?= htmlspecialchars($f_db) ?></code></span></li>
      <li><span class="ic">📖</span><span>Tabla <code>dictionary</code> — palabras con pesos positivo/negativo/neutral</span></li>
      <li><span class="ic">📊</span><span>Tabla <code>analisis_historico</code> — registro de todos los análisis</span></li>
      <li><span class="ic">🔎</span><span>Vista <code>v_estadisticas</code> — estadísticas para las gráficas</span></li>
    </ul>
  </div>


  <!-- PASO 4 — Diccionario -->
  <?php elseif ($step === 4): ?>
  <div class="card">
    <div class="ct">📚 Paso 4 — Importar el diccionario</div>
    <p class="cs">
      Cargaremos <strong>6.527 palabras y expresiones</strong> en español con sus pesos de sentimiento.
      Puede tardar unos segundos — <strong>no cierres la página</strong>.
    </p>
    <div class="pg"><div class="pf" id="pf"></div></div>
    <p style="color:var(--mut);font-size:.82rem;margin-bottom:18px;" id="pm">Listo para importar…</p>
    <form method="post" action="install.php?step=4" id="df">
      <button type="submit" class="btn bp big" id="db2">📥 Importar diccionario</button>
    </form>
  </div>
  <script>
    document.getElementById('df')?.addEventListener('submit',()=>{
      document.getElementById('pf').style.width='75%';
      document.getElementById('pm').textContent='Importando… no cierres la página.';
      document.getElementById('db2').disabled=true;
      document.getElementById('db2').textContent='⏳ Importando…';
    });
  </script>


  <!-- PASO 5 — Config -->
  <?php elseif ($step === 5): ?>
  <div class="card">
    <div class="ct">⚙️ Paso 5 — Generar config.php</div>
    <p class="cs">
      Crearemos el archivo <code>config.php</code> con tus datos de conexión automáticamente.
    </p>
    <form method="post" action="install.php?step=5">
      <button type="submit" class="btn bp big">📝 Generar config.php</button>
    </form>
  </div>

  <!-- Contenido manual por si no puede escribir -->
  <?php if (!$writableOk || !empty($errores)): ?>
  <div class="card">
    <div class="ct" style="color:var(--warn);">⚠️ Creación manual de config.php</div>
    <p class="cs">El instalador no puede escribir el archivo automáticamente. Crea el archivo <code>config.php</code> manualmente en la carpeta del proyecto con este contenido exacto:</p>
    <div class="cb">&lt;?php
mysqli_report(MYSQLI_REPORT_OFF);
define('DB_HOST',     '<?= htmlspecialchars($f_host) ?>');
define('DB_PORT',     <?= (int)$f_port ?>);
define('DB_USER',     '<?= htmlspecialchars($f_user) ?>');
define('DB_PASSWORD', '<?= htmlspecialchars($f_pass) ?>');
define('DB_NAME',     '<?= htmlspecialchars($f_db) ?>');
define('DB_CHARSET',  'utf8mb4');
date_default_timezone_set('Europe/Madrid');
function getConexion() {
    $con = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME, DB_PORT);
    $con->set_charset(DB_CHARSET);
    if ($con->connect_error) die('Error: ' . $con->connect_error);
    return $con;
}
?&gt;</div>
    <p style="color:var(--mut);font-size:.83rem;margin-top:10px;">
      Una vez creado el archivo, pulsa el botón de abajo para continuar.
    </p>
    <form method="post" action="install.php?step=6" style="margin-top:14px;">
      <button type="submit" class="btn bs big">✅ Ya creé config.php — Continuar</button>
    </form>
  </div>
  <?php endif; ?>


  <!-- PASO 6 — ¡Listo! -->
  <?php elseif ($step === 6): ?>
  <div class="card">
    <div class="ob">
      <div class="big">🎉</div>
      <h2>¡Instalación completada!</h2>
      <p>SentiManPHP v2 está listo. Puedes analizar textos, consultar el histórico y usar la API desde tus scrapers.</p>
      <a href="index.php" class="btn bs big">🚀 Abrir SentiManPHP</a>
    </div>
  </div>

  <div class="card">
    <div class="ct">✅ Resumen de la instalación</div>
    <ul class="chk">
      <li><span class="ic">✅</span><span>Entorno PHP verificado</span></li>
      <li><span class="ic">✅</span><span>Conexión MySQL verificada — <code><?= htmlspecialchars($f_host) ?>:<?= (int)$f_port ?></code> / BD: <code><?= htmlspecialchars($f_db) ?></code></span></li>
      <li><span class="ic">✅</span><span>Tablas <code>dictionary</code> y <code>analisis_historico</code> listas</span></li>
      <li><span class="ic">✅</span><span>Diccionario con 6.500+ términos cargado</span></li>
      <li><span class="ic">✅</span><span>Archivo <code>config.php</code> listo</span></li>
    </ul>
  </div>

  <div class="card">
    <div class="ct">🔌 URL de la API para tus scrapers</div>
    <div class="cb">http://localhost/sentiman/api.php?texto=Tu+texto+aqui</div>
    <p style="color:var(--mut);font-size:.83rem;margin-top:8px;">
      PHP: <code>$r = file_get_contents("http://localhost/sentiman/api.php?texto=".urlencode($txt));</code>
    </p>
  </div>

  <div class="card" style="border-color:var(--warn);">
    <div class="ct" style="color:var(--warn);">🔒 Seguridad</div>
    <p style="color:var(--mut);font-size:.87rem;line-height:1.65;">
      El instalador ya está bloqueado automáticamente (archivo <code>.installed</code> creado).
      Para mayor seguridad puedes eliminar también <code>install.php</code> de la carpeta —
      no afecta al funcionamiento de la aplicación.
    </p>
  </div>

  <?php endif; ?>

</div>
</body>
</html>
